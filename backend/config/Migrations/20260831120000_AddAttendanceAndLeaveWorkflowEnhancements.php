<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddAttendanceAndLeaveWorkflowEnhancements extends AbstractMigration
{
    public function change(): void
    {
        $this->table('attendance_records')
            ->addColumn('employee_id', 'integer')
            ->addColumn('work_date', 'date')
            ->addColumn('clock_in_at', 'datetime', ['null' => true])
            ->addColumn('clock_out_at', 'datetime', ['null' => true])
            ->addColumn('clock_in_note', 'text', ['null' => true])
            ->addColumn('clock_out_note', 'text', ['null' => true])
            ->addColumn('status', 'enum', ['values' => ['present', 'remote', 'late', 'absent', 'on_leave'], 'default' => 'present'])
            ->addColumn('total_minutes', 'integer', ['default' => 0])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['employee_id', 'work_date'], ['unique' => true])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('approval_delegations')
            ->addColumn('delegator_id', 'integer')
            ->addColumn('delegate_id', 'integer')
            ->addColumn('starts_on', 'date')
            ->addColumn('ends_on', 'date')
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addForeignKey('delegator_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('delegate_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('leave_request_audits')
            ->addColumn('leave_request_id', 'integer')
            ->addColumn('actor_id', 'integer')
            ->addColumn('action', 'string', ['limit' => 50])
            ->addColumn('reason', 'text', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addForeignKey('leave_request_id', 'leave_requests', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('actor_id', 'employees', 'id', ['delete' => 'RESTRICT'])
            ->create();

        $this->table('leave_requests')
            ->addColumn('duration', 'enum', ['values' => ['full_day', 'half_day'], 'default' => 'full_day', 'after' => 'requested_days'])
            ->addColumn('cancelled_at', 'datetime', ['null' => true, 'after' => 'approved_at'])
            ->addColumn('cancellation_reason', 'text', ['null' => true, 'after' => 'cancelled_at'])
            ->update();
    }
}
