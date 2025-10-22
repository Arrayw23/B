<?php
/**
 * Installer — Firebase‑style MySQL Database Manager
 *
 * This script will create the following structure in the current directory:
 *   /index.php
 *   /dashboard.php
 *   /api/handler.php
 *   /assets/app.js
 *
 * Usage:
 * - Upload this single file to your PHP host and open it in a browser.
 * - Click the install button (or just load the page). Existing files are not overwritten
 *   unless you add ?overwrite=1 to the URL.
 *
 * Security:
 * - This installer only writes files relative to its own directory (using __DIR__).
 * - Review the generated files if desired, then delete this installer once done.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
$baseDir = __DIR__;
$overwrite = isset($_GET['overwrite']) && $_GET['overwrite'] === '1';

function ensure_dir(string $path): void {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

$files = [];

$files['index.php'] = <<<'IDXPHP'
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
IDXPHP;

$files['dashboard.php'] = <<<'DASHPHP'
<?php
/**
 * MySQL Database Manager — Dashboard
 *
 * API Documentation (proxied via /api/handler.php)
 * Base URL: https://aos.madoosi.com/api/db/api/api.php
 * Auth Header: X-Api-Key: <session_api_key>
 *
 * Endpoints:
 * - GET    /api.php?table=<table_name>                 → Fetch rows
 * - POST   /api.php?table=<table_name>                 → Insert row
 * - PUT    /api.php?table=<table_name>                 → Update row
 * - DELETE /api.php?table=<table_name>                 → Remove row
 * - POST   /api.php?action=generatekey                 → Create new API user and key
 *
 * Notes:
 * - This page requires login. The API key is stored ONLY server-side in the PHP session.
 * - All client requests go to /api/handler.php; the key is never exposed.
 */

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (isset($_GET['logout'])) {
    // Graceful logout
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /index.php');
    exit;
}

if (empty($_SESSION['auth']) || empty($_SESSION['api_key'])) {
    header('Location: /index.php');
    exit;
}

