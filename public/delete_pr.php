<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID tidak valid']); exit; }

// Ambil info
$stmt = $conn->prepare("SELECT pdf_path FROM prs WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Data tidak ditemukan']); exit; }

// Hapus file jika ada & lokasinya benar (di public/generated/)
$deletedFile = false;
if (!empty($row['pdf_path'])) {
  // normalisasi path relatif
  $rel = str_replace('\\', '/', $row['pdf_path']);  // windows-safe
  $rel = ltrim($rel, '/');                          // buang leading slash
  if (strpos($rel, './') === 0) $rel = substr($rel, 2);

  // hanya izinkan yang diawali 'generated/'
  if (strpos($rel, 'generated/') === 0) {
    // KARENA file ada di dalam public/, kita tidak naik ke parent
    $abs = __DIR__ . '/' . $rel;                    // public/generated/...
    if (is_file($abs)) {
      @unlink($abs);
      $deletedFile = true;
    } else {
      // fallback realpath, plus guard agar tetap di folder generated
      $absReal = realpath($abs);
      $genDir  = realpath(__DIR__ . '/generated');
      if ($absReal && $genDir && strpos($absReal, $genDir) === 0 && is_file($absReal)) {
        @unlink($absReal);
        $deletedFile = true;
      }
    }
  }
}

// Hapus data (items ikut terhapus jika FK ON DELETE CASCADE)
$del = $conn->prepare("DELETE FROM prs WHERE id=?");
$del->bind_param('i', $id);
$del->execute();

echo json_encode(['ok'=>true,'file_deleted'=>$deletedFile]);
