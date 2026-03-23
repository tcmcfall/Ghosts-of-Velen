<?php
// panels/admin-dashboard-panel.php
declare(strict_types=1);
require_once __DIR__ . '/../auth/bootstrap.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';
if (($_SESSION['role'] ?? null) !== 'admin') { http_response_code(403); exit('Admins only.'); }

$csrf_safe = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$appEnv     = htmlspecialchars((string)($_ENV['APP_ENV'] ?? 'prod'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$phpVersion = htmlspecialchars(PHP_VERSION, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$API = [
  'campaigns' => [
    'list'   => '../auth/api/admin/campaigns/list.php',
    'create' => '../auth/api/admin/campaigns/create.php',
    'update' => '../auth/api/admin/campaigns/update.php',
    'delete' => '../auth/api/admin/campaigns/delete.php',
    'add'    => '../auth/api/admin/campaigns/add_user.php',
    'remove' => '../auth/api/admin/campaigns/remove_user.php',
  ],
  'users' => [
    'list'   => '../auth/api/admin/users/list.php',
    'create' => '../auth/api/admin/users/create.php',
    'update' => '../auth/api/admin/users/update.php',
    'delete' => '../auth/api/admin/users/delete.php'
  ],
  // Dispatches API
  'dispatches' => [
    'list'   => '../auth/api/dispatches/list.php',
    'get'    => '../auth/api/dispatches/get.php',
    'create' => '../auth/api/dispatches/create.php',
    'update' => '../auth/api/dispatches/update.php',
    'delete' => '../auth/api/dispatches/delete.php'
  ]
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo $csrf_safe; ?>">
  <link rel="stylesheet" href="../css/global.css" />
  <link rel="stylesheet" href="../css/admin-dashboard-panel.css" />
  <script>
    window.CSRF_TOKEN="<?php echo $csrf_safe; ?>";
    window.API=<?php echo json_encode($API, JSON_UNESCAPED_SLASHES); ?>;
    window.GOV={appEnv:"<?php echo $appEnv; ?>",phpVersion:"<?php echo $phpVersion; ?>"};</script>
  <script src="../js/admin-dashboard-panel.js" defer></script>

  <style>
    /* --- Dispatches styles (clean, contemporary, consistent) --- */
    .hidden { display: none !important; }

    .dispatch-tools {
      position: sticky; top: 8px; z-index: 2;
      display: flex; gap: 8px; align-items: center; margin: 12px 0;
    }

    .create-dispatch {
      position: sticky; top: 8px; z-index: 1;
      border: 1px solid #000;
      background: rgba(255,255,255,0.75);
      box-shadow: 0 0 15px rgba(0,0,0,0.5);
      border-radius: 6px;
      padding: 12px;
      max-width: 900px;
    }
    .create-dispatch h3 { margin: 0 0 8px 0; font-family: 'Jim Nightshade', cursive; }
    .create-dispatch .row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .create-dispatch input[type="text"],
    .create-dispatch input[type="url"] {
      padding: 8px 10px; border: 1px solid #000; border-radius: 4px;
      background: rgba(255,255,255,0.9);
      width: 100%;
    }
    .create-dispatch .thumb-note { opacity: .8; }

    .rich-toolbar { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0; }
    .rich-toolbar button {
      padding: 4px 8px; border: 1px solid #000; background: rgba(255,255,255,0.85);
      cursor: pointer; border-radius: 4px;
    }
    .rich-editor {
      min-height: 240px; border: 1px solid #000; background: rgba(255,255,255,0.9);
      padding: 10px; border-radius: 4px; overflow: auto;
    }

    .dispatch-list { display: grid; gap: 12px; grid-template-columns: 1fr; }
    .dispatch-card {
      display: grid; grid-template-columns: 110px 1fr auto; gap: 12px;
      border: 1px solid #000; background: rgba(255,255,255,0.85); border-radius: 6px;
      padding: 10px 12px; align-items: start;
    }
    .dispatch-thumb {
      width: 100px; height: 100px; object-fit: cover; border: 1px solid #000; border-radius: 4px; background:#eee;
    }
    .dispatch-body h4 { margin: 0 0 6px 0; font-family: 'Jim Nightshade', cursive; }
    .dispatch-body .meta { font-size: 12px; opacity: .75; margin-bottom: 6px; }
    .dispatch-body .snippet { font-size: 14px; line-height: 1.5; color: #111; }
    .dispatch-actions { display: flex; gap: 6px; }

    /* Single modal for link/image/video insertion to avoid double pop-ups */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.5);
      display: none; align-items: center; justify-content: center; z-index: 9999;
    }
    .modal {
      width: 520px; max-width: 90vw;
      background: rgba(255,255,255,0.95); border: 1px solid #000; border-radius: 6px;
      box-shadow: 0 0 15px rgba(0,0,0,0.5); padding: 12px;
    }
    .modal h3 { margin: 0 0 8px 0; }
    .modal .row { display: flex; gap: 8px; align-items: center; }
    .modal input[type="url"], .modal input[type="text"] {
      padding: 8px 10px; border: 1px solid #000; border-radius: 4px; width: 100%;
      background: #fff;
    }
    .modal .actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px; }
  </style>
</head>
<body class="admin-dashboard-open">
  <div id="admin-panel" class="panel-window">
    <div class="panel-header">
      <span class="panel-title">Admin Dashboard</span>
      <div class="panel-controls">
        <button class="ghost danger" id="btn-close" title="Close">X</button>
      </div>
    </div>

    <div class="panel-tabs">
      <button class="tab-btn active" data-tab="campaigns">Campaigns</button>
      <button class="tab-btn" data-tab="users">Users</button>
      <button class="tab-btn" data-tab="dispatches">Dispatches</button>
      <button class="tab-btn" data-tab="system">Settings</button>
    </div>

    <div class="panel-content">
      <!-- Campaigns -->
      <section id="tab-campaigns" class="tab active">
        <div class="section-header">
          <h2>Campaign Management</h2>
          <form id="create-campaign-form" class="inline-form">
            <input type="text" name="name" placeholder="New campaign name" required />
            <select name="dm_username" id="create-campaign-dm" required>
              <option value="">Assign DM</option>
            </select>
            <button type="submit">Create Campaign</button>
          </form>
          <div id="campaign-status" class="status">Ready.</div>
        </div>

        <div class="tools-row">
          <input type="search" id="campaign-search" placeholder="Search campaigns..." />
        </div>

        <div id="campaigns-table-wrap" class="table-wrap">
          <table class="list-table" id="campaigns-table">
            <thead>
              <tr><th>ID</th><th>Campaign</th><th>DM</th><th>Members</th><th>Actions</th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div id="campaign-detail" class="detail-card hidden">
          <h3 id="cd-title">Campaign</h3>
          <div class="grid-form">
            <label>Campaign name
              <input type="text" id="cd-name" placeholder="Campaign name">
            </label>
            <label>DM
              <select id="cd-set-dm" required></select>
            </label>
            <button id="cd-save" class="primary">Save Campaign</button>
          </div>

          <div class="row">
            <label>Add user:</label>
            <select id="cd-add-user"></select>
            <button id="cd-apply-add-user">Add</button>
          </div>
          <div class="row">
            <label>Members:</label>
            <ul id="cd-members" class="chip-list"></ul>
          </div>
          <div class="danger-zone">
            <form id="delete-campaign-form" class="inline-form">
              <input type="text" name="confirm" placeholder="Type campaign name to confirm delete" />
              <button type="submit" class="danger">Delete Campaign</button>
            </form>
          </div>
        </div>
      </section>

      <!-- Users -->
      <section id="tab-users" class="tab">
        <div class="section-header">
          <h2>User Management</h2>
          <form id="create-user-form" class="grid-form">
            <input type="text" name="username" placeholder="Username (2-10 alnum)" required />
            <input type="text" name="full_name" placeholder="Full name" />
            <input type="email" name="email" placeholder="Email" required />
            <select name="flow">
              <option value="activation">Send activation link</option>
              <option value="initial_password">Generate initial password (force change)</option>
            </select>
            <button type="submit">Create User</button>
          </form>
          <div id="user-status" class="status">Ready.</div>
        </div>

        <div class="tools-row">
          <input type="search" id="user-search" placeholder="Search users..." />
        </div>

        <div id="users-table-wrap" class="table-wrap">
          <table class="list-table" id="users-table">
            <thead>
              <tr><th>ID</th><th>Username</th><th>Full name</th><th>Email</th><th>Role</th><th>Last Login</th><th>Actions</th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div id="user-detail" class="detail-card hidden">
          <h3 id="ud-title">User</h3>
          <form id="update-user-form" class="grid-form">
            <input type="hidden" name="user_id" />
            <input type="text" name="full_name" placeholder="Full name" />
            <input type="email" name="email" placeholder="Email" />
            <select name="role"><option value="user">user</option><option value="admin">admin</option></select>
            <label class="checkbox"><input type="checkbox" name="force_password_change" /> Force password change on next login</label>
            <div class="row">
              <button type="submit">Save</button>
              <button type="button" id="ud-resend" class="ghost">Resend Invite</button>
              <button type="button" id="ud-activate" class="ghost">Activate</button>
              <button type="button" id="ud-deactivate" class="ghost">Deactivate</button>
            </div>
          </form>
          <div class="row">
            <h4>Campaign Memberships</h4>
            <ul id="ud-memberships" class="chip-list"></ul>
          </div>
          <div class="danger-zone">
            <form id="delete-user-form" class="inline-form">
              <input type="text" name="confirm" placeholder="Type username to confirm delete" />
              <button type="submit" class="danger">Delete User</button>
            </form>
          </div>
        </div>
      </section>

      <!-- Dispatches -->
      <section id="tab-dispatches" class="tab">
        <div class="section-header">
          <h2>Dispatches</h2>
          <div class="status inline" id="dispatch-status">Ready.</div>
        </div>

        <!-- Create -->
        <div class="create-dispatch" id="disp-create">
          <h3>Create Dispatch</h3>
          <input id="disp-new-title" type="text" placeholder="Dispatch title..." />
          <div class="rich-toolbar" id="disp-new-toolbar" aria-label="Formatting">
            <button type="button" data-cmd="bold">B</button>
            <button type="button" data-cmd="italic">I</button>
            <button type="button" data-cmd="underline">U</button>
            <button type="button" data-cmd="insertUnorderedList">Bulleted List</button>
            <button type="button" data-cmd="insertOrderedList">Numbered List</button>
            <button type="button" data-action="link">Insert Link</button>
            <button type="button" data-action="image">Insert Image</button>
            <button type="button" data-action="video">Insert Video</button>
          </div>
          <div id="disp-new-editor" class="rich-editor" contenteditable="true" spellcheck="true"></div>

          <div class="row" style="margin-top:8px;">
            <input id="disp-new-thumb-url" type="url" placeholder="Optional thumbnail URL..." />
            <input id="disp-new-thumb-file" type="file" accept="image/*" />
            <small class="thumb-note">Thumbnail is limited to max 350x350. Body images can be full size.</small>
          </div>

          <div class="row" style="margin-top:8px;">
            <button type="button" id="disp-publish" class="primary">Publish</button>
            <button type="button" id="disp-clear" class="ghost">Clear</button>
          </div>
        </div>

        <!-- Tools -->
        <div class="dispatch-tools">
          <input type="search" id="disp-search" placeholder="Search dispatches..." />
          <select id="disp-sort">
            <option value="created_at">Sort by Created</option>
            <option value="updated_at">Sort by Updated</option>
            <option value="title">Sort by Title</option>
          </select>
          <select id="disp-dir">
            <option value="desc">Newest first</option>
            <option value="asc">Oldest first</option>
          </select>
        </div>

        <!-- List -->
        <div id="dispatches-list" class="dispatch-list"></div>

        <!-- Edit card -->
        <div id="disp-edit-card" class="create-dispatch hidden">
          <h3>Edit Dispatch</h3>
          <input type="hidden" id="disp-edit-id" />
          <input id="disp-edit-title" type="text" placeholder="Dispatch title..." />
          <div class="rich-toolbar" id="disp-edit-toolbar" aria-label="Formatting">
            <button type="button" data-cmd="bold">B</button>
            <button type="button" data-cmd="italic">I</button>
            <button type="button" data-cmd="underline">U</button>
            <button type="button" data-cmd="insertUnorderedList">Bulleted List</button>
            <button type="button" data-cmd="insertOrderedList">Numbered List</button>
            <button type="button" data-action="link">Insert Link</button>
            <button type="button" data-action="image">Insert Image</button>
            <button type="button" data-action="video">Insert Video</button>
          </div>
          <div id="disp-edit-editor" class="rich-editor" contenteditable="true" spellcheck="true"></div>
          <div class="row" style="margin-top:8px;">
            <input id="disp-edit-thumb-url" type="url" placeholder="Optional thumbnail URL..." />
            <input id="disp-edit-thumb-file" type="file" accept="image/*" />
            <small class="thumb-note">Thumbnail is limited to max 350x350.</small>
          </div>
          <div class="row" style="margin-top:8px;">
            <button type="button" id="disp-save" class="primary">Save Changes</button>
            <button type="button" id="disp-cancel" class="ghost">Cancel</button>
          </div>
        </div>
      </section>

      <!-- Settings -->
      <section id="tab-system" class="tab">
        <h2>Settings</h2>
        <pre id="audit-output" class="auditbox">(no API activity yet)</pre>
      </section>
    </div>
  </div>

  <!-- Single modal used for link/image/video insertion -->
  <div class="modal-overlay" id="ins-modal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="ins-title">
      <h3 id="ins-title">Insert</h3>
      <div class="row" style="margin:8px 0;">
        <input type="url" id="ins-url" placeholder="Enter URL (http...)" />
        <span>or</span>
        <input type="file" id="ins-file" accept="image/*,video/*" />
      </div>
      <div class="actions">
        <button type="button" id="ins-insert" class="primary">Insert</button>
        <button type="button" id="ins-cancel" class="ghost">Cancel</button>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // --- Tabs header buttons ---
      document.getElementById('btn-close')?.addEventListener('click',()=>{ window.location.href='/map.php'; });

      // --- Utilities ---
      const API = window.API;
      const DAPI = API && API.dispatches ? API.dispatches : null;
      if (!DAPI) return;

      const csrf = window.CSRF_TOKEN || '';
      const $ = (s, c=document)=>c.querySelector(s);
      const $$= (s, c=document)=>Array.from(c.querySelectorAll(s));
      const statusEl = $('#dispatch-status');

      function setStatus(msg, ok=true){ if(statusEl){ statusEl.textContent=msg; statusEl.style.color=ok?'#063':'#900'; } }
      function esc(s){ return String(s??'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
      function textOnly(html){ const d=document.createElement('div'); d.innerHTML=html||''; return (d.textContent||'').trim(); }
      function ts(s){ try{ return new Date(s.replace(' ','T')).toLocaleString(); }catch(e){ return s||''; } }
      function broadcastChange(){ try{ localStorage.setItem('dispatches_last_change', String(Date.now())); }catch(e){} }

      async function getJSON(url, params){
        const qs = params ? '?'+new URLSearchParams(params).toString() : '';
        const r = await fetch(url+qs,{credentials:'same-origin'});
        if(!r.ok) throw new Error('HTTP '+r.status);
        if(!(r.headers.get('content-type')||'').includes('application/json')) throw new Error('Non-JSON response');
        return r.json();
      }
      async function postJSON(url, data){
        const body = new URLSearchParams(Object.assign({ csrf_token: csrf }, data));
        const r = await fetch(url, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':csrf}, body });
        if(!r.ok) throw new Error('HTTP '+r.status);
        if(!(r.headers.get('content-type')||'').includes('application/json')) throw new Error('Non-JSON response');
        return r.json();
      }

      // Resize only for thumbnails (max 350x350). Body media can be full size.
      function fileToDataURL(file){
        return new Promise((resolve,reject)=>{
          const fr = new FileReader();
          fr.onload = ()=> resolve(String(fr.result||''));
          fr.onerror = reject;
          fr.readAsDataURL(file);
        });
      }
      function fileToThumbDataURL(file, maxW=350, maxH=350){
        return new Promise((resolve,reject)=>{
          const url = URL.createObjectURL(file);
          const img = new Image();
          img.onload = function(){
            let w = img.naturalWidth, h = img.naturalHeight;
            const ratio = Math.min(maxW/w, maxH/h, 1);
            const cw = Math.round(w*ratio), ch = Math.round(h*ratio);
            const cv = document.createElement('canvas'); cv.width=cw; cv.height=ch;
            const ctx = cv.getContext('2d'); ctx.drawImage(img,0,0,cw,ch);
            try { resolve(cv.toDataURL('image/png', 0.92)); }
            catch(e){ reject(e); }
            URL.revokeObjectURL(url);
          };
          img.onerror = reject;
          img.src = url;
        });
      }

      // --- Dispatches state + UI refs ---
      const q = { page:1, limit:25, search:'', sort:'created_at', dir:'desc' };
      const listEl = $('#dispatches-list');

      // Create UI
      const newTitle   = $('#disp-new-title');
      const newEditor  = $('#disp-new-editor');
      const newBar     = $('#disp-new-toolbar');
      const thumbUrl   = $('#disp-new-thumb-url');
      const thumbFile  = $('#disp-new-thumb-file');
      const btnPublish = $('#disp-publish');
      const btnClear   = $('#disp-clear');

      // Edit UI
      const editCard   = $('#disp-edit-card');
      const editId     = $('#disp-edit-id');
      const editTitle  = $('#disp-edit-title');
      const editEditor = $('#disp-edit-editor');
      const editBar    = $('#disp-edit-toolbar');
      const editTUrl   = $('#disp-edit-thumb-url');
      const editTFile  = $('#disp-edit-thumb-file');
      const btnSave    = $('#disp-save');
      const btnCancel  = $('#disp-cancel');

      // Tools
      const sInput = $('#disp-search');
      const sSort  = $('#disp-sort');
      const sDir   = $('#disp-dir');

      // Single modal for inserting link/image/video (prevents double pop-ups)
      const modal      = $('#ins-modal');
      const modalTitle = $('#ins-title');
      const modalUrl   = $('#ins-url');
      const modalFile  = $('#ins-file');
      const modalOK    = $('#ins-insert');
      const modalCancel= $('#ins-cancel');

      let modalCtx = null; // { type: 'link'|'image'|'video', editorEl: HTMLElement }

      function openModal(type, editor){
        modalCtx = { type, editorEl: editor };
        modalTitle.textContent = type === 'link' ? 'Insert Link' : (type === 'image' ? 'Insert Image' : 'Insert Video');
        modalUrl.value = '';
        if (modalFile) modalFile.value = '';
        modal.style.display = 'flex';
        setTimeout(()=> modalUrl.focus(), 0);
      }
      function closeModal(){ modal.style.display = 'none'; modalCtx = null; }

      modalCancel.addEventListener('click', closeModal);
      modal.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });

      modalOK.addEventListener('click', async ()=>{
        if (!modalCtx) return;
        const { type, editorEl } = modalCtx;
        let html = '';

        // Prefer file if chosen; otherwise use URL.
        const f = modalFile && modalFile.files && modalFile.files[0] ? modalFile.files[0] : null;
        const u = modalUrl.value.trim();

        try {
          if (type === 'link') {
            const href = u || (f ? await fileToDataURL(f) : '');
            if (!href) { closeModal(); return; }
            html = '<a href="'+esc(href)+'" target="_blank" rel="noopener">'+esc(href)+'</a>';
          } else if (type === 'image') {
            if (f) {
              const data = await fileToDataURL(f); // full-size allowed for body
              html = '<img src="'+esc(data)+'" alt="" />';
            } else if (u) {
              html = '<img src="'+esc(u)+'" alt="" />';
            }
          } else if (type === 'video') {
            if (f) {
              const data = await fileToDataURL(f); // will embed as data URI
              html = '<video src="'+esc(data)+'" controls style="max-width:100%;height:auto;"></video>';
            } else if (u) {
              // Treat as embeddable URL (iframe)
              html = '<div class="video-wrap"><iframe src="'+esc(u)+'" frameborder="0" allowfullscreen style="width:100%;height:360px;"></iframe></div>';
            }
          }
        } catch(e){ /* ignore and fall through */ }

        if (html) {
          editorEl.focus();
          document.execCommand('insertHTML', false, html);
        }
        closeModal();
      });

      function bindToolbar(barEl, editorEl){
        if (!barEl || barEl.__bound) return; // guard: bind once
        barEl.__bound = true;
        barEl.addEventListener('click', (e)=>{
          const b = e.target.closest('button'); if(!b) return;
          e.preventDefault();
          const cmd = b.getAttribute('data-cmd');
          const act = b.getAttribute('data-action');

          editorEl.focus();
          if (cmd) { document.execCommand(cmd, false, null); return; }
          if (act === 'link')  { openModal('link',  editorEl); return; }
          if (act === 'image') { openModal('image', editorEl); return; }
          if (act === 'video') { openModal('video', editorEl); return; }
        });
      }
      bindToolbar(newBar,  newEditor);
      bindToolbar(editBar, editEditor);

      // --- CRUD ---
      async function loadList(){
        setStatus('Loading...');
        try {
          const items = await getJSON(DAPI.list, q);
          renderList(Array.isArray(items) ? items : (items.items || []));
          setStatus('Ready.');
        } catch(e) {
          listEl.innerHTML = '<div class="dispatch-card"><div></div><div class="dispatch-body"><h4>Error</h4><p class="snippet">Failed to load dispatches.</p></div><div></div></div>';
          setStatus('Failed to load', false);
        }
      }

      function renderList(items){
        listEl.innerHTML = '';
        if (!items.length) {
          listEl.innerHTML = '<div class="dispatch-card"><div></div><div class="dispatch-body"><h4>No dispatches</h4><p class="snippet">Create one above.</p></div><div></div></div>';
          return;
        }
        const frag = document.createDocumentFragment();
        items.forEach(it=>{
          const card = document.createElement('div');
          card.className = 'dispatch-card';

          const img = document.createElement('img');
          img.className = 'dispatch-thumb';
          if (it.thumbnail_url) {
            img.src = it.thumbnail_url;
          } else {
            img.style.visibility = 'hidden';
          }

          const body = document.createElement('div');
          body.className = 'dispatch-body';
          const h4   = document.createElement('h4'); h4.textContent = it.title || 'Untitled';
          const meta = document.createElement('div'); meta.className='meta';
          meta.textContent = it.updated_at ? ('Updated ' + ts(it.updated_at)) : (it.created_at ? ('Created ' + ts(it.created_at)) : '');
          const snip = document.createElement('p'); snip.className='snippet';
          snip.textContent = textOnly(it.content_html).slice(0, 240);

          body.appendChild(h4); body.appendChild(meta); body.appendChild(snip);

          const actions = document.createElement('div'); actions.className='dispatch-actions';
          const eBtn = document.createElement('button'); eBtn.className='small'; eBtn.textContent='Edit'; eBtn.dataset.id = String(it.id); eBtn.dataset.action='edit';
          const dBtn = document.createElement('button'); dBtn.className='small danger'; dBtn.textContent='Delete'; dBtn.dataset.id = String(it.id); dBtn.dataset.action='delete';
          actions.appendChild(eBtn); actions.appendChild(dBtn);

          card.appendChild(img); card.appendChild(body); card.appendChild(actions);
          frag.appendChild(card);
        });
        listEl.appendChild(frag);
      }

      listEl.addEventListener('click', async (e)=>{
        const btn = e.target.closest('button[data-action]'); if(!btn) return;
        const id = btn.dataset.id;
        if (btn.dataset.action === 'edit') {
          openEdit(id);
        } else if (btn.dataset.action === 'delete') {
          if (!confirm('Delete this dispatch?')) return;
          setStatus('Deleting...');
          try { await postJSON(DAPI.delete, { id }); setStatus('Deleted.'); broadcastChange(); loadList(); }
          catch(err){ setStatus('Delete failed', false); }
        }
      });

      async function openEdit(id){
        setStatus('Loading dispatch...');
        try {
          const it = await getJSON(DAPI.get, { id });
          editId.value = it.id;
          editTitle.value = it.title || '';
          editEditor.innerHTML = it.content_html || '';
          editTUrl.value = it.thumbnail_url || '';
          editCard.classList.remove('hidden');
          window.scrollTo({ top: 0, behavior: 'smooth' });
          setStatus('Ready.');
        } catch(e) {
          setStatus('Unable to load dispatch', false);
        }
      }

      btnSave.addEventListener('click', async ()=>{
        const id    = editId.value;
        const title = editTitle.value.trim();
        const html  = editEditor.innerHTML.trim();
        if (!id || !title || !html) { alert('Title and content are required.'); return; }

        let imgURL = editTUrl.value.trim();
        const f = editTFile.files && editTFile.files[0] ? editTFile.files[0] : null;
        if (f) {
          try { imgURL = await fileToThumbDataURL(f, 350, 350); }
          catch(e){ alert('Could not process thumbnail.'); return; }
        }

        setStatus('Saving...');
        try {
          await postJSON(DAPI.update, { id, title, content_html: html, thumbnail_url: imgURL });
          editCard.classList.add('hidden');
          setStatus('Saved.');
          broadcastChange();
          loadList();
        } catch(e) {
          setStatus('Save failed', false);
        }
      });
      btnCancel.addEventListener('click', ()=>{ editCard.classList.add('hidden'); });

      btnPublish.addEventListener('click', async ()=>{
        const title = newTitle.value.trim();
        const html  = newEditor.innerHTML.trim();
        if (!title || !html) { alert('Title and content are required.'); return; }

        let imgURL = thumbUrl.value.trim();
        const f = thumbFile.files && thumbFile.files[0] ? thumbFile.files[0] : null;
        if (f) {
          try { imgURL = await fileToThumbDataURL(f, 350, 350); }
          catch(e){ alert('Could not process thumbnail.'); return; }
        }

        setStatus('Publishing...');
        try {
          await postJSON(DAPI.create, { title, content_html: html, thumbnail_url: imgURL });
          newTitle.value = ''; newEditor.innerHTML = ''; thumbUrl.value=''; if (thumbFile) thumbFile.value='';
          setStatus('Published.');
          broadcastChange();
          loadList();
        } catch(e) {
          setStatus('Publish failed', false);
        }
      });

      btnClear.addEventListener('click', ()=>{
        newTitle.value = '';
        newEditor.innerHTML = '';
        thumbUrl.value = '';
        if (thumbFile) thumbFile.value = '';
      });

      // Search/sort
      function reload(){ q.page=1; loadList(); }
      sInput.addEventListener('input', ()=>{ q.search = sInput.value.trim(); reload(); });
      sSort.addEventListener('change', ()=>{ q.sort = sSort.value; reload(); });
      sDir.addEventListener('change',  ()=>{ q.dir  = sDir.value;  reload(); });

      // Load when tab is activated (and now if already active)
      const dispBtn = document.querySelector('.tab-btn[data-tab="dispatches"]');
      const dispTab = document.getElementById('tab-dispatches');
      if (dispTab.classList.contains('active')) { loadList(); }
      dispBtn?.addEventListener('click', ()=> loadList(), { once:true });
    })();
  </script>
</body>
</html>

