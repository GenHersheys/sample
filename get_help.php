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
        $location = trim($_POST['location'] ?? '');
        $need_type = trim($_POST['need_type'] ?? '');
        $details = trim($_POST['details'] ?? '');

        if ($name === '' || $contact === '' || $location === '' || $need_type === '' || $details === '') {
            $message = 'All fields are required.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO help_requests (name, contact, location, need_type, details) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssss', $name, $contact, $location, $need_type, $details);

            if ($stmt->execute()) {
                $message = 'Help request submitted. A response team will contact you soon.';
                $message_type = 'success';
                $_POST = [];
            } else {
                $message = 'Failed to submit request. Please try again.';
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
        <h2>Request Help</h2>
        <p class="form-hint">Submit an urgent assistance request for yourself or your community.</p>

        <?php if ($message): ?>
            <div class="flash flash-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="get_help.php" class="incident-form">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name" required maxlength="100"
                       value="<?php echo e($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="contact">Phone or Email</label>
                <input type="text" id="contact" name="contact" required maxlength="100"
                       value="<?php echo e($_POST['contact'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="location">Your Location</label>
                <input type="text" id="location" name="location" required maxlength="255"
                       value="<?php echo e($_POST['location'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="need_type">Type of Assistance</label>
                <select id="need_type" name="need_type" required>
                    <option value="">Select...</option>
                    <?php
                    $types = ['Food & Water', 'Medical', 'Shelter', 'Rescue', 'Transport', 'Other'];
                    foreach ($types as $type):
                    ?>
                        <option value="<?php echo e($type); ?>" <?php echo ($_POST['need_type'] ?? '') === $type ? 'selected' : ''; ?>>
                            <?php echo e($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="details">Details</label>
                <textarea id="details" name="details" rows="4" required><?php echo e($_POST['details'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-submit btn-submit-blue">Submit Help Request</button>
        </form>
    </div>

    <a href="<?php echo e(is_logged_in() ? user_home_url() : 'login.php'); ?>" class="btn-big btn-back">Back to Home</a>
</main>

<?php include_once 'includes/footer.php'; ?>
