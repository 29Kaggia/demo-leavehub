<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/** Creates one HR notification per birthday and work anniversary each day. */
class EmployeeMilestonesCommand extends Command
{
    public static function getDescription(): string
    {
        return 'Notify HR administrators about employee birthdays and work anniversaries due today.';
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $today = new DateTime('now', 'Africa/Nairobi');
        $employees = TableRegistry::getTableLocator()->get('Employees');
        $notifications = TableRegistry::getTableLocator()->get('Notifications');
        $hrAdmins = $employees->find()->where(['role' => 'hr_admin', 'is_active' => true])->all()->toList();

        if (!$hrAdmins) {
            $io->out('No active HR administrators found.');
            return static::CODE_SUCCESS;
        }

        $team = $employees->find()
            ->where(['is_active' => true])
            ->select(['id', 'first_name', 'last_name', 'date_of_birth', 'employment_start_date'])
            ->all();
        $created = 0;

        foreach ($team as $employee) {
            foreach ($this->milestonesFor($employee, $today) as $milestone) {
                foreach ($hrAdmins as $admin) {
                    $key = sprintf('%s-%d-%s', $milestone['type'], $employee->id, $today->format('Y'));
                    $exists = $notifications->find()
                        ->where(['employee_id' => $admin->id, 'milestone_key' => $key])
                        ->first();
                    if ($exists) {
                        continue;
                    }
                    $notification = $notifications->newEntity([
                        'employee_id' => $admin->id,
                        'type' => $milestone['type'],
                        'milestone_key' => $key,
                        'title' => $milestone['title'],
                        'message' => $milestone['message'],
                    ]);
                    if ($notifications->save($notification)) {
                        $created++;
                    }
                }
            }
        }

        $io->success(sprintf('Created %d HR milestone notification%s.', $created, $created === 1 ? '' : 's'));
        return static::CODE_SUCCESS;
    }

    /** @return list<array{type: string, title: string, message: string}> */
    private function milestonesFor(object $employee, DateTime $today): array
    {
        $name = trim($employee->first_name . ' ' . $employee->last_name);
        $milestones = [];
        if ($this->isToday($employee->date_of_birth, $today)) {
            $milestones[] = ['type' => 'birthday', 'title' => 'Employee birthday today', 'message' => sprintf("Today is %s's birthday.", $name)];
        }
        if ($this->isToday($employee->employment_start_date, $today)) {
            $startDate = $this->dateValue($employee->employment_start_date);
            $years = (int)$today->format('Y') - (int)$startDate->format('Y');
            $milestones[] = ['type' => 'work_anniversary', 'title' => 'Work anniversary today', 'message' => sprintf('%s celebrates %d year%s with the company today.', $name, $years, $years === 1 ? '' : 's')];
        }
        return $milestones;
    }

    private function isToday(mixed $value, DateTime $today): bool
    {
        return $value !== null && $value !== '' && $this->dateValue($value)->format('m-d') === $today->format('m-d');
    }

    private function dateValue(mixed $value): \DateTimeInterface
    {
        return $value instanceof \DateTimeInterface ? $value : new \DateTimeImmutable((string)$value);
    }
}
