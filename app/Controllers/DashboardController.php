<?php

namespace App\Controllers;

use App\Core\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
        ]);
    }
}
