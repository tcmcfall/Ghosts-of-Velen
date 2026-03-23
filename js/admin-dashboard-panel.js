// /js/admin-dashboard-panel.js
(() => {
  "use strict";

  const SEL = {
    tabBtn: '.tab-btn', tab: '.tab',

    // Header buttons
    btnLogout: '#btn-logout', btnMax: '#btn-max', btnClose: '#btn-close',

    // Campaigns
    campTable: '#campaigns-table',
    campTableWrap: '#campaigns-table-wrap',
    campSection: '#tab-campaigns',
    createCampaignForm: '#create-campaign-form',
    campaignStatus: '#campaign-status',
    campDetail: '#campaign-detail',
    cdTitle: '#cd-title',
    cdName: '#cd-name',
    cdSetDm: '#cd-set-dm',
    cdSave: '#cd-save',
    cdAddUser: '#cd-add-user',
    cdApplyAddUser: '#cd-apply-add-user',
    cdMembers: '#cd-members',
    deleteCampaignForm: '#delete-campaign-form',

    // Users
    usersTable: '#users-table',
    usersTableWrap: '#users-table-wrap',
    usersSection: '#tab-users',
    createUserForm: '#create-user-form',
    userStatus: '#user-status',
    userSearch: '#user-search',
    userDetail: '#user-detail',
    udTitle: '#ud-title',
    updateUserForm: '#update-user-form',
    udResend: '#ud-resend',
    udActivate: '#ud-activate',
    udDeactivate: '#ud-deactivate',
    udMemberships: '#ud-memberships',

    // Audit
    auditBox: '#audit-output',

    // Dispatches
    dispTab: '#tab-dispatches',
    dispStatus: '#dispatch-status',
    dispChicklet: '#create-dispatch-chicklet',
    dispNewTitle: '#disp-new-title',
    dispNewToolbar: '#disp-new-toolbar',
    dispNewEditor: '#disp-new-editor',
    dispNewImage: '#disp-new-image',
    dispPublish: '#disp-publish',
    dispClear: '#disp-clear',
    dispSearch: '#disp-search',
    dispSort: '#disp-sort',
    dispDir: '#disp-dir',
    dispList: '#dispatches-list',
    dispDetail: '#disp-detail',
    dispId: '#disp-id',
    dispTitleInput: '#disp-title-input',
    dispEditToolbar: '#disp-edit-toolbar',
    dispEditor: '#disp-editor',
    dispImageUrl: '#disp-image-url',
    dispSave: '#disp-save',
    dispCancel: '#disp-cancel'
  };

  const API = window.API;

  const state = {
    users: { page:1, limit:25, search:'', sort:'username', dir:'asc', total:0, data:[] },
    campaigns:{ page:1, limit:25, search:'', sort:'name', dir:'asc', total:0, data:[] },
    usersCache: [],
    audit: [], maxAudit: 200,
    openCampaignId: null, openUserId: null,

    // Dispatches
    dispatches: { page:1, limit:25, search:'', sort:'created_at', dir:'desc', total:0, items:[] },
    editingId: null
  };

  const csrfToken = (() => {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : (window.CSRF_TOKEN || '');
  })();
  const baseHeaders = { 'Accept': 'application/json' };
  if (csrfToken) baseHeaders['X-CSRF-Token'] = csrfToken;

  const esc = s => String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
  const stripTags = html => String(html||'').replace(/<\/?[^>]+(>|$)/g, '');
  const debounce = (fn,ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };
  const nowIso = () => new Date().toISOString().replace('T',' ').replace('Z','Z');

  function auditPush(e){ state.audit.push(e); if(state.audit.length>state.maxAudit) state.audit.shift(); renderAudit(); }
  function renderAudit(){
    const box = document.querySelector(SEL.auditBox); if (!box) return;
    box.textContent = state.audit.slice().reverse().map(e=>{
      const head = `[${e.when}] ${e.method} ${e.url} -> ${e.status}${e.ok?' OK':''} (${e.ms}ms)`;
      const req  = e.params ? `\n? params: ${JSON.stringify(e.params)}` : (e.body ? `\n? body: ${JSON.stringify(e.body)}` : '');
      const rsp  = e.text ? `\n? raw: ${e.text.slice(0,4000)}` : '';
      return head+req+rsp;
    }).join('\n\n');
  }
  async function apiGET(url, params={}){
    const merged = Object.assign({ csrf_token: csrfToken||'' }, params);
    const qs = new URLSearchParams(merged).toString();
    const full = qs ? `${url}?${qs}` : url;
    const t0=performance.now(); let res, text='', ct='', ok=false, status=0;
    try { res=await fetch(full,{credentials:'same-origin',headers:baseHeaders}); status=res.status; ct=res.headers.get('content-type')||''; text=await res.text(); ok=res.ok; }
    catch (err) { text=String(err?.message||err); }
    finally { auditPush({when:nowIso(), method:'GET', url:full, params:merged, status, ok, ms:Math.round(performance.now()-t0), ct, text}); }
    if (!ok) throw new Error(text.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim() || `HTTP ${status}`);
    if (!ct.includes('application/json')) throw new Error(`Non-JSON response (${status})`);
    return JSON.parse(text);
  }
  async function apiPOST(url, data={}){
    const body = new URLSearchParams(Object.assign({ csrf_token: csrfToken||'' }, data));
    const t0=performance.now(); let res, text='', ct='', ok=false, status=0;
    try { res=await fetch(url,{method:'POST',credentials:'same-origin',headers:{...baseHeaders,'Content-Type':'application/x-www-form-urlencoded'},body}); status=res.status; ct=res.headers.get('content-type')||''; text=await res.text(); ok=res.ok && !/^\s*\{?"?ok"?\s*:\s*false/i.test(text); }
    catch (err) { text=String(err?.message||err); }
    finally { const sent=Object.fromEntries(body); delete sent.csrf_token; auditPush({when:nowIso(),method:'POST',url,body:sent,status,ok,ms:Math.round(performance.now()-t0),ct,text}); }
    if (!ok) throw new Error(text.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim() || `HTTP ${status}`);
    if (!ct.includes('application/json')) throw new Error(`Non-JSON response (${status})`);
    return JSON.parse(text);
  }

  // ---------- Users ----------
  function renderUsers(){
    const t=document.querySelector(SEL.usersTable); if(!t) return;
    const s=state.users; const rows=s.data||[];
    const tbody = rows.length ? rows.map(u=>`
      <tr>
        <td>${u.id}</td>
        <td><button class="link view-u" data-id="${u.id}">${esc(u.username)}</button></td>
        <td>${u.full_name?esc(u.full_name):''}</td>
        <td>${esc(u.email||'')}</td>
        <td>${esc(u.role)}</td>
        <td>${u.last_login_at?esc(u.last_login_at):''}</td>
        <td>
          <button class="small tgl" data-id="${u.id}" data-active="${u.is_active?1:0}">${u.is_active?'Deactivate':'Activate'}</button>
          <button class="small danger del-u" data-id="${u.id}" data-username="${esc(u.username)}">Delete</button>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="7" class="empty">No users found.</td></tr>`;
    (t.querySelector('tbody')||t.appendChild(document.createElement('tbody'))).innerHTML = tbody;
  }
  async function loadUsers(){
    const wrap=document.querySelector(SEL.usersTableWrap); if(wrap) wrap.classList.add('loading');
    try{
      const s=state.users;
      const r=await apiGET(API.users.list,{page:s.page,limit:s.limit,search:s.search,sort:s.sort,dir:s.dir});
      s.data=r.data||r.items||[]; s.page=r.page||1; s.limit=r.limit||s.limit; s.total=r.total||0; s.sort=r.sort||s.sort; s.dir=r.dir||s.dir; s.search=r.search??s.search;
      renderUsers();
    } finally { if(wrap) wrap.classList.remove('loading'); }
  }
  async function loadUsersCache(){
    const r=await apiGET(API.users.list,{page:1,limit:200,sort:'username',dir:'asc'});
    state.usersCache=r.data||r.items||[];
    refreshUserOptions();
  }
  function refreshUserOptions(){
    const dmCreate=document.querySelector('#create-campaign-dm');
    const dmEdit=document.querySelector(SEL.cdSetDm);
    const addSel=document.querySelector(SEL.cdAddUser);
    const mk=(arr,withEmpty,valueKey,labelFn)=>{
      const f=document.createDocumentFragment();
      if(withEmpty){ const o=document.createElement('option'); o.value=''; o.textContent='(none)'; f.appendChild(o); }
      arr.forEach(u=>{ const o=document.createElement('option'); o.value=String(u[valueKey]); o.textContent=labelFn(u); f.appendChild(o); });
      return f;
    };
    if(dmCreate){ dmCreate.innerHTML=''; dmCreate.appendChild(mk(state.usersCache,true,'username',u=>`${u.username} (${u.role||'user'})`)); }
    if(dmEdit){ dmEdit.innerHTML=''; dmEdit.appendChild(mk(state.usersCache,true,'username',u=>`${u.username} (${u.role||'user'})`)); }
    if(addSel){ addSel.innerHTML=''; const n=mk(state.usersCache,false,'id',u=>u.username); const first=document.createElement('option'); first.value=''; first.textContent='(select user…)'; addSel.appendChild(first); addSel.appendChild(n); }
  }

  function bindUsersTable(){
    const t=document.querySelector(SEL.usersTable); if(!t) return;
    t.addEventListener('click',async e=>{
      const b=e.target.closest('button'); if(!b) return;
      const s=document.querySelector(SEL.userStatus);
      if(b.classList.contains('view-u')){ openUserDetail(b.dataset.id); return; }
      if(b.classList.contains('tgl')){
        const id=b.dataset.id; const newActive=b.dataset.active==='1'?0:1;
        if(s) s.textContent='Updating…';
        try{ await apiPOST(API.users.update,{user_id:id,is_active:newActive}); if(s) s.textContent='? Updated.'; await loadUsers(); }
        catch(err){ if(s) s.textContent='? '+err.message; }
        return;
      }
      if(b.classList.contains('del-u')){
        const id=b.dataset.id, uname=b.dataset.username;
        const chk=prompt(`Type the username to confirm delete:\n${uname}`); if(chk!==uname) return;
        if(s) s.textContent='Deleting…';
        try{ await apiPOST(API.users.delete,{user_id:id,confirm:uname}); if(s) s.textContent='? Deleted.'; document.querySelector(SEL.userDetail)?.classList.add('hidden'); await loadUsers(); }
        catch(err){ if(s) s.textContent='? '+err.message; }
      }
    });
  }
  async function openUserDetail(id){
    const card=document.querySelector(SEL.userDetail); if(!card) return;
    card.classList.remove('hidden'); state.openUserId=Number(id);
    document.querySelector(SEL.udTitle).textContent='User #'+id;
    await loadUsers(); const u=(state.users.data||[]).find(x=>Number(x.id)===Number(id));
    const f=document.querySelector(SEL.updateUserForm);
    if (u && f){ f.user_id.value=id; f.full_name.value=u.full_name||''; f.email.value=u.email||''; f.role.value=u.role||'user'; f.force_password_change.checked=!!u.force_password_change; }
    document.querySelector(SEL.udMemberships).innerHTML = u && u.campaigns ? u.campaigns.map(c=>`<li class="chip">${esc(c.name||('Campaign #'+(c.campaign_id??'?')))}</li>`).join('') : '';
  }
  function bindUserDetail(){
    const f=document.querySelector(SEL.updateUserForm); if(f){
      f.addEventListener('submit',async e=>{
        e.preventDefault(); const s=document.querySelector(SEL.userStatus);
        const fd=new FormData(f);
        const payload={ user_id:fd.get('user_id'), full_name:fd.get('full_name')||'', email:fd.get('email')||'', role:fd.get('role')||'user', force_password_change:fd.get('force_password_change')?1:0 };
        if(s) s.textContent='Saving…';
        try{ await apiPOST(API.users.update,payload); if(s) s.textContent='? Saved.'; await loadUsers(); await loadUsersCache(); }
        catch(err){ if(s) s.textContent='? '+err.message; }
      });
    }
    document.querySelector(SEL.udResend)?.addEventListener('click',async ()=>{
      const s=document.querySelector(SEL.userStatus); if(s) s.textContent='Resending…';
      try{ await apiPOST(API.users.update,{ user_id:state.openUserId, resend_invite:1 }); if(s) s.textContent='? Invite resent.'; }
      catch(err){ if(s) s.textContent='? '+err.message; }
    });
    document.querySelector(SEL.udActivate)?.addEventListener('click',()=>toggleUserActive(1));
    document.querySelector(SEL.udDeactivate)?.addEventListener('click',()=>toggleUserActive(0));
    async function toggleUserActive(a){
      const s=document.querySelector(SEL.userStatus); if(s) s.textContent='Updating…';
      try{ await apiPOST(API.users.update,{ user_id:state.openUserId, is_active:a }); if(s) s.textContent='? Updated.'; await loadUsers(); }
      catch(err){ if(s) s.textContent='? '+err.message; }
    }
  }
  function bindCreateUser(){
    const f=document.querySelector(SEL.createUserForm); if(!f) return;
    f.addEventListener('submit', async e=>{
      e.preventDefault(); const s=document.querySelector(SEL.userStatus);
      const fd=new FormData(f); const payload=Object.fromEntries(fd.entries());
      if(s) s.textContent='Creating…';
      try{ await apiPOST(API.users.create,payload); f.reset(); if(s) s.textContent='? Created.'; await loadUsers(); await loadUsersCache(); }
      catch(err){ if(s) s.textContent='? '+err.message; }
    });
  }

  // ---------- Campaigns ----------
  function renderCampaigns(){
    const t=document.querySelector(SEL.campTable); if(!t) return;
    const s=state.campaigns; const rows=s.data||[];
    const tbody = rows.length ? rows.map(r=>`
      <tr>
        <td>${r.id}</td>
        <td><button class="link view-c" data-id="${r.id}">${esc(r.name)}</button></td>
        <td>${r.dm_username?esc(r.dm_username):''}</td>
        <td>${(r.members&&r.members.length)?r.members.map(m=>esc(m.username)).join(', '):''}</td>
        <td></td>
      </tr>
    `).join('') : `<tr><td colspan="5" class="empty">No campaigns found.</td></tr>`;
    (t.querySelector('tbody')||t.appendChild(document.createElement('tbody'))).innerHTML = tbody;
  }
  async function loadCampaigns(){
    const wrap=document.querySelector(SEL.campTableWrap); if(wrap) wrap.classList.add('loading');
    try{
      const s=state.campaigns;
      const r=await apiGET(API.campaigns.list,{page:s.page,limit:s.limit,search:s.search,sort:s.sort,dir:s.dir});
      s.data=r.data||r.items||[]; s.page=r.page||1; s.limit=r.limit||s.limit; s.total=r.total||0; s.sort=r.sort||s.sort; s.dir=r.dir||s.dir; s.search=r.search??s.search;
      renderCampaigns();
    } finally { if(wrap) wrap.classList.remove('loading'); }
  }
  function findCampaign(id){ id=Number(id); return (state.campaigns.data||[]).find(c=>Number(c.id)===id)||null; }
  async function openCampaignDetail(id){
    let c=findCampaign(id); if(!c){ await loadCampaigns(); c=findCampaign(id); }
    if(!c) return;
    state.openCampaignId=c.id;
    document.querySelector(SEL.campDetail)?.classList.remove('hidden');
    document.querySelector(SEL.cdTitle).textContent = c.name || `Campaign #${c.id}`;
    const nameEl=document.querySelector(SEL.cdName); if(nameEl) nameEl.value=c.name||'';
    const dmSel=document.querySelector(SEL.cdSetDm); if(dmSel) dmSel.value=c.dm_username||'';
    renderMembers(c);
  }
  function renderMembers(c){
    const ul=document.querySelector(SEL.cdMembers); if(!ul) return;
    ul.innerHTML='';
    (c.members||[]).forEach(m=>{
      const li=document.createElement('li');
      li.className='chip';
      li.innerHTML=`<span>${esc(m.username)}</span><button class="x" data-user="${m.id}" title="Remove">×</button>`;
      ul.appendChild(li);
    });
    if(!ul.children.length) ul.innerHTML='<li class="empty">(no members yet)</li>';
  }
  function bindCampaignsTable(){
    const t=document.querySelector(SEL.campTable); if(!t) return;
    t.addEventListener('click', e=>{
      const b=e.target.closest('button.view-c'); if(!b) return;
      openCampaignDetail(b.dataset.id);
    });
  }
  function bindCampaignDetail(){
    document.querySelector(SEL.cdSave)?.addEventListener('click', async ()=>{
      const id=state.openCampaignId; if(!id) return;
      const s=document.querySelector(SEL.campaignStatus);
      const name=(document.querySelector(SEL.cdName)?.value||'').trim();
      const dm=(document.querySelector(SEL.cdSetDm)?.value||'').trim();
      if(!name){ if(s) s.textContent='? Name is required.'; return; }
      if(!dm){ if(s) s.textContent='? DM is required.'; return; }
      if(s) s.textContent='Saving…';
      try{
        await apiPOST(API.campaigns.update,{campaign_id:id,name,dm_username:dm});
        const c=findCampaign(id); if(c){ c.name=name; c.dm_username=dm; }
        document.querySelector(SEL.cdTitle).textContent=name;
        renderCampaigns();
        if(s) s.textContent='? Saved.';
      }catch(err){ if(s) s.textContent='? '+err.message; }
    });

    document.querySelector(SEL.cdApplyAddUser)?.addEventListener('click', async ()=>{
      const id=state.openCampaignId; if(!id) return;
      const sel=document.querySelector(SEL.cdAddUser); const uid=Number(sel?.value||'')||0; if(!uid) return;
      const s=document.querySelector(SEL.campaignStatus); if(s) s.textContent='Adding user…';
      try{
        await apiPOST(API.campaigns.add,{campaign_id:id,user_id:uid});
        const u=state.usersCache.find(x=>Number(x.id)===uid);
        const c=findCampaign(id); if(c){ c.members=c.members||[]; if(!c.members.some(m=>Number(m.id)===uid)) c.members.push({id:uid,username:u?u.username:`user#${uid}`}); renderMembers(c); renderCampaigns(); }
        if(s) s.textContent='? User added.'; sel.value='';
      }catch(err){ if(s) s.textContent='? '+err.message; }
    });

    document.querySelector(SEL.cdMembers)?.addEventListener('click', async e=>{
      const x=e.target.closest('.x'); if(!x) return;
      const id=state.openCampaignId; const uid=Number(x.getAttribute('data-user'));
      const s=document.querySelector(SEL.campaignStatus); if(s) s.textContent='Removing user…';
      try{
        await apiPOST(API.campaigns.remove,{campaign_id:id,user_id:uid});
        const c=findCampaign(id); if(c){ c.members=(c.members||[]).filter(m=>Number(m.id)!==uid); renderMembers(c); renderCampaigns(); }
        if(s) s.textContent='? User removed.';
      }catch(err){ if(s) s.textContent='? '+err.message; }
    });

    document.querySelector(SEL.deleteCampaignForm)?.addEventListener('submit', async e=>{
      e.preventDefault();
      const id=state.openCampaignId; if(!id) return;
      const expected=document.querySelector(SEL.cdTitle)?.textContent||'';
      const given=new FormData(e.currentTarget).get('confirm')?.toString()||'';
      const s=document.querySelector(SEL.campaignStatus);
      if(given!==expected){ if(s) s.textContent='? Confirmation text mismatch.'; return; }
      if(s) s.textContent='Deleting…';
      try{
        await apiPOST(API.campaigns.delete,{id});
        document.querySelector(SEL.campDetail)?.classList.add('hidden');
        state.openCampaignId=null;
        await loadCampaigns();
        if(s) s.textContent='? Deleted.';
      }catch(err){ if(s) s.textContent='? '+err.message; }
    });
  }

  // ---------- Dispatches ----------
  function setDispStatus(msg, ok=true){
    const el=document.querySelector(SEL.dispStatus);
    if (!el) return;
    el.textContent=msg;
    el.style.color= ok ? '#063' : '#900';
  }

  function bindRichToolbar(toolbarEl, editorEl){
    if(!toolbarEl || !editorEl) return;
    toolbarEl.addEventListener('click', e=>{
      const btn=e.target.closest('button'); if(!btn) return;
      e.preventDefault(); editorEl.focus();
      const cmd=btn.getAttribute('data-cmd');
      const action=btn.getAttribute('data-action');
      if(cmd){ document.execCommand(cmd,false,null); return; }
      if(action==='link'){
        const url=prompt('Enter URL:'); if(url) document.execCommand('createLink',false,url);
      } else if(action==='image'){
        const url=prompt('Image URL:'); if(url) document.execCommand('insertHTML',false,`<img src="${esc(url)}" alt="">`);
      } else if(action==='video'){
        const url=prompt('Video/Embed URL (YouTube/iframe src):');
        if(url){ document.execCommand('insertHTML',false,`<div class="video-wrap"><iframe src="${esc(url)}" frameborder="0" allowfullscreen style="width:100%;height:360px;"></iframe></div>`); }
      }
    });
  }

  function expandChicklet(expand){
    const c=document.querySelector(SEL.dispChicklet); if(!c) return;
    const expEl=c.querySelector('.create-dispatch-expanded');
    if(expand){
      c.classList.remove('collapsed'); c.classList.add('expanded'); c.setAttribute('aria-expanded','true');
      if(expEl) expEl.style.display='';
    } else {
      c.classList.remove('expanded'); c.classList.add('collapsed'); c.setAttribute('aria-expanded','false');
      if(expEl) expEl.style.display='none';
    }
  }

  function bindDispatchChicklet(){
    const c=document.querySelector(SEL.dispChicklet);
    const title=document.querySelector(SEL.dispNewTitle);
    if(!c || !title) return;
    title.addEventListener('focus', ()=>expandChicklet(true));
    // Click outside collapses but keeps content
    document.addEventListener('mousedown', (e)=>{
      if(!c.contains(e.target)) expandChicklet(false);
    });
  }

  function renderDispatchList(){
    const wrap=document.querySelector(SEL.dispList); if(!wrap) return;
    const items = state.dispatches.items || [];
    if(!items.length){
      wrap.innerHTML = '<div class="empty-note">(no dispatches yet)</div>';
      return;
    }
    wrap.innerHTML = items.map(it=>{
      const img = it.thumbnail_url ? `<div class="card-media"><img src="${esc(it.thumbnail_url)}" alt=""></div>` : '';
      const snip = esc(stripTags(it.content_html)).slice(0,220) + (stripTags(it.content_html).length>220?'…':'');
      const when = it.created_at ? new Date(it.created_at.replace(' ','T')).toLocaleString() : '';
      return `
        <article class="dispatch-card" data-id="${it.id}">
          ${img}
          <div class="card-body">
            <h3 class="card-title">${esc(it.title||'Untitled')}</h3>
            <time class="card-time" datetime="${esc(it.created_at||'')}">${esc(when)}</time>
            <p class="card-snippet">${snip}</p>
          </div>
          <div class="card-actions">
            <button class="icon btn-disp-edit" title="Edit" aria-label="Edit">&#9881;</button>
            <button class="icon danger btn-disp-del" title="Delete" aria-label="Delete">×</button>
          </div>
        </article>`;
    }).join('');
  }

  async function loadDispatches(){
    setDispStatus('Loading…');
    try{
      const s=state.dispatches;
      const r=await apiGET(API.dispatches.list,{page:s.page,limit:s.limit,search:s.search,sort:s.sort,dir:s.dir});
      s.items = r.items || r.data || [];
      s.total = r.total || s.items.length;
      setDispStatus('Ready.');
      renderDispatchList();
    } catch(e){
      setDispStatus('Failed to load', false);
      document.querySelector(SEL.dispList).innerHTML='<div class="empty-note">Unable to load dispatches.</div>';
    }
  }

  function bindDispatchTools(){
    const q=document.querySelector(SEL.dispSearch);
    const sort=document.querySelector(SEL.dispSort);
    const dir=document.querySelector(SEL.dispDir);
    if(q){
      const run=debounce(()=>{ state.dispatches.search=q.value.trim(); state.dispatches.page=1; loadDispatches(); },300);
      q.addEventListener('input', run);
    }
    sort?.addEventListener('change', ()=>{ state.dispatches.sort=sort.value; loadDispatches(); });
    dir?.addEventListener('change', ()=>{ state.dispatches.dir=dir.value; loadDispatches(); });
  }

  function bindDispatchCreate(){
    const pub=document.querySelector(SEL.dispPublish);
    const clr=document.querySelector(SEL.dispClear);
    const title=document.querySelector(SEL.dispNewTitle);
    const editor=document.querySelector(SEL.dispNewEditor);
    const img=document.querySelector(SEL.dispNewImage);
    if(!pub || !title || !editor) return;

    pub.addEventListener('click', async ()=>{
      const t=title.value.trim();
      const html=editor.innerHTML.trim();
      const image=(img?.value||'').trim();
      if(!t || !html){ alert('Title and content are required.'); return; }
      setDispStatus('Publishing…');
      try{
        await apiPOST(API.dispatches.create,{ title:t, content_html:html, thumbnail_url:image });
        title.value=''; editor.innerHTML=''; if(img) img.value='';
        expandChicklet(false);
        await loadDispatches();
        setDispStatus('Published.');
      }catch(e){ setDispStatus('Publish failed', false); }
    });

    clr?.addEventListener('click', ()=>{ title.value=''; editor.innerHTML=''; if(img) img.value=''; });
  }

  function bindDispatchListActions(){
    const list=document.querySelector(SEL.dispList);
    if(!list) return;

    list.addEventListener('click', async e=>{
      const card=e.target.closest('.dispatch-card'); if(!card) return;
      const id=Number(card.getAttribute('data-id')||0);
      if(e.target.closest('.btn-disp-edit')){ openDispatchEditor(id); }
      if(e.target.closest('.btn-disp-del')){ deleteDispatch(id); }
    });
  }

  function bindRichEditors(){
    bindRichToolbar(document.querySelector(SEL.dispNewToolbar), document.querySelector(SEL.dispNewEditor));
    bindRichToolbar(document.querySelector(SEL.dispEditToolbar), document.querySelector(SEL.dispEditor));
  }

  async function openDispatchEditor(id){
    setDispStatus('Loading dispatch…');
    try{
      const it = await apiGET(API.dispatches.get, { id });
      state.editingId = it.id;
      document.querySelector(SEL.dispId).value = it.id;
      document.querySelector(SEL.dispTitleInput).value = it.title || '';
      document.querySelector(SEL.dispEditor).innerHTML = it.content_html || '';
      document.querySelector(SEL.dispImageUrl).value = it.thumbnail_url || '';
      document.querySelector(SEL.dispDetail).classList.remove('hidden');
      setDispStatus('Ready.');
    } catch(e){ setDispStatus('Unable to load dispatch', false); }
  }

  async function deleteDispatch(id){
    if(!confirm('Delete this dispatch?')) return;
    setDispStatus('Deleting…');
    try{
      await apiPOST(API.dispatches.delete, { id });
      await loadDispatches();
      setDispStatus('Deleted.');
    } catch(e){ setDispStatus('Delete failed', false); }
  }

  function bindDispatchEdit(){
    const save=document.querySelector(SEL.dispSave);
    const cancel=document.querySelector(SEL.dispCancel);
    if(save){
      save.addEventListener('click', async ()=>{
        const id = Number(document.querySelector(SEL.dispId).value||0);
        const title = document.querySelector(SEL.dispTitleInput).value.trim();
        const html  = document.querySelector(SEL.dispEditor).innerHTML.trim();
        const image = document.querySelector(SEL.dispImageUrl).value.trim();
        if(!id || !title || !html){ alert('Title and content are required.'); return; }
        setDispStatus('Saving…');
        try{
          await apiPOST(API.dispatches.update,{ id, title, content_html:html, thumbnail_url:image });
          document.querySelector(SEL.dispDetail).classList.add('hidden');
          await loadDispatches();
          setDispStatus('Saved.');
        } catch(e){ setDispStatus('Save failed', false); }
      });
    }
    cancel?.addEventListener('click', ()=>{ document.querySelector(SEL.dispDetail).classList.add('hidden'); });
  }

  // ---------- Tabs & header ----------
  function bindTabs(){
    const btns=document.querySelectorAll(SEL.tabBtn);
    const tabs=document.querySelectorAll(SEL.tab);
    btns.forEach(b=>b.addEventListener('click',()=>{
      const t=b.getAttribute('data-tab');
      btns.forEach(x=>x.classList.toggle('active',x===b));
      tabs.forEach(x=>x.classList.toggle('active',x.id===`tab-${t}`));
      if(t==='users') loadUsers();
      if(t==='campaigns') loadCampaigns();
      renderAudit();
    }));
  }
  function bindHeader(){
    document.querySelector(SEL.btnLogout)?.addEventListener('click',()=>{ window.location.href='/auth/logout.php'; });
    document.querySelector(SEL.btnMax)?.addEventListener('click',()=>{ document.getElementById('admin-panel')?.classList.toggle('max'); });
    document.querySelector(SEL.btnClose)?.addEventListener('click',()=>{ window.location.href='/map.php'; });
  }

  function bindSearch(){
    const input=document.querySelector(SEL.userSearch); if(!input) return;
    const run=debounce(()=>{ state.users.search=input.value.trim(); state.users.page=1; loadUsers(); },300);
    input.addEventListener('input',run);
    input.addEventListener('keypress',e=>{ if(e.key==='Enter'){ state.users.search=input.value.trim(); state.users.page=1; loadUsers(); }});
  }

  // ---------- Init ----------
  window.addEventListener('DOMContentLoaded', async ()=>{
    bindHeader();
    bindTabs();
    bindSearch();
    bindCreateUser();
    bindUsersTable();
    bindUserDetail();

    bindCampaignsTable();
    bindCampaignDetail();

    if (document.querySelector(SEL.usersTable)) loadUsers();
    if (document.querySelector(SEL.campTable)) loadCampaigns();

    await loadUsersCache();
    renderAudit();
  });
})();

