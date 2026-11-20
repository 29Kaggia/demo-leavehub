<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class EmployeeDocumentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('employee_documents');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Employees', ['foreignKey' => 'employee_id']);
    }
}
