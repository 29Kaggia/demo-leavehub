<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class EmployeeLifecycleTasksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('employee_lifecycle_tasks');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Employees', ['foreignKey' => 'employee_id']);
        // Keep the association property distinct from the completed_by foreign-key field.
        // Without this, CakePHP raises a field/association collision while marshalling
        // a checklist task, preventing its completion state from being saved.
        $this->belongsTo('CompletedBy', [
            'className' => 'Employees',
            'foreignKey' => 'completed_by',
            'propertyName' => 'completed_by_employee',
        ]);
    }
}
