<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeaveRequestsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('leave_requests');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Employees', ['foreignKey' => 'employee_id']);
        $this->belongsTo('LeaveTypes', ['foreignKey' => 'leave_type_id']);
        $this->hasMany('LeaveApprovals', ['foreignKey' => 'leave_request_id']);
        $this->hasMany('LeaveAttachments', ['foreignKey' => 'leave_request_id']);
        $this->hasMany('LeaveRequestAudits', ['foreignKey' => 'leave_request_id']);
    }
}
