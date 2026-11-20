<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddEmployeeGender extends AbstractMigration
{
    public function change(): void
    {
        $this->table('employees')
            ->addColumn('gender', 'string', ['limit' => 10, 'null' => true, 'after' => 'last_name'])
            ->update();
    }
}
