<?php
declare(strict_types=1);

use Migrations\AbstractMigration;
use Cake\Core\Configure;

/** Establish organization ownership and the first employee payroll fields. */
final class AddOrganizationsAndPayrollProfiles extends AbstractMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->table('organizations')
            ->addColumn('name', 'string', ['limit' => 160])
            ->addColumn('country_code', 'string', ['limit' => 2, 'default' => 'KE'])
            ->addColumn('currency_code', 'string', ['limit' => 3, 'default' => 'KES'])
            ->addColumn('pay_frequency', 'enum', ['values' => ['monthly', 'biweekly', 'weekly'], 'default' => 'monthly'])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->create();

        $this->table('organizations')->insert([
            ['name' => 'Tech100 Africa', 'country_code' => 'KE', 'currency_code' => 'KES', 'pay_frequency' => 'monthly', 'created' => $now, 'modified' => $now],
        ])->save();
        $organizationId = (int)$this->fetchRow("SELECT id FROM organizations WHERE name = 'Tech100 Africa' ORDER BY id ASC LIMIT 1")['id'];

        $this->table('departments')
            ->addColumn('organization_id', 'integer', ['null' => true, 'after' => 'id'])
            ->update();
        $this->execute("UPDATE departments SET organization_id = {$organizationId} WHERE organization_id IS NULL");
        $this->table('departments')->changeColumn('organization_id', 'integer')->update();

        $this->table('employees')
            ->addColumn('organization_id', 'integer', ['null' => true, 'after' => 'id'])
            ->update();
        $this->execute("UPDATE employees SET organization_id = {$organizationId} WHERE organization_id IS NULL");
        $this->table('employees')->changeColumn('organization_id', 'integer')->update();
        $this->table('employees')->removeIndex(['employee_number'])->addIndex(['organization_id', 'employee_number'], ['unique' => true])->update();

        $this->table('departments')
            ->removeIndex(['name'])
            ->addIndex(['organization_id', 'name'], ['unique' => true])
            ->addForeignKey('organization_id', 'organizations', 'id', ['delete' => 'CASCADE'])
            ->update();
        $this->table('employees')
            ->addForeignKey('organization_id', 'organizations', 'id', ['delete' => 'CASCADE'])
            ->update();

        $this->table('employee_pay_profiles')
            ->addColumn('employee_id', 'integer')
            ->addColumn('base_salary', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
            ->addColumn('currency_code', 'string', ['limit' => 3, 'default' => 'KES'])
            ->addColumn('pay_frequency', 'enum', ['values' => ['monthly', 'biweekly', 'weekly'], 'default' => 'monthly'])
            ->addColumn('fixed_allowances', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
            ->addColumn('recurring_deductions', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
            ->addColumn('effective_from', 'date')
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['employee_id', 'effective_from'], ['unique' => true])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->create();

        if (Configure::read('debug')) {
            $demoEmployees = $this->fetchAll("SELECT id, employee_number FROM employees WHERE employee_number IN ('DEMO-HR-001', 'DEMO-LM-001', 'DEMO-RO-001', 'DEMO-EMP-001')");
            $salaries = ['DEMO-HR-001' => 175000, 'DEMO-LM-001' => 145000, 'DEMO-RO-001' => 82000, 'DEMO-EMP-001' => 76000];
            foreach ($demoEmployees as $demoEmployee) {
                $salary = $salaries[$demoEmployee['employee_number']];
                $this->table('employee_pay_profiles')->insert([[
                    'employee_id' => (int)$demoEmployee['id'],
                    'base_salary' => $salary,
                    'currency_code' => 'KES',
                    'pay_frequency' => 'monthly',
                    'fixed_allowances' => 5000,
                    'recurring_deductions' => 0,
                    'effective_from' => date('Y-m-01'),
                    'created' => $now,
                    'modified' => $now,
                ]])->save();
            }
        }
    }

    public function down(): void
    {
        $this->table('employee_pay_profiles')->drop()->save();
        $this->table('employees')->dropForeignKey('organization_id')->removeIndex(['organization_id', 'employee_number'])->addIndex(['employee_number'], ['unique' => true])->removeColumn('organization_id')->update();
        $this->table('departments')->dropForeignKey('organization_id')->removeIndex(['organization_id', 'name'])->addIndex(['name'], ['unique' => true])->removeColumn('organization_id')->update();
        $this->table('organizations')->drop()->save();
    }
}
