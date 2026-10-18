<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Workflow;

class TrashController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $workflow = new Workflow();
        $workflow->purgeExpiredTrash();
        $filters = [
            'recently_deleted' => in_array((string) ($_GET['recently_deleted'] ?? ''), ['24-hours', '7-days', '30-days'], true)
                ? (string) $_GET['recently_deleted']
                : '',
            'progress' => in_array((string) ($_GET['progress'] ?? ''), ['none', 'partial', 'complete'], true)
                ? (string) $_GET['progress']
                : '',
        ];
        $trashItems = $workflow->trashItems($filters);
        $this->view('trash/index', [
            'title' => 'Trash',
            'active' => 'trash',
            'trashItems' => $trashItems,
            'filters' => $filters,
            'hasFilters' => $filters['recently_deleted'] !== '' || $filters['progress'] !== '',
            'totalTrashItems' => $workflow->trashCount(),
            'restored' => ($_GET['restored'] ?? '') === '1',
            'purged' => ($_GET['purged'] ?? '') === '1',
            'notFound' => ($_GET['not_found'] ?? '') === '1',
        ]);
    }

    public function restore(string $processId): void
    {
        $this->requireLogin();
        $restored = (new Workflow())->restoreDeletedProcess((int) $processId);
        $this->redirect('/BTS/trash?' . ($restored ? 'restored=1' : 'not_found=1'));
    }

    public function deletePermanently(string $processId): void
    {
        $this->requireLogin();
        $deleted = (new Workflow())->permanentlyDeleteProcess((int) $processId);
        $this->redirect('/BTS/trash?' . ($deleted ? 'purged=1' : 'not_found=1'));
    }
}
