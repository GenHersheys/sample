<?php
require_once 'config/db.php';

$page_title = 'Activities — Disaster Relief Net';
$message = '';
$message_type = '';
$upload_dir = __DIR__ . '/uploads/activities/files/';
$max_bytes = 20 * 1024 * 1024;
$allowed_mimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv',
    'application/zip', 'application/x-zip-compressed',
];

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$is_staff = is_admin();
$folders = [];
$files = [];
$active_folder = null;
$active_folder_id = (int) ($_GET['folder'] ?? 0);

if (table_exists($conn, 'activity_folders')) {
    $result = $conn->query('SELECT folder_id, name FROM activity_folders ORDER BY sort_order ASC, folder_id ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $folders[] = $row;
        }
    }
}

if (empty($folders) && table_exists($conn, 'activity_folders')) {
    $folders = [
        ['folder_id' => 1, 'name' => 'Activity 1'],
        ['folder_id' => 2, 'name' => 'Activity 2'],
        ['folder_id' => 3, 'name' => 'Activity 3'],
    ];
}

if ($active_folder_id <= 0 && !empty($folders)) {
    $active_folder_id = (int) $folders[0]['folder_id'];
}

foreach ($folders as $folder) {
    if ((int) $folder['folder_id'] === $active_folder_id) {
        $active_folder = $folder;
        break;
    }
}

if (!$active_folder && !empty($folders)) {
    $active_folder = $folders[0];
    $active_folder_id = (int) $active_folder['folder_id'];
}

