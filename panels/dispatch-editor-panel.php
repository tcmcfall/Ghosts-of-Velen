<?php
// panels/dispatch-editor-panel.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../includes/acl.php';
require_once __DIR__ . '/../includes/csrf.php';

$role = (string)($_SESSION['role'] ?? 'user');
$canManageDispatches = isAdmin() || !empty($_SESSION['is_dm']);
if (!$canManageDispatches) { http_response_code(403); exit('Dispatch editors only.'); }

$csrf_safe   = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$appEnv      = htmlspecialchars((string)($_ENV['APP_ENV'] ?? 'prod'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$phpVersion  = htmlspecialchars(PHP_VERSION, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// API endpoints (dispatches)
$API = [
  'dispatches' => [
    'list'   => '../auth/api/dispatches/list.php',
    'get'    => '../auth/api/dispatches/get.php',
    'create' => '../auth/api/dispatches/create.php',
    'update' => '../auth/api/dispatches/update.php',
    'delete' => '../auth/api/dispatches/delete.php',
    'upload' => '../auth/api/dispatches/upload.php',
  ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Dispatch Editor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo $csrf_safe; ?>">
  <link rel="stylesheet" href="../css/global.css" />
  <link rel="stylesheet" href="../css/dispatch-editor-panel.css" />
  <script>
    window.CSRF_TOKEN = "<?php echo $csrf_safe; ?>";
    window.API = <?php echo json_encode($API, JSON_UNESCAPED_SLASHES); ?>;
    window.GOV = { appEnv:"<?php echo $appEnv; ?>", phpVersion:"<?php echo $phpVersion; ?>" };
    window.USER_ROLE = "<?php echo htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>";
  </script>
  <script src="../js/dispatch-editor-panel.js" defer></script>
</head>
<body class="dispatch-editor-body">
  <div id="dispatch-editor-root">
    <header class="de-header">
      <h1>Dispatch Editor</h1>
      <nav class="de-actions">
        <a href="/map.php" class="btn">Back to Map</a>
      </nav>
    </header>

    <main class="de-main">
      <!-- LEFT: Create / Edit -->
      <section class="de-compose" aria-label="Compose Dispatch">
        <div class="card">
          <div class="row gap-sm">
            <label class="block">Title
              <input id="de-title" type="text" placeholder="Enter dispatch title" autocomplete="off" />
            </label>
            <label class="block">Thumbnail (optional, = 500×500)
              <!-- Accept URL or upload; if a file is chosen, upload endpoint stores and returns a URL -->
              <div class="thumb-row">
                <input id="de-thumb-url" type="url" placeholder="Thumbnail URL (optional)" />
                <input id="de-thumb-file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" />
                <button id="de-thumb-upload" type="button" class="ghost">Upload Thumb</button>
              </div>
              <small class="muted">Admins may upload any supported size. Campaign DMs are limited to 5 MB per file.</small>
            </label>
          </div>

          <!-- Toolbar -->
          <div class="toolbar" id="de-toolbar" aria-label="Formatting">
            <button type="button" data-cmd="bold">Bold</button>
            <button type="button" data-cmd="italic">Italic</button>
            <button type="button" data-cmd="underline">Underline</button>
            <button type="button" data-cmd="insertUnorderedList">Bulleted List</button>
            <button type="button" data-cmd="insertOrderedList">Numbered List</button>
            <button type="button" data-action="link">Link</button>
            <button type="button" data-action="image">Image</button>
            <button type="button" data-action="video">Video</button>
          </div>

          <!-- Editor -->
          <div id="de-editor" class="editor" contenteditable="true" spellcheck="true" aria-label="Dispatch content"></div>

          <!-- Publish/Save -->
          <div class="row gap-sm mt-sm">
            <button id="de-publish" class="primary">Publish</button>
            <button id="de-clear" class="ghost">Clear</button>
            <div id="de-status" class="status">Ready.</div>
          </div>
        </div>

        <!-- Edit panel (populates when clicking an item on the right) -->
        <div id="de-edit-card" class="card hidden">
          <h2>Edit Dispatch</h2>
          <input type="hidden" id="edit-id" />
          <label class="block">Title
            <input id="edit-title" type="text" placeholder="Dispatch title" />
          </label>

          <div class="toolbar" id="edit-toolbar">
            <button type="button" data-cmd="bold">Bold</button>
            <button type="button" data-cmd="italic">Italic</button>
            <button type="button" data-cmd="underline">Underline</button>
            <button type="button" data-cmd="insertUnorderedList">Bulleted List</button>
            <button type="button" data-cmd="insertOrderedList">Numbered List</button>
            <button type="button" data-action="link">Link</button>
            <button type="button" data-action="image">Image</button>
            <button type="button" data-action="video">Video</button>
          </div>

          <div id="edit-editor" class="editor" contenteditable="true" spellcheck="true" aria-label="Dispatch content"></div>

          <div class="row gap-sm mt-sm">
            <label class="block grow">Thumbnail URL
              <input id="edit-thumb-url" type="url" placeholder="Optional thumbnail URL" />
            </label>
            <input id="edit-thumb-file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" />
            <button id="edit-thumb-upload" type="button" class="ghost">Upload Thumb</button>
          </div>

          <div class="row gap-sm mt-sm">
            <button id="edit-save" class="primary">Save Changes</button>
            <button id="edit-cancel" class="ghost">Cancel</button>
            <button id="edit-delete" class="danger ghost">Delete</button>
            <div id="edit-status" class="status">Ready.</div>
          </div>
        </div>
      </section>

      <!-- RIGHT: List -->
      <aside class="de-list" aria-label="Existing Dispatches">
        <div class="card">
          <div class="row gap-sm">
            <input id="list-search" type="search" placeholder="Search dispatches..." />
            <select id="list-sort">
              <option value="created_at">Sort by Created</option>
              <option value="updated_at">Sort by Updated</option>
              <option value="title">Sort by Title</option>
            </select>
            <select id="list-dir">
              <option value="desc">Newest first</option>
              <option value="asc">Oldest first</option>
            </select>
          </div>
          <div id="list-wrap" class="list-wrap"></div>
        </div>
      </aside>
    </main>
  </div>
</body>
</html>

