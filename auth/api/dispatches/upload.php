<?php
// auth/api/dispatches/upload.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/acl.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate_request()) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Bad request']);
  exit;
}

$isAdminUser = isAdmin();
$canManageDispatches = $isAdminUser || !empty($_SESSION['is_dm']);
if (!$canManageDispatches) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit; }

$kind = (string)($_POST['kind'] ?? '');
$title = trim((string)($_POST['title'] ?? 'untitled'));

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'No file']); exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Upload error']); exit; }

if (!$isAdminUser && $file['size'] > 5*1024*1024) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Upload limit is 5 MB']); exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

$allowedImg = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'];
$allowedVid = ['video/mp4'=>'mp4','video/webm'=>'webm','video/ogg'=>'ogv'];

$ext = null;
$isImage = isset($allowedImg[$mime]);
$isVideo = isset($allowedVid[$mime]);

if ($kind === 'thumbnail') {
  if (!$isImage) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Thumbnail must be an image']); exit; }
  $ext = $allowedImg[$mime];
} elseif ($kind === 'image') {
  if (!$isImage) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Unsupported image type']); exit; }
  $ext = $allowedImg[$mime];
} elseif ($kind === 'video') {
  if (!$isVideo) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Unsupported video type']); exit; }
  $ext = $allowedVid[$mime];
} else {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid upload kind']); exit;
}

$slug = preg_replace('~[^a-z0-9]+~i','-', $title);
$slug = trim($slug, '-');
if ($slug === '') $slug = 'untitled';

$today = (new DateTime('now'))->format('Y-m-d');
$baseDir = realpath(__DIR__ . '/../../../..') . '/assets/dispatches/uploads';
$destDir = $baseDir . '/' . $slug . '-' . $today;

// ensure dir
if (!is_dir($destDir) && !@mkdir($destDir, 0775, true)) {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Cannot create upload directory']); exit;
}

$basename = bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = $destDir . '/' . $basename;

// Process thumbnail: resize to max 500x500
if ($kind === 'thumbnail' && $isImage) {
  if (!function_exists('imagecreatefromstring')) {
    // fallback: move without resizing
    if (!move_uploaded_file($file['tmp_name'], $destPath)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Save failed']); exit; }
  } else {
    $data = file_get_contents($file['tmp_name']);
    $src = @imagecreatefromstring($data);
    if (!$src) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid image']); exit; }
    $w = imagesx($src); $h = imagesy($src);
    $max = 500.0;
    $scale = min(1.0, $max / max($w,$h));
    $nw = (int)floor($w * $scale);
    $nh = (int)floor($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh, $w,$h);
    switch ($ext) {
      case 'png': imagepng($dst, $destPath, 6); break;
      case 'webp': imagewebp($dst, $destPath, 90); break;
      case 'gif': imagegif($dst, $destPath); break;
      default: imagejpeg($dst, $destPath, 90); break;
    }
    imagedestroy($dst); imagedestroy($src);
    @unlink($file['tmp_name']);
  }
} else {
  if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Save failed']); exit;
  }
}

// Build public URL
$docRoot = realpath(__DIR__ . '/../../../..');
$public = str_replace($docRoot, '', $destPath);
$public = str_replace('\\', '/', $public);

echo json_encode(['ok'=>true, 'url'=>$public]);
