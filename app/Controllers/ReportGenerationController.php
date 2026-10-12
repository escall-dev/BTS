<?php

namespace App\Controllers;

use App\Core\Controller;

class ReportGenerationController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();

        $this->view('reports/index', [
            'title' => 'Report Generation',
            'active' => 'reports',
        ]);
    }
}