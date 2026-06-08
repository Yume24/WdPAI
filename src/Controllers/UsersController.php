<?php

namespace FurEver\Controllers;

use FurEver\Core\Session;
use FurEver\Models\Role;
use FurEver\Repositories\RolesRepository;
use FurEver\Repositories\UsersRepository;

final class UsersController extends AppController
{
    private UsersRepository $users;
    private RolesRepository $roles;

    public function __construct()
    {
        $this->users = UsersRepository::getInstance();
        $this->roles = new RolesRepository();
    }

    public function index(): void
    {
        $this->requireAuth([Role::ADMIN]);
        $this->render('users', [
            'title'     => 'User Management – FurEver',
            'activeNav' => 'users',
            'users'     => $this->users->all(),
            'roles'     => $this->roles->all(),
            'countsByRole' => $this->users->countByRole(),
        ]);
    }

    public function changeRole(): void
    {
        $this->requireAuth([Role::ADMIN]);
        $this->requireCsrf();
        $userId = (int) $this->queryParam('id', 0);
        $roleId = (int) $this->postParam('role_id', 0);

        if ($userId === Session::userId()) {
            $this->flash('error', 'You cannot change your own role.');
            $this->redirect('/users');
        }
        if (!$this->roles->findById($roleId)) {
            $this->flash('error', 'Invalid role.');
            $this->redirect('/users');
        }

        $this->users->updateRole($userId, $roleId);
        $this->flash('success', 'Role updated.');
        $this->redirect('/users');
    }

    public function toggleActive(): void
    {
        $this->requireAuth([Role::ADMIN]);
        $this->requireCsrf();
        $userId = (int) $this->queryParam('id', 0);

        if ($userId === Session::userId()) {
            $this->flash('error', 'You cannot deactivate your own account.');
            $this->redirect('/users');
        }

        $now = $this->users->toggleActive($userId);
        $this->flash('success', $now ? 'User activated.' : 'User deactivated.');
        $this->redirect('/users');
    }
}
