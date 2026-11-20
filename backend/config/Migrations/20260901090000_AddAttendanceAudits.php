<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddAttendanceAudits extends AbstractMigration
{
    public function change(): void
    {
        $this->table('attendance_audits')
            ->addColumn('attendance_record_id', 'integer')
            ->addColumn('actor_id', 'integer')
            ->addColumn('action', 'string', ['limit' => 50])
            ->addColumn('reason', 'text')
            ->addColumn('before_data', 'text', ['null' => true])
            ->addColumn('after_data', 'text', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['attendance_record_id'])
            ->addIndex(['created'])
            ->addForeignKey('attendance_record_id', 'attendance_records', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('actor_id', 'employees', 'id', ['delete' => 'RESTRICT'])
            ->create();
    }
}
