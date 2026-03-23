<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/bootstrap.php';
require_once __DIR__ . '/auth/auth_check.php';
?>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link href="https://fonts.googleapis.com/css2?family=Bilbo&family=Jim+Nightshade&family=Quattrocento&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Ghosts of Velen</title>
  <link rel="stylesheet" href="css/map.css" />
  <link rel="stylesheet" href="css/global.css" />
  <link rel="stylesheet" href="css/sepia_animations.css" />

  <link rel="stylesheet" href="css/calendar-panel.css">
  <script src="js/calendar-panel.js" defer></script>
  <link rel="stylesheet" href="css/compendium-panel.css">
  <link rel="stylesheet" href="css/journal-panel.css">
</head>
<body>
  <div id="map-root">
    <div id="map-visual-wrapper">
      <div id="map-border">
        <!-- Site menu is now scoped to the map border so it aligns with the map’s top border -->
        <nav id="site-menu" aria-label="Primary">
          <a href="#" onclick="openCompendiumPanel(); return false;">Compendium</a>
          <a href="#" onclick="openCalendarPanel(); return false;">Calendar</a>
          <a href="#" onclick="openWeatherPanel(); return false;">Weather</a>
          <a href="#" onclick="openTradePanel(); return false;">Trade</a>
          <a href="#" onclick="openJournalPanel(); return false;">Journal</a>
        </nav>

        <div id="map-inner">
          <div id="clouds-layer"></div>
          <img id="main-map" src="assets/img/region_map.webp" alt="Region Map" />
        </div>

        <!-- Audio Controls -->
        <div id="audio-controls">
          <button id="mute-btn">Mute</button>
          <input type="range" id="volume-slider" min="0" max="1" step="0.01" value="0.10" />
        </div>
      </div>
    </div>

    <img id="world-overlay" class="overlay hidden" alt="Region Overlay" />
    <div id="region-overlay" class="hidden"></div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js"></script>
  <script src="js/main.js"></script>
  <script src="js/compendium-panel.js"></script>
  <script src="js/journal-panel.js"></script>
  <script src="js/trade-panel.js"></script>
  <script src="js/weather-panel.js"></script>
</body>
</html>

