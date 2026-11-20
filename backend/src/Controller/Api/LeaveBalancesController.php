<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\I18n\FrozenDate;
use Cake\ORM\TableRegistry;

class LeaveBalancesController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $year = (int)($this->request->getQuery('year') ?: date('Y'));
        $locator = TableRegistry::getTableLocator();
        $types = $locator->get('LeaveTypes')->find()->where(['is_active' => true])->orderBy(['name' => 'ASC'])->all();
        $stored = $locator->get('LeaveBalances')->find()
            ->where(['employee_id' => $employee->id, 'year' => $year])
            ->all()
            ->indexBy('leave_type_id')
            ->toArray();
        $data = [];
        foreach ($types as $type) {
            $balance = $stored[$type->id] ?? null;
            $entitled = $balance ? (float)$balance->entitled_days : (float)$type->days_allowed;
            $used = $balance ? (float)$balance->used_days : 0.0;
            $data[] = ['leave_type_id' => $type->id, 'leave_type' => $type->name, 'year' => $year, 'entitled_days' => $entitled, 'used_days' => $used, 'remaining_days' => max(0, $entitled - $used)];
        }
        return $this->json(['data' => $data]);
    }
}
