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
        $filters = $this->filters($_GET);
        $totalTrashItems = $workflow->trashCount($filters);
        $perPage = $filters['per_page'] === 'all' ? max(1, $totalTrashItems) : (int) $filters['per_page'];
        $pageCount = max(1, (int) ceil($totalTrashItems / $perPage));
        $page = min($filters['page'], $pageCount);
        $filters['page'] = $page;
        $trashItems = $workflow->trashItems($filters, $perPage, ($page - 1) * $perPage);
        $this->view('trash/index', [
            'title' => 'Trash',
            'active' => 'trash',
            'trashItems' => $trashItems,
            'filters' => $filters,
            'hasFilters' => $filters['recently_deleted'] !== '' || $filters['progress'] !== '' || $filters['workflow'] !== '' || $filters['process_number'] !== '',
            'totalTrashItems' => $totalTrashItems,
            'trashPerPage' => $perPage,
            'trashPage' => $page,
            'trashPageCount' => $pageCount,
            'restored' => ($_GET['restored'] ?? '') === '1',
            'purged' => ($_GET['purged'] ?? '') === '1',
            'notFound' => ($_GET['not_found'] ?? '') === '1',
        ]);
    }

    public function suggestions(): void
    {
        $this->requireLogin();
        $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);
        $suggestions = $query === '' ? [] : (new Workflow())->trashSuggestions($this->filters($_GET), $query);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['suggestions' => $suggestions], JSON_INVALID_UTF8_SUBSTITUTE);
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

    private function filters(array $input): array
    {
        return [
            'recently_deleted' => in_array((string) ($input['recently_deleted'] ?? ''), ['24-hours', '7-days', '30-days'], true)
                ? (string) $input['recently_deleted']
                : '',
            'progress' => in_array((string) ($input['progress'] ?? ''), ['none', 'partial', 'complete'], true)
                ? (string) $input['progress']
                : '',
            'workflow' => in_array((string) ($input['workflow'] ?? ''), [Workflow::PURCHASE_START, Workflow::VOUCHER_START], true)
                ? (string) $input['workflow']
                : '',
            'process_number' => mb_substr(trim((string) ($input['process_number'] ?? '')), 0, 120),
            'per_page' => in_array((string) ($input['per_page'] ?? '10'), ['5', '10', '15', '20', 'all'], true)
                ? (string) ($input['per_page'] ?? '10')
                : '10',
            'page' => max(1, (int) ($input['page'] ?? 1)),
        ];
    }
}
