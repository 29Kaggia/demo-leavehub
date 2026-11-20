<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;

class ApprovalDelegationsController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $employee = $this->approver();
        $items = TableRegistry::getTableLocator()->get('ApprovalDelegations')->find()->contain(['Delegates'])->where(['delegator_id' => $employee->id])->orderBy(['starts_on' => 'DESC'])->all()->toList();
        return $this->json(['data' => $items]);
    }

    public function create(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $employee = $this->approver(); $data = $this->request->getData();
        $delegateId = (int)($data['delegate_id'] ?? 0); $startsOn = (string)($data['starts_on'] ?? ''); $endsOn = (string)($data['ends_on'] ?? '');
        if (!$delegateId || !$startsOn || !$endsOn || $endsOn < $startsOn || $delegateId === (int)$employee->id) throw new UnprocessableEntityException('Choose another employee and a valid delegation period.');
        $employees = TableRegistry::getTableLocator()->get('Employees');
        if (!$employees->exists(['id' => $delegateId, 'is_active' => true, 'organization_id' => $employee->organization_id])) throw new UnprocessableEntityException('Choose an active employee from your organization as delegate.');
        $delegations = TableRegistry::getTableLocator()->get('ApprovalDelegations');
        $item = $delegations->newEntity(['delegator_id' => $employee->id, 'delegate_id' => $delegateId, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);
        if (!$delegations->save($item)) throw new UnprocessableEntityException('Delegation could not be saved.');
        return $this->json(['data' => $item, 'message' => 'Approval delegation saved.'], 201);
    }

    public function candidates(): \Cake\Http\Response
    {
        $employee = $this->approver();
        $items = TableRegistry::getTableLocator()->get('Employees')->find()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->where(['is_active' => true, 'id !=' => $employee->id, 'organization_id' => $employee->organization_id])
            ->orderBy(['last_name' => 'ASC', 'first_name' => 'ASC'])
            ->all()->toList();
        return $this->json(['data' => $items]);
    }

    public function delete(int $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['delete']); $employee = $this->approver(); $delegations = TableRegistry::getTableLocator()->get('ApprovalDelegations'); $item = $delegations->find()->where(['id' => $id, 'delegator_id' => $employee->id])->firstOrFail();
        $delegations->deleteOrFail($item);
        return $this->json(['message' => 'Approval delegation removed.']);
    }

    private function approver(): object
    {
        $employee = $this->currentEmployee();
        if (!in_array($employee->role, ['line_manager', 'relief_officer'], true)) throw new UnauthorizedException('Only approvers can manage approval delegation.');
        return $employee;
    }
}