$username = isset($_SESSION['username']) ? (string)$_SESSION['username'] : 'user';
?>
<!doctype html>
<html lang="en" data-theme="dark">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Database Manager — Dashboard</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- FlyonUI CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flyonui/dist/flyonui.min.css" />

    <style>
      .sidebar { width: 260px; }
      .sidebar-collapsed { width: 0; }
      .content { min-height: calc(100vh - 56px); }
      .modal-backdrop { background: rgba(2,6,23,0.75); }
    </style>
  </head>
  <body class="bg-slate-950 text-slate-100">
    <div id="app" class="min-h-screen">
      <!-- Top bar -->
      <header class="border-b border-slate-800 bg-slate-900/70 backdrop-blur sticky top-0 z-40">
        <div class="px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button id="btnSidebarToggle" class="sm:hidden inline-flex items-center justify-center rounded-md border border-slate-700 bg-slate-800 p-2 hover:bg-slate-700">
              <!-- Heroicon: bars-3 -->
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            <span class="inline-flex h-8 w-8 items-center justify-center rounded bg-blue-600 text-white font-bold">DB</span>
            <span class="font-semibold">Database Manager</span>
          </div>

          <div class="flex items-center gap-3">
            <!-- Dark mode toggle -->
            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
              <span class="text-xs text-slate-400">Dark</span>
              <input id="toggleTheme" type="checkbox" class="toggle toggle-primary" checked />
            </label>

            <!-- User menu -->
            <div class="dropdown dropdown-end">
              <label tabindex="0" class="btn btn-ghost btn-sm text-slate-200">
                <span class="hidden sm:inline"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                <!-- Heroicon: chevron-down -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 ml-1">
                  <path fill-rule="evenodd" d="M12 14.25a.75.75 0 0 1-.53-.22l-4.5-4.5a.75.75 0 1 1 1.06-1.06L12 12.44l3.97-3.97a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-.53.22z" clip-rule="evenodd" />
                </svg>
              </label>
              <ul tabindex="0" class="menu dropdown-content bg-slate-900 rounded-box z-[1] mt-2 w-52 p-2 shadow border border-slate-800">
                <li><a href="/dashboard.php?logout=1">Logout</a></li>
              </ul>
            </div>
          </div>
        </div>
      </header>

      <div class="flex">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar hidden sm:block border-r border-slate-800 bg-slate-900/40">
          <nav class="p-4 space-y-2">
            <div class="text-xs uppercase tracking-wide text-slate-500 px-2">Navigation</div>
            <a href="#" id="navDataExplorer" class="block rounded-md px-3 py-2 hover:bg-slate-800 active:bg-slate-800">Data Explorer</a>
            <a href="#" id="navGenerateKey" class="block rounded-md px-3 py-2 hover:bg-slate-800">Generate API Key</a>
          </nav>
        </aside>

        <!-- Main content -->
        <main class="content flex-1 p-4 sm:p-6">
          <!-- Alerts / Toast container -->
          <div id="toastContainer" class="fixed top-16 right-4 z-50 space-y-2"></div>

          <!-- Data Explorer panel -->
          <section id="panelData" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
              <div class="flex-1">
                <label class="block text-sm text-slate-400 mb-1">Table Name</label>
                <input id="tableInput" type="text" placeholder="e.g. users" class="w-full sm:max-w-xs rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 placeholder-slate-400 focus:border-blue-500 focus:outline-none" />
              </div>
              <div class="flex items-center gap-2">
                <button id="btnFetch" class="btn btn-primary btn-sm">Fetch</button>
                <button id="btnRefresh" class="btn btn-ghost btn-sm">Refresh
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 ml-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992m0 0V4.355m0 4.993-3.181-3.181a8.25 8.25 0 1 0 2.914 8.068" />
                  </svg>
                </button>
                <button id="btnAddRow" class="btn btn-success btn-sm">Add Row
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 ml-1">
                    <path d="M12 4.5a.75.75 0 0 1 .75.75V11h5.75a.75.75 0 0 1 0 1.5H12.75v5.75a.75.75 0 0 1-1.5 0V12.5H5.5a.75.75 0 0 1 0-1.5h5.75V5.25A.75.75 0 0 1 12 4.5z" />
                  </svg>
                </button>
              </div>
            </div>

            <div id="stats" class="text-xs text-slate-400"></div>

            <div id="dataTableContainer" class="overflow-auto border border-slate-800 rounded-lg"></div>
          </section>

          <!-- Generate Key panel (modal-like card) -->
          <section id="panelGenerateKey" class="hidden">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
              <h2 class="text-lg font-semibold mb-2">Generate API Key</h2>
              <p class="text-sm text-slate-400 mb-4">Create a new API user and key. This will not affect your current session key.</p>
              <div class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1">
                  <label class="block text-sm text-slate-400 mb-1">New Username</label>
                  <input id="genUsername" type="text" placeholder="newclient" class="w-full sm:max-w-xs rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 placeholder-slate-400 focus:border-blue-500 focus:outline-none" />
                </div>
                <button id="btnGenerateKey" class="btn btn-primary btn-sm">Generate</button>
              </div>
              <div id="genResult" class="mt-4 text-sm"></div>
            </div>
          </section>
        </main>
      </div>

      <!-- Row Editor Modal -->
      <div id="rowModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 modal-backdrop"></div>
        <div class="relative w-full max-w-2xl mx-4 rounded-xl border border-slate-800 bg-slate-900 p-0 shadow-2xl">
          <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
            <h3 id="rowModalTitle" class="font-semibold">Add Row</h3>
            <button id="rowModalClose" class="btn btn-ghost btn-xs">Close</button>
          </div>
          <div class="p-4 space-y-3">
            <label class="block text-sm text-slate-400">Row JSON</label>
            <textarea id="rowJson" rows="10" class="w-full rounded-md border border-slate-700 bg-slate-800 p-3 font-mono text-xs text-slate-200 placeholder-slate-400 focus:border-blue-500 focus:outline-none" placeholder='{"username":"alice"}'></textarea>
            <p class="text-xs text-slate-500">Provide a JSON object. For updates, include the primary key field (e.g. <code>id</code>).</p>
          </div>
          <div class="px-4 py-3 border-t border-slate-800 flex items-center justify-end gap-2">
            <button id="rowModalCancel" class="btn btn-ghost btn-sm">Cancel</button>
            <button id="rowModalSave" class="btn btn-primary btn-sm">Save</button>
          </div>
        </div>
      </div>

      <!-- Delete Confirm Modal -->
      <div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 modal-backdrop"></div>
        <div class="relative w-full max-w-md mx-4 rounded-xl border border-slate-800 bg-slate-900 p-0 shadow-2xl">
          <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
            <h3 class="font-semibold">Delete Row</h3>
            <button id="deleteModalClose" class="btn btn-ghost btn-xs">Close</button>
          </div>
          <div class="p-4">
            <p class="text-sm text-slate-300">Are you sure you want to delete this row?</p>
            <pre id="deletePreview" class="mt-3 max-h-40 overflow-auto rounded bg-slate-800 p-3 text-xs text-slate-300"></pre>
          </div>
          <div class="px-4 py-3 border-t border-slate-800 flex items-center justify-end gap-2">
            <button id="deleteModalCancel" class="btn btn-ghost btn-sm">Cancel</button>
            <button id="deleteModalConfirm" class="btn btn-error btn-sm">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <script src="/assets/app.js"></script>
  </body>
