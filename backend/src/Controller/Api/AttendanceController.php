<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;

class AttendanceController extends AppController
{
    public function today(): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $record = $this->records()->find()->where(['employee_id' => $employee->id, 'work_date' => date('Y-m-d')])->first();
        return $this->json(['data' => $record]);
    }

    public function history(): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $records = $this->records()->find()->where(['employee_id' => $employee->id])->orderBy(['work_date' => 'DESC'])->limit(90)->all()->toList();
        return $this->json(['data' => $records]);
    }

    public function clockIn(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $employee = $this->currentEmployee();
        $today = date('Y-m-d');
        if ($this->isOnApprovedLeave((int)$employee->id, $today)) {
            throw new UnprocessableEntityException('You cannot clock in while you have approved leave today. Ask HR to override it if this is incorrect.');
        }
        $records = $this->records();
        if ($records->find()->where(['employee_id' => $employee->id, 'work_date' => $today])->first()) {
            throw new UnprocessableEntityException('You have already clocked in today.');
        }
        $data = $this->request->getData();
        $status = $data['status'] ?? 'present';
        if (!in_array($status, ['present', 'remote'], true)) {
            throw new UnprocessableEntityException('Choose a valid attendance status.');
        }
        $record = $records->newEntity(['employee_id' => $employee->id, 'work_date' => $today, 'clock_in_at' => new \DateTimeImmutable(), 'clock_in_note' => trim((string)($data['note'] ?? '')) ?: null, 'status' => $status]);
        if (!$records->save($record)) throw new UnprocessableEntityException('Clock-in could not be recorded.');
        return $this->json(['data' => $record, 'message' => 'Clock-in recorded.'], 201);
    }

    public function clockOut(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $employee = $this->currentEmployee();
        $record = $this->records()->find()->where(['employee_id' => $employee->id, 'work_date' => date('Y-m-d')])->first();
        if (!$record || !$record->clock_in_at) throw new UnprocessableEntityException('Clock in before clocking out.');
        if ($record->clock_out_at) throw new UnprocessableEntityException('You have already clocked out today.');
        $now = new \DateTimeImmutable();
        $record->clock_out_at = $now;
        $record->clock_out_note = trim((string)($this->request->getData('note') ?? '')) ?: null;
        $record->total_minutes = max(0, (int)(($now->getTimestamp() - $record->clock_in_at->getTimestamp()) / 60));
        if (!$this->records()->save($record)) throw new UnprocessableEntityException('Clock-out could not be recorded.');
        return $this->json(['data' => $record, 'message' => 'Clock-out recorded.']);
    }

    public function report(): \Cake\Http\Response
    {
        $this->requireAdmin();
        $employee = $this->currentEmployee();
        $query = $this->records()->find()->contain(['Employees' => fn ($q) => $q->select(['id', 'first_name', 'last_name', 'email', 'department_id'])])->innerJoinWith('Employees', fn ($q) => $q->where(['Employees.is_active' => true]))->orderBy(['AttendanceRecords.work_date' => 'DESC']);
        $query->where(['Employees.organization_id' => $employee->organization_id]);
        if ($start = $this->request->getQuery('start')) $query->where(['AttendanceRecords.work_date >=' => $start]);
        if ($end = $this->request->getQuery('end')) $query->where(['AttendanceRecords.work_date <=' => $end]);
        if ($employeeId = $this->request->getQuery('employee_id')) $query->where(['AttendanceRecords.employee_id' => (int)$employeeId]);
        return $this->json(['data' => $query->all()->toList()]);
    }

    public function correct(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['patch']);
        $actor = $this->currentEmployee();
        $this->requireAdmin();
        $records = $this->records();
        $organizationEmployees = TableRegistry::getTableLocator()->get('Employees')->find()->select(['id'])->where(['organization_id' => $actor->organization_id]);
        $record = $records->find()->where(['id' => $id, 'employee_id IN' => $organizationEmployees])->firstOrFail();
        $data = $this->request->getData();
        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '') {
            throw new UnprocessableEntityException('Provide a reason for this attendance correction.');
        }

        $status = (string)($data['status'] ?? $record->status);
        if (!in_array($status, ['present', 'remote', 'late', 'absent', 'on_leave'], true)) {
            throw new UnprocessableEntityException('Choose a valid attendance status.');
        }

        try {
            $clockIn = $this->dateTimeOrNull($data['clock_in_at'] ?? $record->clock_in_at);
            $clockOut = $this->dateTimeOrNull($data['clock_out_at'] ?? $record->clock_out_at);
        } catch (\Exception) {
            throw new UnprocessableEntityException('Enter valid clock-in and clock-out times.');
        }
        if ($clockIn && $clockOut && $clockOut < $clockIn) {
            throw new UnprocessableEntityException('Clock-out time must be after clock-in time.');
        }

        $before = $this->snapshot($record);
        $record->status = $status;
        $record->clock_in_at = $clockIn;
        $record->clock_out_at = $clockOut;
        $record->clock_in_note = trim((string)($data['clock_in_note'] ?? $record->clock_in_note ?? '')) ?: null;
        $record->clock_out_note = trim((string)($data['clock_out_note'] ?? $record->clock_out_note ?? '')) ?: null;
        $record->total_minutes = $clockIn && $clockOut ? max(0, (int)(($clockOut->getTimestamp() - $clockIn->getTimestamp()) / 60)) : 0;
        $after = $this->snapshot($record);

        $records->getConnection()->transactional(function () use ($records, $record, $actor, $reason, $before, $after): void {
            if (!$records->save($record)) {
                throw new UnprocessableEntityException('Attendance correction could not be saved.');
            }
            $audits = TableRegistry::getTableLocator()->get('AttendanceAudits');
            $audit = $audits->newEntity([
                'attendance_record_id' => $record->id,
                'actor_id' => $actor->id,
                'action' => 'attendance_corrected',
                'reason' => $reason,
                'before_data' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_data' => json_encode($after, JSON_THROW_ON_ERROR),
            ]);
            if (!$audits->save($audit)) {
                throw new UnprocessableEntityException('Attendance correction audit could not be saved.');
            }
        });

        return $this->json(['data' => $record, 'message' => 'Attendance correction saved and added to the audit log.']);
    }

    private function records(): object { return TableRegistry::getTableLocator()->get('AttendanceRecords'); }
    private function requireAdmin(): void { if ($this->currentEmployee()->role !== 'hr_admin') throw new UnauthorizedException('Only HR administrators can view attendance reports.'); }
    private function isOnApprovedLeave(int $employeeId, string $date): bool { return TableRegistry::getTableLocator()->get('LeaveRequests')->exists(['employee_id' => $employeeId, 'status' => 'approved', 'start_date <=' => $date, 'end_date >=' => $date]); }
    private function dateTimeOrNull(mixed $value): ?\DateTimeImmutable { if ($value === null || $value === '') return null; return $value instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($value) : new \DateTimeImmutable((string)$value); }
    private function snapshot(object $record): array { return ['status' => $record->status, 'clock_in_at' => $record->clock_in_at?->format(DATE_ATOM), 'clock_out_at' => $record->clock_out_at?->format(DATE_ATOM), 'clock_in_note' => $record->clock_in_note, 'clock_out_note' => $record->clock_out_note, 'total_minutes' => (int)$record->total_minutes]; }
}
