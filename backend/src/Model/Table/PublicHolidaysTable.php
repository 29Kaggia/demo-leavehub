<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class PublicHolidaysTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('public_holidays');
        $this->addBehavior('Timestamp');
    }
}
