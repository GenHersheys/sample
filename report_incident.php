<?php
require_once 'config/db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'error';
    } else {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $loc = trim($_POST['location'] ?? '');
        $lat = $_POST['latitude'] ?? '';
        $lng = $_POST['longitude'] ?? '';
        $latitude = ($lat !== '' && is_numeric($lat)) ? (float) $lat : null;
        $longitude = ($lng !== '' && is_numeric($lng)) ? (float) $lng : null;
        $user = current_user();
        $reporter_id = ($user && is_admin()) ? $user['id'] : null;

        if ($title === '' || $desc === '' || $loc === '') {
            $message = 'All fields are required.';
            $message_type = 'error';
        } else {
            $has_geo = column_exists($conn, 'incidents', 'latitude');
            $has_reporter = column_exists($conn, 'incidents', 'reported_by');

            if ($has_geo && $has_reporter && $latitude !== null && $longitude !== null && $reporter_id) {
                $stmt = $conn->prepare(
                    'INSERT INTO incidents (title, description, location, latitude, longitude, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $status = 'Pending';
                $stmt->bind_param('sssddis', $title, $desc, $loc, $latitude, $longitude, $reporter_id, $status);
            } elseif ($has_geo && $latitude !== null && $longitude !== null) {
                $stmt = $conn->prepare(
                    'INSERT INTO incidents (title, description, location, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $status = 'Pending';
                $stmt->bind_param('sssdds', $title, $desc, $loc, $latitude, $longitude, $status);
            } elseif ($has_reporter && $reporter_id) {
                $stmt = $conn->prepare(
                    'INSERT INTO incidents (title, description, location, reported_by, status) VALUES (?, ?, ?, ?, ?)'
                );
                $status = 'Pending';
                $stmt->bind_param('sssis', $title, $desc, $loc, $reporter_id, $status);
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO incidents (title, description, location, status) VALUES (?, ?, ?, ?)'
                );
                $status = 'Pending';
                $stmt->bind_param('ssss', $title, $desc, $loc, $status);
            }

            if ($stmt->execute()) {
                header('Location: report_incident.php?reported=1');
                exit;
            }

            $message = 'Failed to submit report. Please try again.';
            $message_type = 'error';
            $stmt->close();
        }
    }
}

$reported = isset($_GET['reported']) && $_GET['reported'] === '1';
$public_layout = !is_logged_in();
include_once 'includes/header.php';
?>

<main class="form-page">
    <div class="card form-card">
        <h2>Report an Incident</h2>
        <p class="form-hint">Public report — no login required. Our response team will review this immediately.</p>

        <?php if ($reported): ?>
            <div class="flash flash-success">Incident reported successfully. Our team has been notified.</div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="flash flash-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        <form method="POST" action="report_incident.php" class="incident-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="latitude" id="latitude" value="<?php echo e($_POST['latitude'] ?? ''); ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?php echo e($_POST['longitude'] ?? ''); ?>">

            <div class="form-group">
                <label for="title">Incident Type / Title</label>
                <input type="text" id="title" name="title" required maxlength="255"
                       value="<?php echo e($_POST['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="location">Location / Area</label>
                <input type="text" id="location" name="location" required maxlength="255"
                       value="<?php echo e($_POST['location'] ?? ''); ?>">
                <span class="form-hint" id="geo-status">Detecting your location...</span>
            </div>

            <div class="form-group">
                <label for="description">Situation Description</label>
                <textarea id="description" name="description" rows="4" required><?php echo e($_POST['description'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit Report</button>
        </form>
    </div>

    <a href="<?php echo e(is_logged_in() ? user_home_url() : 'login.php'); ?>" class="btn-big btn-back">Back to Home</a>
</main>

<script>
(function () {
    var status = document.getElementById('geo-status');
    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');
    if (!navigator.geolocation) {
        status.textContent = 'Geolocation unavailable — you can still submit.';
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            latInput.value = pos.coords.latitude.toFixed(6);
            lngInput.value = pos.coords.longitude.toFixed(6);
            status.textContent = 'GPS coordinates captured for map placement.';
        },
        function () { status.textContent = 'Could not detect location — you can still submit.'; },
        { enableHighAccuracy: true, timeout: 10000 }
    );
})();
</script>

<?php include_once 'includes/footer.php'; ?>
