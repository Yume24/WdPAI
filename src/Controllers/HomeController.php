<?php

namespace FurEver\Controllers;

use FurEver\Repositories\AnimalsRepository;
use FurEver\Repositories\SpeciesRepository;

final class HomeController extends AppController
{
    public function index(): void
    {
        $animals = new AnimalsRepository();
        $species = new SpeciesRepository();
        $featured = $animals->featured(6);
        $stats = $animals->counts();

        $this->render('home', [
            'title'     => 'Find Your FurEver Friend',
            'activeNav' => 'home',
            'featured'  => $featured,
            'stats'     => $stats,
            'species'   => $species->all(),
        ]);
    }
}
