<?php
$logoPath = ltrim(str_replace(chr(92), '/', (string) ($uiSettings['UI_LOGO_PATH'] ?? '')), '/');
$logoIsValid = preg_match('/^uploads\/branding\/system-logo-[a-f0-9]{16}\.(?:png|jpe?g|webp)$/i', $logoPath) === 1
    && is_file(__DIR__ . '/../../../public/' . $logoPath);
ob_start();
?>
<div class="auth-stage">
    <aside class="auth-brand auth-brand--transactions" aria-label="Transaction accountability">
        <p class="auth-brand__eyebrow">Treasury • Accountability</p>
        <h2 class="auth-brand__headline" data-brand-headline>
            <span>Make Every</span>
            <span>Transaction</span>
            <strong>Accountable.</strong>
        </h2>
        <span class="auth-brand__rule" aria-hidden="true"></span>
    </aside>
    <section class="auth-panel" aria-labelledby="login-title">
        <div class="auth-orbit auth-orbit-top" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="auth-orbit auth-orbit-bottom" aria-hidden="true"><span></span><span></span><span></span></div>
        <header class="auth-header">
            <div class="auth-logo">
                <?php if ($logoIsValid): ?>
                    <img src="/BTS/<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($sidebarTitle) ?> logo">
                <?php else: ?>
                    <span class="brand-mark" aria-hidden="true"><span></span><span></span></span>
                <?php endif; ?>
            </div>
            <p class="auth-eyebrow"><?= htmlspecialchars($sidebarTitle) ?></p>
            <h1 id="login-title">Login</h1>
            <p class="auth-welcome">Hello there! Sign in to continue.</p>
        </header>
        <form method="post" action="/BTS/login" class="form-stack auth-form">
            <?php if (!empty($error)): ?>
                <p class="alert auth-error" role="alert"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <label>
                Username
                <input type="text" name="username" autocomplete="username" required>
            </label>
            <label>
                Password
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="auth-submit">Sign in</button>
        </form>
        <?php if (trim((string) ($appVersion ?? '')) !== ''): ?>
            <p class="auth-version"><?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </section>
    <aside class="auth-brand auth-brand--process" aria-label="Making every process possible">
        <p class="auth-brand__eyebrow">Treasury • Possibility</p>
        <h2 class="auth-brand__headline" data-brand-headline>
            <span>Make Every</span>
            <span>Process</span>
            <strong>Possible.</strong>
        </h2>
        <span class="auth-brand__rule" aria-hidden="true"></span>
    </aside>
</div>
<?php
$content = ob_get_clean();
$active = 'auth';
require __DIR__ . '/../layout/main.php';
