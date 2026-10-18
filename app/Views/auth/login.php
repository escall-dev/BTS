<?php
$logoPath = ltrim(str_replace(chr(92), '/', (string) ($uiSettings['UI_LOGO_PATH'] ?? '')), '/');
$logoIsValid = preg_match('/^uploads\/branding\/system-logo-[a-f0-9]{16}\.(?:png|jpe?g|webp)$/i', $logoPath) === 1
    && is_file(__DIR__ . '/../../../public/' . $logoPath);
ob_start();
?>
<div class="auth-mesh" aria-hidden="true">
    <span class="auth-mesh__layer auth-mesh__layer--accent"></span>
    <span class="auth-mesh__layer auth-mesh__layer--sky"></span>
    <span class="auth-mesh__layer auth-mesh__layer--white"></span>
    <span class="auth-mesh__layer auth-mesh__layer--blue"></span>
</div>
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
        <form method="post" action="/BTS/login" autocomplete="on" class="form-stack auth-form" data-login-form>
            <?php if (!empty($error)): ?>
                <p class="alert auth-error" role="alert"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <div class="auth-field">
                <label for="login-username">Username</label>
                <input id="login-username" type="text" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
            </div>
            <div class="auth-field">
                <label for="login-password">Password</label>
                <div class="auth-password-field">
                    <input id="login-password" type="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" aria-controls="login-password">
                        <svg data-password-eye-open viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg data-password-eye-closed viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.8 10.8 0 0 0 4.1-.8"/></svg>
                    </button>
                </div>
            </div>
            <label class="auth-remember" for="remember-username">
                <input id="remember-username" type="checkbox" data-remember-username>
                <span>Remember me</span>
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
