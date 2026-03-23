// dispatch-editor-panel.js
(() => {
  "use strict";

  const API = (window.API && window.API.dispatches) || null;
  if (!API) return;

  const csrf = window.CSRF_TOKEN || '';
  const USER_ROLE = (window.USER_ROLE || 'user').toLowerCase();

  // ---------- helpers ----------
  const $  = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const fmt = (dt) => { if (!dt) return ''; try { return new Date(dt.replace(' ', 'T')).toLocaleString(); } catch(_) { return dt; } };

  async function apiGET(url, params={}) {
    const qs = new URLSearchParams(params);
    const res = await fetch(qs.toString() ? `${url}?${qs}` : url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf }
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) throw new Error('Non-JSON response');
    return res.json();
  }
  async function apiPOST(url, data={}) {
    const body = new URLSearchParams(Object.assign({ csrf_token: csrf }, data));
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
      body
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) throw new Error('Non-JSON response');
    return res.json();
  }
  async function uploadMedia(file, kind, titleForPath) {
    if (!file) throw new Error('No file selected');
    if (USER_ROLE !== 'admin' && file.size > 5 * 1024 * 1024) {
      throw new Error('DM upload limit is 5 MB');
    }
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('file', file);
    fd.append('kind', kind);
    if (titleForPath) fd.append('title', titleForPath);
    const res = await fetch(API.upload, { method: 'POST', credentials: 'same-origin', body: fd });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data || data.ok !== true || !data.url) throw new Error(data && data.error ? data.error : 'Upload failed');
    return data.url; // absolute or site-relative
  }

  function setStatus(id, msg, ok=true) {
    const el = $(id);
    if (!el) return;
    el.textContent = msg;
    el.classList.toggle('err', !ok);
  }

  // ---------- caret management ----------
  function makeCaretAware(editorEl) {
    let savedRange = null;

    function saveRange() {
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) return;
      savedRange = sel.getRangeAt(0).cloneRange();
    }
    function restoreRange() {
      if (!savedRange) return;
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }
    function insertNodeAtCaret(node) {
      editorEl.focus();
      restoreRange();
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) {
        editorEl.appendChild(node);
        return;
      }
      const range = sel.getRangeAt(0);
      range.deleteContents();
      range.insertNode(node);
      // move caret after node
      range.setStartAfter(node);
      range.setEndAfter(node);
      sel.removeAllRanges();
      sel.addRange(range);
      saveRange();
    }
    function applyCmd(cmd, arg=null) {
      editorEl.focus();
      restoreRange();
      document.execCommand(cmd, false, arg);
      saveRange();
    }

    // track selection
    ['keyup','mouseup','touchend','blur'].forEach(ev => {
      editorEl.addEventListener(ev, saveRange);
    });

    return { saveRange, restoreRange, insertNodeAtCaret, applyCmd };
  }

  function bindToolbar(toolbarEl, caret, opts={}) {
    toolbarEl.addEventListener('click', async (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      const cmd = btn.getAttribute('data-cmd');
      const action = btn.getAttribute('data-action');
      e.preventDefault();

      if (cmd) {
        caret.applyCmd(cmd);
        return;
      }

      // Helper to choose display size
      function pickSizeClass() {
        const v = (prompt('Size? Enter: sm, md, or lg', 'md') || 'md').trim().toLowerCase();
        return v === 'sm' ? 'media-sm' : v === 'lg' ? 'media-lg' : 'media-md';
      }

      if (action === 'link') {
        const url = prompt('Enter URL:');
        if (url) caret.applyCmd('createLink', url);
        return;
      }

      if (action === 'image') {
        // URL or Upload
        const choice = (prompt('Type "url" to insert by URL, or "upload" to upload from your device:', 'url') || 'url').trim().toLowerCase();
        const cls = pickSizeClass();
        if (choice === 'upload') {
          const input = document.createElement('input');
          input.type = 'file';
          input.accept = 'image/png,image/jpeg,image/webp,image/gif';
          input.onchange = async () => {
            const f = input.files && input.files[0];
            if (!f) return;
            try {
              const url = await uploadMedia(f, 'image', $('#de-title')?.value || $('#edit-title')?.value || 'dispatch');
              const fig = document.createElement('figure');
              fig.className = `media ${cls}`;
              const img = document.createElement('img');
              img.src = url;
              img.alt = '';
              fig.appendChild(img);
              caret.insertNodeAtCaret(fig);
            } catch (err) {
              alert(err.message || String(err));
            }
          };
          input.click();
        } else {
          const url = prompt('Image URL:');
          if (!url) return;
          const fig = document.createElement('figure');
          fig.className = `media ${cls}`;
          const img = document.createElement('img');
          img.src = url;
          img.alt = '';
          fig.appendChild(img);
          caret.insertNodeAtCaret(fig);
        }
        return;
      }

      if (action === 'video') {
        const choice = (prompt('Type "url" (mp4/stream/embed), or "upload" to upload a video:', 'url') || 'url').trim().toLowerCase();
        const cls = pickSizeClass();
        if (choice === 'upload') {
          const input = document.createElement('input');
          input.type = 'file';
          input.accept = 'video/mp4,video/webm,video/ogg';
          input.onchange = async () => {
            const f = input.files && input.files[0];
            if (!f) return;
            try {
              const url = await uploadMedia(f, 'video', $('#de-title')?.value || $('#edit-title')?.value || 'dispatch');
              const fig = document.createElement('figure');
              fig.className = `media ${cls}`;
              const vid = document.createElement('video');
              vid.src = url;
              vid.controls = true;
              fig.appendChild(vid);
              caret.insertNodeAtCaret(fig);
            } catch (err) {
              alert(err.message || String(err));
            }
          };
          input.click();
        } else {
          const url = prompt('Video URL (mp4/webm/ogg or embeddable iframe src):');
          if (!url) return;
          // Heuristic: if looks like iframe src (youtube, etc.), insert iframe; else <video>
          const fig = document.createElement('figure');
          fig.className = `media ${cls}`;
          if (/^(https?:)?\/\/(www\.)?(youtube\.com|youtu\.be|player\.vimeo\.com)/i.test(url)) {
            const ifr = document.createElement('iframe');
            ifr.src = url;
            ifr.width = '560';
            ifr.height = '315';
            ifr.frameBorder = '0';
            ifr.allowFullscreen = true;
            fig.appendChild(ifr);
          } else {
            const vid = document.createElement('video');
            vid.src = url;
            vid.controls = true;
            fig.appendChild(vid);
          }
          caret.insertNodeAtCaret(fig);
        }
        return;
      }
    });
  }

  // ---------- compose (create) ----------
  const titleEl      = $('#de-title');
  const editorEl     = $('#de-editor');
  const toolbarEl    = $('#de-toolbar');
  const statusEl     = $('#de-status');
  const btnPublish   = $('#de-publish');
  const btnClear     = $('#de-clear');

  const thumbUrlEl   = $('#de-thumb-url');
  const thumbFileEl  = $('#de-thumb-file');
  const thumbUpBtn   = $('#de-thumb-upload');

  const caretCreate = makeCaretAware(editorEl);
  bindToolbar(toolbarEl, caretCreate);

  thumbUpBtn?.addEventListener('click', async () => {
    const f = thumbFileEl?.files && thumbFileEl.files[0];
    if (!f) { alert('Choose a thumbnail image first.'); return; }
    try {
      const url = await uploadMedia(f, 'thumbnail', titleEl?.value || 'dispatch');
      thumbUrlEl.value = url;
      setStatus('#de-status', 'Thumbnail uploaded.', true);
    } catch (e) {
      setStatus('#de-status', e.message || 'Upload failed', false);
    }
  });

  btnPublish?.addEventListener('click', async () => {
    const title = (titleEl?.value || '').trim();
    const html  = (editorEl?.innerHTML || '').trim();
    if (!title || !html) { alert('Title and content are required.'); return; }

    setStatus('#de-status', 'Publishing…');
    try {
      const payload = {
        title,
        content_html: html,
        thumbnail_url: (thumbUrlEl?.value || '').trim()
      };
      const rsp = await apiPOST(API.create, payload);
      if (!rsp || rsp.ok !== true) throw new Error(rsp && rsp.error ? rsp.error : 'Create failed');

      // reset and reload
      titleEl.value = '';
      editorEl.innerHTML = '';
      thumbUrlEl.value = '';
      if (thumbFileEl) thumbFileEl.value = '';
      await loadList();
      setStatus('#de-status', 'Published.', true);
    } catch (e) {
      setStatus('#de-status', e.message || 'Publish failed', false);
    }
  });

  btnClear?.addEventListener('click', () => {
    titleEl.value = '';
    editorEl.innerHTML = '';
    thumbUrlEl.value = '';
    if (thumbFileEl) thumbFileEl.value = '';
    setStatus('#de-status', 'Cleared.', true);
  });

  // ---------- edit ----------
  const editCard   = $('#de-edit-card');
  const editId     = $('#edit-id');
  const editTitle  = $('#edit-title');
  const editEditor = $('#edit-editor');
  const editToolbar= $('#edit-toolbar');
  const editSave   = $('#edit-save');
  const editCancel = $('#edit-cancel');
  const editDelete = $('#edit-delete');
  const editStatus = $('#edit-status');

  const editThumbUrl  = $('#edit-thumb-url');
  const editThumbFile = $('#edit-thumb-file');
  const editThumbBtn  = $('#edit-thumb-upload');

  const caretEdit = makeCaretAware(editEditor);
  bindToolbar(editToolbar, caretEdit);

  editThumbBtn?.addEventListener('click', async () => {
    const f = editThumbFile?.files && editThumbFile.files[0];
    if (!f) { alert('Choose a thumbnail image first.'); return; }
    try {
      const url = await uploadMedia(f, 'thumbnail', editTitle?.value || 'dispatch');
      editThumbUrl.value = url;
      setStatus('#edit-status', 'Thumbnail uploaded.', true);
    } catch (e) {
      setStatus('#edit-status', e.message || 'Upload failed', false);
    }
  });

  editSave?.addEventListener('click', async () => {
    const id    = (editId?.value || '').trim();
    const title = (editTitle?.value || '').trim();
    const html  = (editEditor?.innerHTML || '').trim();
    if (!id || !title || !html) { alert('ID, title, content required.'); return; }
    setStatus('#edit-status', 'Saving…');
    try {
      const rsp = await apiPOST(API.update, {
        id, title, content_html: html, thumbnail_url: (editThumbUrl?.value || '').trim()
      });
      if (!rsp || rsp.ok !== true) throw new Error(rsp && rsp.error ? rsp.error : 'Update failed');
      editCard.classList.add('hidden');
      await loadList();
      setStatus('#de-status', 'Saved.', true);
    } catch (e) {
      setStatus('#edit-status', e.message || 'Save failed', false);
    }
  });

  editCancel?.addEventListener('click', () => {
    editCard.classList.add('hidden');
  });

  editDelete?.addEventListener('click', async () => {
    const id = (editId?.value || '').trim();
    if (!id) return;
    if (!confirm('Delete this dispatch?')) return;
    setStatus('#edit-status', 'Deleting…');
    try {
      const rsp = await apiPOST(API.delete, { id });
      if (!rsp || rsp.ok !== true) throw new Error(rsp && rsp.error ? rsp.error : 'Delete failed');
      editCard.classList.add('hidden');
      await loadList();
      setStatus('#de-status', 'Deleted.', true);
    } catch (e) {
      setStatus('#edit-status', e.message || 'Delete failed', false);
    }
  });

  // ---------- list ----------
  const listWrap = $('#list-wrap');
  const listSearch = $('#list-search');
  const listSort = $('#list-sort');
  const listDir  = $('#list-dir');

  async function loadList() {
    const params = {
      search: (listSearch?.value || '').trim(),
      sort:   (listSort?.value || 'created_at'),
      dir:    (listDir?.value || 'desc'),
      include_global: USER_ROLE === 'admin' ? '1' : '0'
    };
    const rows = await apiGET(API.list, params);
    renderList(Array.isArray(rows) ? rows : []);
  }

  function renderList(items) {
    listWrap.innerHTML = '';
    if (!items.length) {
      listWrap.innerHTML = '<p class="muted">No dispatches found.</p>';
      return;
    }
    const frag = document.createDocumentFragment();
    items.forEach(it => {
      const div = document.createElement('div');
      div.className = 'list-item';
      div.innerHTML =
        `<img class="list-thumb" src="${esc(it.thumbnail_url || '')}" onerror="this.style.display='none'" alt="">
         <div>
           <div class="list-title">${esc(it.title || 'Untitled')}</div>
           <div class="list-meta">${esc(fmt(it.created_at))}${it.updated_at ? ' • edited ' + esc(fmt(it.updated_at)) : ''}</div>
         </div>
         <div class="list-actions">
           <button class="ghost" data-id="${esc(String(it.id))}" data-act="edit">Edit</button>
           <button class="danger ghost" data-id="${esc(String(it.id))}" data-act="delete">Delete</button>
         </div>`;
      frag.appendChild(div);
    });
    listWrap.appendChild(frag);
  }

  listWrap.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const act= btn.getAttribute('data-act');
    if (act === 'edit') {
      try {
        const it = await apiGET(API.get, { id });
        editId.value = it.id;
        editTitle.value = it.title || '';
        editEditor.innerHTML = it.content_html || '';
        editThumbUrl.value = it.thumbnail_url || '';
        editCard.classList.remove('hidden');
        setStatus('#edit-status', 'Ready.', true);
      } catch (e2) {
        alert(e2.message || 'Unable to load dispatch.');
      }
    } else if (act === 'delete') {
      if (!confirm('Delete this dispatch?')) return;
      try {
        const rsp = await apiPOST(API.delete, { id });
        if (!rsp || rsp.ok !== true) throw new Error(rsp && rsp.error ? rsp.error : 'Delete failed');
        await loadList();
      } catch (e3) {
        alert(e3.message || 'Delete failed');
      }
    }
  });

  listSearch?.addEventListener('input', () => { loadList(); });
  listSort?.addEventListener('change', () => { loadList(); });
  listDir ?.addEventListener('change', () => { loadList(); });

  // initial
  window.addEventListener('DOMContentLoaded', () => {
    loadList();
  });
})();

