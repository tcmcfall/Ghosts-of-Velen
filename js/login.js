(function () {
  // --- Random splash image (1..10), rotating every 30 seconds without immediate repeats ---
  var imgEl = document.getElementById('splash-image');
  if (imgEl) {
    var basePath = '../assets/img/login/';
    var prefix = 'gov_splash_';
    var ext = '.png';
    var total = 10;
    var key = 'gov_lastSplash';
    var rotationDelayMs = 30000;
    var filenames = [];
    var current = null;
    var rotationTimer = null;

    function pad2(n) { return String(n).padStart(2, '0'); }
    function buildPath(filename) { return basePath + filename; }

    for (var i = 1; i <= total; i++) {
      filenames.push(prefix + pad2(i) + ext);
    }

    function readLastSplash() {
      try { return localStorage.getItem(key) || null; } catch (e) {}
      return null;
    }

    function persistSplash(filename) {
      try { localStorage.setItem(key, filename); } catch (e) {}
    }

    function chooseRandomSplash(excluded) {
      var candidates = filenames.filter(function (filename) {
        return filename !== excluded;
      });

      if (candidates.length === 0) {
        return excluded || filenames[0] || null;
      }

      return candidates[Math.floor(Math.random() * candidates.length)];
    }

    function applySplash(filename) {
      if (!filename) {
        return;
      }

      current = filename;
      imgEl.src = buildPath(filename);
      persistSplash(filename);
    }

    function rotateSplash() {
      if (filenames.length <= 1) {
        return;
      }

      applySplash(chooseRandomSplash(current));
    }

    filenames.forEach(function (filename) {
      var preload = new Image();
      preload.src = buildPath(filename);
    });

    applySplash(chooseRandomSplash(readLastSplash()));
    rotationTimer = window.setInterval(rotateSplash, rotationDelayMs);

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        if (rotationTimer !== null) {
          window.clearInterval(rotationTimer);
          rotationTimer = null;
        }
        return;
      }

      if (rotationTimer === null && filenames.length > 1) {
        rotateSplash();
        rotationTimer = window.setInterval(rotateSplash, rotationDelayMs);
      }
    });
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
