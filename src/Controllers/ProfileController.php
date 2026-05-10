<?php

namespace FurEver\Controllers;

use FurEver\Core\Session;
use FurEver\Repositories\UserProfilesRepository;
use FurEver\Repositories\UsersRepository;

final class ProfileController extends AppController
{
    private UsersRepository $users;
    private UserProfilesRepository $profiles;

    public function __construct()
    {
        $this->users = new UsersRepository();
        $this->profiles = new UserProfilesRepository();
    }

    public function show(): void
    {
        $this->requireAuth();
        $user = $this->users->findById((int) Session::userId());

        $this->render('profile', [
            'title'     => 'My Profile – FurEver',
            'activeNav' => 'profile',
            'user'      => $user,
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $userId = (int) Session::userId();
        $this->profiles->upsert(
            $userId,
            trim((string) $this->postParam('full_name', '')) ?: null,
            trim((string) $this->postParam('phone', '')) ?: null,
            trim((string) $this->postParam('address', '')) ?: null,
            trim((string) $this->postParam('bio', '')) ?: null,
        );
        $this->flash('success', 'Profile saved.');
        $this->redirect('/profile');
    }
}
