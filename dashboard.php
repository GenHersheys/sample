<?php
require_once 'config/db.php';
require_civilian_dashboard();

$user = current_user();

$alerts = [];
$alert_result = $conn->query(
    "SELECT alert_id, title, content, severity, created_at FROM alerts ORDER BY created_at DESC LIMIT 5"
);
if ($alert_result) {
    while ($row = $alert_result->fetch_assoc()) {
        $alerts[] = $row;
    }
}
if (empty($alerts)) {
    $alerts[] = [
        'title' => 'No active threats reported',
        'content' => 'Stay vigilant and monitor local broadcasts.',
        'severity' => 'Low',
    ];
}

$incidents = [];
$incident_sql = 'SELECT incident_id, title, location, status, reported_at FROM incidents ORDER BY reported_at DESC LIMIT 5';
if (column_exists($conn, 'incidents', 'latitude')) {
    $incident_sql = 'SELECT incident_id, title, location, status, latitude, longitude, reported_at FROM incidents ORDER BY reported_at DESC LIMIT 5';
}
$incident_result = $conn->query($incident_sql);
if ($incident_result) {
    while ($row = $incident_result->fetch_assoc()) {
        $incidents[] = $row;
    }
}

$map_incidents = array_values(array_filter($incidents, function ($row) {
    return !empty($row['latitude']) && !empty($row['longitude']);
}));

$guides = [];
if (table_exists($conn, 'preparedness_guides')) {
    $guide_result = $conn->query(
        "SELECT title, summary, category FROM preparedness_guides ORDER BY created_at DESC LIMIT 5"
    );
    if ($guide_result) {
        while ($row = $guide_result->fetch_assoc()) {
            $guides[] = $row;
        }
    }
}

$open_count = 0;
$stats_result = $conn->query("SELECT COUNT(*) AS c FROM incidents WHERE status IN ('Pending', 'Active')");
if ($stats_result) {
    $open_count = (int) $stats_result->fetch_assoc()['c'];
}

$help_count = 0;
if (table_exists($conn, 'help_requests')) {
    $help_result = $conn->query("SELECT COUNT(*) AS c FROM help_requests WHERE status = 'pending'");
    if ($help_result) {
        $help_count = (int) $help_result->fetch_assoc()['c'];
    }
}

$activity_files = [];
if (table_exists($conn, 'activity_files')) {
    $file_result = $conn->query(
        'SELECT file_id, original_name, file_size, mime_type, created_at
         FROM activity_files
         ORDER BY created_at DESC
         LIMIT 5'
    );
    if ($file_result) {
        while ($row = $file_result->fetch_assoc()) {
            $activity_files[] = $row;
        }
    }
}

$welcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';

include_once 'includes/header.php';
?>

