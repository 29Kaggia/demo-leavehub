<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeaveBalancesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('leave_balances');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Employees', ['foreignKey' => 'employee_id']);
        $this->belongsTo('LeaveTypes', ['foreignKey' => 'leave_type_id']);
    }
}