</html>
DASHPHP;

$files['api/handler.php'] = <<<'HANDLERPHP'
<?php
/**
 * MySQL Database Manager — Backend Proxy Handler
 *
 * API Documentation (proxied here, never exposed to client)
 * Base URL: https://aos.madoosi.com/api/db/api/api.php
 * Auth Header: X-Api-Key: <session_api_key>
 *
 * Endpoints:
 * - GET    /api.php?table=<table_name>                 → Fetch rows
 * - POST   /api.php?table=<table_name>                 → Insert row
 * - PUT    /api.php?table=<table_name>                 → Update row
 * - DELETE /api.php?table=<table_name>                 → Remove row
 * - POST   /api.php?action=generatekey                 → Create new API user and key
 *
 * Notes:
 * - This file validates session authentication and forwards requests to the upstream API with the stored API key.
 * - The client must NEVER see the API key; it stays server-side within this handler.
 */

// ------------------------------
// Session and guards
// ------------------------------
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['auth']) || empty($_SESSION['api_key'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Allow only specific actions
$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
if (!in_array($method, $allowedMethods, true)) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Read JSON body if present
$rawBody = file_get_contents('php://input');
$body = [];
if (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $body = $decoded;
    }
}

// Simple router via query params: action or table
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$table  = isset($_GET['table']) ? trim((string)$_GET['table']) : '';

$baseUrl = 'https://aos.madoosi.com/api/db/api/api.php';

// Build upstream URL
$upstreamUrl = $baseUrl;
$query = [];
if ($action !== '') {
    $query['action'] = $action;
}
if ($table !== '') {
    $query['table'] = $table;
}
if (!empty($query)) {
    $upstreamUrl .= '?' . http_build_query($query);
}

// Prepare cURL
$ch = curl_init($upstreamUrl);
$headers = [
    'Content-Type: application/json',
    'X-Api-Key: ' . $_SESSION['api_key'],
];

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
}

$responseBody = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'Upstream connection failed',
        'detail' => $error,
    ]);
    exit;
}

// Forward status code and body (ensure valid JSON)
if ($httpCode >= 400) {
    http_response_code($httpCode);
}

$decodedResp = json_decode($responseBody, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo json_encode($decodedResp);
} else {
    // Non-JSON response; wrap it
    echo json_encode([
        'status' => ($httpCode >= 400 ? 'error' : 'success'),
        'raw' => $responseBody,
    ]);
}
HANDLERPHP;

$files['assets/app.js'] = <<<'APPJS'
/*
MySQL Database Manager — Frontend JS

Notes:
- All network calls go to /api/handler.php to keep the API key hidden in the session.
- Uses Fetch API and minimal vanilla JS.
- Provides toast/alert helpers, panel switching, and CRUD actions.

API Summary (server proxies these):
GET    /api/handler.php?table=<table>
POST   /api/handler.php?table=<table>            body: {...}
PUT    /api/handler.php?table=<table>            body: {...}
DELETE /api/handler.php?table=<table>            body: { id: 123 }
POST   /api/handler.php?action=generatekey       body: { username: "newclient" }
*/

