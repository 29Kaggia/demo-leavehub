<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddEmployeePasswordHash extends AbstractMigration
{
    public function change(): void
    {
        $this->table('employees')
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true, 'after' => 'email'])
            ->update();
    }
}