function activities_url(int $folder_id, array $extra = []): string
{
    $params = array_merge(['folder' => $folder_id], $extra);
    return 'activities.php?' . http_build_query($params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'upload';
    $post_folder = (int) ($_POST['folder_id'] ?? $active_folder_id);

    if (!is_admin()) {
        $message = 'You must be logged in as staff to manage files.';
        $message_type = 'error';
    } elseif (!verify_csrf()) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'error';
    } elseif (!table_exists($conn, 'activity_files')) {
        $message = 'Activities are not set up yet. Run setup.php first.';
        $message_type = 'error';
    } elseif ($action === 'add_folder') {
        $name = trim($_POST['folder_name'] ?? '');
        if ($name === '') {
            $message = 'Folder name is required.';
            $message_type = 'error';
        } elseif (!table_exists($conn, 'activity_folders')) {
            $message = 'Folders are not set up yet. Run setup.php first.';
            $message_type = 'error';
        } else {
            $sort = count($folders) + 1;
            $stmt = $conn->prepare('INSERT INTO activity_folders (name, sort_order) VALUES (?, ?)');
            $stmt->bind_param('si', $name, $sort);
            if ($stmt->execute()) {
                $new_id = (int) $conn->insert_id;
                header('Location: ' . activities_url($new_id, ['added' => 1]));
                exit;
            }
            $message = 'Could not create folder.';
            $message_type = 'error';
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $file_id = (int) ($_POST['file_id'] ?? 0);
        $stmt = $conn->prepare('SELECT file_path, folder_id FROM activity_files WHERE file_id = ? LIMIT 1');
        $stmt->bind_param('i', $file_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $message = 'File not found.';
            $message_type = 'error';
        } else {
            $del = $conn->prepare('DELETE FROM activity_files WHERE file_id = ?');
            $del->bind_param('i', $file_id);
            if ($del->execute()) {
                $disk_path = __DIR__ . '/' . $row['file_path'];
                if (is_file($disk_path)) {
                    unlink($disk_path);
                }
                header('Location: ' . activities_url((int) $row['folder_id'], ['deleted' => 1]));
                exit;
            }
            $message = 'Could not delete file.';
            $message_type = 'error';
            $del->close();
        }
    } else {
        $user = current_user();
        $uploaded = 0;
        $errors = [];

        if (empty($_FILES['files']['name']) || !is_array($_FILES['files']['name'])) {
            $message = 'Please choose at least one file to upload.';
            $message_type = 'error';
        } else {
            $count = count($_FILES['files']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = $_FILES['files']['name'][$i] . ': upload failed.';
                    continue;
                }
                if ($_FILES['files']['size'][$i] > $max_bytes) {
                    $errors[] = $_FILES['files']['name'][$i] . ': exceeds 20 MB limit.';
                    continue;
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['files']['tmp_name'][$i]);
                if (!in_array($mime, $allowed_mimes, true)) {
                    $errors[] = $_FILES['files']['name'][$i] . ': file type not allowed.';
                    continue;
                }

                $original = basename($_FILES['files']['name'][$i]);
                $ext = pathinfo($original, PATHINFO_EXTENSION);
                $stored = 'file_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
                $relative = 'uploads/activities/files/' . $stored;

                if (!move_uploaded_file($_FILES['files']['tmp_name'][$i], $upload_dir . $stored)) {
                    $errors[] = $original . ': could not save file.';
                    continue;
                }

                $size = (int) $_FILES['files']['size'][$i];
                $stmt = $conn->prepare(
                    'INSERT INTO activity_files (folder_id, original_name, stored_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('isssisi', $post_folder, $original, $stored, $relative, $size, $mime, $user['id']);
                if ($stmt->execute()) {
                    $uploaded++;
                } else {
                    unlink($upload_dir . $stored);
                    $errors[] = $original . ': database error.';
                }
                $stmt->close();
            }

            if ($uploaded > 0 && empty($errors)) {
                header('Location: ' . activities_url($post_folder, ['uploaded' => $uploaded]));
                exit;
            }
            if ($uploaded > 0) {
                $message = $uploaded . ' file(s) uploaded. Some files failed: ' . implode(' ', $errors);
                $message_type = 'success';
            } elseif (!empty($errors)) {
                $message = implode(' ', $errors);
                $message_type = 'error';
            } else {
                $message = 'No files were uploaded.';
                $message_type = 'error';
            }
        }
    }
}

if ($active_folder && table_exists($conn, 'activity_files')) {
    $stmt = $conn->prepare(
        'SELECT file_id, original_name, file_size, mime_type, created_at
         FROM activity_files
         WHERE folder_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->bind_param('i', $active_folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $files[] = $row;
    }
    $stmt->close();
}

$uploaded_count = isset($_GET['uploaded']) ? (int) $_GET['uploaded'] : 0;
$deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$added = isset($_GET['added']) && $_GET['added'] === '1';
$public_layout = !is_logged_in();
$nav_variant = 'activities';

include_once 'includes/header.php';
?>

<main class="page-container activities-layout wire-activities">
    <div class="page-header">
        <span class="section-label">School Activities</span>
        <h1>Activity File Drive</h1>
        <p class="page-subtitle">Browse, download, and manage preparedness documents organized by school activity.</p>
    </div>

    <?php if ($uploaded_count > 0): ?>
        <div class="flash flash-success"><?php echo e((string) $uploaded_count); ?> file(s) uploaded.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <div class="flash flash-success">File deleted.</div>
    <?php endif; ?>
    <?php if ($added): ?>
        <div class="flash flash-success">Activity folder created.</div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="flash flash-<?php echo e($message_type); ?>"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="activities-body">
    <aside class="wire-activities-sidebar card">
        <h2 class="wire-sidebar-title">Activities</h2>
        <ul class="wire-activity-list">
            <?php foreach ($folders as $folder): ?>
                <li class="<?php echo (int) $folder['folder_id'] === $active_folder_id ? 'active' : ''; ?>">
                    <a href="<?php echo e(activities_url((int) $folder['folder_id'])); ?>">
                        <?php echo e($folder['name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($is_staff && table_exists($conn, 'activity_folders')): ?>
        <form method="POST" action="<?php echo e(activities_url($active_folder_id)); ?>" class="wire-add-folder-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_folder">
            <input type="hidden" name="folder_id" value="<?php echo (int) $active_folder_id; ?>">
            <input type="text" name="folder_name" placeholder="New activity name" maxlength="255" required>
            <button type="submit" class="wire-add-folder-btn">+ Add</button>
        </form>
        <?php endif; ?>
    </aside>

    <section class="wire-activities-main">
        <div class="wire-folder-header">
            <span class="wire-folder-icon" aria-hidden="true">&#128193;</span>
            <h2><?php echo e($active_folder['name'] ?? 'Activity'); ?></h2>
        </div>

        <div class="wire-folder-toolbar card">
            <?php if ($is_staff): ?>
                <label for="file-input" class="wire-upload-btn">Upload Files</label>
                <span class="form-hint"><?php echo count($files); ?> file(s) in this activity</span>
            <?php elseif (is_logged_in()): ?>
                <span class="form-hint"><?php echo count($files); ?> file(s) — view only. Staff credentials are required to upload.</span>
            <?php else: ?>
                <span class="form-hint"><?php echo count($files); ?> file(s) &mdash; <a href="signin.php?redirect=<?php echo urlencode('activities.php?folder=' . $active_folder_id); ?>">Sign in</a> to browse as a member</span>
            <?php endif; ?>
        </div>

        <div class="wire-content-box card">
            <h3 class="wire-content-label">Content</h3>

            <?php if ($is_staff): ?>
            <form method="POST" action="<?php echo e(activities_url($active_folder_id)); ?>" enctype="multipart/form-data"
                  class="wire-upload-form" id="upload-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="folder_id" value="<?php echo (int) $active_folder_id; ?>">
                <input type="file" name="files[]" id="file-input" multiple class="drive-file-input"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.jpg,.jpeg,.png,.gif,.webp">
                <div class="wire-dropzone" id="dropzone">
                    <strong>Drop files here</strong> or click to browse
                    <span class="form-hint">PDF, Word, Excel, images, ZIP — max 20 MB each</span>
                </div>
            </form>
            <?php endif; ?>

            <?php if (empty($files)): ?>
                <p class="wire-content-empty">No files in this activity yet.</p>
            <?php else: ?>
                <ul class="wire-file-list">
                    <?php foreach ($files as $file): ?>
                        <?php $icon = file_type_icon($file['mime_type'], $file['original_name']); ?>
                        <li class="wire-file-item">
                            <span class="wire-file-type wire-icon-<?php echo e($icon); ?>" aria-hidden="true">&#128196;</span>
                            <div class="wire-file-details">
                                <strong><?php echo e($file['original_name']); ?></strong>
                                <span><?php echo e(format_bytes((int) $file['file_size'])); ?> &middot; <?php echo e(date('M j, Y', strtotime($file['created_at']))); ?></span>
                            </div>
                            <div class="wire-file-actions">
                                <a href="download_file.php?id=<?php echo (int) $file['file_id']; ?>" class="wire-file-link">Download</a>
                                <?php if ($is_staff): ?>
                                <form method="POST" action="<?php echo e(activities_url($active_folder_id)); ?>"
                                      onsubmit="return confirm('Delete this file?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file_id" value="<?php echo (int) $file['file_id']; ?>">
                                    <input type="hidden" name="folder_id" value="<?php echo (int) $active_folder_id; ?>">
                                    <button type="submit" class="wire-file-delete">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
    </div>
</main>

<?php if ($is_staff): ?>
<script>
(function () {
    var form = document.getElementById('upload-form');
    var input = document.getElementById('file-input');
    var zone = document.getElementById('dropzone');
    if (!form || !input || !zone) return;

    zone.addEventListener('click', function () { input.click(); });
    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('wire-dropzone-active');
    });
    zone.addEventListener('dragleave', function () {
        zone.classList.remove('wire-dropzone-active');
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('wire-dropzone-active');
        input.files = e.dataTransfer.files;
        if (input.files.length) form.submit();
    });
    input.addEventListener('change', function () {
        if (input.files.length) form.submit();
    });
})();
</script>
<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>
