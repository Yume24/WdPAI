<?php

namespace FurEver\Controllers;

use FurEver\Core\Session;
use FurEver\Models\Role;
use FurEver\Repositories\AdoptionsRepository;
use FurEver\Repositories\AnimalsRepository;
use FurEver\Repositories\AuditLogRepository;
use FurEver\Repositories\UsersRepository;
use FurEver\Repositories\VolunteerAssignmentsRepository;
use FurEver\Repositories\VolunteerShiftsRepository;

final class DashboardController extends AppController
{
    public function index(): void
    {
        $this->requireAuth();

        $animals = new AnimalsRepository();
        $adoptions = new AdoptionsRepository();
        $shifts = new VolunteerShiftsRepository();
        $assignments = new VolunteerAssignmentsRepository();
        $users = new UsersRepository();
        $audit = new AuditLogRepository();

        $animalCounts = $animals->counts();
        $stats = [
            'total_animals'      => $animalCounts['total'],
            'pending_adoptions'  => $adoptions->countPending(),
            'volunteers_week'    => $assignments->countActiveVolunteersThisWeek(),
            'adopted_total'      => $animalCounts['adopted'],
            'shifts_week'        => $shifts->countThisWeek(),
        ];

        $role = Session::role();
        $vars = [
            'title'     => 'Dashboard – FurEver',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
        ];

        if ($role === Role::ADMIN) {
            $vars['users']       = $users->all();
            $vars['recentAudit'] = $audit->recent(8);
        }
        if (in_array($role, [Role::ADMIN, Role::WORKER], true)) {
            $vars['recentAdoptions'] = array_slice($adoptions->all(), 0, 6);
        }
        if ($role === Role::VOLUNTEER) {
            $vars['myShifts'] = $assignments->shiftsForVolunteer((int) Session::userId(), date('Y-m-d'));
        }
        if ($role === Role::ADOPTER) {
            $vars['myRequests'] = $adoptions->forApplicant((int) Session::userId());
        }

        $this->render('dashboard', $vars);
    }
}
