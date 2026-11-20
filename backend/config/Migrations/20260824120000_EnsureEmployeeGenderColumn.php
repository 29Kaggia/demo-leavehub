<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class EnsureEmployeeGenderColumn extends AbstractMigration
{
    public function up(): void
    {
        $employees = $this->table('employees');
        if (!$employees->hasColumn('gender')) {
            $employees
                ->addColumn('gender', 'string', ['limit' => 10, 'null' => true])
                ->update();
        }
    }

    public function down(): void
    {
        $employees = $this->table('employees');
        if ($employees->hasColumn('gender')) {
            $employees->removeColumn('gender')->update();
        }
    }
}
