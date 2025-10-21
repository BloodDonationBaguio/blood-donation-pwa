 <?php
// Manage Pages Tab Content
$success = '';
$error = '';

// Define available pages and their default content
$pages = [
    'home' => [
        'title' => 'Home',
        'file' => 'index.php',
        'description' => 'Main landing page of the website'
    ],
    'about' => [
        'title' => 'About Us',
        'file' => 'about.php',
        'description' => 'Information about the blood donation organization'
    ],
    'eligibility' => [
        'title' => 'Eligibility',
        'file' => 'eligibility.php',
        'description' => 'Requirements and guidelines for blood donation'
    ],
    'faq' => [
        'title' => 'FAQ',
        'file' => 'faq.php',
        'description' => 'Frequently asked questions about blood donation'
    ],
    'contact' => [
        'title' => 'Contact Us',
        'file' => 'contact.php',
        'description' => 'Contact information and form'
    ]
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pageId = $_POST['page_id'] ?? '';
        
        if (!isset($pages[$pageId])) {
            throw new Exception("Invalid page selected");
        }
        
        $page = $pages[$pageId];
        $filePath = __DIR__ . '/../../../' . $page['file'];
        
        // Make sure the file exists and is writable
        if (!file_exists($filePath)) {
            throw new Exception("Page file not found: " . $page['file']);
        }
        
        if (!is_writable($filePath)) {
            throw new Exception("Page file is not writable. Please check file permissions.");
        }
        
        // Handle different actions
        if ($_POST['action'] === 'edit') {
            // For viewing/editing, read the file
            $content = file_get_contents($filePath);
        } elseif ($_POST['action'] === 'save' && isset($_POST['content'])) {
            // For saving changes
            $content = $_POST['content'];
            
            // Create a backup before saving
            $backupDir = __DIR__ . '/../../../backups/pages/' . date('Y-m-d');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $backupFile = $backupDir . '/' . $pageId . '_' . time() . '.php';
            copy($filePath, $backupFile);
            
            // Save the new content
            if (file_put_contents($filePath, $content) === false) {
                throw new Exception("Failed to save page content");
            }
            
            $success = "Page '{$page['title']}' has been updated successfully. A backup was created at: " . 
                      str_replace(__DIR__ . '/../../../', '', $backupFile);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current page content if editing
$currentPage = null;
$pageContent = '';
if (isset($_GET['edit']) && isset($pages[$_GET['edit']])) {
    $currentPage = $pages[$_GET['edit']];
    $pageContent = file_get_contents(__DIR__ . '/../../../' . $currentPage['file']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Website Pages</h2>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= $error ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Page List -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Available Pages</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($pages as $id => $page): ?>
                    <a href="?tab=manage-pages&edit=<?= $id ?>" 
                       class="list-group-item list-group-item-action <?= ($currentPage && $currentPage['file'] === $page['file']) ? 'active' : '' ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?= $page['title'] ?></h6>
                        </div>
                        <small class="text-muted"><?= $page['description'] ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Page Backups</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    A backup is automatically created each time you save changes to a page.
                    Backups are stored in the <code>/backups/pages/</code> directory.
                </p>
                <a href="#" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#backupModal">
                    <i class="fas fa-history me-1"></i> View All Backups
                </a>
            </div>
        </div>
    </div>
    
    <!-- Page Editor -->
    <div class="col-md-8">
        <?php if ($currentPage): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editing: <?= $currentPage['title'] ?></h5>
                    <a href="?tab=manage-pages" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i> Close
                    </a>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="page_id" value="<?= array_search($currentPage, $pages) ?>">
                    
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">File:</label>
                            <input type="text" class="form-control mb-2" value="<?= $currentPage['file'] ?>" readonly>
                            
                            <label class="form-label">Content:</label>
                            <textarea id="pageContent" name="content" class="form-control font-monospace" 
                                     style="min-height: 400px; font-size: 14px;"><?= htmlspecialchars($pageContent) ?></textarea>
                            
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i> 
                                Edit the page content above. Basic HTML is supported.
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                        <a href="?tab=manage-pages" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Add CodeMirror for syntax highlighting -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/eclipse.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
            <script>
                // Initialize CodeMirror
                const editor = CodeMirror.fromTextArea(document.getElementById('pageContent'), {
                    lineNumbers: true,
                    mode: 'application/x-httpd-php',
                    theme: 'eclipse',
                    lineWrapping: true,
                    indentUnit: 4,
                    extraKeys: {
                        "Tab": function(cm) {
                            cm.replaceSelection("    ", "end");
                        }
                    }
                });
                
                // Set editor height
                editor.setSize(null, '500px');
            </script>
            
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h5>Select a page to edit</h5>
                    <p class="text-muted">Choose a page from the left panel to view and edit its content.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Backup Modal -->
<div class="modal fade" id="backupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Page Backups</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Backup Date</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backupList">
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Load backups when modal is shown
document.getElementById('backupModal').addEventListener('show.bs.modal', function() {
    const tbody = document.getElementById('backupList');
    tbody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </td>
        </tr>
    `;
    
    // In a real app, you would fetch this via AJAX
    // This is a simplified example
    setTimeout(() => {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-4 text-muted">
                    Backup listing would appear here in a production environment.
                    <div class="mt-2">
                        <small>Backups are stored in: <code>/backups/pages/YYYY-MM-DD/</code></small>
                    </div>
                </td>
            </tr>
        `;
    }, 500);
});

// Auto-save draft every 2 minutes
let saveDraftTimeout;
if (typeof editor !== 'undefined') {
    editor.on('change', function() {
        clearTimeout(saveDraftTimeout);
        saveDraftTimeout = setTimeout(function() {
            // In a real app, you would save the draft via AJAX
            console.log('Auto-saving draft...');
        }, 120000); // 2 minutes
    });
}
</script>

<style>
/* Custom styles for the editor */
.CodeMirror {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    height: 500px;
}
</style>