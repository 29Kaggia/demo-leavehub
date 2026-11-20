<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Utility\DocumentStorage;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

class ApprovalInboxController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $approvals = TableRegistry::getTableLocator()->get('LeaveApprovals')
            ->find()
            ->innerJoinWith('LeaveRequests')
            ->where(['LeaveApprovals.approver_id' => $employee->id, 'LeaveApprovals.decision' => 'pending'])
            ->where(['LeaveRequests.employee_id IN' => TableRegistry::getTableLocator()->get('Employees')->find()->select(['id'])->where(['is_active' => true])])
            ->where(['OR' => [
                ['LeaveApprovals.stage' => 'relief_officer', 'LeaveRequests.status' => 'pending_relief_officer'],
                ['LeaveApprovals.stage' => 'line_manager', 'LeaveRequests.status' => 'pending_line_manager'],
            ]])
            ->contain(['LeaveRequests' => ['Employees', 'LeaveTypes', 'LeaveAttachments']])
            ->orderBy(['LeaveRequests.start_date' => 'ASC'])
            ->all()
            ->toList();
        return $this->json(['data' => $approvals]);
    }

    public function downloadAttachment(int $id): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $attachment = TableRegistry::getTableLocator()->get('LeaveAttachments')
            ->find()
            ->contain(['LeaveRequests' => ['LeaveApprovals', 'Employees']])
            ->where(['LeaveAttachments.id' => $id])
            ->first();
        if (!$attachment) {
            throw new NotFoundException('Attachment not found.');
        }
        $request = $attachment->leave_request;
        if ((int)$request->employee->organization_id !== (int)$employee->organization_id) {
            throw new ForbiddenException('You cannot access this document.');
        }
        $isApprover = false;
        foreach ($request->leave_approvals as $approval) {
            if ((int)$approval->approver_id === (int)$employee->id) {
                $isApprover = true;
                break;
            }
        }
        if ($employee->role !== 'hr_admin' && (int)$request->employee_id !== (int)$employee->id && !$isApprover) {
            throw new ForbiddenException('You cannot access this document.');
        }
        $path = DocumentStorage::path($attachment->storage_path);
        if (!is_file($path)) {
            throw new NotFoundException('Attachment file not found.');
        }
        return $this->response->withFile($path, ['download' => true, 'name' => $attachment->original_filename]);
    }
}
