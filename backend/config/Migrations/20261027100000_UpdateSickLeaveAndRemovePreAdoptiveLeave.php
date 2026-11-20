<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class UpdateSickLeaveAndRemovePreAdoptiveLeave extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("UPDATE leave_types SET days_allowed = 14 WHERE code = 'SICK'");
        $this->execute("UPDATE leave_balances SET entitled_days = 14 WHERE leave_type_id IN (SELECT id FROM leave_types WHERE code = 'SICK')");
        $this->execute("UPDATE leave_types SET is_active = 0 WHERE code = 'PRE_ADOPTIVE'");
    }

    public function down(): void
    {
        $this->execute("UPDATE leave_types SET days_allowed = 45 WHERE code = 'SICK'");
        $this->execute("UPDATE leave_balances SET entitled_days = 45 WHERE leave_type_id IN (SELECT id FROM leave_types WHERE code = 'SICK')");
        $this->execute("UPDATE leave_types SET is_active = 1 WHERE code = 'PRE_ADOPTIVE'");
    }
}
