<?php

namespace FurEver\Controllers;

use FurEver\Core\Session;
use FurEver\Models\Animal;
use FurEver\Models\Role;
use FurEver\Repositories\AnimalsRepository;
use FurEver\Repositories\MedicalRecordsRepository;
use FurEver\Repositories\SpeciesRepository;
use FurEver\Services\UploadService;
use FurEver\Services\Validator;

final class AnimalsController extends AppController
{
    private AnimalsRepository $animals;
    private SpeciesRepository $species;
    private MedicalRecordsRepository $medical;
    private UploadService $uploads;

    public function __construct()
    {
        $this->animals = new AnimalsRepository();
        $this->species = new SpeciesRepository();
        $this->medical = new MedicalRecordsRepository();
        $this->uploads = new UploadService('animals');
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'species_id' => $this->queryParam('species_id'),
            'status'     => $this->queryParam('status'),
            'gender'     => $this->queryParam('gender'),
            'q'          => $this->queryParam('q'),
        ];
        $animals = $this->animals->filter(array_filter($filters, fn($v) => $v !== null && $v !== ''));

        $this->render('animals', [
            'title'     => 'Animal Inventory – FurEver',
            'activeNav' => 'animals',
            'animals'   => $animals,
            'species'   => $this->species->all(),
            'statuses'  => Animal::statuses(),
            'filters'   => $filters,
            'canManage' => in_array(Session::role(), [Role::ADMIN, Role::WORKER], true),
        ]);
    }

    public function show(): void
    {
        $this->requireAuth();
        $id = (int) ($this->queryParam('id', 0));
        $animal = $this->animals->findById($id);
        if (!$animal) {
            (new ErrorController())->render404();
            return;
        }

        $this->render('animal', [
            'title'        => $animal->name . ' – FurEver',
            'activeNav'    => 'animals',
            'animal'       => $animal,
            'records'      => $this->medical->forAnimal($animal->id),
            'canManage'    => in_array(Session::role(), [Role::ADMIN, Role::WORKER], true),
            'canApply'     => Session::role() === Role::ADOPTER && $animal->status !== Animal::STATUS_ADOPTED,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->render('animals/create', [
            'title'     => 'Add Animal – FurEver',
            'activeNav' => 'animals',
            'species'   => $this->species->all(),
            'statuses'  => Animal::statuses(),
            'genders'   => Animal::genders(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->requireCsrf();

        $data = [
            'name'          => trim((string) $this->postParam('name', '')),
            'species_id'    => (int) $this->postParam('species_id', 0),
            'breed'         => $this->postParam('breed') ?: null,
            'gender'        => (string) $this->postParam('gender', Animal::GENDER_UNKNOWN),
            'date_of_birth' => $this->postParam('date_of_birth') ?: null,
            'intake_date'   => (string) $this->postParam('intake_date', date('Y-m-d')),
            'status'        => (string) $this->postParam('status', Animal::STATUS_AVAILABLE),
            'description'   => $this->postParam('description') ?: null,
            'created_by'    => Session::userId(),
        ];

        $v = (new Validator($data))
            ->required('name')
            ->required('species_id')
            ->required('intake_date')->date('intake_date')
            ->in('gender', Animal::genders())
            ->in('status', Animal::statuses());
        if ($v->fails()) {
            $this->flash('error', $v->firstErrorString());
            $this->redirect('/animals/create');
        }

        try {
            $photoPath = $this->uploads->storeImage($_FILES['photo'] ?? []);
            $data['photo_path'] = $photoPath;
        } catch (\Throwable $e) {
            $this->flash('error', 'Photo upload failed: ' . $e->getMessage());
            $this->redirect('/animals/create');
        }

        $id = $this->animals->create($data);
        $this->flash('success', 'Animal added.');
        $this->redirect('/animal?id=' . $id);
    }

    public function edit(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $id = (int) $this->queryParam('id', 0);
        $animal = $this->animals->findById($id);
        if (!$animal) {
            (new ErrorController())->render404();
            return;
        }

        $this->render('animals/edit', [
            'title'     => 'Edit ' . $animal->name . ' – FurEver',
            'activeNav' => 'animals',
            'animal'    => $animal,
            'species'   => $this->species->all(),
            'statuses'  => Animal::statuses(),
            'genders'   => Animal::genders(),
        ]);
    }

    public function update(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->requireCsrf();

        $id = (int) $this->queryParam('id', 0);
        $animal = $this->animals->findById($id);
        if (!$animal) {
            (new ErrorController())->render404();
            return;
        }

        $data = [
            'name'          => trim((string) $this->postParam('name', $animal->name)),
            'species_id'    => (int) $this->postParam('species_id', $animal->speciesId),
            'breed'         => $this->postParam('breed', $animal->breed) ?: null,
            'gender'        => (string) $this->postParam('gender', $animal->gender),
            'date_of_birth' => $this->postParam('date_of_birth') ?: $animal->dateOfBirth,
            'intake_date'   => (string) $this->postParam('intake_date', $animal->intakeDate),
            'status'        => (string) $this->postParam('status', $animal->status),
            'description'   => $this->postParam('description') ?: $animal->description,
        ];

        try {
            $photoPath = $this->uploads->storeImage($_FILES['photo'] ?? []);
            if ($photoPath !== null) {
                $this->uploads->delete($animal->photoPath);
                $data['photo_path'] = $photoPath;
            }
        } catch (\Throwable $e) {
            $this->flash('error', 'Photo upload failed: ' . $e->getMessage());
            $this->redirect('/animals/' . $id . '/edit');
        }

        $this->animals->update($id, $data);
        $this->flash('success', 'Animal updated.');
        $this->redirect('/animal?id=' . $id);
    }

    public function delete(): void
    {
        $this->requireAuth([Role::ADMIN, Role::WORKER]);
        $this->requireCsrf();
        $id = (int) $this->queryParam('id', 0);
        $animal = $this->animals->findById($id);
        if ($animal) {
            $this->uploads->delete($animal->photoPath);
            $this->animals->delete($id);
            $this->flash('success', 'Animal removed.');
        }
        $this->redirect('/animals');
    }

    public function apiList(): void
    {
        $this->requireAuth();
        $filters = array_filter([
            'species_id' => $this->queryParam('species_id'),
            'status'     => $this->queryParam('status'),
            'gender'     => $this->queryParam('gender'),
            'q'          => $this->queryParam('q'),
        ], fn($v) => $v !== null && $v !== '');

        $animals = $this->animals->filter($filters);
        $payload = array_map(fn(Animal $a) => [
            'id'            => $a->id,
            'name'          => $a->name,
            'species'       => $a->speciesName,
            'species_icon'  => $a->speciesIcon,
            'breed'         => $a->breed,
            'gender'        => $a->gender,
            'status'        => $a->status,
            'status_label'  => $a->statusLabel(),
            'badge'         => $a->badgeClass(),
            'photo_path'    => $a->photoPath,
        ], $animals);
        $this->json(['animals' => $payload]);
    }

    public function apiShow(): void
    {
        $this->requireAuth();
        $id = (int) $this->queryParam('id', 0);
        $animal = $this->animals->findById($id);
        if (!$animal) {
            $this->json(['error' => 'Not found'], 404);
        }
        $this->json([
            'id' => $animal->id,
            'name' => $animal->name,
            'species' => $animal->speciesName,
            'breed' => $animal->breed,
            'status' => $animal->status,
            'description' => $animal->description,
            'photo_path' => $animal->photoPath,
        ]);
    }
}