(function () {
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  // Elements
  const tableInput = $('#tableInput');
  const btnFetch = $('#btnFetch');
  const btnRefresh = $('#btnRefresh');
  const btnAddRow = $('#btnAddRow');
  const stats = $('#stats');
  const dataTableContainer = $('#dataTableContainer');

  const rowModal = $('#rowModal');
  const rowModalTitle = $('#rowModalTitle');
  const rowJson = $('#rowJson');
  const rowModalSave = $('#rowModalSave');
  const rowModalCancel = $('#rowModalCancel');
  const rowModalClose = $('#rowModalClose');

  const deleteModal = $('#deleteModal');
  const deletePreview = $('#deletePreview');
  const deleteModalConfirm = $('#deleteModalConfirm');
  const deleteModalCancel = $('#deleteModalCancel');
  const deleteModalClose = $('#deleteModalClose');

  const toastContainer = $('#toastContainer');

  const navDataExplorer = $('#navDataExplorer');
  const navGenerateKey = $('#navGenerateKey');
  const panelData = $('#panelData');
  const panelGenerateKey = $('#panelGenerateKey');
  const genUsername = $('#genUsername');
  const btnGenerateKey = $('#btnGenerateKey');

  const btnSidebarToggle = $('#btnSidebarToggle');
  const sidebar = $('#sidebar');
  const toggleTheme = $('#toggleTheme');

  let currentTable = '';
  let currentRows = [];
  let editingMode = 'add'; // or 'edit'
  let editingRow = null;
  let deleteRow = null;

  // ------------------------------
  // UI helpers
  // ------------------------------
  function showToast({ title = 'Notice', message = '', type = 'info', timeout = 3000 }) {
    const colors = {
      info: 'border-sky-700 bg-sky-900/30 text-sky-100',
      success: 'border-emerald-700 bg-emerald-900/30 text-emerald-100',
      error: 'border-rose-700 bg-rose-900/30 text-rose-100',
      warning: 'border-amber-700 bg-amber-900/30 text-amber-100',
    };

    const el = document.createElement('div');
    el.className = `rounded-md border p-3 shadow ${colors[type] || colors.info}`;
    el.innerHTML = `<div class="font-medium">${escapeHtml(title)}</div><div class="text-sm opacity-90">${escapeHtml(message)}</div>`;
    toastContainer.appendChild(el);
    setTimeout(() => el.remove(), timeout);
  }

  function escapeHtml(str) {
    if (typeof str !== 'string') return String(str);
    return str.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function showPanel(panel) {
    if (panel === 'data') {
      panelData.classList.remove('hidden');
      panelGenerateKey.classList.add('hidden');
    } else {
      panelData.classList.add('hidden');
      panelGenerateKey.classList.remove('hidden');
    }
  }

  function openRowModal(mode, row) {
    editingMode = mode;
    editingRow = row || null;
    rowModalTitle.textContent = mode === 'add' ? 'Add Row' : 'Edit Row';
    rowJson.value = mode === 'add' ? '' : JSON.stringify(row, null, 2);
    rowModal.classList.remove('hidden');
  }

  function closeRowModal() {
    rowModal.classList.add('hidden');
    rowJson.value = '';
  }

  function openDeleteModal(row) {
    deleteRow = row;
    deletePreview.textContent = JSON.stringify(row, null, 2);
    deleteModal.classList.remove('hidden');
  }

  function closeDeleteModal() {
    deleteRow = null;
    deleteModal.classList.add('hidden');
    deletePreview.textContent = '';
  }

  function buildTable(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
      dataTableContainer.innerHTML = `<div class="p-6 text-sm text-slate-400">No data to display.</div>`;
      return;
    }

    const cols = Array.from(
      rows.reduce((set, row) => {
        Object.keys(row).forEach((k) => set.add(k));
        return set;
      }, new Set())
    );

    const thead = `<thead class="bg-slate-900/60 sticky top-0">
      <tr>
        ${cols.map((c) => `<th class="px-3 py-2 text-left text-xs font-semibold text-slate-300 border-b border-slate-800">${escapeHtml(c)}</th>`).join('')}
        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-300 border-b border-slate-800">Actions</th>
      </tr>
    </thead>`;

    const tbody = `<tbody>
      ${rows
        .map((row) => {
          return `<tr class="hover:bg-slate-900/40">
            ${cols
              .map((c) => `<td class="px-3 py-2 text-sm border-b border-slate-800">${escapeHtml(row[c] === undefined ? '' : String(row[c]))}</td>`)
              .join('')}
            <td class="px-3 py-2 text-sm border-b border-slate-800 text-right">
              <button class="btn btn-ghost btn-xs mr-1" data-action="edit" data-row='${escapeHtml(
                JSON.stringify(row)
              )}'>
                Edit
              </button>
              <button class="btn btn-error btn-xs" data-action="delete" data-row='${escapeHtml(
                JSON.stringify(row)
              )}'>
                Delete
              </button>
            </td>
          </tr>`;
        })
        .join('')}
    </tbody>`;

    dataTableContainer.innerHTML = `<table class="min-w-full text-slate-200">${thead}${tbody}</table>`;

    // Bind row actions
    $$('#dataTableContainer [data-action="edit"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const row = JSON.parse(btn.getAttribute('data-row'));
        openRowModal('edit', row);
      });
    });
    $$('#dataTableContainer [data-action="delete"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const row = JSON.parse(btn.getAttribute('data-row'));
        openDeleteModal(row);
      });
    });
  }

  // ------------------------------
  // API helpers
  // ------------------------------
  async function apiRequest(path, { method = 'GET', body } = {}) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body !== undefined) opts.body = JSON.stringify(body);
    const res = await fetch(path, opts);
    let json = null;
    try { json = await res.json(); } catch (e) {}
    if (!res.ok) {
      const message = (json && (json.message || json.error)) || `HTTP ${res.status}`;
      throw new Error(message);
    }
    return json;
  }

  async function fetchTable(table) {
    currentTable = table;
    stats.textContent = 'Loading…';
    try {
      const data = await apiRequest(`/api/handler.php?table=${encodeURIComponent(table)}`);
      currentRows = Array.isArray(data.rows) ? data.rows : [];
      buildTable(currentRows);
      stats.textContent = `${currentRows.length} row(s)`;
      showToast({ title: 'Fetched', message: `Table ${table} loaded.`, type: 'success' });
    } catch (e) {
      stats.textContent = '';
      showToast({ title: 'Fetch failed', message: e.message, type: 'error' });
    }
  }

  async function createRow(table, obj) {
    const data = await apiRequest(`/api/handler.php?table=${encodeURIComponent(table)}`, { method: 'POST', body: obj });
    return data;
  }

  async function updateRow(table, obj) {
    const data = await apiRequest(`/api/handler.php?table=${encodeURIComponent(table)}`, { method: 'PUT', body: obj });
    return data;
  }

  async function deleteRowApi(table, obj) {
    const data = await apiRequest(`/api/handler.php?table=${encodeURIComponent(table)}`, { method: 'DELETE', body: obj });
    return data;
  }

  async function generateKey(username) {
    const data = await apiRequest(`/api/handler.php?action=generatekey`, { method: 'POST', body: { username } });
    return data;
  }

  // ------------------------------
  // Event bindings
  // ------------------------------
  btnFetch && btnFetch.addEventListener('click', () => {
    const t = (tableInput.value || '').trim();
    if (!t) {
      showToast({ title: 'Table required', message: 'Enter a table name', type: 'warning' });
      return;
    }
    fetchTable(t);
  });

  btnRefresh && btnRefresh.addEventListener('click', () => {
    if (!currentTable) return;
    fetchTable(currentTable);
  });

  btnAddRow && btnAddRow.addEventListener('click', () => openRowModal('add'));

  // Row modal
  rowModalCancel && rowModalCancel.addEventListener('click', closeRowModal);
  rowModalClose && rowModalClose.addEventListener('click', closeRowModal);
  rowModalSave && rowModalSave.addEventListener('click', async () => {
    const t = (tableInput.value || '').trim();
    if (!t) return showToast({ title: 'Table required', message: 'Enter a table name', type: 'warning' });

    let obj = null;
    try { obj = JSON.parse(rowJson.value || '{}'); }
    catch (e) {
      return showToast({ title: 'Invalid JSON', message: 'Fix JSON and retry', type: 'error' });
    }

    try {
      if (editingMode === 'add') {
        await createRow(t, obj);
        showToast({ title: 'Row added', message: 'Insert success', type: 'success' });
      } else {
        await updateRow(t, obj);
        showToast({ title: 'Row updated', message: 'Update success', type: 'success' });
      }
      closeRowModal();
      if (currentTable) fetchTable(currentTable);
    } catch (e) {
      showToast({ title: 'Save failed', message: e.message, type: 'error' });
    }
  });

  // Delete modal
  deleteModalCancel && deleteModalCancel.addEventListener('click', closeDeleteModal);
  deleteModalClose && deleteModalClose.addEventListener('click', closeDeleteModal);
  deleteModalConfirm && deleteModalConfirm.addEventListener('click', async () => {
    if (!deleteRow) return;
    const t = currentTable;
    const id = deleteRow.id;
    if (id === undefined) {
      return showToast({ title: 'Missing id', message: 'Row has no id field', type: 'error' });
    }
    try {
      await deleteRowApi(t, { id });
      showToast({ title: 'Row deleted', message: `id=${id}`, type: 'success' });
      closeDeleteModal();
      if (currentTable) fetchTable(currentTable);
    } catch (e) {
      showToast({ title: 'Delete failed', message: e.message, type: 'error' });
    }
  });

  // Sidebar toggle (mobile)
  btnSidebarToggle && btnSidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
  });

  // Theme toggle (default dark on)
  toggleTheme && toggleTheme.addEventListener('change', () => {
    const isDark = toggleTheme.checked;
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    document.documentElement.classList[isDark ? 'add' : 'remove']('dark');
  });

  // Navigation
  navDataExplorer && navDataExplorer.addEventListener('click', (e) => {
    e.preventDefault();
    showPanel('data');
  });
  navGenerateKey && navGenerateKey.addEventListener('click', (e) => {
    e.preventDefault();
    showPanel('key');
  });

  btnGenerateKey && btnGenerateKey.addEventListener('click', async () => {
    const u = (genUsername.value || '').trim();
    if (!u) return showToast({ title: 'Username required', message: 'Provide a username', type: 'warning' });
    try {
      const data = await generateKey(u);
      const pretty = JSON.stringify(data, null, 2);
      $('#genResult').innerHTML = `<pre class="rounded bg-slate-900 border border-slate-800 p-3 text-xs whitespace-pre-wrap">${escapeHtml(pretty)}</pre>`;
      showToast({ title: 'Key created', message: 'See result below', type: 'success' });
    } catch (e) {
      showToast({ title: 'Generation failed', message: e.message, type: 'error' });
    }
  });

  // Auto-load: if table present in hash like #users
  if (location.hash && location.hash.length > 1) {
    const t = decodeURIComponent(location.hash.slice(1));
    tableInput.value = t;
    fetchTable(t);
  }
})();
APPJS;

