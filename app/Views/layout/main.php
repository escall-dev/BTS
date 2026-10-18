<?php
$app = require __DIR__ . '/../../../config/app.php';
$pageTitle = isset($title) ? $title . ' - ' . $app['name'] : $app['name'];
$active = $active ?? '';
$username = (string) ($_SESSION['user']['username'] ?? 'User');
$userInitial = strtoupper(substr($username, 0, 1));
$sidebarTitle = $sidebarTitle ?? 'BrgyTreasurySystem';
$uiSettings = $uiSettings ?? [];
$uiTheme = $uiTheme ?? [];
if (!empty($_SESSION['user']) && $uiTheme === []) {
    try {
        $uiModel = new \App\Models\UiSettings();
        if ($uiSettings === []) $uiSettings = $uiModel->all();
        $sidebarTitle = trim((string) ($uiSettings['UI_APP_TITLE'] ?? 'BrgyTreasurySystem')) ?: 'BrgyTreasurySystem';
        $uiTheme = $uiModel->theme($uiSettings);
    } catch (\Throwable) {}
}
$faviconPath = ltrim(str_replace(chr(92), '/', (string) ($uiSettings['UI_LOGO_PATH'] ?? '')), '/');
$faviconIsCustom = preg_match('/^uploads\/branding\/system-logo-[a-f0-9]{16}\.(?:png|jpe?g|webp)$/i', $faviconPath) === 1
    && is_file(__DIR__ . '/../../../public/' . $faviconPath);
