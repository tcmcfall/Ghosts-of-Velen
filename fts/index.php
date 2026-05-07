<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$isAuthenticated = isset($_SESSION['user_id'], $_SESSION['username']);
$username = $isAuthenticated ? (string)$_SESSION['username'] : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ghosts of Velen FTS</title>
  <link href="https://fonts.googleapis.com/css2?family=Bilbo&family=Jim+Nightshade&family=Quattrocento:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="fts.css">
</head>
<body>
  <main id="fts-page">
    <section id="fts-folio" aria-label="Fantasy Trade Simulator landing">
      <div class="folio-paper">
        <header class="folio-header">
          <p class="kicker">Ghosts of Velen</p>
          <h1>Fantasy Trade Simulator</h1>
          <p class="subtitle">Hosted Wizard Workbench</p>
        </header>

        <?php if ($isAuthenticated): ?>
          <p class="welcome-line">Welcome back, <?php echo htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>.</p>
        <?php endif; ?>

        <div class="folio-divider" aria-hidden="true"><span></span></div>

        <div class="folio-grid">
          <section class="folio-copy" aria-label="Overview">
            <p>
              This workspace provides a campaign-safe authoring surface for FTS entities, trade narratives,
              and deterministic export artifacts.
            </p>
            <p>
              The hosted model keeps wizard data editable and auditable while preserving compatibility with
              runtime toolkit contracts.
            </p>
            <ul class="feature-list">
              <li>Schema-governed authoring for mapRegion, mapLocale, mapPoint, mapRoute, and mapAgent models.</li>
              <li>Deterministic export + hashing pipeline aligned to toolkit import behavior.</li>
              <li>Migration-backed data lifecycle with auditable operational events.</li>
            </ul>
          </section>

          <aside class="folio-status" aria-label="System status">
            <h2>System Status</h2>
            <div class="meta-grid">
              <span class="meta-chip">Alpha v0.1.0</span>
              <span id="fts-health" class="meta-chip status-warn">Checking API</span>
              <span id="fts-php-version" class="meta-chip">PHP Pending</span>
            </div>
            <p class="status-note">
              Health endpoint:
              <a href="/api/fts/health.php">/api/fts/health.php</a>
            </p>
          </aside>
        </div>
      </div>
    </section>
  </main>

  <script>
    (function () {
      var healthChip = document.getElementById('fts-health');
      var phpChip = document.getElementById('fts-php-version');

      function setState(text, className) {
        healthChip.textContent = text;
        healthChip.classList.remove('status-ok', 'status-warn', 'status-down');
        healthChip.classList.add(className);
      }

      fetch('/api/fts/health.php', { cache: 'no-store', credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.ok) {
            setState('API Online', 'status-ok');
          } else {
            setState('API Degraded', 'status-warn');
          }

          if (data && data.php_version) {
            phpChip.textContent = 'PHP ' + data.php_version;
          } else {
            phpChip.textContent = 'PHP Unknown';
          }
        })
        .catch(function () {
          setState('API Offline', 'status-down');
          phpChip.textContent = 'PHP Unknown';
        });
    }());
  </script>
</body>
</html>
