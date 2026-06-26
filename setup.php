<?php
/**
 * Run once if login hangs or fails: http://localhost/disaster_system/setup.php
 * Then delete this file.
 */
require_once 'config/db.php';

$messages = [];

function run_sql(mysqli $conn, string $sql, string $label): void
{
    global $messages;
    if ($conn->query($sql)) {
        $messages[] = "OK: $label";
    } else {
        $messages[] = "INFO ($label): " . $conn->error;
    }
}

if (!column_exists($conn, 'users', 'last_seen')) {
    run_sql($conn, 'ALTER TABLE users ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL', 'users.last_seen');
}

$role_col = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($role_col && ($role_row = $role_col->fetch_assoc())) {
    $role_type = strtolower($role_row['Type'] ?? '');
    if (strpos($role_type, 'enum') !== false && strpos($role_type, 'civilian') === false) {
        run_sql($conn, "ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'Civilian'", 'users.role varchar');
    }
}

if (!column_exists($conn, 'incidents', 'latitude')) {
    run_sql($conn, 'ALTER TABLE incidents ADD COLUMN latitude DECIMAL(10,8) NULL', 'incidents.latitude');
    run_sql($conn, 'ALTER TABLE incidents ADD COLUMN longitude DECIMAL(11,8) NULL', 'incidents.longitude');
    run_sql($conn, 'ALTER TABLE incidents ADD COLUMN reported_by INT(11) NULL', 'incidents.reported_by');
}

if (!table_exists($conn, 'checkins')) {
    run_sql($conn, "CREATE TABLE checkins (
        checkin_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        latitude DECIMAL(10,8) NOT NULL,
        longitude DECIMAL(11,8) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )", 'checkins table');
}

