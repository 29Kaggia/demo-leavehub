<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;

class NotificationsController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $items = TableRegistry::getTableLocator()->get('Notifications')->find()
            ->where(['employee_id' => $employee->id])->orderBy(['created' => 'DESC'])->limit(20)->all()->toList();
        return $this->json(['data' => $items]);
    }

    public function markRead(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['patch']);
        $employee = $this->currentEmployee();
        $notifications = TableRegistry::getTableLocator()->get('Notifications');
        $item = $notifications->find()->where(['id' => $id, 'employee_id' => $employee->id])->firstOrFail();
        $item->read_at = new \DateTimeImmutable();
        $notifications->saveOrFail($item);
        return $this->json(['data' => $item]);
    }
}
