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
        <?php $isLoginLocked = (int) ($lockoutSeconds ?? 0) > 0; ?>
        <form method="post" action="/BTS/login" autocomplete="on" class="form-stack auth-form" data-login-form data-login-lockout-until="<?= $isLoginLocked ? time() + (int) $lockoutSeconds : 0 ?>" data-login-lockout-status-url="/BTS/login/lockout-status">
            <?php if (!empty($error)): ?>
                <p class="alert auth-error" role="alert" data-login-error><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p class="alert auth-error" role="alert" data-login-error hidden></p>
            <?php endif; ?>
            <p class="alert auth-error" role="status" aria-live="polite" aria-atomic="true" data-login-lockout-status<?= $isLoginLocked ? '' : ' hidden' ?>><?= $isLoginLocked ? htmlspecialchars((string) $lockoutMessage, ENT_QUOTES, 'UTF-8') : '' ?></p>
            <div class="auth-field">
                <label for="login-username">Username</label>
                <input id="login-username" type="text" name="username" value="<?= htmlspecialchars((string) ($username ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" autocapitalize="none" spellcheck="false" minlength="1" maxlength="64" pattern="[A-Za-z0-9_.@-]+" title="Use letters, numbers, periods, underscores, hyphens, or @." required<?= $isLoginLocked ? ' disabled' : '' ?>>
            </div>
            <div class="auth-field">
                <label for="login-password">Password</label>
                <div class="auth-password-field">
                    <input id="login-password" type="password" name="password" autocomplete="current-password" maxlength="4096" required<?= $isLoginLocked ? ' disabled' : '' ?>>
                    <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false" aria-controls="login-password"<?= $isLoginLocked ? ' disabled' : '' ?>>
                        <svg data-password-eye-open viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg data-password-eye-closed viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.8 10.8 0 0 0 4.1-.8"/></svg>
                    </button>
                </div>
            </div>
            <label class="auth-remember" for="remember-username">
                <input id="remember-username" type="checkbox" data-remember-username<?= $isLoginLocked ? ' disabled' : '' ?>>
                <span>Remember me</span>
            </label>
            <button type="submit" class="auth-submit"<?= $isLoginLocked ? ' disabled' : '' ?>>Sign in</button>
        </form>
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
<footer class="auth-footer">
    <nav class="auth-footer__links" aria-label="System information">
        <button type="button" data-auth-info="privacy">Privacy Policy</button>
        <button type="button" data-auth-info="terms">Terms &amp; Policies</button>
        <button type="button" data-auth-info="community">Community Standards</button>
        <button type="button" data-auth-info="about">About Us</button>
    </nav>
    <?php if (trim((string) ($appVersion ?? '')) !== ''): ?>
        <p class="auth-footer__version"><?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</footer>
<dialog class="auth-info-dialog" data-auth-info-dialog aria-labelledby="auth-info-title" aria-describedby="auth-info-content">
    <div class="auth-info-dialog__panel">
        <p class="auth-info-dialog__eyebrow">Barangay Treasury System</p>
        <h2 id="auth-info-title" data-auth-info-title></h2>
        <div id="auth-info-content" class="auth-info-dialog__content" data-auth-info-content></div>
        <button type="button" class="auth-info-dialog__close" data-auth-info-close>Close</button>
    </div>
</dialog>
<div class="auth-info-copy" data-auth-info-copy hidden>
    <p data-auth-info-text="privacy">Barangay Treasury System processes personal data in accordance with Republic Act No. 10173, or the Data Privacy Act of 2012, its Implementing Rules and Regulations, and applicable issuances of the National Privacy Commission. It also records transaction information to record payments, manage treasury records, and meet official reporting and audit requirements. Only authorized personnel may access this information for their assigned duties. The Barangay stores records on its designated system and applies safeguards to protect them. It may disclose records when required or permitted by law.</p>
    <p data-auth-info-text="terms">This system is for authorized Barangay Treasury work. Use your assigned account, protect your password, and access only the records needed for your duties. Do not share, misuse, or alter records, or attempt to bypass system controls. The system records user activity for security and audit purposes. Report errors or suspected security incidents to Developers.</p>
    <p data-auth-info-text="community">Use the system responsibly and only for official duties. Protect residents’ and employees’ information. Do not browse records without an official reason, disclose information through unapproved channels, falsify transactions, or interfere with system security. Report suspected misuse or unauthorized access to Developers.</p>
    <p data-auth-info-text="about">This system supports Barangay in recording treasury transactions and managing related services. The Barangay remains responsible for its compliance with Republic Act No. 11032 and its Citizen’s Charter</p>
</div>
<?php
$content = ob_get_clean();
$active = 'auth';
require __DIR__ . '/../layout/main.php';
