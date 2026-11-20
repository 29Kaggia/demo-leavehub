<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class AttendanceAuditsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('attendance_audits');
        $this->addBehavior('Timestamp');
        $this->belongsTo('AttendanceRecords', ['foreignKey' => 'attendance_record_id']);
        $this->belongsTo('Actors', ['className' => 'Employees', 'foreignKey' => 'actor_id']);
    }
}
