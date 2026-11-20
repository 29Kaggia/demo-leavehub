<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddLeavePlanningFeatures extends AbstractMigration
{
    public function change(): void
    {
        $this->table('public_holidays')
            ->addColumn('holiday_date', 'date')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['holiday_date'], ['unique' => true])
            ->create();

        $this->table('notifications')
            ->addColumn('employee_id', 'integer')
            ->addColumn('title', 'string', ['limit' => 160])
            ->addColumn('message', 'text')
            ->addColumn('leave_request_id', 'integer', ['null' => true])
            ->addColumn('read_at', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addIndex(['employee_id', 'read_at'])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('leave_request_id', 'leave_requests', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
