<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\ORM\TableRegistry;

class DepartmentsController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $this->requireAdmin();
        $organizationId = (int)$this->currentEmployee()->organization_id;
        $departments = TableRegistry::getTableLocator()->get('Departments')
            ->find()
            ->where(['organization_id' => $organizationId])
            ->orderBy(['name' => 'ASC'])
            ->all()
            ->toList();
        return $this->json(['data' => $departments]);
    }

    public function create(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();
        $name = trim((string)$this->request->getData('name'));
        if (!$name) {
            throw new UnprocessableEntityException('Department name is required.');
        }
        $departments = TableRegistry::getTableLocator()->get('Departments');
        $department = $departments->newEntity(['name' => $name, 'organization_id' => (int)$this->currentEmployee()->organization_id]);
        if (!$departments->save($department)) {
            throw new UnprocessableEntityException('Department could not be created.');
        }
        return $this->json(['data' => $department], 201);
    }

    private function requireAdmin(): void
    {
        if ($this->currentEmployee()->role !== 'hr_admin') {
            throw new UnauthorizedException('Only HR administrators can manage departments.');
        }
    }
}
