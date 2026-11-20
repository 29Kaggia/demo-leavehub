<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Align existing pending approval records with the employee assignments set by HR.
 */
final class SyncPendingApprovalsWithAssignments extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            UPDATE leave_approvals approvals
            INNER JOIN leave_requests requests ON requests.id = approvals.leave_request_id
            INNER JOIN employees employees ON employees.id = requests.employee_id
            SET approvals.approver_id = employees.relief_officer_id
            WHERE approvals.stage = 'relief_officer'
              AND approvals.decision = 'pending'
              AND requests.status IN ('pending_relief_officer', 'pending_line_manager')
              AND employees.relief_officer_id IS NOT NULL
              AND approvals.approver_id <> employees.relief_officer_id
        SQL);

        $this->execute(<<<'SQL'
            UPDATE leave_approvals approvals
            INNER JOIN leave_requests requests ON requests.id = approvals.leave_request_id
            INNER JOIN employees employees ON employees.id = requests.employee_id
            SET approvals.approver_id = employees.line_manager_id
            WHERE approvals.stage = 'line_manager'
              AND approvals.decision = 'pending'
              AND requests.status IN ('pending_relief_officer', 'pending_line_manager')
              AND employees.line_manager_id IS NOT NULL
              AND approvals.approver_id <> employees.line_manager_id
        SQL);
    }

    public function down(): void
    {
        // A prior approver cannot be inferred safely after reassignment.
    }
}