<main class="dashboard-layout">
    <div class="page-header dashboard-header">
        <span class="section-label">Community Portal</span>
        <h1>Public Dashboard</h1>
        <p class="page-subtitle">Welcome, <?php echo e($user['display_name']); ?> — view alerts, incidents, and school activities.</p>
    </div>

    <?php if ($welcome): ?>
        <div class="flash flash-success">Account created successfully. Welcome to Disaster Relief Net!</div>
    <?php endif; ?>

    <section class="alert-carousel" aria-label="Emergency Alerts">
        <div class="alert-slides">
            <?php foreach ($alerts as $i => $alert): ?>
                <?php $sev = alert_severity_class($alert['severity'] ?? 'Low'); ?>
                <div class="alert-slide alert-<?php echo e($sev); ?><?php echo $i === 0 ? ' active' : ''; ?>">
                    <h2>Emergency Alerts</h2>
                    <p class="alert-title">
                        <strong>[<?php echo e(strtoupper($alert['severity'] ?? 'NOTICE')); ?>]</strong>
                        <?php echo e($alert['title']); ?>
                    </p>
                    <p><?php echo e($alert['content']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($alerts) > 1): ?>
            <div class="carousel-dots" role="tablist">
                <?php foreach ($alerts as $i => $alert): ?>
                    <button type="button" class="carousel-dot<?php echo $i === 0 ? ' active' : ''; ?>"
                            aria-label="Alert <?php echo $i + 1; ?>" data-index="<?php echo $i; ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <h3 class="section-title">Quick Actions</h3>
    <div class="action-buttons">
        <a href="report_incident.php" class="btn-big btn-primary">
            Report Incident
            <small>Submit a situation report</small>
        </a>
        <a href="get_help.php" class="btn-big">
            Get Help
            <small>Request emergency assistance</small>
        </a>
        <a href="activities.php" class="btn-big">
            View Activities
            <small>Browse school activity files</small>
        </a>
    </div>

    <div class="dashboard-grid">
        <div class="main-content-column">
            <div class="card">
                <h3>Live Incident Map</h3>
                <div id="incident-map" class="map-container"></div>
                <p class="form-hint map-hint"><?php echo count($map_incidents); ?> incident(s) with GPS on map</p>
            </div>

            <div class="card">
                <h3>Latest Situation Reports</h3>
                <?php if (empty($incidents)): ?>
                    <p class="empty-state">No situation reports yet.</p>
                <?php else: ?>
                    <ul class="content-list">
                        <?php foreach ($incidents as $incident): ?>
                            <li class="content-list-item">
                                <div class="content-list-body">
                                    <strong><?php echo e($incident['title']); ?></strong>
                                    <span class="incident-meta">
                                        <?php echo e($incident['location']); ?> &middot;
                                        <?php echo e(format_status($incident['status'])); ?> &middot;
                                        <?php echo e(date('M j, g:i A', strtotime($incident['reported_at']))); ?>
                                    </span>
                                </div>
                                <span class="list-icon" aria-hidden="true">&#128196;</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Preparedness Guides</h3>
                <?php if (empty($guides)): ?>
                    <p class="empty-state">Guides coming soon.</p>
                <?php else: ?>
                    <ul class="content-list">
                        <?php foreach ($guides as $guide): ?>
                            <li class="content-list-item">
                                <div class="content-list-body">
                                    <strong><?php echo e($guide['title']); ?></strong>
                                    <span class="incident-meta"><?php echo e($guide['summary']); ?></span>
                                </div>
                                <span class="list-icon" aria-hidden="true">&#128214;</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header-row">
                    <h3>School Activities</h3>
                    <a href="activities.php" class="card-link">Browse all files</a>
                </div>
                <?php if (empty($activity_files)): ?>
                    <p class="empty-state">No activity files published yet.</p>
                <?php else: ?>
                    <ul class="content-list">
                        <?php foreach ($activity_files as $file): ?>
                            <li class="content-list-item">
                                <div class="content-list-body">
                                    <strong><?php echo e($file['original_name']); ?></strong>
                                    <span class="incident-meta">
                                        <?php echo e(format_bytes((int) $file['file_size'])); ?>
                                        &middot; <?php echo e(date('M j, Y', strtotime($file['created_at']))); ?>
                                    </span>
                                </div>
                                <a href="download_file.php?id=<?php echo (int) $file['file_id']; ?>" class="list-icon" title="Download">&#11015;</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-column">
            <div class="card">
                <h3>Community Status</h3>
                <ul class="stats-list">
                    <li>Active incidents: <strong><?php echo e((string) $open_count); ?></strong></li>
                    <li>Pending help requests: <strong><?php echo e((string) $help_count); ?></strong></li>
                    <li>Active alerts: <strong><?php echo e((string) count($alerts)); ?></strong></li>
                </ul>
                <hr class="divider">
                <p class="form-hint">Need assistance? <a href="get_help.php">Submit a help request</a> or <a href="volunteer.php">volunteer</a>.</p>
            </div>

            <div class="card account-card">
                <h3>Your Account</h3>
                <p class="form-hint">Signed in as <strong><?php echo e($user['display_name']); ?></strong></p>
                <p class="form-hint">Civilian account — view-only access. File uploads and admin tools require staff credentials.</p>
                <a href="logout.php" class="btn btn-outline btn-block-sm">Sign Out</a>
            </div>
        </div>
    </div>
</main>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var MAP_INCIDENTS = <?php echo json_encode(array_map(function ($i) {
    return [
        'title' => $i['title'],
        'location' => $i['location'],
        'lat' => (float) ($i['latitude'] ?? 0),
        'lng' => (float) ($i['longitude'] ?? 0),
    ];
}, $map_incidents), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

(function () {
    var slides = document.querySelectorAll('.alert-slide');
    var dots = document.querySelectorAll('.carousel-dot');
    var current = 0, timer;
    function showSlide(i) {
        slides.forEach(function (s, n) { s.classList.toggle('active', n === i); });
        dots.forEach(function (d, n) { d.classList.toggle('active', n === i); });
        current = i;
    }
    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            showSlide(parseInt(dot.dataset.index, 10));
            clearInterval(timer);
            timer = setInterval(function () { showSlide((current + 1) % slides.length); }, 6000);
        });
    });
    if (slides.length > 1) timer = setInterval(function () { showSlide((current + 1) % slides.length); }, 6000);
})();

(function () {
    var mapEl = document.getElementById('incident-map');
    if (!mapEl || typeof L === 'undefined') return;
    var map = L.map(mapEl).setView([14.5995, 120.9842], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    var bounds = [];
    MAP_INCIDENTS.forEach(function (inc) {
        L.marker([inc.lat, inc.lng]).addTo(map).bindPopup('<strong>' + inc.title + '</strong><br>' + inc.location);
        bounds.push([inc.lat, inc.lng]);
    });
    if (bounds.length === 1) map.setView(bounds[0], 13);
    else if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30] });
})();
</script>

<?php include_once 'includes/footer.php'; ?>
