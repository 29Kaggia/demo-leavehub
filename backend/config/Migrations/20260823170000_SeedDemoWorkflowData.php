<?php
declare(strict_types=1);

use \Migrations\AbstractMigration;
use Cake\Core\Configure;

/**
 * Demo accounts and a pending request for exercising the leave workflow locally.
 */
final class SeedDemoWorkflowData extends AbstractMigration
{
    public function up(): void
    {
        if (!Configure::read('debug')) {
            return;
        }

        if ($this->fetchRow("SELECT id FROM employees WHERE email = 'demo.employee@leavehub.test'")) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $passwordHash = password_hash('DemoPass123!', PASSWORD_DEFAULT);

        $this->table('departments')->insert([
            ['name' => 'People Operations', 'created' => $now, 'modified' => $now],
            ['name' => 'Engineering', 'created' => $now, 'modified' => $now],
        ])->save();

        $peopleOpsId = (int)$this->fetchRow("SELECT id FROM departments WHERE name = 'People Operations'")['id'];
        $engineeringId = (int)$this->fetchRow("SELECT id FROM departments WHERE name = 'Engineering'")['id'];

        $this->table('employees')->insert([
            [
                'employee_number' => 'DEMO-HR-001',
                'first_name' => 'Harper',
                'last_name' => 'Reed',
                'email' => 'demo.hr@leavehub.test',
                'password_hash' => $passwordHash,
                'department_id' => $peopleOpsId,
                'role' => 'hr_admin',
                'is_active' => true,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'employee_number' => 'DEMO-LM-001',
                'first_name' => 'Liam',
                'last_name' => 'Mason',
                'email' => 'demo.manager@leavehub.test',
                'password_hash' => $passwordHash,
                'department_id' => $engineeringId,
                'role' => 'line_manager',
                'is_active' => true,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'employee_number' => 'DEMO-RO-001',
                'first_name' => 'Riley',
                'last_name' => 'Okafor',
                'email' => 'demo.relief@leavehub.test',
                'password_hash' => $passwordHash,
                'department_id' => $engineeringId,
                'role' => 'relief_officer',
                'is_active' => true,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'employee_number' => 'DEMO-EMP-001',
                'first_name' => 'Avery',
                'last_name' => 'Kim',
                'email' => 'demo.employee@leavehub.test',
                'password_hash' => $passwordHash,
                'department_id' => $engineeringId,
                'role' => 'employee',
                'is_active' => true,
                'created' => $now,
                'modified' => $now,
            ],
        ])->save();

        $managerId = (int)$this->fetchRow("SELECT id FROM employees WHERE email = 'demo.manager@leavehub.test'")['id'];
        $reliefOfficerId = (int)$this->fetchRow("SELECT id FROM employees WHERE email = 'demo.relief@leavehub.test'")['id'];
        $employeeId = (int)$this->fetchRow("SELECT id FROM employees WHERE email = 'demo.employee@leavehub.test'")['id'];
        $annualLeaveId = (int)$this->fetchRow("SELECT id FROM leave_types WHERE code = 'ANNUAL'")['id'];

        $this->execute("UPDATE employees SET line_manager_id = {$managerId}, relief_officer_id = {$reliefOfficerId}, modified = '{$now}' WHERE id = {$employeeId}");

        $this->table('leave_balances')->insert([
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $annualLeaveId,
                'year' => 2026,
                'entitled_days' => 21,
                'used_days' => 0,
                'created' => $now,
                'modified' => $now,
            ],
        ])->save();

        $this->table('leave_requests')->insert([
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $annualLeaveId,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-16',
                'requested_days' => 3,
                'reason' => 'Family event',
                'handover_notes' => 'Project updates and contact details are documented for cover.',
                'status' => 'pending_relief_officer',
                'submitted_at' => $now,
                'created' => $now,
                'modified' => $now,
            ],
        ])->save();

        $requestId = (int)$this->fetchRow("SELECT id FROM leave_requests WHERE employee_id = {$employeeId} ORDER BY id DESC LIMIT 1")['id'];
        $this->table('leave_approvals')->insert([
            ['leave_request_id' => $requestId, 'approver_id' => $reliefOfficerId, 'stage' => 'relief_officer', 'decision' => 'pending', 'created' => $now, 'modified' => $now],
            ['leave_request_id' => $requestId, 'approver_id' => $managerId, 'stage' => 'line_manager', 'decision' => 'pending', 'created' => $now, 'modified' => $now],
        ])->save();
    }
}
