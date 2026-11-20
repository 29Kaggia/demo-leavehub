<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;

class NotificationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('notifications');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Employees', ['foreignKey' => 'employee_id']);
        $this->belongsTo('LeaveRequests', ['foreignKey' => 'leave_request_id']);
    }

    /**
     * New in-app alerts are also emailed to their intended recipient.
     * A delivery failure is logged but never prevents the in-app alert from saving.
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$entity->isNew()) {
            return;
        }

        $recipient = $this->getAssociation('Employees')
            ->find()
            ->select(['id', 'email', 'first_name'])
            ->where(['Employees.id' => $entity->employee_id, 'Employees.is_active' => true])
            ->first();
        if (!$recipient || !$recipient->email) {
            return;
        }

        try {
            $mailer = new Mailer('default');
            $message = $mailer->getMessage();
            $message
                ->setTo($recipient->email, $recipient->first_name)
                ->setSubject('LeaveHub: ' . $entity->title)
                ->setEmailFormat('text')
                ->setBodyText($entity->message . "\n\nOpen LeaveHub to view this notification.");
            $mailer->getTransport()->send($message);
        } catch (\Throwable $exception) {
            Log::warning(sprintf(
                'Notification email could not be sent to employee %d: %s',
                $entity->employee_id,
                $exception->getMessage(),
            ));
        }
    }
}
