<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Initial schema for the company's leave-management workflow.
 */
final class CreateLeaveManagementTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('departments')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['name'], ['unique' => true])
            ->create();

        $this->table('employees')
            ->addColumn('employee_number', 'string', ['limit' => 50])
            ->addColumn('first_name', 'string', ['limit' => 80])
            ->addColumn('last_name', 'string', ['limit' => 80])
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('department_id', 'integer', ['null' => true])
            ->addColumn('line_manager_id', 'integer', ['null' => true])
            ->addColumn('relief_officer_id', 'integer', ['null' => true])
            ->addColumn('role', 'enum', ['values' => ['employee', 'relief_officer', 'line_manager', 'hr_admin'], 'default' => 'employee'])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['employee_number'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addForeignKey('department_id', 'departments', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('line_manager_id', 'employees', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('relief_officer_id', 'employees', 'id', ['delete' => 'SET_NULL'])
            ->create();

        $this->table('leave_types')
            ->addColumn('name', 'string', ['limit' => 80])
            ->addColumn('code', 'string', ['limit' => 40])
            ->addColumn('days_allowed', 'integer')
            ->addColumn('calculation_basis', 'enum', ['values' => ['working_days', 'calendar_days']])
            ->addColumn('requires_supporting_document', 'boolean', ['default' => true])
            ->addColumn('requires_handover_notes', 'boolean', ['default' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['code'], ['unique' => true])
            ->create();

        $this->table('leave_balances')
            ->addColumn('employee_id', 'integer')
            ->addColumn('leave_type_id', 'integer')
            ->addColumn('year', 'integer')
            ->addColumn('entitled_days', 'decimal', ['precision' => 5, 'scale' => 1])
            ->addColumn('used_days', 'decimal', ['precision' => 5, 'scale' => 1, 'default' => 0])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['employee_id', 'leave_type_id', 'year'], ['unique' => true])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('leave_type_id', 'leave_types', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('leave_requests')
            ->addColumn('employee_id', 'integer')
            ->addColumn('leave_type_id', 'integer')
            ->addColumn('start_date', 'date')
            ->addColumn('end_date', 'date')
            ->addColumn('requested_days', 'decimal', ['precision' => 5, 'scale' => 1])
            ->addColumn('reason', 'text', ['null' => true])
            ->addColumn('handover_notes', 'text')
            ->addColumn('status', 'enum', ['values' => ['draft', 'pending_relief_officer', 'pending_line_manager', 'approved', 'rejected', 'cancelled'], 'default' => 'draft'])
            ->addColumn('submitted_at', 'datetime', ['null' => true])
            ->addColumn('approved_at', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['employee_id', 'status'])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('leave_type_id', 'leave_types', 'id', ['delete' => 'RESTRICT'])
            ->create();

        $this->table('leave_approvals')
            ->addColumn('leave_request_id', 'integer')
            ->addColumn('approver_id', 'integer')
            ->addColumn('stage', 'enum', ['values' => ['relief_officer', 'line_manager']])
            ->addColumn('decision', 'enum', ['values' => ['pending', 'approved', 'rejected'], 'default' => 'pending'])
            ->addColumn('comments', 'text', ['null' => true])
            ->addColumn('decided_at', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['leave_request_id', 'stage'], ['unique' => true])
            ->addForeignKey('leave_request_id', 'leave_requests', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('approver_id', 'employees', 'id', ['delete' => 'RESTRICT'])
            ->create();

        $this->table('leave_attachments')
            ->addColumn('leave_request_id', 'integer')
            ->addColumn('original_filename', 'string', ['limit' => 255])
            ->addColumn('storage_path', 'string', ['limit' => 255])
            ->addColumn('mime_type', 'string', ['limit' => 120])
            ->addColumn('size_bytes', 'integer')
            ->addColumn('created', 'datetime')
            ->addForeignKey('leave_request_id', 'leave_requests', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('leave_types')->insert([
            ['name' => 'Annual leave', 'code' => 'ANNUAL', 'days_allowed' => 21, 'calculation_basis' => 'working_days', 'requires_supporting_document' => true, 'requires_handover_notes' => true, 'is_active' => true, 'created' => date('Y-m-d H:i:s'), 'modified' => date('Y-m-d H:i:s')],
            ['name' => 'Maternity leave', 'code' => 'MATERNITY', 'days_allowed' => 90, 'calculation_basis' => 'calendar_days', 'requires_supporting_document' => true, 'requires_handover_notes' => true, 'is_active' => true, 'created' => date('Y-m-d H:i:s'), 'modified' => date('Y-m-d H:i:s')],
            ['name' => 'Paternity leave', 'code' => 'PATERNITY', 'days_allowed' => 14, 'calculation_basis' => 'calendar_days', 'requires_supporting_document' => true, 'requires_handover_notes' => true, 'is_active' => true, 'created' => date('Y-m-d H:i:s'), 'modified' => date('Y-m-d H:i:s')],
            ['name' => 'Sick leave', 'code' => 'SICK', 'days_allowed' => 14, 'calculation_basis' => 'working_days', 'requires_supporting_document' => true, 'requires_handover_notes' => true, 'is_active' => true, 'created' => date('Y-m-d H:i:s'), 'modified' => date('Y-m-d H:i:s')],
        ])->save();
    }
}
