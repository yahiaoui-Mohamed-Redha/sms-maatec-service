<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/excel_import.php';
require_login();

verify_csrf_token();
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['file']['tmp_name'];
        $result = import_policies_from_excel($pdo, $tmp);
    } else {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => ['Upload failed']];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Import - Insurance SMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-amber-100 min-h-screen p-4">
  <div class="max-w-3xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold text-red-700">Import Excel</h1>
      <a href="/dashboard.php" class="text-gray-700 underline">Back</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?php echo e(get_csrf_token()); ?>" />
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Excel File (.xlsx, .xls, .csv)</label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" class="block w-full" required />
      </div>
      <button class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded">Upload & Import</button>
    </form>

    <div class="mt-6 text-sm text-gray-700">
      <p class="font-semibold mb-2">Expected headers (first row):</p>
      <ul class="list-disc ml-5">
        <li>Insurance Number</li>
        <li>Customer Name</li>
        <li>Customer Phone Number</li>
        <li>Start Date</li>
        <li>End Date</li>
      </ul>
    </div>

    <?php if ($result): ?>
      <div class="mt-6">
        <div class="mb-2">Inserted: <span class="font-semibold"><?php echo (int)$result['inserted']; ?></span></div>
        <div class="mb-2">Skipped: <span class="font-semibold"><?php echo (int)$result['skipped']; ?></span></div>
        <?php if (!empty($result['errors'])): ?>
          <div class="mt-2 bg-red-50 border border-red-200 text-red-700 rounded p-3">
            <div class="font-semibold mb-1">Errors:</div>
            <ul class="list-disc ml-5 text-sm">
              <?php foreach ($result['errors'] as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>