<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;

class LeaveCalendarController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $start = (string)($this->request->getQuery('start') ?: date('Y-m-01'));
        $end = (string)($this->request->getQuery('end') ?: date('Y-m-t'));
        $requests = TableRegistry::getTableLocator()->get('LeaveRequests')->find()
            ->contain(['Employees', 'LeaveTypes'])
            ->where([
                'LeaveRequests.status' => 'approved',
                'LeaveRequests.start_date <=' => $end,
                'LeaveRequests.end_date >=' => $start,
                'Employees.is_active' => true,
            ]);
        if ($employee->role === 'hr_admin') {
            // HR can see company availability.
            $requests->where(['Employees.organization_id' => $employee->organization_id]);
        } elseif (in_array($employee->role, ['line_manager', 'relief_officer'], true)) {
            $requests->matching('LeaveApprovals', fn ($q) => $q->where(['LeaveApprovals.approver_id' => $employee->id]));
        } else {
            $requests->where(['LeaveRequests.employee_id' => $employee->id]);
        }
        return $this->json(['data' => $requests->orderBy(['LeaveRequests.start_date' => 'ASC'])->all()->toList()]);
    }
}