$faviconHref = $faviconIsCustom ? '/BTS/' . $faviconPath : '/BTS/assets/images/favicon.svg';
$navItems = [
    'dashboard' => ['/BTS/dashboard', 'Dashboard', 'dashboard'],
    'reports' => ['/BTS/reports', 'Report Generation', 'reports'],
    'archive' => ['/BTS/archive', 'Archived Reports', 'archive'],
    'trash' => ['/BTS/trash', 'Trash', 'trash'],
    'system-input' => ['/BTS/system-input', 'System Input', 'settings'],
];
$sidebarIcon = static function (string $icon): string {
    return match ($icon) {
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm6 1.5V9h4.5L13 4.5ZM8 13h8v1.7H8V13Zm0 4h8v1.7H8V17Z"/></svg>',
        'archive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V5Zm1 5h14v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-9Zm4 3v2h6v-2H9Z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h8l1 2h4v2H3V5h4l1-2Zm-3 6h14l-1 12H6L5 9Zm4 3v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.3A3.7 3.7 0 1 0 12 15.7 3.7 3.7 0 0 0 12 8.3Zm8.2 3.7c0 .5-.1 1-.2 1.5l2 1.5-2 3.5-2.4-1a8.2 8.2 0 0 1-2.6 1.5l-.4 2.5h-4l-.4-2.5a8.2 8.2 0 0 1-2.6-1.5l-2.4 1-2-3.5 2-1.5A7.3 7.3 0 0 1 4.8 12c0-.5.1-1 .2-1.5L3 9l2-3.5 2.4 1a8.2 8.2 0 0 1 2.6-1.5l.4-2.5h4l.4 2.5a8.2 8.2 0 0 1 2.6 1.5l2.4-1 2 3.5-2 1.5c.1.5.2 1 .2 1.5Z"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 8l9 5 9-5-9-5Zm-7 8 7 4 7-4v5l-7 4-7-4v-5Z"/></svg>',
    };
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($faviconHref) ?>"<?= $faviconIsCustom ? '' : ' type="image/svg+xml"' ?>>
    <script>try{if(localStorage.getItem('bts.sidebar.collapsed')==='1'){document.documentElement.classList.add('sidebar-is-collapsed')}}catch(error){}</script>
    <link rel="stylesheet" href="/BTS/assets/css/app.css?v=<?= filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?>">
    <?php if ($uiTheme !== []): ?><style>:root{--ebony:<?= htmlspecialchars($uiTheme['primary']) ?>;--primary:<?= htmlspecialchars($uiTheme['primary']) ?>;--primary-dark:<?= htmlspecialchars($uiTheme['primary_dark']) ?>;--sidebar:<?= htmlspecialchars($uiTheme['primary_dark']) ?>;--sidebar-foreground:<?= htmlspecialchars($uiTheme['sidebar_foreground']) ?>;--sidebar-muted:<?= htmlspecialchars($uiTheme['sidebar_muted']) ?>;--palm:<?= htmlspecialchars($uiTheme['accent']) ?>;--accent:<?= htmlspecialchars($uiTheme['accent']) ?>;--accent-foreground:<?= htmlspecialchars($uiTheme['accent_foreground']) ?>}</style><?php endif; ?>
</head>
<body class="<?= empty($_SESSION['user']) ? 'is-guest' : '' ?>">
    <?php if (!empty($_SESSION['user'])): ?>
        <div class="app-shell">
            <aside class="sidebar">
                <a class="brand" href="/BTS/dashboard" aria-label="<?= htmlspecialchars($sidebarTitle) ?> dashboard">
                    <?php if (!empty($uiSettings['UI_LOGO_PATH']) && is_file(__DIR__ . '/../../../public/' . $uiSettings['UI_LOGO_PATH'])): ?><img class="brand-logo" src="/BTS/<?= htmlspecialchars($uiSettings['UI_LOGO_PATH']) ?>" alt=""><?php else: ?><span class="brand-mark" aria-hidden="true"><span></span><span></span></span><?php endif; ?>
                    <span class="brand-title"><?= htmlspecialchars($sidebarTitle) ?></span>
                </a>

                
                <nav class="side-nav" aria-label="Primary navigation">
                    <?php foreach ($navItems as $key => [$href, $label, $icon]): ?>
                        <a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= htmlspecialchars($href) ?>" aria-label="<?= htmlspecialchars($label) ?>">
                            <span class="nav-icon"><?= $sidebarIcon($icon) ?></span>
                            <span class="nav-label"><?= htmlspecialchars($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="sidebar-spacer" aria-hidden="true"></div>

                <section class="sidebar-account" aria-label="User account">
                    <div class="sidebar-section-label">User Account</div>
                    <div class="account-card">
                        <span class="account-avatar" aria-hidden="true"><?= htmlspecialchars($userInitial) ?></span>
                        <span class="account-meta">
                            <strong><?= htmlspecialchars($username) ?></strong>
                            <small>Treasury user</small>
                        </span>
                    </div>
                    <a class="sidebar-logout" href="/BTS/logout" aria-label="Logout" title="Logout">
                        <span class="sidebar-logout-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg></span>
                        <span class="sidebar-logout-label">Logout</span>
                    </a>
                </section>
            </aside>
            <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-expanded="true" aria-label="Minimize sidebar" title="Minimize sidebar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6v12"/></svg>
            </button>
            <div class="content-shell">
                <header class="topbar">
                    <div>
                        <strong><?= htmlspecialchars($title ?? $app['name']) ?></strong>
                    </div>
                </header>
                <main class="page">
                    <?= $content ?? '' ?>
                </main>
            </div>
        </div>
    <?php else: ?>
        <main class="guest-page">
            <?= $content ?? '' ?>
        </main>
    <?php endif; ?>

    <dialog class="app-confirm-dialog" data-confirmation-modal aria-labelledby="app-confirmation-title" aria-describedby="app-confirmation-message">
        <div class="app-confirm-dialog__panel">
            <span class="app-confirm-dialog__success-icon" data-confirmation-success-icon aria-hidden="true" hidden><svg viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7"/></svg></span>
            <span class="app-confirm-dialog__eyebrow" data-confirmation-eyebrow>Confirm changes</span>
            <h2 id="app-confirmation-title" data-confirmation-title>Are you sure?</h2>
            <p id="app-confirmation-message" data-confirmation-message></p>
            <p class="app-confirm-dialog__note" data-confirmation-note hidden></p>
            <ul class="app-confirm-dialog__changes" data-confirmation-changes hidden></ul>
            <div class="app-confirm-dialog__actions">
                <button type="button" class="secondary" data-confirmation-secondary hidden></button>
                <button type="button" class="secondary" data-confirmation-cancel>Cancel</button>
                <button type="button" data-confirmation-accept>Confirm</button>
            </div>
        </div>
    </dialog>

    <?php if ($active === 'auth'): ?>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/SplitText.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js"></script>
    <?php endif; ?>
    <script src="/BTS/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?>"></script>
</body>
</html>
