<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FTS Wizards</title>
  <style>
    body {
      margin: 0;
      font-family: "Trebuchet MS", "Segoe UI", Arial, sans-serif;
      background: #0b1f2e;
      color: #e9eef3;
    }
    .wrap {
      max-width: 720px;
      margin: 10vh auto;
      padding: 24px;
      background: #132b3d;
      border: 1px solid #2a4a62;
      border-radius: 12px;
      box-shadow: 0 14px 34px rgba(0, 0, 0, 0.35);
    }
    h1 { margin-top: 0; }
    code {
      background: #0c2231;
      border: 1px solid #29475d;
      border-radius: 6px;
      padding: 2px 6px;
    }
    a { color: #9ed8ff; }
  </style>
</head>
<body>
  <main class="wrap">
    <h1>FTS Wizards Alpha</h1>
    <p>Initial hosted deployment path is active.</p>
    <p>API health endpoint: <a href="/api/fts/health.php"><code>/api/fts/health.php</code></a></p>
  </main>
</body>
</html>

