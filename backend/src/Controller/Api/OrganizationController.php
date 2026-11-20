<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\ORM\TableRegistry;

class OrganizationController extends AppController
{
    public function view(): \Cake\Http\Response
    {
        $employee = $this->currentEmployee();
        $organization = TableRegistry::getTableLocator()->get('Organizations')->get((int)$employee->organization_id);
        return $this->json(['data' => $organization]);
    }

    public function update(): \Cake\Http\Response
    {
        $this->request->allowMethod(['patch']);
        $employee = $this->currentEmployee();
        if ($employee->role !== 'hr_admin') {
            throw new ForbiddenException('Only organization administrators can update organization settings.');
        }
        $data = $this->request->getData();
        $name = trim((string)($data['name'] ?? ''));
        $country = strtoupper(trim((string)($data['country_code'] ?? '')));
        $currency = strtoupper(trim((string)($data['currency_code'] ?? '')));
        $frequency = (string)($data['pay_frequency'] ?? '');
        if ($name === '' || strlen($name) > 160 || !preg_match('/^[A-Z]{2}$/', $country) || !preg_match('/^[A-Z]{3}$/', $currency) || !in_array($frequency, ['monthly', 'biweekly', 'weekly'], true)) {
            throw new BadRequestException('Enter an organization name, two-letter country code, three-letter currency code, and valid pay frequency.');
        }
        $organizations = TableRegistry::getTableLocator()->get('Organizations');
        $organization = $organizations->get((int)$employee->organization_id);
        $organization = $organizations->patchEntity($organization, ['name' => $name, 'country_code' => $country, 'currency_code' => $currency, 'pay_frequency' => $frequency]);
        if (!$organizations->save($organization)) {
            throw new BadRequestException('Organization settings could not be saved.');
        }
        return $this->json(['data' => $organization, 'message' => 'Organization settings saved.']);
    }
}
