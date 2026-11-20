<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

class AuthController extends AppController
{
    public function options(): \Cake\Http\Response
    {
        return $this->json(['data' => ['signup_enabled' => (bool)Configure::read('Auth.allowSignup', true)]]);
    }

    public function signup(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        if (!Configure::read('Auth.allowSignup', true)) {
            throw new ForbiddenException('Account creation is disabled. Contact your HR administrator.');
        }
        $data = $this->request->getData();
        $required = ['first_name', 'last_name', 'email', 'password', 'organization_name'];
        foreach ($required as $field) {
            if (!trim((string)($data[$field] ?? ''))) {
                throw new UnprocessableEntityException(ucwords(str_replace('_', ' ', $field)) . ' is required.');
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new UnprocessableEntityException('Enter a valid email address.');
        }
        if (strlen((string)$data['password']) < 8) {
            throw new UnprocessableEntityException('Your password must have at least 8 characters.');
        }
        $employees = TableRegistry::getTableLocator()->get('Employees');
        $existing = $employees->find()->where([
            'email' => strtolower(trim((string)$data['email'])),
        ])->first();
        if ($existing) {
            throw new UnprocessableEntityException('An account with that email already exists.');
        }
        $organizationName = trim((string)$data['organization_name']);
        if (mb_strlen($organizationName) > 160) {
            throw new UnprocessableEntityException('Organization name must be 160 characters or fewer.');
        }
        $organizations = TableRegistry::getTableLocator()->get('Organizations');
        $employee = $employees->getConnection()->transactional(function () use ($organizations, $employees, $organizationName, $data) {
            $organization = $organizations->newEntity([
                'name' => $organizationName,
                'country_code' => 'KE',
                'currency_code' => 'KES',
                'pay_frequency' => 'monthly',
            ]);
            if (!$organizations->save($organization)) {
                throw new UnprocessableEntityException('Organization could not be created.');
            }
            $newEmployee = $employees->newEntity([
                'organization_id' => $organization->id,
                'employee_number' => 'EMP-' . strtoupper(bin2hex(random_bytes(4))),
                'first_name' => trim((string)$data['first_name']),
                'last_name' => trim((string)$data['last_name']),
                'email' => strtolower(trim((string)$data['email'])),
                'password_hash' => password_hash((string)$data['password'], PASSWORD_DEFAULT),
                'role' => 'hr_admin',
                'is_active' => true,
            ]);
            if (!$employees->save($newEmployee)) {
                throw new UnprocessableEntityException('Organization administrator account could not be created.');
            }
            return $newEmployee;
        });
        $this->request->getSession()->write('Auth.employee_id', $employee->id);
        return $this->json(['data' => $this->employeeData($employee), 'message' => 'Organization and administrator account created successfully.'], 201);
    }

    public function login(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $email = strtolower(trim((string)$this->request->getData('email')));
        $password = (string)$this->request->getData('password');
        if (!$email || !$password) {
            throw new BadRequestException('Email and password are required.');
        }
        $employee = TableRegistry::getTableLocator()->get('Employees')
            ->find()
            ->where(['email' => $email, 'is_active' => true])
            ->first();
        if (!$employee || !$employee->password_hash || !password_verify($password, $employee->password_hash)) {
            throw new UnauthorizedException('Email or password is incorrect.');
        }
        $this->request->getSession()->write('Auth.employee_id', $employee->id);
        return $this->json(['data' => $this->employeeData($employee), 'message' => 'Welcome back.']);
    }

    public function logout(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->request->getSession()->delete('Auth');
        return $this->json(['message' => 'You have been signed out.']);
    }

    public function changePassword(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $employee = $this->currentEmployee();
        $data = $this->request->getData();
        $currentPassword = (string)($data['current_password'] ?? '');
        $newPassword = (string)($data['new_password'] ?? '');

        if (!$employee->password_hash || !password_verify($currentPassword, $employee->password_hash)) {
            throw new UnauthorizedException('Current password is incorrect.');
        }
        if (strlen($newPassword) < 8) {
            throw new UnprocessableEntityException('Your new password must have at least 8 characters.');
        }
        if (password_verify($newPassword, $employee->password_hash)) {
            throw new UnprocessableEntityException('Choose a password different from your current one.');
        }

        $employee->password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!TableRegistry::getTableLocator()->get('Employees')->save($employee)) {
            throw new UnprocessableEntityException('Your password could not be changed.');
        }

        return $this->json(['message' => 'Your password has been changed.']);
    }

    public function me(): \Cake\Http\Response
    {
        return $this->json(['data' => $this->employeeData($this->currentEmployee())]);
    }
}
