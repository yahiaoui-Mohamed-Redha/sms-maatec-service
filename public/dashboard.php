<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/db.php';
require_login();

$q = trim((string)($_GET['q'] ?? ''));
$status = $_GET['status'] ?? '';
$expiring = (int)($_GET['expiring'] ?? ($_ENV['EXPIRY_WINDOW_DAYS'] ?? 10));
$filters = ['q' => $q, 'status' => $status, 'expiring' => $expiring];
$policies = fetch_policies($pdo, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Insurance SMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-amber-100 min-h-screen p-4">
  <div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold text-red-700">Dashboard</h1>
      <div class="flex items-center gap-2">
        <a href="/upload.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">Import Excel</a>
        <a href="/export.php" class="bg-white border border-red-600 text-red-700 hover:bg-red-50 px-4 py-2 rounded">Export CSV</a>
        <a href="/logout.php" class="text-gray-700 underline">Logout</a>
      </div>
    </div>

    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-white rounded p-3 mb-4">
      <input name="q" value="<?php echo e($q); ?>" placeholder="Search name, number, phone" class="border rounded px-3 py-2" />
      <select name="status" class="border rounded px-3 py-2">
        <option value="">All</option>
        <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
        <option value="notified" <?php echo $status==='notified'?'selected':''; ?>>Notified</option>
      </select>
      <select name="expiring" class="border rounded px-3 py-2">
        <?php foreach ([3,7,10,15,30] as $d): ?>
          <option value="<?php echo $d; ?>" <?php echo ($expiring==$d)?'selected':''; ?>>Expiring in ≤ <?php echo $d; ?> days</option>
        <?php endforeach; ?>
      </select>
      <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">Filter</button>
    </form>

    <div class="overflow-x-auto bg-white rounded shadow">
      <table class="min-w-full">
        <thead class="bg-red-50">
          <tr class="text-left">
            <th class="px-3 py-2">Insurance #</th>
            <th class="px-3 py-2">Customer</th>
            <th class="px-3 py-2">Phone</th>
            <th class="px-3 py-2">Start</th>
            <th class="px-3 py-2">End</th>
            <th class="px-3 py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($policies as $p): ?>
            <tr class="border-t">
              <td class="px-3 py-2 font-medium"><?php echo e($p['insurance_number']); ?></td>
              <td class="px-3 py-2"><?php echo e($p['customer_name']); ?></td>
              <td class="px-3 py-2"><?php echo e($p['phone']); ?></td>
              <td class="px-3 py-2"><?php echo e($p['start_date']); ?></td>
              <td class="px-3 py-2"><?php echo e($p['end_date']); ?></td>
              <td class="px-3 py-2">
                <?php if ((int)$p['notified'] === 1): ?>
                  <span class="text-green-700 bg-green-100 px-2 py-1 rounded text-sm">Notified</span>
                <?php else: ?>
                  <span class="text-amber-800 bg-amber-100 px-2 py-1 rounded text-sm">Pending</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>