if (!table_exists($conn, 'help_requests')) {
    run_sql($conn, "CREATE TABLE help_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact VARCHAR(100) NOT NULL,
        location VARCHAR(255) NOT NULL,
        need_type VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        status ENUM('pending','assigned','resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", 'help_requests table');
}

if (!table_exists($conn, 'volunteer_signups')) {
    run_sql($conn, "CREATE TABLE volunteer_signups (
        signup_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact VARCHAR(100) NOT NULL,
        skills VARCHAR(255) NOT NULL,
        availability VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", 'volunteer_signups table');
}

if (!table_exists($conn, 'volunteer_assignments')) {
    run_sql($conn, "CREATE TABLE volunteer_assignments (
        assignment_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        location VARCHAR(255) NOT NULL,
        status ENUM('assigned','in_progress','completed') DEFAULT 'assigned',
        due_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )", 'volunteer_assignments table');
}

if (!table_exists($conn, 'preparedness_guides')) {
    run_sql($conn, "CREATE TABLE preparedness_guides (
        guide_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        summary TEXT NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'General',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", 'preparedness_guides table');
}

if (!table_exists($conn, 'preparedness_guides')) {
    run_sql($conn, "CREATE TABLE preparedness_guides (
        guide_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        summary TEXT NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'General',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", 'preparedness_guides table');
}

if (!table_exists($conn, 'school_activities')) {
    run_sql($conn, "CREATE TABLE school_activities (
        activity_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        event_date DATE NULL,
        image_path VARCHAR(255) NULL,
        uploaded_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL
    )", 'school_activities table');
}

if (!table_exists($conn, 'activity_files')) {
    run_sql($conn, "CREATE TABLE activity_files (
        file_id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id INT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL DEFAULT 0,
        mime_type VARCHAR(100) NOT NULL,
        uploaded_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL
    )", 'activity_files table');
}

if (!column_exists($conn, 'activity_files', 'folder_id')) {
    run_sql($conn, 'ALTER TABLE activity_files ADD COLUMN folder_id INT NULL', 'activity_files.folder_id');
}

if (!table_exists($conn, 'activity_folders')) {
    run_sql($conn, "CREATE TABLE activity_folders (
        folder_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )", 'activity_folders table');
}

if (table_exists($conn, 'activity_folders')) {
    $count = (int) ($conn->query('SELECT COUNT(*) AS c FROM activity_folders')->fetch_assoc()['c'] ?? 0);
    if ($count === 0) {
        run_sql($conn, "INSERT INTO activity_folders (name, sort_order) VALUES
            ('Activity 1', 1),
            ('Activity 2', 2),
            ('Activity 3', 3)", 'default activity folders');
    }
}

if (!table_exists($conn, 'supplies')) {
    run_sql($conn, "CREATE TABLE supplies (
        supply_id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'General',
        quantity INT NOT NULL DEFAULT 0,
        unit VARCHAR(50) NOT NULL DEFAULT 'units',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )", 'supplies table');
}

function upsert_user(mysqli $conn, string $email, string $name, string $role, string $hash): void
{
    global $messages;
    $check = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$exists) {
        $stmt = $conn->prepare('INSERT INTO users (name, email, password, role, geo_status) VALUES (?, ?, ?, ?, ?)');
        $geo = 'Online';
        $stmt->bind_param('sssss', $name, $email, $hash, $role, $geo);
        $stmt->execute();
        $stmt->close();
        $messages[] = "OK: Created user ($email / password)";
    } else {
        $stmt = $conn->prepare('UPDATE users SET password = ?, name = ?, role = ?, geo_status = ? WHERE email = ?');
        $geo = 'Online';
        $stmt->bind_param('sssss', $hash, $name, $role, $geo, $email);
        $stmt->execute();
        $stmt->close();
        $messages[] = "OK: Reset password for $email";
    }
}

$hash = password_hash('password', PASSWORD_DEFAULT);
upsert_user($conn, 'admin', 'System Administrator', 'Admin', $hash);
upsert_user($conn, 'agent', 'Agent John D.', 'Agent', $hash);

$res = $conn->query("SELECT user_id FROM users WHERE email = 'agent' LIMIT 1");
$agent_id = $res ? ($res->fetch_assoc()['user_id'] ?? null) : null;

if ($agent_id && table_exists($conn, 'volunteer_assignments')) {
    $count = (int) ($conn->query('SELECT COUNT(*) AS c FROM volunteer_assignments')->fetch_assoc()['c'] ?? 0);
    if ($count === 0) {
        $stmt = $conn->prepare('INSERT INTO volunteer_assignments (user_id, title, description, location, status, due_date) VALUES (?, ?, ?, ?, ?, CURDATE())');
        $t1 = 'Relief Distribution — Sector 4';
        $d1 = 'Coordinate food pack distribution at Barangay Hall.';
        $l1 = 'Sector 4, East District';
        $s1 = 'assigned';
        $stmt->bind_param('issss', $agent_id, $t1, $d1, $l1, $s1);
        $stmt->execute();
        $stmt->close();
        $messages[] = 'OK: Sample assignments added';
    }
}

if (table_exists($conn, 'preparedness_guides')) {
    $count = (int) ($conn->query('SELECT COUNT(*) AS c FROM preparedness_guides')->fetch_assoc()['c'] ?? 0);
    if ($count === 0) {
        run_sql($conn, "INSERT INTO preparedness_guides (title, summary, category) VALUES
            ('Emergency Go-Bag Checklist', 'Essential items to pack: water, food, first aid, documents, flashlight.', 'Prepare'),
            ('Family Communication Plan', 'Designate meeting points and emergency contacts for all family members.', 'Prepare'),
            ('Flood Safety Guidelines', 'Move to higher ground. Avoid walking or driving through floodwaters.', 'Know Your Risks')", 'preparedness guides seed');
    }
}

if (table_exists($conn, 'supplies')) {
    $count = (int) ($conn->query('SELECT COUNT(*) AS c FROM supplies')->fetch_assoc()['c'] ?? 0);
    if ($count === 0) {
        run_sql($conn, "INSERT INTO supplies (item_name, category, quantity, unit) VALUES
            ('Rice Packs', 'Food', 500, 'bags'),
            ('Drinking Water', 'Food', 1200, 'bottles'),
            ('First Aid Kits', 'Medical', 85, 'kits'),
            ('Emergency Blankets', 'Shelter', 300, 'pcs'),
            ('Tents (4-person)', 'Shelter', 45, 'units'),
            ('Hygiene Kits', 'General', 200, 'kits')", 'supplies seed');
        $messages[] = 'OK: Sample supply inventory added';
    }
}

include_once 'includes/header.php';
?>
<main class="form-page">
    <div class="card form-card">
        <h2>Database Setup Complete</h2>
        <p class="form-hint">Admin login: <strong>admin</strong> or <strong>agent</strong> / <strong>password</strong></p>
        <ul class="setup-log">
            <?php foreach ($messages as $msg): ?>
                <li><?php echo e($msg); ?></li>
            <?php endforeach; ?>
        </ul>
        <p><a href="signin.php" class="btn-big btn-primary" style="margin-top:16px;">Staff Login</a></p>
    </div>
</main>
<?php include_once 'includes/footer.php'; ?>