// Ensure directories
ensure_dir($baseDir . DIRECTORY_SEPARATOR . 'api');
ensure_dir($baseDir . DIRECTORY_SEPARATOR . 'assets');

$results = [];
foreach ($files as $relative => $content) {
    $path = $baseDir . DIRECTORY_SEPARATOR . $relative;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        ensure_dir($dir);
    }

    if (file_exists($path) && !$overwrite) {
        $results[] = [
            'file' => $relative,
            'status' => 'skipped',
            'message' => 'Already exists (use ?overwrite=1 to replace)'
        ];
        continue;
    }

    $ok = (bool)file_put_contents($path, $content);
    $results[] = [
        'file' => $relative,
        'status' => $ok ? 'written' : 'error',
        'message' => $ok ? 'Created/updated' : 'Failed to write file',
    ];
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Installer — Database Manager</title>
    <style>
      :root { color-scheme: dark; }
      body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 0; background: #0b1220; color: #dbe3f4; }
      .wrap { max-width: 900px; margin: 40px auto; padding: 0 16px; }
      .card { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 20px; }
      .row { display: grid; grid-template-columns: 1fr 100px 1fr; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px solid #1e293b; }
      .row:first-child { border-top: 0; }
      .status { font-weight: 600; }
      .ok { color: #34d399; }
      .skip { color: #fbbf24; }
      .err { color: #f87171; }
      a.btn { display: inline-block; background: #2563eb; color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; }
      .muted { color: #93a4bf; font-size: 12px; }
      code { background: #111827; padding: 2px 6px; border-radius: 6px; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <h1>Installer — Firebase‑style Database Manager</h1>
      <p class="muted">Installer location: <code><?= htmlspecialchars($baseDir, ENT_QUOTES, 'UTF-8') ?></code></p>

      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px;">
          <div>
            <div><strong>Result</strong></div>
            <div class="muted">Append <code>?overwrite=1</code> to overwrite existing files.</div>
          </div>
          <div style="display:flex; gap:8px;">
            <a class="btn" href="./installer.php?overwrite=1">Run with overwrite</a>
            <a class="btn" href="./index.php">Open app</a>
          </div>
        </div>
        <div style="margin-top:10px;">
          <?php foreach ($results as $r): $s = $r['status']; ?>
            <div class="row">
              <div><code><?= htmlspecialchars($r['file'], ENT_QUOTES, 'UTF-8') ?></code></div>
              <div class="status <?php echo $s === 'written' ? 'ok' : ($s === 'skipped' ? 'skip' : 'err'); ?>"><?php echo htmlspecialchars(strtoupper($s), ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="muted"><?= htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <p class="muted" style="margin-top:16px;">After installation, you may delete <code>installer.php</code>.</p>
    </div>
  </body>
</html>
