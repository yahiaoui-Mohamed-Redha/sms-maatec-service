<?php
// send_sms.php (manual send/test)
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/sms.php';
require_login();

$message = '';
$error = '';
verify_csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number = trim((string)($_POST['number'] ?? ''));
    $text = trim((string)($_POST['message'] ?? ''));
    if ($number === '' || $text === '') {
        $error = 'Phone and message are required';
    } else {
        $res = send_sms_via_infobip($pdo, null, $number, $text);
        if ($res['ok']) {
            $message = 'Message sent successfully!';
        } else {
            $error = 'Error: ' . ($res['error'] ?? 'Unknown');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send SMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-amber-100 min-h-screen p-4">
  <div class="max-w-md mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold text-red-700">Send SMS</h1>
      <a href="/dashboard.php" class="text-gray-700 underline">Back</a>
    </div>

    <?php if ($message): ?><div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded p-3"><?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded p-3"><?php echo e($error); ?></div><?php endif; ?>

    <form method="post" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?php echo e(get_csrf_token()); ?>" />
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
        <input type="text" id="number" name="number" placeholder="e.g., 447415... (with country code)" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
        <textarea id="message" name="message" rows="4" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
      </div>
      <div class="flex items-center justify-end">
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded">Send Message</button>
      </div>
    </form>
  </div>
</body>
</html>