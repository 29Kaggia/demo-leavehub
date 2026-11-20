<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Utility\DocumentStorage;
use App\Service\KenyaHolidayCalendar;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\I18n\Date;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\UploadedFileInterface;

class LeaveRequestsController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $currentEmployee = $this->currentEmployee();
        $employeeId = $this->request->getQuery('employee_id');
        $requests = TableRegistry::getTableLocator()->get('LeaveRequests')
            ->find()
            ->contain([
                'LeaveTypes',
                'Employees' => fn ($query) => $query->select(['id', 'first_name', 'last_name', 'email', 'department_id', 'line_manager_id', 'relief_officer_id', 'role']),
                'LeaveApprovals',
                'LeaveAttachments',
            ])
            ->orderBy(['LeaveRequests.created' => 'DESC']);

        if ($currentEmployee->role !== 'hr_admin') {
            $requests->where(['LeaveRequests.employee_id' => $currentEmployee->id]);
        } else {
            // Inactive employees are hidden from HR's operational leave activity.
            $requests->where(['Employees.is_active' => true, 'Employees.organization_id' => $currentEmployee->organization_id]);
            if ($employeeId !== null) {
                $requests->where(['LeaveRequests.employee_id' => (int)$employeeId]);
            }
        }

        return $this->json(['data' => $requests->all()->toList()]);
    }

    public function create(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $data = $this->request->getData();
        $currentEmployee = $this->currentEmployee();
        $employeeId = (int)$currentEmployee->id;
        $leaveTypeId = (int)($data['leave_type_id'] ?? 0);
        $handoverNotes = trim((string)($data['handover_notes'] ?? ''));
        $document = $data['supporting_document'] ?? null;
        $duration = $data['duration'] ?? 'full_day';

        if (!$employeeId || !$leaveTypeId || !$handoverNotes) {
            throw new UnprocessableEntityException('Employee, leave type, and handover notes are required.');
        }
        if (!$document instanceof UploadedFileInterface || $document->getError() !== UPLOAD_ERR_OK) {
            throw new UnprocessableEntityException('A supporting document is required.');
        }

        try {
            $start = new Date((string)($data['start_date'] ?? ''));
            $end = new Date((string)($data['end_date'] ?? ''));
        } catch (\Throwable) {
            throw new UnprocessableEntityException('Provide valid start and end dates.');
        }
        if ($end < $start) {
            throw new UnprocessableEntityException('The end date must be on or after the start date.');
        }
        if (!in_array($duration, ['full_day', 'half_day'], true) || ($duration === 'half_day' && $start->format('Y-m-d') !== $end->format('Y-m-d'))) {
            throw new UnprocessableEntityException('Half-day leave must start and end on the same date.');
        }

        $locator = TableRegistry::getTableLocator();
        $employees = $locator->get('Employees');
        $types = $locator->get('LeaveTypes');
        $requests = $locator->get('LeaveRequests');
        $approvals = $locator->get('LeaveApprovals');
        $attachments = $locator->get('LeaveAttachments');
        $employee = $employees->find()->where(['id' => $employeeId, 'is_active' => true])->first();
        $type = $types->find()->where(['id' => $leaveTypeId, 'is_active' => true])->first();
        if (!$employee || !$type) {
            throw new NotFoundException('The employee or leave type does not exist.');
        }
        if ($type->code === 'MATERNITY' && $employee->gender !== 'female') {
            throw new UnprocessableEntityException('Maternity leave is available to employees recorded as female. Ask HR to update your employee profile if this is incorrect.');
        }
        if ($type->code === 'PATERNITY' && $employee->gender !== 'male') {
            throw new UnprocessableEntityException('Paternity leave is available to employees recorded as male. Ask HR to update your employee profile if this is incorrect.');
        }
        if (!$employee->relief_officer_id || !$employee->line_manager_id) {
            throw new UnprocessableEntityException('Assign both a relief officer and line manager before applying for leave.');
        }

        $days = $duration === 'half_day' ? 0.5 : $this->countLeaveDays($start, $end, $type->calculation_basis);
        if ($days < 1) {
            throw new UnprocessableEntityException('Choose at least one working day that is not a public holiday.');
        }
        if ($days > (float)$type->days_allowed) {
            throw new UnprocessableEntityException('This request exceeds the allowed leave entitlement.');
        }
        $year = (int)$start->format('Y');
        $balance = $locator->get('LeaveBalances')->find()
            ->where(['employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId, 'year' => $year])
            ->first();
        $used = $balance ? (float)$balance->used_days : 0.0;
        if ($days + $used > (float)$type->days_allowed) {
            throw new UnprocessableEntityException('This request exceeds the employee’s remaining leave balance.');
        }

        $connection = $requests->getConnection();
        $request = $connection->transactional(function () use ($requests, $approvals, $attachments, $data, $employee, $type, $start, $end, $days, $document, $duration, $locator): object {
            $request = $requests->newEntity([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'requested_days' => $days,
                'duration' => $duration,
                'reason' => trim((string)($data['reason'] ?? '')) ?: null,
                'handover_notes' => trim((string)$data['handover_notes']),
                'status' => 'pending_relief_officer',
                'submitted_at' => new \DateTimeImmutable(),
            ]);
            if (!$requests->save($request)) {
                throw new UnprocessableEntityException('The leave request could not be saved.');
            }
            $reliefApproverId = (int)$employee->relief_officer_id;
            foreach ([['relief_officer', $reliefApproverId], ['line_manager', $employee->line_manager_id]] as [$stage, $approverId]) {
                $approverId = $this->delegatedApprover((int)$approverId);
                if ($stage === 'relief_officer') {
                    $reliefApproverId = $approverId;
                }
                $approval = $approvals->newEntity(['leave_request_id' => $request->id, 'approver_id' => $approverId, 'stage' => $stage]);
                if (!$approvals->save($approval)) {
                    throw new UnprocessableEntityException('Approval workflow could not be created.');
                }
            }
            $this->saveAttachment($attachments, $request->id, $document);
            $this->notify($locator, $reliefApproverId, 'Leave request awaiting review', sprintf('%s %s has requested %s.', $employee->first_name, $employee->last_name, $type->name), (int)$request->id);
            return $request;
        });

        return $this->json(['data' => $request, 'message' => 'Leave request submitted for relief-officer approval.'], 201);
    }

    public function decide(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $data = $this->request->getData();
        $approverId = (int)$this->currentEmployee()->id;
        $decision = $data['decision'] ?? '';
        if (!$approverId || !in_array($decision, ['approved', 'rejected'], true)) {
            throw new BadRequestException('Approver ID and a valid decision are required.');
        }
        $locator = TableRegistry::getTableLocator();
        $requests = $locator->get('LeaveRequests');
        $employee = $this->currentEmployee();
        $orgEmployeeIds = $locator->get('Employees')->find()->select(['id'])->where(['organization_id' => $employee->organization_id]);
        $request = $requests->find()->where(['id' => $id, 'employee_id IN' => $orgEmployeeIds])->first();
        if (!$request) {
            throw new NotFoundException('Leave request not found.');
        }
        $stage = $request->status === 'pending_relief_officer' ? 'relief_officer' : ($request->status === 'pending_line_manager' ? 'line_manager' : null);
        if (!$stage) {
            throw new UnprocessableEntityException('This request is no longer awaiting an approval.');
        }
        $approvals = $locator->get('LeaveApprovals');
        $approval = $approvals->find()->where(['leave_request_id' => $id, 'stage' => $stage, 'approver_id' => $approverId, 'decision' => 'pending'])->first();
        if (!$approval) {
            throw new UnprocessableEntityException('This approver cannot action the current stage.');
        }
        $connection = $requests->getConnection();
        $connection->transactional(function () use ($approval, $approvals, $request, $requests, $locator, $decision, $data, $stage): void {
            $approval = $approvals->patchEntity($approval, ['decision' => $decision, 'comments' => trim((string)($data['comments'] ?? '')) ?: null, 'decided_at' => new \DateTimeImmutable()]);
            if (!$approvals->save($approval)) {
                throw new UnprocessableEntityException('Approval could not be saved.');
            }
            $request->status = $decision === 'rejected' ? 'rejected' : ($stage === 'relief_officer' ? 'pending_line_manager' : 'approved');
            if ($request->status === 'approved') {
                $request->approved_at = new \DateTimeImmutable();
                $this->addToLeaveBalance($locator, $request);
            }
            if (!$requests->save($request)) {
                throw new UnprocessableEntityException('Leave request status could not be updated.');
            }
            $requestWithDetails = $requests->find()->contain(['Employees', 'LeaveTypes'])->where(['LeaveRequests.id' => $request->id])->firstOrFail();
            $employeeName = $requestWithDetails->employee->first_name . ' ' . $requestWithDetails->employee->last_name;
            if ($decision === 'rejected') {
                $this->notify($locator, (int)$request->employee_id, 'Leave request declined', sprintf('Your %s request was declined.', $requestWithDetails->leave_type->name), (int)$request->id);
            } elseif ($stage === 'relief_officer') {
                $next = $approvals->find()->where(['leave_request_id' => $request->id, 'stage' => 'line_manager'])->firstOrFail();
                $this->notify($locator, (int)$next->approver_id, 'Leave request awaiting approval', sprintf('%s has been cleared for cover and is ready for your review.', $employeeName), (int)$request->id);
                $this->notify($locator, (int)$request->employee_id, 'Cover confirmed', 'Your relief officer approved the cover. Your manager will now review the request.', (int)$request->id);
            } else {
                $this->notify($locator, (int)$request->employee_id, 'Leave request approved', sprintf('Your %s request has been approved.', $requestWithDetails->leave_type->name), (int)$request->id);
            }
        });
        return $this->json(['data' => $request, 'message' => 'Decision recorded.']);
    }

    public function cancel(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $employee = $this->currentEmployee();
        $requests = TableRegistry::getTableLocator()->get('LeaveRequests');
        $request = $requests->find()->where(['id' => $id, 'employee_id' => $employee->id])->firstOrFail();
        if ((int)$request->employee_id !== (int)$employee->id) throw new \Cake\Http\Exception\UnauthorizedException('You can only cancel your own leave requests.');
        if (!in_array($request->status, ['pending_relief_officer', 'pending_line_manager', 'approved'], true)) throw new UnprocessableEntityException('This leave request cannot be cancelled.');
        $reason = trim((string)($this->request->getData('reason') ?? ''));
        if (!$reason) throw new UnprocessableEntityException('Provide a reason for cancelling this leave request.');
        $requests->getConnection()->transactional(function () use ($requests, $request, $employee, $reason): void {
            if ($request->status === 'approved') $this->removeFromLeaveBalance(TableRegistry::getTableLocator(), $request);
            $request->status = 'cancelled'; $request->cancelled_at = new \DateTimeImmutable(); $request->cancellation_reason = $reason;
            if (!$requests->save($request)) throw new UnprocessableEntityException('Leave cancellation could not be saved.');
            $this->audit($request->id, $employee->id, 'cancelled', $reason);
        });
        return $this->json(['data' => $request, 'message' => 'Leave request cancelled.']);
    }

    public function override(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $actor = $this->currentEmployee();
        if ($actor->role !== 'hr_admin') throw new \Cake\Http\Exception\UnauthorizedException('Only HR administrators can override leave requests.');
        $data = $this->request->getData(); $status = $data['status'] ?? ''; $reason = trim((string)($data['reason'] ?? ''));
        if (!in_array($status, ['approved', 'rejected', 'cancelled'], true) || !$reason) throw new UnprocessableEntityException('Choose an override status and provide an audit reason.');
        $requests = TableRegistry::getTableLocator()->get('LeaveRequests');
        $organizationEmployees = TableRegistry::getTableLocator()->get('Employees')->find()->select(['id'])->where(['organization_id' => $actor->organization_id]);
        $request = $requests->find()->where(['id' => $id, 'employee_id IN' => $organizationEmployees])->firstOrFail();
        $requests->getConnection()->transactional(function () use ($requests, $request, $actor, $status, $reason): void {
            $wasApproved = $request->status === 'approved';
            if (!$wasApproved && $status === 'approved') { $request->approved_at = new \DateTimeImmutable(); $this->addToLeaveBalance(TableRegistry::getTableLocator(), $request); }
            if ($wasApproved && $status !== 'approved') $this->removeFromLeaveBalance(TableRegistry::getTableLocator(), $request);
            $request->status = $status;
            if ($status === 'cancelled') { $request->cancelled_at = new \DateTimeImmutable(); $request->cancellation_reason = $reason; }
            if (!$requests->save($request)) throw new UnprocessableEntityException('Leave override could not be saved.');
            $this->audit($request->id, $actor->id, 'hr_override_' . $status, $reason);
        });
        return $this->json(['data' => $request, 'message' => 'HR override recorded.']);
    }

    public function audits(int $id): \Cake\Http\Response
    {
        $actor = $this->currentEmployee();
        if ($actor->role !== 'hr_admin') throw new \Cake\Http\Exception\UnauthorizedException('Only HR administrators can view leave audit history.');
        $organizationEmployees = TableRegistry::getTableLocator()->get('Employees')->find()->select(['id'])->where(['organization_id' => $actor->organization_id]);
        $organizationRequestIds = TableRegistry::getTableLocator()->get('LeaveRequests')->find()->select(['id'])->where(['employee_id IN' => $organizationEmployees]);
        if (!TableRegistry::getTableLocator()->get('LeaveRequests')->exists(['id' => $id, 'id IN' => $organizationRequestIds])) {
            throw new NotFoundException('Leave request not found.');
        }
        $items = TableRegistry::getTableLocator()->get('LeaveRequestAudits')->find()
            ->contain(['Actors' => fn ($query) => $query->select(['id', 'first_name', 'last_name'])])
            ->where(['leave_request_id' => $id])
            ->orderBy(['LeaveRequestAudits.created' => 'DESC'])
            ->all()->toList();
        return $this->json(['data' => $items]);
    }

    private function countLeaveDays(Date $start, Date $end, string $basis): int
    {
        if ($basis === 'calendar_days') {
            return $start->diffInDays($end) + 1;
        }
        $calendar = new KenyaHolidayCalendar();
        for ($year = (int)$start->format('Y'); $year <= (int)$end->format('Y'); $year++) {
            $calendar->ensureYear($year);
        }
        $holidayDates = TableRegistry::getTableLocator()->get('PublicHolidays')->find()
            ->select(['holiday_date'])
            ->where(['holiday_date >=' => $start, 'holiday_date <=' => $end])
            ->all()
            ->map(fn ($holiday) => $holiday->holiday_date->format('Y-m-d'))
            ->toArray();
        $count = 0;
        for ($date = $start; $date <= $end; $date = $date->addDays(1)) {
            if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidayDates, true)) {
                $count++;
            }
        }
        return $count;
    }

    private function saveAttachment(object $attachments, int $requestId, UploadedFileInterface $file): void
    {
        $extension = pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');
        $directory = DocumentStorage::directory('leave-documents');
        $file->moveTo($directory . DS . $filename);
        $attachment = $attachments->newEntity([
            'leave_request_id' => $requestId,
            'original_filename' => $file->getClientFilename() ?? 'document',
            'storage_path' => 'uploads/leave-documents/' . $filename,
            'mime_type' => $file->getClientMediaType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?? 0,
        ]);
        if (!$attachments->save($attachment)) {
            throw new UnprocessableEntityException('Supporting document could not be saved.');
        }
    }

    private function addToLeaveBalance(object $locator, object $request): void
    {
        $balances = $locator->get('LeaveBalances');
        $year = (int)(new \DateTimeImmutable((string)$request->start_date))->format('Y');
        $balance = $balances->find()->where(['employee_id' => $request->employee_id, 'leave_type_id' => $request->leave_type_id, 'year' => $year])->first();
        if (!$balance) {
            $type = $locator->get('LeaveTypes')->get($request->leave_type_id);
            $balance = $balances->newEntity(['employee_id' => $request->employee_id, 'leave_type_id' => $request->leave_type_id, 'year' => $year, 'entitled_days' => $type->days_allowed, 'used_days' => 0]);
        }
        $balance->used_days = (float)$balance->used_days + (float)$request->requested_days;
        if (!$balances->save($balance)) {
            throw new UnprocessableEntityException('Leave balance could not be updated.');
        }
    }

    private function removeFromLeaveBalance(object $locator, object $request): void
    {
        $balance = $locator->get('LeaveBalances')->find()->where(['employee_id' => $request->employee_id, 'leave_type_id' => $request->leave_type_id, 'year' => (int)(new \DateTimeImmutable((string)$request->start_date))->format('Y')])->first();
        if ($balance) { $balance->used_days = max(0, (float)$balance->used_days - (float)$request->requested_days); $locator->get('LeaveBalances')->saveOrFail($balance); }
    }

    private function delegatedApprover(int $approverId): int
    {
        $delegation = TableRegistry::getTableLocator()->get('ApprovalDelegations')->find()->where(['delegator_id' => $approverId, 'starts_on <=' => date('Y-m-d'), 'ends_on >=' => date('Y-m-d')])->first();
        return $delegation ? (int)$delegation->delegate_id : $approverId;
    }

    private function audit(int $requestId, int $actorId, string $action, ?string $reason): void
    {
        $audits = TableRegistry::getTableLocator()->get('LeaveRequestAudits');
        $audits->saveOrFail($audits->newEntity(['leave_request_id' => $requestId, 'actor_id' => $actorId, 'action' => $action, 'reason' => $reason]));
    }

    private function notify(object $locator, int $employeeId, string $title, string $message, int $requestId): void
    {
        $notifications = $locator->get('Notifications');
        $notification = $notifications->newEntity(['employee_id' => $employeeId, 'title' => $title, 'message' => $message, 'leave_request_id' => $requestId]);
        if (!$notifications->save($notification)) {
            throw new UnprocessableEntityException('Notification could not be created.');
        }
    }
}
