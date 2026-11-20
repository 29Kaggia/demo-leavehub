<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddEmploymentDatesAndMilestoneNotifications extends AbstractMigration
{
    public function up(): void
    {
        $this->table('employees')
            ->addColumn('employment_start_date', 'date', ['null' => true, 'after' => 'date_of_birth'])
            ->update();

        // Existing accounts predate this field, so their registration date is the best available start date.
        $this->execute('UPDATE employees SET employment_start_date = DATE(created) WHERE employment_start_date IS NULL');

        $this->table('notifications')
            ->addColumn('type', 'string', ['limit' => 50, 'null' => true, 'after' => 'title'])
            ->addColumn('milestone_key', 'string', ['limit' => 120, 'null' => true, 'after' => 'leave_request_id'])
            ->addIndex(['employee_id', 'milestone_key'], ['unique' => true, 'name' => 'notifications_employee_milestone_unique'])
            ->update();
    }

    public function down(): void
    {
        $this->table('notifications')
            ->removeIndexByName('notifications_employee_milestone_unique')
            ->removeColumn('type')
            ->removeColumn('milestone_key')
            ->update();

        $this->table('employees')
            ->removeColumn('employment_start_date')
            ->update();
    }
}
