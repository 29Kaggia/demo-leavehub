<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeaveTypesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('leave_types');
        $this->setDisplayField('name');
        $this->hasMany('LeaveRequests', ['foreignKey' => 'leave_type_id']);
    }
}
