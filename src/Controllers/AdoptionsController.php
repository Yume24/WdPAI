<?php

namespace FurEver\Controllers;

use FurEver\Core\Session;
use FurEver\Models\Role;
use FurEver\Repositories\AdoptionsRepository;
use FurEver\Services\AdoptionService;
use InvalidArgumentException;

final class AdoptionsController extends AppController
{
    private AdoptionsRepository $repo;
    private AdoptionService $service;

    public function __construct()
    {
        $this->repo = new AdoptionsRepository();
        $this->service = AdoptionService::create();
    }

    public function index(): void
    {
        $this->requireAuth();
        $role = Session::role();
        $statusFilter = (string) $this->queryParam('status', 'all');

        if ($role === Role::ADOPTER) {
            $requests = $this->repo->forApplicant((int) Session::userId());
        } elseif (in_array($role, [Role::ADMIN, Role::WORKER], true)) {
            $requests = $this->repo->all($statusFilter === 'all' ? null : $statusFilter);
        } else {
            (new ErrorController())->render403();
            return;
        }

        $this->render('adoptions', [
            'title'        => 'Adoption Requests – FurEver',
            'activeNav'    => 'adoptions',
            'requests'     => $requests,
            'statusFilter' => $statusFilter,
            'canReview'    => in_array($role, [Role::ADMIN, Role::WORKER], true),
        ]);
    }

    public function mine(): void
    {
        $this->requireAuth([Role::ADOPTER]);
        $requests = $this->repo->forApplicant((int) Session::userId());

        $this->render('my-adoptions', [
            'title'     => 'My Adoption Requests – FurEver',
            'activeNav' => 'my-adoptions',
            'requests'  => $requests,
        ]);
    }

    public function submit(): void
    {
        $this->requireAuth([Role::ADOPTER]);
        $this->requireCsrf();

        $animalId = (int) $this->postParam('animal_id', 0);
        $message = trim((string) $this->postParam('message', '')) ?: null;

        try {
            $this->service->submit($animalId, (int) Session::userId(), $message);
            $this->flash('success', 'Adoption request submitted.');
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/animal?id=' . $animalId);
    }

    public function approve(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->requireCsrf();
        $id = (int) $this->queryParam('id', 0);
        $notes = $this->postParam('notes') ?: null;

        try {
            $this->service->approve($id, (int) Session::userId(), $notes);
            if ($this->expectsJson()) {
                $this->json(['ok' => true, 'status' => 'approved']);
            }
            $this->flash('success', 'Adoption approved.');
        } catch (InvalidArgumentException $e) {
            if ($this->expectsJson()) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/adoptions');
    }

    public function reject(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->requireCsrf();
        $id = (int) $this->queryParam('id', 0);
        $notes = $this->postParam('notes') ?: null;

        try {
            $this->service->reject($id, (int) Session::userId(), $notes);
            if ($this->expectsJson()) {
                $this->json(['ok' => true, 'status' => 'rejected']);
            }
            $this->flash('success', 'Request rejected.');
        } catch (InvalidArgumentException $e) {
            if ($this->expectsJson()) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/adoptions');
    }
}
