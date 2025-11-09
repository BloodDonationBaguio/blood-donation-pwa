<?php
// Simple test page to preview donor details modal rendering from the PWA endpoint
// Finds the latest donor with simple screening and loads their details inline

require_once __DIR__ . '/../db.php';

$defaultDonorId = null;
try {
    $stmt = $pdo->query("SELECT d.id
                         FROM donors d
                         JOIN donor_medical_screening_simple ms ON d.id = ms.donor_id
                         ORDER BY ms.created_at DESC
                         LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['id'])) {
        $defaultDonorId = (int)$row['id'];
    }
} catch (Exception $e) {
    // Ignore errors and just require manual ID entry
}

$initialId = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : $defaultDonorId;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Donor Modal Test (PWA)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-4">
    <h1 class="h4 mb-3">Donor Details Modal — Inline Screening Test</h1>
    <p class="text-muted">This page fetches <code>simple_ajax_donor_details.php</code> from the PWA directory and renders the inline medical screening view.</p>

    <form class="row g-3 align-items-center mb-4" onsubmit="return false;">
      <div class="col-auto">
        <label for="donorId" class="col-form-label">Donor ID</label>
      </div>
      <div class="col-auto">
        <input type="number" class="form-control" id="donorId" placeholder="Enter donor ID" value="<?php echo $initialId ? htmlspecialchars((string)$initialId, ENT_QUOTES, 'UTF-8') : '';?>">
      </div>
      <div class="col-auto">
        <button id="loadBtn" class="btn btn-primary"><i class="fas fa-eye me-1"></i>Load Details</button>
      </div>
      <?php if (!$initialId): ?>
      <div class="col-12">
        <div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>No recent donor with simple screening found. Please enter a Donor ID.</div>
      </div>
      <?php endif; ?>
    </form>

    <div id="result" class="bg-white border rounded p-3">
      <div class="text-muted">Result will appear here.</div>
    </div>
  </div>

  <script>
    const initialId = <?php echo $initialId ? json_encode($initialId, JSON_UNESCAPED_SLASHES) : 'null'; ?>;

    function loadDonor(id) {
      const result = document.getElementById('result');
      if (!id || isNaN(parseInt(id))) {
        alert('Please enter a valid Donor ID');
        return;
      }
      result.innerHTML = `
        <div class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>`;

      fetch(`../simple_ajax_donor_details.php?action=get_donor_details&donor_id=${encodeURIComponent(id)}`)
        .then(r => r.text())
        .then(html => {
          result.innerHTML = html;
        })
        .catch(err => {
          console.error('Error:', err);
          result.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Fetch error: ${String(err)}</div>`;
        });
    }

    document.getElementById('loadBtn').addEventListener('click', (e) => {
      e.preventDefault();
      const id = document.getElementById('donorId').value;
      loadDonor(id);
    });

    document.addEventListener('DOMContentLoaded', () => {
      if (initialId) {
        loadDonor(initialId);
      }
    });
  </script>
</body>
</html>