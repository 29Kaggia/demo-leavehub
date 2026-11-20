<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;

class AuditLogController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $actor = $this->currentEmployee();
        if ($actor->role !== 'hr_admin') {
            throw new UnauthorizedException('Only HR administrators can view the audit log.');
        }
        $locator = TableRegistry::getTableLocator();
        $organizationEmployees = $locator->get('Employees')->find()->select(['id'])->where(['organization_id' => $actor->organization_id]);
        $organizationRequestIds = $locator->get('LeaveRequests')->find()->select(['id'])->where(['employee_id IN' => $organizationEmployees]);
        $organizationAttendanceIds = $locator->get('AttendanceRecords')->find()->select(['id'])->where(['employee_id IN' => $organizationEmployees]);
        $attendance = $locator->get('AttendanceAudits')->find()
            ->contain(['Actors' => fn ($q) => $q->select(['id', 'first_name', 'last_name']), 'AttendanceRecords' => ['Employees' => fn ($q) => $q->select(['id', 'first_name', 'last_name'])]])
            ->where(['attendance_record_id IN' => $organizationAttendanceIds])
            ->orderBy(['AttendanceAudits.created' => 'DESC'])->limit(100)->all();
        $leave = $locator->get('LeaveRequestAudits')->find()
            ->contain(['Actors' => fn ($q) => $q->select(['id', 'first_name', 'last_name']), 'LeaveRequests' => ['Employees' => fn ($q) => $q->select(['id', 'first_name', 'last_name']), 'LeaveTypes' => fn ($q) => $q->select(['id', 'name'])]])
            ->where(['leave_request_id IN' => $organizationRequestIds])
            ->orderBy(['LeaveRequestAudits.created' => 'DESC'])->limit(100)->all();

        $items = [];
        foreach ($attendance as $audit) $items[] = ['id' => 'attendance-' . $audit->id, 'area' => 'attendance', 'action' => $audit->action, 'reason' => $audit->reason, 'created' => $audit->created, 'actor' => $audit->actor, 'subject' => $audit->attendance_record?->employee, 'work_date' => $audit->attendance_record?->work_date, 'before_data' => json_decode((string)$audit->before_data, true), 'after_data' => json_decode((string)$audit->after_data, true)];
        foreach ($leave as $audit) $items[] = ['id' => 'leave-' . $audit->id, 'area' => 'leave', 'action' => $audit->action, 'reason' => $audit->reason, 'created' => $audit->created, 'actor' => $audit->actor, 'subject' => $audit->leave_request?->employee, 'leave_type' => $audit->leave_request?->leave_type?->name];
        usort($items, fn (array $a, array $b) => strtotime((string)$b['created']) <=> strtotime((string)$a['created']));
        return $this->json(['data' => $items]);
    }
}
