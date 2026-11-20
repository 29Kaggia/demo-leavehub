<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeaveApprovalsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('leave_approvals');
        $this->addBehavior('Timestamp');
        $this->belongsTo('LeaveRequests', ['foreignKey' => 'leave_request_id']);
        $this->belongsTo('Approvers', ['className' => 'Employees', 'foreignKey' => 'approver_id']);
    }
}
