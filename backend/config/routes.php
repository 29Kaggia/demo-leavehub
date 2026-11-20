<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/*
 * This file is loaded in the context of the `Application` class.
 * So you can use `$this` to reference the application class instance
 * if required.
 */
return function (RouteBuilder $routes): void {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->connect('/auth/options', ['prefix' => 'Api', 'controller' => 'Auth', 'action' => 'options', '_method' => 'GET']);
        $builder->connect('/auth/signup', ['prefix' => 'Api', 'controller' => 'Auth', 'action' => 'signup', '_method' => 'POST']);
        $builder->connect('/auth/login', ['prefix' => 'Api', 'controller' => 'Auth', 'action' => 'login', '_method' => 'POST']);
        $builder->connect('/auth/logout', ['prefix' => 'Api', 'controller' => 'Auth', 'action' => 'logout', '_method' => 'POST']);
        $builder->connect('/auth/change-password', ['prefix' => 'Api', 'controller' => 'Auth', 'action' => 'changePassword', '_method' => 'POST']);
        $builder->connect('/auth/me', ['prefix' => 'Api', 'controller' => 'Auth', 'action' => 'me', '_method' => 'GET']);
        $builder->connect('/organization', ['prefix' => 'Api', 'controller' => 'Organization', 'action' => 'view', '_method' => 'GET']);
        $builder->connect('/organization', ['prefix' => 'Api', 'controller' => 'Organization', 'action' => 'update', '_method' => 'PATCH']);
        $builder->connect('/employees/{id}/pay-profile', ['prefix' => 'Api', 'controller' => 'EmployeePayProfiles', 'action' => 'view', '_method' => 'GET'], ['pass' => ['id']]);
        $builder->connect('/employees/{id}/pay-profile', ['prefix' => 'Api', 'controller' => 'EmployeePayProfiles', 'action' => 'save', '_method' => ['PUT', 'PATCH']], ['pass' => ['id']]);
        $builder->connect('/leave-types', ['prefix' => 'Api', 'controller' => 'LeaveTypes', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/leave-assistant', ['prefix' => 'Api', 'controller' => 'LeaveAssistant', 'action' => 'chat', '_method' => 'POST']);
        $builder->connect('/leave-balances', ['prefix' => 'Api', 'controller' => 'LeaveBalances', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/public-holidays', ['prefix' => 'Api', 'controller' => 'PublicHolidays', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/public-holidays', ['prefix' => 'Api', 'controller' => 'PublicHolidays', 'action' => 'create', '_method' => 'POST']);
        $builder->connect('/notifications', ['prefix' => 'Api', 'controller' => 'Notifications', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/notifications/{id}/read', ['prefix' => 'Api', 'controller' => 'Notifications', 'action' => 'markRead', '_method' => 'PATCH'], ['pass' => ['id']]);
        $builder->connect('/leave-calendar', ['prefix' => 'Api', 'controller' => 'LeaveCalendar', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/attendance/today', ['prefix' => 'Api', 'controller' => 'Attendance', 'action' => 'today', '_method' => 'GET']);
        $builder->connect('/attendance/history', ['prefix' => 'Api', 'controller' => 'Attendance', 'action' => 'history', '_method' => 'GET']);
        $builder->connect('/attendance/clock-in', ['prefix' => 'Api', 'controller' => 'Attendance', 'action' => 'clockIn', '_method' => 'POST']);
        $builder->connect('/attendance/clock-out', ['prefix' => 'Api', 'controller' => 'Attendance', 'action' => 'clockOut', '_method' => 'POST']);
        $builder->connect('/attendance/report', ['prefix' => 'Api', 'controller' => 'Attendance', 'action' => 'report', '_method' => 'GET']);
        $builder->connect('/attendance/{id}', ['prefix' => 'Api', 'controller' => 'Attendance', 'action' => 'correct', '_method' => 'PATCH'], ['pass' => ['id']]);
        $builder->connect('/audit-log', ['prefix' => 'Api', 'controller' => 'AuditLog', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/team-availability', ['prefix' => 'Api', 'controller' => 'TeamAvailability', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/employees', ['prefix' => 'Api', 'controller' => 'Employees', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/employees', ['prefix' => 'Api', 'controller' => 'Employees', 'action' => 'create', '_method' => 'POST']);
        $builder->connect('/employees/{id}', ['prefix' => 'Api', 'controller' => 'Employees', 'action' => 'view', '_method' => 'GET'], ['pass' => ['id']]);
        $builder->connect('/employees/{id}', ['prefix' => 'Api', 'controller' => 'Employees', 'action' => 'update', '_method' => 'PATCH'], ['pass' => ['id']]);
        $builder->connect('/employee-documents/{id}/review', ['prefix' => 'Api', 'controller' => 'Employees', 'action' => 'reviewDocument', '_method' => 'PATCH'], ['pass' => ['id']]);
        $builder->connect('/employees/{id}/lifecycle', ['prefix' => 'Api', 'controller' => 'EmployeeLifecycle', 'action' => 'view', '_method' => 'GET'], ['pass' => ['id']]);
        $builder->connect('/employees/{id}/lifecycle/task', ['prefix' => 'Api', 'controller' => 'EmployeeLifecycle', 'action' => 'updateTask', '_method' => 'PATCH'], ['pass' => ['id']]);
        $builder->connect('/employees/{id}/offboard', ['prefix' => 'Api', 'controller' => 'EmployeeLifecycle', 'action' => 'offboard', '_method' => 'POST'], ['pass' => ['id']]);
        $builder->connect('/profile', ['prefix' => 'Api', 'controller' => 'Profiles', 'action' => 'view', '_method' => 'GET']);
        $builder->connect('/profile', ['prefix' => 'Api', 'controller' => 'Profiles', 'action' => 'update', '_method' => 'PATCH']);
        $builder->connect('/profile/documents', ['prefix' => 'Api', 'controller' => 'Profiles', 'action' => 'upload', '_method' => 'POST']);
        $builder->connect('/employee-documents/{id}/download', ['prefix' => 'Api', 'controller' => 'Profiles', 'action' => 'download', '_method' => 'GET'], ['pass' => ['id']]);
        $builder->connect('/departments', ['prefix' => 'Api', 'controller' => 'Departments', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/departments', ['prefix' => 'Api', 'controller' => 'Departments', 'action' => 'create', '_method' => 'POST']);
        $builder->connect('/approval-inbox', ['prefix' => 'Api', 'controller' => 'ApprovalInbox', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/approval-delegations', ['prefix' => 'Api', 'controller' => 'ApprovalDelegations', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/approval-delegations/candidates', ['prefix' => 'Api', 'controller' => 'ApprovalDelegations', 'action' => 'candidates', '_method' => 'GET']);
        $builder->connect('/approval-delegations', ['prefix' => 'Api', 'controller' => 'ApprovalDelegations', 'action' => 'create', '_method' => 'POST']);
        $builder->connect('/approval-delegations/{id}', ['prefix' => 'Api', 'controller' => 'ApprovalDelegations', 'action' => 'delete', '_method' => 'DELETE'], ['pass' => ['id']]);
        $builder->connect('/attachments/{id}/download', ['prefix' => 'Api', 'controller' => 'ApprovalInbox', 'action' => 'downloadAttachment', '_method' => 'GET'], ['pass' => ['id']]);
        $builder->connect('/leave-requests', ['prefix' => 'Api', 'controller' => 'LeaveRequests', 'action' => 'index', '_method' => 'GET']);
        $builder->connect('/leave-requests', ['prefix' => 'Api', 'controller' => 'LeaveRequests', 'action' => 'create', '_method' => 'POST']);
        $builder->connect('/leave-requests/{id}/decision', ['prefix' => 'Api', 'controller' => 'LeaveRequests', 'action' => 'decide', '_method' => 'POST'], ['pass' => ['id']]);
        $builder->connect('/leave-requests/{id}/cancel', ['prefix' => 'Api', 'controller' => 'LeaveRequests', 'action' => 'cancel', '_method' => 'POST'], ['pass' => ['id']]);
        $builder->connect('/leave-requests/{id}/override', ['prefix' => 'Api', 'controller' => 'LeaveRequests', 'action' => 'override', '_method' => 'POST'], ['pass' => ['id']]);
        $builder->connect('/leave-requests/{id}/audits', ['prefix' => 'Api', 'controller' => 'LeaveRequests', 'action' => 'audits', '_method' => 'GET'], ['pass' => ['id']]);
    });

    $routes->scope('/', function (RouteBuilder $builder): void {
        /*
         * Here, we are connecting '/' (base path) to a controller called 'Pages',
         * its action called 'display', and we pass a param to select the view file
         * to use (in this case, templates/Pages/home.php)...
         */
        $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);

        /*
         * ...and connect the rest of 'Pages' controller's URLs.
         */
        $builder->connect('/pages/*', 'Pages::display');

        /*
         * Connect catchall routes for all controllers.
         *
         * The `fallbacks` method is a shortcut for
         *
         * ```
         * $builder->connect('/{controller}', ['action' => 'index']);
         * $builder->connect('/{controller}/{action}/*', []);
         * ```
         *
         * It is NOT recommended to use fallback routes after your initial prototyping phase!
         * See https://book.cakephp.org/5/en/development/routing.html#fallbacks-method for more information
         */
        $builder->fallbacks();
    });

    /*
     * If you need a different set of middleware or none at all,
     * open new scope and define routes there.
     *
     * ```
     * $routes->scope('/api', function (RouteBuilder $builder): void {
     *     // No $builder->applyMiddleware() here.
     *
     *     // Parse specified extensions from URLs
     *     // $builder->setExtensions(['json', 'xml']);
     *
     *     // Connect API actions here.
     * });
     * ```
     */
};
