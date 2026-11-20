<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class OrganizationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('organizations');
        $this->addBehavior('Timestamp');
        $this->hasMany('Employees', ['foreignKey' => 'organization_id']);
        $this->hasMany('Departments', ['foreignKey' => 'organization_id']);
    }
}
