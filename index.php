<?php
/**
 * MySQL Database Manager — Login
 *
 * API Documentation (used by server-side proxy only)
 * Base URL: https://aos.madoosi.com/api/db/api/api.php
 * Auth Header: X-Api-Key: <your_api_key_here>
 *
 * Endpoints:
 * - GET    /api.php?table=<table_name>                 → Fetch rows
 *   Response: { "status":"success", "table":"users", "rows":[ { "id":1, "username":"admin" } ] }
 * - POST   /api.php?table=<table_name>                 → Insert row
 *   Body: { "username":"newuser", "email":"test@example.com" }
 * - PUT    /api.php?table=<table_name>                 → Update row
 *   Body: { "id":15, "email":"updated@example.com" }
 * - DELETE /api.php?table=<table_name>                 → Remove row
 *   Body: { "id":15 }
 * - POST   /api.php?action=generatekey                 → Create new API user and key
 *   Body: { "username":"newclient" }
 *
 * Notes:
 * - This application never exposes the API key to the client. All requests go through /api/handler.php.
 * - Fixed username/password are verified here; the API key is stored in the PHP session after login.
 */

// ------------------------------
// Session bootstrap
// ------------------------------
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// If already authenticated, go to dashboard
if (!empty($_SESSION['auth']) && !empty($_SESSION['api_key'])) {
    header('Location: /dashboard.php');
    exit;
}

// ------------------------------
// Fixed credentials
// ------------------------------
const AUTH_USERNAME = 'admin';
const AUTH_PASSWORD = 'admin123';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)($_POST['password']) : '';
    $apiKey   = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';

    if ($username !== AUTH_USERNAME || $password !== AUTH_PASSWORD) {
        $errors[] = 'Invalid username or password.';
    }
    if ($apiKey === '') {
        $errors[] = 'API key is required.';
    }

    if (empty($errors)) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['api_key'] = $apiKey;
        $_SESSION['username'] = $username;
        header('Location: /dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en" data-theme="dark">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Database Manager — Login</title>

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      // Tailwind config to prefer dark mode by default
      window.tailwind = window.tailwind || {};
    </script>

    <!-- FlyonUI (CDN) - styles for components like alerts/toggles/modals -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flyonui/dist/flyonui.min.css" />

    <!-- Heroicons: we embed inline SVGs where needed -->
  </head>
  <body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="min-h-screen flex flex-col">
      <!-- Top bar -->
      <header class="border-b border-slate-800 bg-slate-900/70 backdrop-blur">
        <div class="mx-auto max-w-3xl px-4 py-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded bg-blue-600 text-white font-bold">DB</span>
            <h1 class="text-lg font-semibold tracking-tight">Firebase‑style Database Manager</h1>
          </div>
        </div>
      </header>

      <!-- Main -->
      <main class="flex-1 flex items-center justify-center px-4">
        <div class="w-full max-w-md">
          <div class="rounded-xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
            <h2 class="text-xl font-semibold mb-1">Sign in</h2>
            <p class="text-sm text-slate-400 mb-6">Enter your credentials and API key to continue.</p>

            <?php if (!empty($errors)): ?>
              <div class="mb-4 rounded-lg border border-red-600/40 bg-red-900/20 p-3 text-sm text-red-200">
                <?php foreach ($errors as $e): ?>
                  <div>• <?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="post" action="/index.php" class="space-y-4">
              <div>
                <label for="username" class="block text-sm mb-1">Username</label>
                <input id="username" name="username" type="text" required autocomplete="username"
                       class="w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 placeholder-slate-400 focus:border-blue-500 focus:outline-none" />
              </div>
              <div>
                <label for="password" class="block text-sm mb-1">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 placeholder-slate-400 focus:border-blue-500 focus:outline-none" />
              </div>
              <div>
                <label for="api_key" class="block text-sm mb-1">API Key</label>
                <input id="api_key" name="api_key" type="password" required placeholder="Paste your API key"
                       class="w-full rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 placeholder-slate-400 focus:border-blue-500 focus:outline-none" />
              </div>
              <button type="submit" class="w-full inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-500 focus:outline-none">Login</button>

              <p class="text-xs text-slate-500 mt-3">Hint for demo: <span class="font-mono">admin / admin123</span>.</p>
            </form>
          </div>
          <p class="mt-6 text-center text-xs text-slate-500">This app stores your API key securely in the server session and never exposes it to the browser.</p>
        </div>
      </main>

      <footer class="py-6 text-center text-xs text-slate-500">
        Built with Tailwind + FlyonUI. Firebase‑style dark UI.
      </footer>
    </div>
  </body>
</html>
