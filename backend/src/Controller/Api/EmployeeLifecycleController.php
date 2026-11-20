<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;

class EmployeeLifecycleController extends AppController
{
    private const ONBOARDING = [
        'profile' => 'Complete employee profile', 'documents' => 'Collect required identity documents',
        'reporting_line' => 'Assign department and reporting line', 'orientation' => 'Complete orientation',
    ];
    private const OFFBOARDING = [
        'knowledge_transfer' => 'Confirm knowledge transfer', 'assets' => 'Recover company assets',
        'access' => 'Disable system access', 'final_balance' => 'Review final leave balance',
    ];

    public function view(int $id): \Cake\Http\Response
    {
        $this->requireAdmin();
        return $this->json(['data' => $this->payload($id)]);
    }

    public function updateTask(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['patch']); $actor = $this->currentEmployee(); $this->requireAdmin();
        $this->requireEmployeeInOrganization($id, (int)$actor->organization_id);
        $data = $this->request->getData(); $phase = (string)($data['phase'] ?? ''); $key = (string)($data['task_key'] ?? ''); $completed = filter_var($data['completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $tasksForPhase = $phase === 'onboarding' ? self::ONBOARDING : ($phase === 'offboarding' ? self::OFFBOARDING : []);
        if (!isset($tasksForPhase[$key])) throw new UnprocessableEntityException('Choose a valid lifecycle task.');
        $tasks = TableRegistry::getTableLocator()->get('EmployeeLifecycleTasks');
        $task = $tasks->find()->where(['employee_id' => $id, 'phase' => $phase, 'task_key' => $key])->first() ?: $tasks->newEntity(['employee_id' => $id, 'phase' => $phase, 'task_key' => $key, 'title' => $tasksForPhase[$key]]);
        $task->completed_at = $completed ? new \DateTimeImmutable() : null; $task->completed_by = $completed ? $actor->id : null;
        if (!$tasks->save($task)) throw new UnprocessableEntityException('Checklist task could not be saved.');
        return $this->json(['data' => $this->payload($id)]);
    }

    public function offboard(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']); $this->requireAdmin(); $data = $this->request->getData(); $reason = trim((string)($data['reason'] ?? '')); $date = trim((string)($data['offboarding_date'] ?? ''));
        if ($reason === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new UnprocessableEntityException('Provide an offboarding date and reason.');
        $employees = TableRegistry::getTableLocator()->get('Employees'); $actor = $this->currentEmployee(); $employee = $employees->find()->where(['id' => $id, 'organization_id' => $actor->organization_id])->firstOrFail(); $employee->offboarding_date = $date; $employee->offboarding_reason = $reason; $employee->is_active = false;
        if (!$employees->save($employee)) throw new UnprocessableEntityException('Employee could not be offboarded.');
        return $this->json(['data' => $this->payload($id), 'message' => 'Employee account deactivated and offboarding record saved.']);
    }

    private function payload(int $id): array
    {
        $locator = TableRegistry::getTableLocator(); $actor = $this->currentEmployee(); $employee = $locator->get('Employees')->find()->contain(['Departments', 'EmployeeDocuments', 'EmployeeLifecycleTasks'])->where(['Employees.id' => $id, 'Employees.organization_id' => $actor->organization_id])->firstOrFail();
        $balances = $locator->get('LeaveBalances')->find()->contain(['LeaveTypes'])->where(['employee_id' => $id, 'year' => (int)date('Y')])->all()->toList();
        return ['employee' => $employee, 'tasks' => $employee->employee_lifecycle_tasks, 'onboarding_tasks' => self::ONBOARDING, 'offboarding_tasks' => self::OFFBOARDING, 'leave_balances' => $balances];
    }
    private function requireEmployeeInOrganization(int $id, int $organizationId): void
    {
        if (!TableRegistry::getTableLocator()->get('Employees')->exists(['id' => $id, 'organization_id' => $organizationId])) {
            throw new \Cake\Http\Exception\NotFoundException('Employee not found in this organization.');
        }
    }
    private function requireAdmin(): void { if ($this->currentEmployee()->role !== 'hr_admin') throw new UnauthorizedException('Only HR administrators can manage employee lifecycle records.'); }
}
