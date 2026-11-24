<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddEmployeeLifecycle extends AbstractMigration
{
    public function change(): void
    {
        $employeeColumns = [
            'offboarding_date' => ['date', ['null' => true, 'after' => 'employment_start_date']],
            'offboarding_reason' => ['text', ['null' => true, 'after' => 'offboarding_date']],
        ];
        foreach ($employeeColumns as $name => [$type, $options]) {
            $employees = $this->table('employees');
            if (!$employees->hasColumn($name)) {
                $employees->addColumn($name, $type, $options)->update();
            }
        }

        if (!$this->hasTable('employee_lifecycle_tasks')) {
            $this->table('employee_lifecycle_tasks')
                ->addColumn('employee_id', 'integer')
                ->addColumn('phase', 'enum', ['values' => ['onboarding', 'offboarding']])
                ->addColumn('task_key', 'string', ['limit' => 80])
                ->addColumn('title', 'string', ['limit' => 160])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addColumn('completed_by', 'integer', ['null' => true])
                ->addColumn('created', 'datetime')
                ->addColumn('modified', 'datetime')
                ->addIndex(['employee_id', 'phase', 'task_key'], ['unique' => true])
                ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('completed_by', 'employees', 'id', ['delete' => 'SET_NULL'])
                ->create();
        }
    }
}
