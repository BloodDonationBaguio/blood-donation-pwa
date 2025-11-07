<?php
// Form Input Validator Test: validates typical donor fields against DB constraints without writing
header('Content-Type: text/html; charset=utf-8');

// Include DB to fetch column limits
$pdo = null; $driver = 'unknown';
$paths = [__DIR__ . '/../../blood-donation-pwa/db.php', __DIR__ . '/../../db.php'];
foreach ($paths as $p) { if (file_exists($p)) { require_once $p; break; } }
if (isset($pdo) && ($pdo instanceof PDO)) { $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); }

function getMax(PDO $pdo, string $driver, string $table, string $column): ?int {
    try {
        if ($driver === 'pgsql' || $driver === 'mysql') {
            $stmt = $pdo->prepare('SELECT character_maximum_length FROM information_schema.columns WHERE table_name = :t AND column_name = :c');
            $stmt->execute([':t' => $table, ':c' => $column]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && $row['character_maximum_length'] ? (int)$row['character_maximum_length'] : null;
        } elseif ($driver === 'sqlite') {
            $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
            $stmt->execute();
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($r['name'] === $column) {
                    if (preg_match('/varchar\s*\((\d+)\)/i', $r['type'], $m)) return (int)$m[1];
                    return null;
                }
            }
        }
    } catch (Throwable $t) { return null; }
    return null;
}

$limits = [];
if ($pdo instanceof PDO) {
    foreach ([
        ['donors','first_name'], ['donors','last_name'], ['donors','email'], ['donors','phone'],
        ['donors','blood_type'], ['donors','address'], ['donors','city'], ['donors','province'],
    ] as $pair) {
        $limits[$pair[1]] = getMax($pdo, $driver, $pair[0], $pair[1]);
    }
}

// Simple validation helpers
function vlen($s) { return mb_strlen($s ?? '', 'UTF-8'); }
function within($s, $max) { return $max === null ? true : vlen($s) <= $max; }
function out($label, $value, $max) {
    $ok = within($value, $max);
    $cls = $ok ? 'ok' : 'err';
    $msg = $ok ? 'OK' : 'Too long';
    $len = vlen($value);
    $m = $max === null ? 'unlimited' : (string)$max;
    echo "<tr><td><strong>" . htmlspecialchars($label) . "</strong></td><td>" . htmlspecialchars($value) . "</td><td>" . $len . "</td><td>" . $m . "</td><td class='$cls'>" . $msg . "</td></tr>";
}

$input = [
    'first_name' => $_POST['first_name'] ?? 'Suzan',
    'last_name'  => $_POST['last_name']  ?? 'Salim',
    'email'      => $_POST['email']      ?? 'noreply@example.com',
    'phone'      => $_POST['phone']      ?? '09954632567',
    'blood_type' => $_POST['blood_type'] ?? 'A+',
    'address'    => $_POST['address']    ?? 'Loaken',
    'city'       => $_POST['city']       ?? 'City of Baguio',
    'province'   => $_POST['province']   ?? 'Benguet',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Form Input Validator Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; }
        table { border-collapse: collapse; border: 1px solid #ddd; min-width: 700px; }
        th, td { padding: 6px 10px; border-top: 1px solid #eee; }
        th { background: #fafafa; text-align: left; }
        .ok { color: #389e0d; }
        .err { color: #cf1322; font-weight: bold; }
        .form { margin-bottom: 18px; }
        input[type=text] { width: 360px; padding: 6px; }
        .submit { padding: 6px 10px; }
    </style>
</head>
<body>
    <h1>Form Input Validator Test</h1>
    <p>Driver: <strong><?php echo htmlspecialchars($driver); ?></strong>. Limits read from donors table when available.</p>

    <form class="form" method="post">
        <div><label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($input['first_name']); ?>"></label></div>
        <div><label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($input['last_name']); ?>"></label></div>
        <div><label>Email: <input type="text" name="email" value="<?php echo htmlspecialchars($input['email']); ?>"></label></div>
        <div><label>Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($input['phone']); ?>"></label></div>
        <div><label>Blood Type: <input type="text" name="blood_type" value="<?php echo htmlspecialchars($input['blood_type']); ?>"></label></div>
        <div><label>Address: <input type="text" name="address" value="<?php echo htmlspecialchars($input['address']); ?>"></label></div>
        <div><label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($input['city']); ?>"></label></div>
        <div><label>Province: <input type="text" name="province" value="<?php echo htmlspecialchars($input['province']); ?>"></label></div>
        <div><button class="submit" type="submit">Validate</button></div>
    </form>

    <table>
        <tr><th>Field</th><th>Value</th><th>Length</th><th>Max Allowed</th><th>Status</th></tr>
        <?php
            out('first_name', $input['first_name'], $limits['first_name'] ?? null);
            out('last_name',  $input['last_name'],  $limits['last_name'] ?? null);
            out('email',      $input['email'],      $limits['email'] ?? null);
            out('phone',      $input['phone'],      $limits['phone'] ?? null);
            out('blood_type', $input['blood_type'], $limits['blood_type'] ?? 10); // default 10
            out('address',    $input['address'],    $limits['address'] ?? null);
            out('city',       $input['city'],       $limits['city'] ?? null);
            out('province',   $input['province'],   $limits['province'] ?? null);
        ?>
    </table>

    <p><a href="index.php">Back to Diagnostics</a></p>
</body>
</html>