<?php

namespace FurEver\Controllers;

use FurEver\Core\Session;
use FurEver\Models\Role;
use FurEver\Repositories\VolunteerAssignmentsRepository;
use FurEver\Repositories\VolunteerShiftsRepository;
use FurEver\Services\Validator;

final class VolunteersController extends AppController
{
    private VolunteerShiftsRepository $shifts;
    private VolunteerAssignmentsRepository $assignments;

    public function __construct()
    {
        $this->shifts = new VolunteerShiftsRepository();
        $this->assignments = new VolunteerAssignmentsRepository();
    }

    public function index(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER, Role::VOLUNTEER]);

        $weekStart = $this->queryParam('week') ?: $this->mondayOfThisWeek();
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $shifts = $this->shifts->inRange($weekStart, $weekEnd);

        $this->render('volunteers', [
            'title'      => 'Volunteer Schedule – FurEver',
            'activeNav'  => 'volunteers',
            'weekStart'  => $weekStart,
            'weekEnd'    => $weekEnd,
            'shifts'     => $shifts,
            'canManage'  => in_array(Session::role(), [Role::ADMIN, Role::WORKER], true),
            'canSignUp'  => Session::role() === Role::VOLUNTEER,
            'currentUid' => Session::userId(),
        ]);
    }

    public function mySchedule(): void
    {
        $this->requireAuth([Role::VOLUNTEER, Role::ADMIN, Role::WORKER]);
        $userId = (int) Session::userId();
        $myShifts = $this->assignments->shiftsForVolunteer($userId, date('Y-m-d'));

        $allShifts = $this->shifts->inRange(date('Y-m-d'), date('Y-m-d', strtotime('+14 days')));

        $signedUpIds = [];
        foreach ($myShifts as $s) {
            $signedUpIds[$s->id] = true;
        }

        $this->render('my-shifts', [
            'title'        => 'My Shifts – FurEver',
            'activeNav'    => 'my-shifts',
            'myShifts'     => $myShifts,
            'allShifts'    => $allShifts,
            'signedUpIds'  => $signedUpIds,
        ]);
    }

    public function createShift(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->requireCsrf();

        $data = [
            'shift_date'       => (string) $this->postParam('shift_date', ''),
            'start_time'       => (string) $this->postParam('start_time', ''),
            'end_time'         => (string) $this->postParam('end_time', ''),
            'task_description' => trim((string) $this->postParam('task_description', '')),
            'location'         => trim((string) $this->postParam('location', '')),
            'capacity'         => (int) $this->postParam('capacity', 1),
        ];

        $v = (new Validator($data))
            ->required('shift_date')->date('shift_date')->notPast('shift_date')
            ->required('start_time')
            ->required('end_time');
        if ($v->fails()) {
            $this->flash('error', $v->firstErrorString());
            $this->redirect('/volunteers');
        }
        if ($data['end_time'] <= $data['start_time']) {
            $this->flash('error', 'End time must be after start time.');
            $this->redirect('/volunteers');
        }

        $this->shifts->create(
            $data['shift_date'],
            $data['start_time'],
            $data['end_time'],
            $data['task_description'] ?: null,
            $data['location'] ?: null,
            max(1, $data['capacity']),
            (int) Session::userId()
        );

        $this->flash('success', 'Shift created.');
        $this->redirect('/volunteers');
    }

    public function deleteShift(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->requireCsrf();
        $id = (int) $this->queryParam('id', 0);
        $this->shifts->delete($id);
        $this->flash('success', 'Shift removed.');
        $this->redirect('/volunteers');
    }

    public function signUp(): void
    {
        $this->requireAuth([Role::VOLUNTEER]);
        $this->requireCsrf();
        $shiftId = (int) $this->queryParam('id', 0);

        $created = $this->assignments->signUp((int) Session::userId(), $shiftId);
        if ($this->expectsJson()) {
            $this->json(['ok' => true, 'signed_up' => $created]);
        }
        $this->flash($created ? 'success' : 'error', $created ? 'Signed up for shift.' : 'You are already signed up.');
        $this->redirect('/my-shifts');
    }

    public function drop(): void
    {
        $this->requireAuth([Role::VOLUNTEER]);
        $this->requireCsrf();
        $shiftId = (int) $this->queryParam('id', 0);
        $this->assignments->drop((int) Session::userId(), $shiftId);
        if ($this->expectsJson()) {
            $this->json(['ok' => true]);
        }
        $this->flash('success', 'Removed from shift.');
        $this->redirect('/my-shifts');
    }

    private function mondayOfThisWeek(): string
    {
        $today = new \DateTimeImmutable('today');
        $dow = (int) $today->format('N'); // 1=Mon
        $monday = $today->modify('-' . ($dow - 1) . ' days');
        return $monday->format('Y-m-d');
    }
}
