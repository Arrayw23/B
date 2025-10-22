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
