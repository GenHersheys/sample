<?php
require_once 'config/db.php';

if (is_logged_in()) {
    header('Location: ' . login_redirect_for_role(current_user()['role']));
    exit;
}

$error = '';
$redirect = safe_redirect($_GET['redirect'] ?? 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $login = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = safe_redirect($_POST['redirect'] ?? 'dashboard.php');

        $stmt = $conn->prepare(
            'SELECT user_id, name, email, password, role FROM users WHERE email = ? OR name = ? LIMIT 1'
        );
        $stmt->bind_param('ss', $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && verify_password($password, $user['password'])) {
            login_user($user);
            $target = login_redirect_for_role($user['role']);
            if ($redirect !== 'index.php' && $redirect !== 'dashboard.php') {
                $target = $redirect;
            } elseif (map_user_role($user['role']) === 'civilian' && $redirect === 'index.php') {
                $target = 'dashboard.php';
            }
            header('Location: ' . $target);
            exit;
        }

        $error = 'Invalid email/username or password.';
    }
}

$page_title = 'Sign In — Disaster Relief Net';
$public_layout = true;
include_once 'includes/header.php';
?>

<main class="auth-page">
    <div class="auth-panel auth-panel-brand">
        <div class="auth-brand-content">
            <span class="hero-badge">Welcome Back</span>
            <h1>Sign In</h1>
            <p>Civilians and staff use the same sign-in page. You will be directed to the correct dashboard based on your account type.</p>
            <ul class="auth-features">
                <li><strong>Civilians</strong> — public dashboard &amp; view activities</li>
                <li><strong>Staff</strong> — operations dashboard &amp; admin tools</li>
            </ul>
            <p class="auth-register-cta">No account yet? <a href="register.php" class="auth-inline-link">Create a civilian account</a></p>
        </div>
    </div>
    <div class="auth-panel auth-panel-form">
        <div class="form-card">
            <h2>Sign in to your account</h2>
            <p class="form-hint">Staff demo: <strong>admin</strong> or <strong>agent</strong> / <strong>password</strong></p>

            <?php if ($error): ?>
                <div class="flash flash-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="signin.php" class="incident-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">

                <div class="form-group">
                    <label for="username">Email or Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           placeholder="Enter your email or username"
                           value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="Enter your password">
                </div>

                <button type="submit" class="btn-submit btn-submit-blue btn-block">Sign In</button>
            </form>

            <p class="auth-alt-action">New civilian user? <a href="register.php"><strong>Create an account</strong></a></p>
            <p class="auth-back-link"><a href="login.php">&larr; Back to homepage</a></p>
        </div>
    </div>
</main>

<?php include_once 'includes/footer.php'; ?>
