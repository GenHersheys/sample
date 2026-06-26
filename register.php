<?php
require_once 'config/db.php';

if (is_logged_in()) {
    header('Location: ' . login_redirect_for_role(current_user()['role']));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $check = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $check->bind_param('s', $email);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'Civilian';
                $geo = 'Online';
                $stmt = $conn->prepare(
                    'INSERT INTO users (name, email, password, role, geo_status) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssss', $name, $email, $hash, $role, $geo);

                if ($stmt->execute()) {
                    $user_id = (int) $conn->insert_id;
                    login_user([
                        'user_id'  => $user_id,
                        'name'     => $name,
                        'email'    => $email,
                        'role'     => $role,
                    ]);
                    header('Location: dashboard.php?welcome=1');
                    exit;
                }

                $error = 'Registration failed. Please try again.';
                $stmt->close();
            }
        }
    }
}

$page_title = 'Create Account — Disaster Relief Net';
$public_layout = true;
include_once 'includes/header.php';
?>

<main class="auth-page">
    <div class="auth-panel auth-panel-brand">
        <div class="auth-brand-content">
            <span class="hero-badge">Community Access</span>
            <h1>Join as a Civilian</h1>
            <p>Create a free account to access the public dashboard, view emergency alerts, browse school activities, and stay informed.</p>
            <ul class="auth-features">
                <li>Public community dashboard</li>
                <li>View activity files &amp; alerts</li>
                <li>Report incidents &amp; request help</li>
                <li>No admin or upload access</li>
            </ul>
        </div>
    </div>
    <div class="auth-panel auth-panel-form">
        <div class="form-card">
            <h2>Create your account</h2>
            <p class="form-hint">Already registered? <a href="signin.php">Sign in here</a></p>

            <?php if ($error): ?>
                <div class="flash flash-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="incident-form">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required maxlength="100"
                           placeholder="Your full name"
                           value="<?php echo e($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required maxlength="100"
                           placeholder="you@example.com"
                           value="<?php echo e($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="6"
                           placeholder="At least 6 characters" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                           placeholder="Repeat your password" autocomplete="new-password">
                </div>

                <button type="submit" class="btn-submit btn-submit-blue btn-block">Create Account</button>
            </form>

            <p class="auth-back-link"><a href="login.php">&larr; Back to homepage</a></p>
        </div>
    </div>
</main>

<?php include_once 'includes/footer.php'; ?>
