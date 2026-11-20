<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;

class EmployeesController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $this->requireAdmin();
        $organizationId = (int)$this->currentEmployee()->organization_id;
        $employees = TableRegistry::getTableLocator()->get('Employees')
            ->find()
            ->select([
                'id',
                'employee_number',
                'first_name',
                'last_name',
                'date_of_birth',
                'employment_start_date',
                'gender',
                'email',
                'department_id',
                'line_manager_id',
                'relief_officer_id',
                'role',
                'is_active',
                'created',
            ])
            ->contain(['Departments', 'LineManagers', 'ReliefOfficers'])
            ->where(['Employees.is_active' => true, 'Employees.organization_id' => $organizationId])
            ->orderBy([
    'Employees.last_name' => 'ASC',
    'Employees.first_name' => 'ASC',
])
            ->all()
            ->toList();

        return $this->json(['data' => $employees]);
    }

    public function create(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();
        $data = $this->employeePayload($this->request->getData(), true);
        $data['organization_id'] = (int)$this->currentEmployee()->organization_id;
        $this->validateOrganizationReferences($data, (int)$data['organization_id']);
        $employees = TableRegistry::getTableLocator()->get('Employees');
        $employee = $employees->newEntity($data);
        if (!$employees->save($employee)) {
            throw new UnprocessableEntityException('Employee could not be created.');
        }
        return $this->json(['data' => $employee], 201);
    }

    public function view(int $id): \Cake\Http\Response
    {
        $this->requireAdmin();
        $employee = TableRegistry::getTableLocator()->get('Employees')->find()
            ->contain(['Departments', 'LineManagers', 'ReliefOfficers', 'EmployeeDocuments'])
            ->where(['Employees.id' => $id, 'Employees.organization_id' => $this->currentEmployee()->organization_id])->firstOrFail();
        return $this->json(['data' => $employee]);
    }

    public function update(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['patch']);
        $this->requireAdmin();
        $employees = TableRegistry::getTableLocator()->get('Employees');
        $employee = $employees->find()->where(['id' => $id, 'organization_id' => $this->currentEmployee()->organization_id])->firstOrFail();
        $payload = $this->employeePayload($this->request->getData(), false);
        $this->validateOrganizationReferences($payload, (int)$this->currentEmployee()->organization_id);
        $employee = $employees->patchEntity($employee, $payload);
        if (!$employees->save($employee)) {
            throw new UnprocessableEntityException('Employee could not be updated.');
        }
        $this->syncPendingApprovals($employee);
        $updatedEmployee = $employees->find()
            ->contain(['Departments', 'LineManagers', 'ReliefOfficers'])
            ->where(['Employees.id' => $employee->id, 'Employees.organization_id' => $this->currentEmployee()->organization_id])
            ->firstOrFail();
        return $this->json(['data' => $updatedEmployee]);
    }

    public function reviewDocument(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['patch']);
        $this->requireAdmin();
        $data = $this->request->getData();
        $status = trim((string)($data['review_status'] ?? ''));
        if (!in_array($status, ['verified', 'rejected', 'needs_replacement'], true)) {
            throw new UnprocessableEntityException('Choose a valid document review status.');
        }
        $notes = trim((string)($data['review_notes'] ?? ''));
        if ($status !== 'verified' && $notes === '') {
            throw new UnprocessableEntityException('Add a note explaining why the document needs attention.');
        }
        $documents = TableRegistry::getTableLocator()->get('EmployeeDocuments');
        $organizationEmployees = TableRegistry::getTableLocator()->get('Employees')->find()->select(['id'])->where(['organization_id' => $this->currentEmployee()->organization_id]);
        $document = $documents->find()->where(['id' => $id, 'employee_id IN' => $organizationEmployees])->firstOrFail();
        $document->review_status = $status;
        $document->review_notes = $notes ?: null;
        $document->reviewed_by = $this->currentEmployee()->id;
        $document->reviewed_at = FrozenTime::now();
        if (!$documents->save($document)) {
            throw new UnprocessableEntityException('Document review could not be saved.');
        }
        return $this->json(['data' => $document]);
    }

    /**
     * Pending approvals follow the employee's currently assigned approvers.
     * This prevents an already-submitted request from being left in the inbox
     * of a previous relief officer or line manager after HR changes an assignment.
     */
    private function syncPendingApprovals(object $employee): void
    {
        $approvals = TableRegistry::getTableLocator()->get('LeaveApprovals');
        foreach ([
            ['relief_officer', $employee->relief_officer_id],
            ['line_manager', $employee->line_manager_id],
        ] as [$stage, $approverId]) {
            if (!$approverId) {
                continue;
            }
            $pendingApprovals = $approvals->find()
                ->innerJoinWith('LeaveRequests')
                ->where([
                    'LeaveRequests.employee_id' => $employee->id,
                    'LeaveRequests.status IN' => ['pending_relief_officer', 'pending_line_manager'],
                    'LeaveApprovals.stage' => $stage,
                    'LeaveApprovals.decision' => 'pending',
                ])
                ->all();
            foreach ($pendingApprovals as $approval) {
                $approval->approver_id = $approverId;
                if (!$approvals->save($approval)) {
                    throw new UnprocessableEntityException('Pending approvals could not be reassigned.');
                }
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function employeePayload(array $data, bool $isNew): array
    {
        foreach (['first_name', 'last_name', 'email'] as $field) {
            if ($isNew && !trim((string)($data[$field] ?? ''))) {
                throw new UnprocessableEntityException(ucwords(str_replace('_', ' ', $field)) . ' is required.');
            }
        }
        $role = $data['role'] ?? 'employee';
        if (($isNew || array_key_exists('role', $data)) && !in_array($role, ['employee', 'relief_officer', 'line_manager', 'hr_admin'], true)) {
            throw new UnprocessableEntityException('Invalid employee role.');
        }
        $result = [];
        foreach (['first_name', 'last_name'] as $field) {
            if ($isNew || array_key_exists($field, $data)) {
                $result[$field] = trim((string)($data[$field] ?? ''));
            }
        }
        if ($isNew || array_key_exists('email', $data)) {
            $result['email'] = strtolower(trim((string)($data['email'] ?? '')));
        }
        foreach (['department_id', 'line_manager_id', 'relief_officer_id'] as $field) {
            if ($isNew || array_key_exists($field, $data)) {
                $result[$field] = $this->nullableId($data[$field] ?? null);
            }
        }
        if ($isNew || array_key_exists('role', $data)) {
            $result['role'] = $role;
        }
        if ($isNew || array_key_exists('is_active', $data)) {
            $result['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
        }
        if ($isNew || array_key_exists('gender', $data)) {
            $gender = strtolower(trim((string)($data['gender'] ?? '')));
            if ($gender !== null && $gender !== '' && !in_array($gender, ['female', 'male'], true)) {
                throw new UnprocessableEntityException('Select a valid gender.');
            }
            $result['gender'] = $gender === '' ? null : $gender;
        }
        if ($isNew || array_key_exists('employment_start_date', $data)) {
            $employmentStartDate = trim((string)($data['employment_start_date'] ?? ''));
            if ($employmentStartDate === '') {
                $result['employment_start_date'] = $isNew ? (new \DateTimeImmutable('today'))->format('Y-m-d') : null;
            } elseif (!$this->validDate($employmentStartDate)) {
                throw new UnprocessableEntityException('Provide a valid employment start date.');
            } else {
                $result['employment_start_date'] = $employmentStartDate;
            }
        }
        if ($isNew) {
            if (empty($result['gender'])) {
                throw new UnprocessableEntityException('Gender is required when adding an employee.');
            }
            if (strlen((string)($data['password'] ?? '')) < 8) {
                throw new UnprocessableEntityException('A password of at least 8 characters is required.');
            }
            $result['employee_number'] = 'EMP-' . strtoupper(bin2hex(random_bytes(4)));
            $result['password_hash'] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
        }
        return $result;
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int)$value;
    }

    /** @param array<string, mixed> $data */
    private function validateOrganizationReferences(array $data, int $organizationId): void
    {
        if (array_key_exists('department_id', $data) && $data['department_id'] !== null && !TableRegistry::getTableLocator()->get('Departments')->exists([
            'id' => $data['department_id'], 'organization_id' => $organizationId,
        ])) {
            throw new UnprocessableEntityException('Choose a department from this organization.');
        }
        foreach (['line_manager_id', 'relief_officer_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && !TableRegistry::getTableLocator()->get('Employees')->exists([
                'id' => $data[$field], 'organization_id' => $organizationId,
            ])) {
                throw new UnprocessableEntityException('Choose employee assignments from this organization.');
            }
        }
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function requireAdmin(): void
    {
        if ($this->currentEmployee()->role !== 'hr_admin') {
            throw new UnauthorizedException('Only HR administrators can manage employees.');
        }
    }
}
