<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\KenyaHolidayCalendar;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Exception\BadRequestException as UnprocessableEntityException;
use Cake\I18n\FrozenDate;
use Cake\ORM\TableRegistry;

class PublicHolidaysController extends AppController
{
    public function index(): \Cake\Http\Response
    {
        $this->currentEmployee();
        $year = (int)($this->request->getQuery('year') ?: date('Y'));
        (new KenyaHolidayCalendar())->ensureYear($year);
        $items = TableRegistry::getTableLocator()->get('PublicHolidays')->find()
            ->where(['holiday_date >=' => sprintf('%d-01-01', $year), 'holiday_date <=' => sprintf('%d-12-31', $year)])
            ->orderBy(['holiday_date' => 'ASC'])->all()->toList();
        return $this->json(['data' => $items]);
    }

    public function create(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();
        $data = $this->request->getData();
        try { $date = new FrozenDate((string)($data['holiday_date'] ?? '')); } catch (\Throwable) { throw new UnprocessableEntityException('Provide a valid holiday date.'); }
        $name = trim((string)($data['name'] ?? ''));
        if (!$name) throw new UnprocessableEntityException('Holiday name is required.');
        $holidays = TableRegistry::getTableLocator()->get('PublicHolidays');
        $holiday = $holidays->newEntity(['holiday_date' => $date, 'name' => $name]);
        if (!$holidays->save($holiday)) throw new UnprocessableEntityException('Holiday could not be saved.');
        return $this->json(['data' => $holiday], 201);
    }

    private function requireAdmin(): void
    {
        if ($this->currentEmployee()->role !== 'hr_admin') throw new UnauthorizedException('Only HR administrators can manage public holidays.');
    }
}
