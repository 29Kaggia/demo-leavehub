<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\Date;
use Cake\ORM\TableRegistry;

class KenyaHolidayCalendar
{
    /**
     * Add predictable statutory holidays without replacing HR-managed holidays.
     * Lunar observances and one-off Gazette notices remain HR-managed because
     * their dates cannot be calculated reliably in advance.
     */
    public function ensureYear(int $year): void
    {
        $holidays = TableRegistry::getTableLocator()->get('PublicHolidays');
        $existingDates = $holidays->find()
            ->select(['holiday_date'])
            ->where([
                'holiday_date >=' => sprintf('%d-01-01', $year),
                'holiday_date <=' => sprintf('%d-12-31', $year),
            ])
            ->all()
            ->map(fn ($holiday) => $holiday->holiday_date->format('Y-m-d'))
            ->toArray();

        $dates = [
            sprintf('%d-01-01', $year) => "New Year's Day",
            $this->easterDate($year, -2) => 'Good Friday',
            $this->easterDate($year, 1) => 'Easter Monday',
            sprintf('%d-05-01', $year) => 'Labour Day',
            sprintf('%d-06-01', $year) => 'Madaraka Day',
            sprintf('%d-10-10', $year) => 'Mazingira Day',
            sprintf('%d-10-20', $year) => 'Mashujaa Day',
            sprintf('%d-12-12', $year) => 'Jamhuri Day',
            sprintf('%d-12-25', $year) => 'Christmas Day',
            sprintf('%d-12-26', $year) => 'Boxing Day',
        ];

        foreach ($dates as $date => $name) {
            if (!in_array($date, $existingDates, true)) {
                $holiday = $holidays->newEntity(['holiday_date' => $date, 'name' => $name]);
                $holidays->saveOrFail($holiday);
                $existingDates[] = $date;
            }
        }

        foreach ($dates as $date => $name) {
            $holidayDate = new Date($date);
            if ($holidayDate->dayOfWeek !== 7) {
                continue;
            }
            $alreadyObserved = $holidays->find()
                ->where([
                    'name' => "$name (observed)",
                    'holiday_date >=' => sprintf('%d-01-01', $year),
                    'holiday_date <=' => sprintf('%d-12-31', $year),
                ])
                ->first();
            if ($alreadyObserved) {
                continue;
            }
            $observed = $holidayDate->addDays(1);
            while (in_array($observed->format('Y-m-d'), $existingDates, true)) {
                $observed = $observed->addDays(1);
            }
            $observedDate = $observed->format('Y-m-d');
            $holiday = $holidays->newEntity(['holiday_date' => $observedDate, 'name' => "$name (observed)"]);
            $holidays->saveOrFail($holiday);
            $existingDates[] = $observedDate;
        }
    }

    private function easterDate(int $year, int $offsetDays): string
    {
        $easter = (new \DateTimeImmutable('@' . easter_date($year)))->setTimezone(new \DateTimeZone('Africa/Nairobi'));
        return $easter->modify("$offsetDays days")->format('Y-m-d');
    }
}
