<?php

declare(strict_types=1);

use FurEver\Controllers\AdoptionsController;
use FurEver\Controllers\AnimalsController;
use FurEver\Controllers\DashboardController;
use FurEver\Controllers\HomeController;
use FurEver\Controllers\ProfileController;
use FurEver\Controllers\SecurityController;
use FurEver\Controllers\UsersController;
use FurEver\Controllers\VolunteersController;
use FurEver\Core\Router;

final class Routing
{
    private static ?Router $router = null;

    public static function build(): Router
    {
        if (self::$router !== null) {
            return self::$router;
        }
        $r = new Router();

        // Public / auth
        $r->get ('/',         SecurityController::class, 'login');
        $r->get ('/login',    SecurityController::class, 'login');
        $r->post('/login',    SecurityController::class, 'login');
        $r->get ('/register', SecurityController::class, 'register');
        $r->post('/register', SecurityController::class, 'register');
        $r->get ('/logout',   SecurityController::class, 'logout');
        $r->post('/logout',   SecurityController::class, 'logout');
        $r->get ('/home',     HomeController::class,     'index');

        // Dashboard / profile
        $r->get ('/dashboard', DashboardController::class, 'index');
        $r->get ('/profile',   ProfileController::class,   'show');
        $r->post('/profile',   ProfileController::class,   'update');

        // Animals
        $r->get ('/animals',                 AnimalsController::class, 'index');
        $r->get ('/animals/create',          AnimalsController::class, 'create');
        $r->post('/animals',                 AnimalsController::class, 'store');
        $r->get ('/animal',                  AnimalsController::class, 'show');
        $r->get ('/animals/{id}/edit',       AnimalsController::class, 'edit');
        $r->post('/animals/{id}/update',     AnimalsController::class, 'update');
        $r->post('/animals/{id}/delete',     AnimalsController::class, 'delete');

        // Adoptions
        $r->get ('/adoptions',                  AdoptionsController::class, 'index');
        $r->get ('/my-adoptions',               AdoptionsController::class, 'mine');
        $r->post('/adoptions',                  AdoptionsController::class, 'submit');
        $r->post('/adoptions/{id}/approve',     AdoptionsController::class, 'approve');
        $r->post('/adoptions/{id}/reject',      AdoptionsController::class, 'reject');

        // Volunteers
        $r->get ('/volunteers',           VolunteersController::class, 'index');
        $r->get ('/my-shifts',            VolunteersController::class, 'mySchedule');
        $r->post('/shifts',               VolunteersController::class, 'createShift');
        $r->post('/shifts/{id}/delete',   VolunteersController::class, 'deleteShift');
        $r->post('/shifts/{id}/signup',   VolunteersController::class, 'signUp');
        $r->post('/shifts/{id}/drop',     VolunteersController::class, 'drop');

        // Users (admin)
        $r->get ('/users',                   UsersController::class, 'index');
        $r->post('/users/{id}/role',         UsersController::class, 'changeRole');
        $r->post('/users/{id}/toggle',       UsersController::class, 'toggleActive');

        // JSON / fetch endpoints
        $r->get ('/api/animals',                  AnimalsController::class,   'apiList');
        $r->get ('/api/animals/{id}',             AnimalsController::class,   'apiShow');
        $r->post('/api/adoptions/{id}/approve',   AdoptionsController::class, 'approve');
        $r->post('/api/adoptions/{id}/reject',    AdoptionsController::class, 'reject');
        $r->post('/api/shifts/{id}/signup',       VolunteersController::class,'signUp');
        $r->post('/api/shifts/{id}/drop',         VolunteersController::class,'drop');

        self::$router = $r;
        return $r;
    }

    public static function dispatch(string $method, string $path): void
    {
        self::build()->dispatch($method, $path);
    }
}
