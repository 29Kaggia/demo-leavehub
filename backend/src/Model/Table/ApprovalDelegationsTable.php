<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class ApprovalDelegationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('approval_delegations');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Delegators', ['className' => 'Employees', 'foreignKey' => 'delegator_id']);
        $this->belongsTo('Delegates', ['className' => 'Employees', 'foreignKey' => 'delegate_id']);
    }
}
