<?php

namespace FurEver\Controllers;

use FurEver\Models\Role;
use FurEver\Services\AuthService;
use FurEver\Services\Validator;
use InvalidArgumentException;

final class SecurityController extends AppController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = AuthService::create();
    }

    public function login(): void
    {
        if ($this->isPost()) {
            $this->requireCsrf();
            $email = trim((string) $this->postParam('email', ''));
            $password = (string) $this->postParam('password', '');

            $v = (new Validator(['email' => $email, 'password' => $password]))
                ->required('email')->email('email')->maxLength('email', 254)
                ->required('password')->maxLength('password', 128);
            if ($v->fails()) {
                $this->render('login', ['messages' => $v->firstErrorString()]);
                return;
            }

            try {
                $this->auth->login($email, $password);
            } catch (InvalidArgumentException $e) {
                $this->render('login', ['messages' => $e->getMessage()]);
                return;
            }

            $this->flash('success', 'Welcome back!');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('login');
    }

    public function register(): void
    {
        if ($this->isPost()) {
            $this->requireCsrf();
            $email    = trim((string) $this->postParam('email', ''));
            $username = trim((string) $this->postParam('username', ''));
            $password = (string) $this->postParam('password', '');
            $confirm  = (string) $this->postParam('password2', '');

            $v = (new Validator(compact('email', 'username', 'password', 'confirm') + ['password2' => $confirm]))
                ->required('email')->email('email')->maxLength('email', 254)
                ->required('username')->minLength('username', 3)->maxLength('username', 50)
                ->required('password')->minLength('password', 8)->maxLength('password', 128)
                ->matches('password', 'password2', 'must match the confirmation');
            if ($v->fails()) {
                $this->render('register', ['messages' => $v->firstErrorString()]);
                return;
            }

            try {
                $this->auth->register($email, $username, $password, $confirm, Role::ADOPTER);
            } catch (InvalidArgumentException $e) {
                $this->render('register', ['messages' => $e->getMessage()]);
                return;
            }

            $this->flash('success', 'Account created. Please sign in.');
            $this->redirect('/login');
            return;
        }

        $this->render('register');
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->redirect('/login');
    }
}
