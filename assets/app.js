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
