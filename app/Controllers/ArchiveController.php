<?php

namespace App\Controllers;

use App\Core\CompletedTransactionCertificationGenerator;
use App\Core\Controller;
use App\Models\SystemInput;
use App\Models\Workflow;

class ArchiveController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $workflow = new Workflow();
        $workflow->purgeExpiredTrash();
        [$filters, $filterErrors] = $this->archiveFilters($_GET);
        $totalArchives = $filterErrors === [] ? $workflow->archiveCount($filters) : 0;
        $perPage = $filters['per_page'] === 'all' ? max(1, $totalArchives) : (int) $filters['per_page'];
        $pageCount = max(1, (int) ceil($totalArchives / $perPage));
        $page = min($filters['page'], $pageCount);
        $filters['page'] = $page;
        $this->view('archive/index', [
            'title' => 'Archived Reports',
            'active' => 'archive',
            'archives' => $filterErrors === [] ? $workflow->archives($filters, $perPage, ($page - 1) * $perPage) : [],
            'filters' => $filters,
            'totalArchives' => $totalArchives,
            'archivePerPage' => $perPage,
            'archivePage' => $page,
            'archivePageCount' => $pageCount,
            'filterErrors' => $filterErrors,
            'deleted' => ($_GET['deleted'] ?? '') === '1',
            'deleteNotFound' => ($_GET['delete_not_found'] ?? '') === '1',
        ]);
    }

    public function suggestions(): void
    {
        $this->requireLogin();
        $query = trim((string) ($_GET['q'] ?? ''));
        if ($query === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['suggestions' => []]);
            return;
        }

        $input = $_GET;
        unset($input['q'], $input['process_number']);
        [$filters, $filterErrors] = $this->archiveFilters($input);
        $suggestions = $filterErrors === []
            ? (new Workflow())->archiveSuggestions($filters, $query)
            : [];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['suggestions' => $suggestions], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function certification(): void
    {
        $this->requireLogin();
        $this->renderCertification();
    }

    public function printCertification(): void
    {
        $this->requireLogin();
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $certificateDate = trim((string) ($_POST['certificate_date'] ?? ''));
        $form = ['start_date' => $startDate, 'end_date' => $endDate, 'certificate_date' => $certificateDate];
        $errors = [];
        foreach ($form as $key => $value) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $errors[$key] = 'Enter a valid date.';
            }
        }
        if ($errors === [] && $startDate > $endDate) {
            $errors['end_date'] = 'End date must be on or after the start date.';
        }
        if ($errors !== []) {
            $this->renderCertification($form, $errors);
            return;
        }

        try {
            $workflow = new Workflow();
            $transactions = $workflow->completedTransactions($startDate, $endDate);
            if ($transactions === []) {
                $this->renderCertification($form, ['report' => 'No completed transactions were found in the selected completion-date range.']);
                return;
            }
            $systemInput = new SystemInput();
            $settings = $systemInput->values();
            $requiredSettings = [
                'PBC_NUMBER_SERIES' => 'PBC Number Series',
                'PBC_BANK_ACCOUNT' => 'PBC Bank Account Number',
                'PBC_BANK_NAME' => 'PBC Bank Name',
                'PBC_BANK_ADDRESS' => 'PBC Bank Address',
            ];
            $missingSettings = [];
            foreach ($requiredSettings as $key => $label) {
                if (trim((string) ($settings[$key] ?? '')) === '') {
                    $missingSettings[] = $label;
                }
            }
            if ($missingSettings !== []) {
                $this->renderCertification($form, [
                    'report' => 'Complete these System Input values before generating: ' . implode(', ', $missingSettings) . '.',
                ]);
                return;
            }
            $pbcNumber = $systemInput->reservePbcNumber($certificateDate);
            $result = (new CompletedTransactionCertificationGenerator())->generate($transactions, $settings, $pbcNumber, $certificateDate);
            $this->streamPdf((string) $result['pdf']);
        } catch (\Throwable $exception) {
            $this->renderCertification($form, ['report' => 'Certification generation failed: ' . $exception->getMessage()]);
        }
    }

    public function delete(string $processId): void
    {
        $this->requireLogin();

        $deleted = (new Workflow())->softDeleteProcess((int) $processId);
        $this->redirect('/BTS/archive?' . ($deleted ? 'deleted=1' : 'delete_not_found=1'));
    }

    private function archiveFilters(array $input): array
    {
        $filters = [
            'status' => trim((string) ($input['status'] ?? '')),
            'workflow' => trim((string) ($input['workflow'] ?? '')),
            'date_from' => trim((string) ($input['date_from'] ?? '')),
            'date_to' => trim((string) ($input['date_to'] ?? '')),
            'process_number' => trim((string) ($input['process_number'] ?? '')),
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'per_page' => in_array((string) ($input['per_page'] ?? '10'), ['5', '10', '15', '20', 'all'], true)
                ? (string) ($input['per_page'] ?? '10')
                : '10',
        ];
        $errors = [];

        if ($filters['status'] !== '' && !in_array($filters['status'], ['draft', 'in_progress', 'completed'], true)) {
            $errors['status'] = 'Select a valid status.';
        }
        if ($filters['workflow'] !== '' && !in_array($filters['workflow'], [Workflow::PURCHASE_START, Workflow::VOUCHER_START], true)) {
            $errors['workflow'] = 'Select a valid workflow type.';
        }
        foreach (['date_from' => 'Created From', 'date_to' => 'Created To'] as $key => $label) {
            if ($filters[$key] === '') {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
            if (!$date || $date->format('Y-m-d') !== $filters[$key]) {
                $errors[$key] = $label . ' must be a valid date.';
            }
        }
        if (!isset($errors['date_from']) && !isset($errors['date_to'])
            && $filters['date_from'] !== ''
            && $filters['date_to'] !== ''
            && $filters['date_from'] > $filters['date_to']) {
            $errors['date_to'] = 'Created To must be on or after Created From.';
        }

        return [$filters, $errors];
    }
    private function renderCertification(array $form = [], array $certificationErrors = []): void
    {
        $today = date('Y-m-d');
        $defaults = [
            'start_date' => date('Y-m-01'),
            'end_date' => $today,
            'certificate_date' => $today,
        ];
        $this->view('archive/certification', [
            'title' => 'Punong Barangay Certification',
            'active' => 'archive',
            'certificationForm' => array_merge($defaults, $form),
            'certificationErrors' => $certificationErrors,
        ]);
    }

    private function streamPdf(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Generated certification PDF was not found.');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename=' . chr(34) . basename($path) . chr(34));
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($path);
        exit;
    }
}
