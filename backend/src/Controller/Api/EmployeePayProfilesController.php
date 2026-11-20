<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

class EmployeePayProfilesController extends AppController
{
    public function view(int $employeeId): \Cake\Http\Response
    {
        $admin = $this->currentEmployee();
        $this->requireAdmin($admin);
        $employee = TableRegistry::getTableLocator()->get('Employees')->find()
            ->where(['id' => $employeeId, 'organization_id' => $admin->organization_id])->first();
        if (!$employee) {
            throw new NotFoundException('Employee not found in this organization.');
        }
        $profile = TableRegistry::getTableLocator()->get('EmployeePayProfiles')->find()
            ->where(['employee_id' => $employeeId])->orderBy(['effective_from' => 'DESC'])->first();
        return $this->json(['data' => $profile]);
    }

    public function save(int $employeeId): \Cake\Http\Response
    {
        $this->request->allowMethod(['put', 'patch']);
        $admin = $this->currentEmployee();
        $this->requireAdmin($admin);
        $employees = TableRegistry::getTableLocator()->get('Employees');
        if (!$employees->exists(['id' => $employeeId, 'organization_id' => $admin->organization_id])) {
            throw new NotFoundException('Employee not found in this organization.');
        }
        $data = $this->request->getData();
        $salary = $data['base_salary'] ?? null;
        $allowances = $data['fixed_allowances'] ?? 0;
        $deductions = $data['recurring_deductions'] ?? 0;
        $effective = trim((string)($data['effective_from'] ?? ''));
        if (!is_numeric($salary) || (float)$salary < 0 || !is_numeric($allowances) || (float)$allowances < 0 || !is_numeric($deductions) || (float)$deductions < 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) {
            throw new BadRequestException('Enter non-negative salary, allowance and deduction amounts and a valid effective date.');
        }
        $organizations = TableRegistry::getTableLocator()->get('Organizations');
        $organization = $organizations->get((int)$admin->organization_id);
        $frequency = (string)($data['pay_frequency'] ?? $organization->pay_frequency);
        if (!in_array($frequency, ['monthly', 'biweekly', 'weekly'], true)) {
            throw new BadRequestException('Choose a valid pay frequency.');
        }
        $profiles = TableRegistry::getTableLocator()->get('EmployeePayProfiles');
        $profile = $profiles->find()->where(['employee_id' => $employeeId, 'effective_from' => $effective])->first() ?? $profiles->newEntity(['employee_id' => $employeeId, 'effective_from' => $effective]);
        $profile = $profiles->patchEntity($profile, [
            'base_salary' => $salary,
            'fixed_allowances' => $allowances,
            'recurring_deductions' => $deductions,
            'currency_code' => $organization->currency_code,
            'pay_frequency' => $frequency,
            'effective_from' => $effective,
        ]);
        if (!$profiles->save($profile)) {
            throw new BadRequestException('Employee pay details could not be saved.');
        }
        return $this->json(['data' => $profile, 'message' => 'Employee pay details saved.']);
    }

    private function requireAdmin(object $employee): void
    {
        if ($employee->role !== 'hr_admin') {
            throw new ForbiddenException('Only organization administrators can view employee pay details.');
        }
    }
}
