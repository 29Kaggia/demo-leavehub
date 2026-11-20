<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeaveRequestAuditsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('leave_request_audits');
        $this->addBehavior('Timestamp');
        $this->belongsTo('LeaveRequests', ['foreignKey' => 'leave_request_id']);
        $this->belongsTo('Actors', ['className' => 'Employees', 'foreignKey' => 'actor_id']);
    }
}
