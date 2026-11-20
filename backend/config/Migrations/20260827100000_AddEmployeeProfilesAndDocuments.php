<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddEmployeeProfilesAndDocuments extends AbstractMigration
{
    public function change(): void
    {
        $this->table('employees')->addColumn('date_of_birth', 'date', ['null' => true, 'after' => 'last_name'])->update();
        $this->table('employee_documents')
            ->addColumn('employee_id', 'integer')
            ->addColumn('document_type', 'string', ['limit' => 80])
            ->addColumn('original_filename', 'string', ['limit' => 255])
            ->addColumn('storage_path', 'string', ['limit' => 255])
            ->addColumn('mime_type', 'string', ['limit' => 120])
            ->addColumn('size_bytes', 'integer')
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
