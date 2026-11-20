<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class EmployeesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('employees');
        $this->setDisplayField('employee_number');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Organizations', ['foreignKey' => 'organization_id']);
        $this->belongsTo('Departments', ['foreignKey' => 'department_id']);
        $this->belongsTo('LineManagers', ['className' => 'Employees', 'foreignKey' => 'line_manager_id']);
        $this->belongsTo('ReliefOfficers', ['className' => 'Employees', 'foreignKey' => 'relief_officer_id']);
        $this->hasMany('LeaveRequests', ['foreignKey' => 'employee_id']);
        $this->hasMany('AttendanceRecords', ['foreignKey' => 'employee_id']);
        $this->hasMany('EmployeeDocuments', ['foreignKey' => 'employee_id']);
        $this->hasMany('EmployeeLifecycleTasks', ['foreignKey' => 'employee_id']);
        $this->hasOne('EmployeePayProfiles', ['foreignKey' => 'employee_id']);
    }
}
