<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddDocumentReviewFields extends AbstractMigration
{
    public function change(): void
    {
        $this->table('employee_documents')
            ->addColumn('review_status', 'string', ['limit' => 24, 'default' => 'pending'])
            ->addColumn('review_notes', 'text', ['null' => true])
            ->addColumn('reviewed_by', 'integer', ['null' => true])
            ->addColumn('reviewed_at', 'datetime', ['null' => true])
            ->addIndex(['review_status'])
            ->addForeignKey('reviewed_by', 'employees', 'id', ['delete' => 'SET_NULL'])
            ->update();
    }
}
