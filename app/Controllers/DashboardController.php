<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Workflow;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();

        $workflow = new Workflow();

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'workQueue' => $workflow->dashboardWorkQueue(),
            'stats' => $workflow->dashboardStats(),
            'barangayName' => trim((string) ($workflow->getSystemInputData()['BRGY'] ?? '')),
        ]);
    }
}
