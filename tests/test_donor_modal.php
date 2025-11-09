<?php
// PWA test page to verify inline donor details rendering.
// Fetches donor details from /blood-donation-pwa/simple_ajax_donor_details.php

$prefillDonorId = '';
try {
    $dbConnectPath = __DIR__ . '/../includes/db_connect.php';
    if (file_exists($dbConnectPath)) {
        require_once $dbConnectPath;
        if (function_exists('getDBConnection')) {
            $conn = getDBConnection();
            if ($conn) {
                $sql = "SELECT id FROM donors WHERE screening_type = 'simple' ORDER BY id DESC LIMIT 1";
                $res = $conn->query($sql);
                if ($res && $row = $res->fetch_assoc()) {
                    $prefillDonorId = $row['id'];
                }
            }
        }
    }
} catch (Throwable $e) {
    // Suppress DB errors on test page; manual entry will still work.
}

if (isset($_GET['donor_id']) && preg_match('/^\d+$/', $_GET['donor_id'])) {
    $prefillDonorId = $_GET['donor_id'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PWA Test Donor Modal Inline Rendering</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .page-wrap { max-width: 1000px; margin: 40px auto; }
    .header { margin-bottom: 20px; }
    .result-card { min-height: 200px; }
    code.small { font-size: .875rem; }
  </style>
  </head>
<body>
  <div class="page-wrap">
    <div class="header d-flex align-items-center justify-content-between">
      <h1 class="h4 mb-0"><i class="fa-solid fa-vial me-2"></i>Inline Donor Details Rendering (PWA Test)</h1>
      <a class="btn btn-outline-secondary btn-sm" href="/tests/test_donor_modal.php" target="_blank">Open root test page</a>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <form id="donorForm" class="row g-3">
          <div class="col-auto">
            <label for="donorId" class="col-form-label">Donor ID</label>
          </div>
          <div class="col-auto">
            <input type="text" id="donorId" name="donorId" class="form-control" placeholder="e.g. 123" value="<?php echo htmlspecialchars($prefillDonorId); ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-1"></i>Load Donor Details</button>
          </div>
          <div class="col-12">
            <p class="text-muted mb-0">This test fetches from <code class="small">/blood-donation-pwa/simple_ajax_donor_details.php</code> and should render the inline status banner, summary badges, and the full medical Q&amp;A accordion.</p>
          </div>
        </form>
      </div>
    </div>

    <div id="result" class="card result-card">
      <div class="card-body">
        <div class="text-muted">Submit a donor ID to load details.</div>
      </div>
    </div>
  </div>

  <script>
  const form = document.getElementById('donorForm');
  const donorInput = document.getElementById('donorId');
  const result = document.getElementById('result');

  async function fetchDetails(donorId) {
    const url = `/blood-donation-pwa/simple_ajax_donor_details.php?donor_id=${encodeURIComponent(donorId)}&id=${encodeURIComponent(donorId)}`;
    try {
      const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const html = await resp.text();
      result.innerHTML = `<div class="card-body">${html}</div>`;
    } catch (err) {
      result.innerHTML = `<div class="card-body"><div class="alert alert-danger">Failed to load: ${err?.message || err}</div></div>`;
    }
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const id = donorInput.value.trim();
    if (!id) {
      result.innerHTML = `<div class="card-body"><div class="alert alert-warning">Please enter a donor ID.</div></div>`;
      return;
    }
    fetchDetails(id);
  });

  (function() {
    const v = donorInput.value.trim();
    if (v) { fetchDetails(v); }
  })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php // End of file ?>