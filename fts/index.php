<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$isAuthenticated = isset($_SESSION['user_id'], $_SESSION['username']);
$username = $isAuthenticated ? (string)$_SESSION['username'] : '';
$primaryHref = $isAuthenticated ? '/map.php' : '/auth/login.php';
$primaryLabel = $isAuthenticated ? 'Enter The Regional Map' : 'Sign In To Begin';
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
    <section id="fts-shell" aria-label="Fantasy Trade Simulator landing">
      <div id="fts-hero-border">
        <div id="fts-hero-inner">
          <img id="fts-hero-art" src="../assets/img/banner_pirate_battle.png" alt="Ghosts of Velen maritime battle banner">
          <div id="fts-hero-overlay"></div>
          <img id="fts-title-image" src="../assets/img/GoV_title_banner_textured.png" alt="Ghosts of Velen">
          <nav id="fts-site-nav" aria-label="Primary shortcuts">
            <a href="/">Home</a>
            <a href="/map.php">Map</a>
            <a href="/auth/login.php">Login</a>
          </nav>
        </div>
      </div>

      <section id="fts-parchment-panel">
        <header id="fts-heading-row">
          <div>
            <h1>Fantasy Trade Simulator</h1>
            <p class="subtitle">Hosted Wizard Workbench</p>
          </div>
          <div class="meta-grid" aria-label="System status">
            <span class="meta-chip">Alpha v0.1.0</span>
            <span id="fts-health" class="meta-chip status-warn">Checking API</span>
            <span id="fts-php-version" class="meta-chip">PHP Pending</span>
          </div>
        </header>

        <?php if ($isAuthenticated): ?>
          <p class="welcome-line">Welcome back, <?php echo htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>.</p>
        <?php endif; ?>

        <p>
          Build and validate FTS campaign entities in a setting-native authoring flow, then export deterministic
          toolkit-compatible payloads for runtime consumption.
        </p>

        <div id="fts-actions">
          <a class="action-primary" href="<?php echo htmlspecialchars($primaryHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($primaryLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
          </a>
          <a class="action-secondary" href="/api/fts/health.php">View API Health JSON</a>
        </div>
      </section>
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
