<?php
require_once 'config/db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'error';
    } else {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $availability = trim($_POST['availability'] ?? '');

        if ($name === '' || $contact === '' || $skills === '' || $availability === '') {
            $message = 'All fields are required.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO volunteer_signups (name, contact, skills, availability) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('ssss', $name, $contact, $skills, $availability);

            if ($stmt->execute()) {
                $message = 'Thank you for volunteering! We will reach out with assignment details.';
                $message_type = 'success';
                $_POST = [];
            } else {
                $message = 'Failed to submit signup. Please try again.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

$public_layout = !is_logged_in();
include_once 'includes/header.php';
?>

<main class="form-page">
    <div class="card form-card">
        <h2>Volunteer Sign-Up</h2>
        <p class="form-hint">Register to support relief operations in your area.</p>

        <?php if ($message): ?>
            <div class="flash flash-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="volunteer.php" class="incident-form">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required maxlength="100"
                       value="<?php echo e($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contact">Phone or Email</label>
                <input type="text" id="contact" name="contact" required maxlength="100"
                       value="<?php echo e($_POST['contact'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="skills">Skills (e.g. First Aid, Driving, Logistics)</label>
                <input type="text" id="skills" name="skills" required maxlength="255"
                       value="<?php echo e($_POST['skills'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="availability">Availability</label>
                <input type="text" id="availability" name="availability" required maxlength="255"
                       placeholder="e.g. Weekdays 8am–5pm"
                       value="<?php echo e($_POST['availability'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn-submit btn-submit-blue">Sign Up</button>
        </form>
    </div>

    <a href="<?php echo e(is_logged_in() ? user_home_url() : 'login.php'); ?>" class="btn-big btn-back">Back to Home</a>
</main>

<?php include_once 'includes/footer.php'; ?>
