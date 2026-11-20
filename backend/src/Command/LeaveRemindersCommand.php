<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/** Sends one daily email/in-app reminder for pending approvals and imminent leave. */
class LeaveRemindersCommand extends Command
{
    public static function getDescription(): string
    {
        return 'Send daily LeaveHub reminders for pending approvals and leave starting in three days.';
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $today = new DateTime('now', 'Africa/Nairobi');
        $notifications = TableRegistry::getTableLocator()->get('Notifications');
        $created = $this->pendingApprovalReminders($notifications, $today);
        $created += $this->upcomingLeaveReminders($notifications, $today);
        $io->success(sprintf('Created %d leave reminder%s.', $created, $created === 1 ? '' : 's'));
        return static::CODE_SUCCESS;
    }

    private function pendingApprovalReminders(object $notifications, DateTime $today): int
    {
        $approvals = TableRegistry::getTableLocator()->get('LeaveApprovals')->find()
            ->innerJoinWith('LeaveRequests')
            ->contain(['LeaveRequests' => ['Employees', 'LeaveTypes'], 'Approvers'])
            ->where(['LeaveApprovals.decision' => 'pending', 'LeaveRequests.status IN' => ['pending_relief_officer', 'pending_line_manager']])
            ->all();
        $created = 0;
        foreach ($approvals as $approval) {
            if (!$approval->approver || !$approval->leave_request) continue;
            $key = sprintf('approval-reminder-%d-%s', $approval->id, $today->format('Ymd'));
            if ($notifications->exists(['employee_id' => $approval->approver_id, 'milestone_key' => $key])) continue;
            $request = $approval->leave_request;
            $employee = $request->employee;
            $notification = $notifications->newEntity([
                'employee_id' => $approval->approver_id,
                'leave_request_id' => $request->id,
                'type' => 'leave_approval_reminder',
                'milestone_key' => $key,
                'title' => 'Reminder: leave request awaiting your decision',
                'message' => sprintf('%s %s has a %s request waiting for your approval.', $employee->first_name, $employee->last_name, $request->leave_type->name),
            ]);
            if ($notifications->save($notification)) $created++;
        }
        return $created;
    }

    private function upcomingLeaveReminders(object $notifications, DateTime $today): int
    {
        $startDate = $today->addDays(3)->format('Y-m-d');
        $requests = TableRegistry::getTableLocator()->get('LeaveRequests')->find()
            ->contain(['Employees', 'LeaveTypes'])
            ->where(['LeaveRequests.status' => 'approved', 'LeaveRequests.start_date' => $startDate])
            ->all();
        $created = 0;
        foreach ($requests as $request) {
            $key = sprintf('leave-start-reminder-%d-%s', $request->id, $startDate);
            if ($notifications->exists(['employee_id' => $request->employee_id, 'milestone_key' => $key])) continue;
            $notification = $notifications->newEntity([
                'employee_id' => $request->employee_id,
                'leave_request_id' => $request->id,
                'type' => 'upcoming_leave_reminder',
                'milestone_key' => $key,
                'title' => 'Upcoming approved leave',
                'message' => sprintf('Your approved %s starts on %s.', $request->leave_type->name, $request->start_date->format('j M Y')),
            ]);
            if ($notifications->save($notification)) $created++;
        }
        return $created;
    }
}
