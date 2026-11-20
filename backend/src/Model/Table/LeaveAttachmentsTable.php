<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeaveAttachmentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('leave_attachments');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);
        $this->belongsTo('LeaveRequests', ['foreignKey' => 'leave_request_id']);
    }
}
