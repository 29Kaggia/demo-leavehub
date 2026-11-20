<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class EmployeePayProfilesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('employee_pay_profiles');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Employees', ['foreignKey' => 'employee_id']);
    }
}
