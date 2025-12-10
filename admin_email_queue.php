<?php
require_once 'includes/session_config.php';
require_once 'includes/session_manager.php';
require_once 'includes/db.php';
require_once 'includes/email_queue_helper.php';

// Admin check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit();
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Queue - Blood Donation Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <h2><i class="fas fa-envelope me-2"></i>Email Queue</h2>
        <p class="text-muted">Emails that could not be sent automatically are queued here. You can view, copy, or delete them.</p>

        <?php
        $emails = get_queued_emails();
        if (empty($emails)) {
            echo '<div class="alert alert-success">No queued emails.</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
            echo '<thead><tr><th>To</th><th>Subject</th><th>Created</th><th>Actions</th></tr></thead><tbody>';
            foreach ($emails as $email) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($email['to']) . '</td>';
                echo '<td>' . htmlspecialchars($email['subject']) . '</td>';
                echo '<td>' . htmlspecialchars($email['created_at']) . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="copyEmail(\'' . htmlspecialchars($email['file'], ENT_QUOTES) . '\')"><i class="fas fa-copy"></i> Copy</button> ';
                echo '<button class="btn btn-sm btn-danger" onclick="deleteEmail(\'' . htmlspecialchars($email['file'], ENT_QUOTES) . '\')"><i class="fas fa-trash"></i> Delete</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        ?>

        <div class="mt-4">
            <h4>Copy All Emails (JSON)</h4>
            <textarea class="form-control" rows="10" readonly><?php echo htmlspecialchars(json_encode($emails, JSON_PRETTY_PRINT)); ?></textarea>
            <button class="btn btn-secondary mt-2" onclick="copyAll()">Copy All to Clipboard</button>
        </div>
    </div>

    <script>
        function copyEmail(file) {
            fetch('admin_email_queue_action.php?action=get&file=' + encodeURIComponent(file))
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        const text = `To: ${data.to}\nSubject: ${data.subject}\n\n${data.html}`;
                        navigator.clipboard.writeText(text);
                        alert('Email copied to clipboard!');
                    }
                });
        }
        function deleteEmail(file) {
            if (!confirm('Delete this queued email?')) return;
            fetch('admin_email_queue_action.php?action=delete&file=' + encodeURIComponent(file))
                .then(r => r.json())
                .then(data => {
                    if (data.ok) location.reload();
                });
        }
        function copyAll() {
            const textarea = document.querySelector('textarea');
            textarea.select();
            document.execCommand('copy');
            alert('All emails copied to clipboard!');
        }
    </script>
</body>
</html>
