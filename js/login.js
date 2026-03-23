(function () {
  // --- Random splash image (1..10) excluding the previously displayed one ---
  var imgEl = document.getElementById('splash-image');
  if (imgEl) {
    var basePath = '../assets/img/login/';
    var prefix = 'gov_splash_';
    var ext = '.png';
    var total = 10;
    var key = 'gov_lastSplash';

    function pad2(n){ return String(n).padStart(2,'0'); }

    var last = null;
    try { last = localStorage.getItem(key) || null; } catch (e) {}

    var candidates = [];
    for (var i = 1; i <= total; i++) {
      var fname = prefix + pad2(i) + ext;
      if (fname !== last) candidates.push(fname);
    }

    var chosen = candidates[Math.floor(Math.random() * candidates.length)];
    imgEl.src = basePath + chosen;

    try { localStorage.setItem(key, chosen); } catch (e) {}
  }

  // --- Dispatch drawer: toggle + fetch entries on open ---
  var drawer = document.getElementById('blog-drawer');
  var btn = document.getElementById('hamburger-btn');
  var content = document.getElementById('blog-drawer-content');

  function render(items) {
    if (!content) return;
    if (!Array.isArray(items) || items.length === 0) {
      content.innerHTML = '<p>No dispatches yet.</p>';
      return;
    }
    var frag = document.createDocumentFragment();
    items.forEach(function (it) {
      var card = document.createElement('article');
      card.className = 'blog-entry';

      var h3 = document.createElement('h3');
      h3.textContent = it.title || 'Untitled';
      card.appendChild(h3);

      var when = document.createElement('time');
      when.dateTime = it.created_at || '';
      when.textContent = it.created_at ? new Date(it.created_at.replace(' ', 'T')).toLocaleString() : '';
      card.appendChild(when);

      var body = document.createElement('div');
      body.className = 'content';
      body.innerHTML = it.content_html || '';
      card.appendChild(body);

      frag.appendChild(card);
    });
    content.innerHTML = '';
    content.appendChild(frag);
  }

  function loadDispatches() {
    if (!content) return;
    fetch('./dispatches_feed.php', { credentials: 'same-origin' })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(new Error('Feed HTTP ' + res.status)); })
      .then(render)
      .catch(function () { content.innerHTML = '<p>Unable to load dispatches.</p>'; });
  }

  function openDrawer() {
    if (!drawer || !btn) return;
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    btn.setAttribute('aria-expanded', 'true');
    loadDispatches(); // refresh on each open
  }
  function closeDrawer() {
    if (!drawer || !btn) return;
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    btn.setAttribute('aria-expanded', 'false');
  }

  if (btn && drawer) {
    btn.addEventListener('click', function () {
      if (drawer.classList.contains('open')) { closeDrawer(); } else { openDrawer(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDrawer();
    });
  }
})();
