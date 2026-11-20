<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    /**
     * Return an API response without rendering a template.
     *
     * @param array<string, mixed> $data Response payload.
     * @param int $status HTTP status code.
     * @return \Cake\Http\Response
     */
    protected function json(array $data, int $status = 200): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody((string)json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Get the signed-in employee or stop the request.
     *
     * @return object
     */
    protected function currentEmployee(): object
    {
        $employeeId = $this->request->getSession()->read('Auth.employee_id');
        if (!$employeeId) {
            throw new UnauthorizedException('Please sign in to continue.');
        }
        $employee = TableRegistry::getTableLocator()->get('Employees')
            ->find()
            ->where(['id' => (int)$employeeId, 'is_active' => true])
            ->first();
        if (!$employee) {
            $this->request->getSession()->delete('Auth');
            throw new UnauthorizedException('Your account is no longer available.');
        }

        return $employee;
    }

    /**
     * @param object $employee Employee entity.
     * @return array<string, mixed>
     */
    protected function employeeData(object $employee): array
    {
        return [
            'id' => $employee->id,
            'organization_id' => $employee->organization_id,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => $employee->email,
            'role' => $employee->role,
        ];
    }
}
