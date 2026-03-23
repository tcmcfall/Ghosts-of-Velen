<?php
// panels/character-picker.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';

// If a character is already selected, go back to the map.
if (!empty($_SESSION['character_id'])) {
    header('Location: /map.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Select Character</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/global.css">
  <style>
    .picker-wrap { max-width: 720px; margin: 4rem auto; padding: 1.5rem; }
    .picker-card { border: 1px solid rgba(0,0,0,.15); border-radius: .75rem; padding: 1.5rem; background: #fff; }
    .picker-actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1rem; }
    .btn { display: inline-block; padding: .6rem .9rem; border-radius: .5rem; text-decoration: none; border: 1px solid transparent; }
    .btn-primary { background: #0a328c; color: #fff; }
    .btn-secondary { background: #f5f5f7; color: #111; border-color: rgba(0,0,0,.15); }
    .hint { color: #555; margin-top: .5rem; }
  </style>
</head>
<body>
  <main class="container picker-wrap">
    <div class="picker-card" role="region" aria-labelledby="picker-title">
      <h1 id="picker-title">Select Your Character</h1>
      <p>
        You must choose a character for this campaign before accessing this page.
        Use the campaign selector to pick one of your characters or create a new one.
      </p>

      <div class="picker-actions">
        <a class="btn btn-primary" href="/auth/select_campaign.php">Open Campaign Selector</a>
        <a class="btn btn-secondary" href="/map.php">Back to Map</a>
      </div>

      <p class="hint">
        If you still do not see your character, confirm that you are assigned to the correct campaign.
      </p>

      <noscript>
        <p class="hint"><strong>JavaScript is not required</strong> for the campaign selector. You can continue directly to the
        <a href="/auth/select_campaign.php">campaign selection page</a>.</p>
      </noscript>
    </div>
  </main>
</body>
</html>

