<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;

class LeaveTypesController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $types = TableRegistry::getTableLocator()->get('LeaveTypes')
            ->find()
            ->where(['is_active' => true])
            ->orderBy(['name' => 'ASC'])
            ->all()
            ->toList();

        return $this->json(['data' => $types]);
    }
}
