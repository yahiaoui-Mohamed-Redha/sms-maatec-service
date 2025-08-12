<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/auth.php';

$error = '';
verify_csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string)($_POST['agency_code'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($code === '' || $password === '') {
        $error = 'Please enter agency code and password';
    } else {
        if (attempt_login($pdo, $code, $password)) {
            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Insurance SMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-amber-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md bg-white rounded-xl shadow-md p-8">
    <h1 class="text-2xl font-bold text-red-700 text-center mb-6">Insurance SMS Portal</h1>
    <?php if ($error): ?>
      <div class="mb-4 text-red-700 bg-red-100 border border-red-300 rounded p-3"><?php echo e($error); ?></div>
    <?php endif; ?>
    <form method="post" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?php echo e(get_csrf_token()); ?>" />
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Agency Code</label>
        <input name="agency_code" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-400" required />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" name="password" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-400" required />
      </div>
      <button class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold rounded px-4 py-2">Sign in</button>
    </form>
  </div>
</body>
</html>