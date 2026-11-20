<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;

class TeamAvailabilityController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $viewer = $this->currentEmployee();
        if (!in_array($viewer->role, ['hr_admin', 'line_manager', 'relief_officer'], true)) {
            throw new UnauthorizedException('Only HR and managers can view team availability.');
        }
        $date = (string)($this->request->getQuery('date') ?: date('Y-m-d'));
        $employees = TableRegistry::getTableLocator()->get('Employees')->find()
            ->contain(['Departments'])
            ->where(['Employees.is_active' => true, 'Employees.organization_id' => $viewer->organization_id]);
        if ($viewer->role !== 'hr_admin') {
            $employees->where(['Employees.line_manager_id' => $viewer->id]);
        }
        $people = $employees->orderBy(['Employees.last_name' => 'ASC', 'Employees.first_name' => 'ASC'])->all()->toList();
        $ids = array_map(fn ($employee) => (int)$employee->id, $people);
        $locator = TableRegistry::getTableLocator();
        $attendance = $ids ? $locator->get('AttendanceRecords')->find()->where(['employee_id IN' => $ids, 'work_date' => $date])->all()->indexBy('employee_id')->toArray() : [];
        $leave = $ids ? $locator->get('LeaveRequests')->find()->contain(['LeaveTypes'])->where(['employee_id IN' => $ids, 'status' => 'approved', 'start_date <=' => $date, 'end_date >=' => $date])->all()->indexBy('employee_id')->toArray() : [];
        $data = array_map(function ($employee) use ($attendance, $leave): array {
            $record = $attendance[$employee->id] ?? null; $request = $leave[$employee->id] ?? null;
            $status = $request ? 'on_leave' : ($record?->status ?? 'available');
            return ['employee' => $employee, 'status' => $status, 'attendance' => $record, 'leave_type' => $request?->leave_type?->name];
        }, $people);
        return $this->json(['data' => $data, 'date' => $date]);
    }
}
