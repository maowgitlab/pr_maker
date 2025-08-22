<?php
require_once __DIR__.'/../config.php';
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

// Ambil POST
$department   = trim($_POST['department'] ?? 'Divisi Logistik');
$purpose      = trim($_POST['purpose'] ?? 'Stokis Banjarmasin');
$dateYmd      = $_POST['date'] ?? date('Y-m-d');
$dateDisplay  = $_POST['date_display'] ?? date('d-M-y');
$prNumber     = trim($_POST['pr_number'] ?? '');

$items        = $_POST['item'] ?? [];
$descs        = $_POST['description'] ?? [];
$units        = $_POST['unit'] ?? [];
$qtys         = $_POST['qty'] ?? [];
$stocks       = $_POST['stock'] ?? [];
$deliveries   = $_POST['delivery_date'] ?? [];
$adds         = $_POST['additional_info'] ?? [];

// Kumpulkan baris
$rows = [];
for ($i=0; $i<count($items); $i++) {
  $item = trim($items[$i] ?? '');
  $desc = trim($descs[$i] ?? '');
  if ($item==='' && $desc==='') continue;

  $deliveryDisp = '';
  if (!empty($deliveries[$i])) $deliveryDisp = date('d-M-y', strtotime($deliveries[$i]));

  $rows[] = [
    'item'              => $item,
    'description'       => $desc,
    'unit'              => trim($units[$i] ?? ''),
    'qty'               => (int)($qtys[$i] ?? 0),
    'stock_on_hand'     => (int)($stocks[$i] ?? 0),
    'delivery_display'  => $deliveryDisp,
    'additional_info'   => trim($adds[$i] ?? ''),
  ];
}
if (empty($rows)) { die('Minimal 1 item diisi.'); }

// Simpan header
$stmt = $conn->prepare("INSERT INTO prs (pr_number,department,purpose,pr_date) VALUES (?,?,?,?)");
$stmt->bind_param('ssss',$prNumber,$department,$purpose,$dateYmd);
$stmt->execute();
$prId = $stmt->insert_id;

// Simpan detail
$itemStmt = $conn->prepare("INSERT INTO pr_items (pr_id,item_name,description,unit,qty,stock_on_hand,delivery_date,additional_info) VALUES (?,?,?,?,?,?,?,?)");
foreach ($rows as $r) {
  $dd = !empty($r['delivery_display']) ? date('Y-m-d', strtotime($r['delivery_display'])) : null;
  $itemStmt->bind_param('isssiiss',$prId,$r['item'],$r['description'],$r['unit'],$r['qty'],$r['stock_on_hand'],$dd,$r['additional_info']);
  $itemStmt->execute();
}

// Data untuk template
$head = [
  'department'   => $department,
  'purpose'      => $purpose,
  'date_display' => $dateDisplay,   // contoh: 16-Aug-25
  'pr_number'    => $prNumber
];

// === Path gambar ===
// 1) pakai path relatif (karena nanti kita set chroot ke __DIR__)
// 2) siapkan juga fallback data URI base64 jika diperlukan
$logoRel = 'assets/logo.png';
$signRel = 'assets/signature.png';

$logoAbs = realpath(__DIR__.'/'.$logoRel);
$signAbs = realpath(__DIR__.'/'.$signRel);

$logoDataUri = ($logoAbs && is_file($logoAbs))
  ? 'data:image/'.pathinfo($logoAbs, PATHINFO_EXTENSION).';base64,'.base64_encode(file_get_contents($logoAbs))
  : '';
$signDataUri = ($signAbs && is_file($signAbs))
  ? 'data:image/'.pathinfo($signAbs, PATHINFO_EXTENSION).';base64,'.base64_encode(file_get_contents($signAbs))
  : '';

// Render HTML dari template
ob_start();
$rowsForView = $rows;
$logoPath = $logoRel;   // utamakan path relatif (akan bekerja dengan chroot)
$signPath = $signRel;
$logoFallback = $logoDataUri; // jika path relatif gagal, template akan pakai ini
$signFallback = $signDataUri;
$head = $head;
include __DIR__.'/pdf_template.php';
$html = ob_get_clean();

// Siapkan DOMPDF
$generatedDir = __DIR__.'/generated';
if (!is_dir($generatedDir)) { @mkdir($generatedDir, 0775, true); }

if (is_file(__DIR__.'/vendor/autoload.php')) {
  require __DIR__.'/vendor/autoload.php';           // Composer
} else {
  require __DIR__.'/vendor/dompdf/autoload.inc.php';// Manual
}
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->setIsHtml5ParserEnabled(true);
$options->setIsRemoteEnabled(true);
// >>> KUNCI: kunci DOMPDF pada folder public agar path relatif aman
$options->setChroot(__DIR__);

$dompdf = new Dompdf($options);

// TIDAK lagi memakai set_base_path() (deprecated di analyzer)
$dompdf->loadHtml($html,'UTF-8');
$dompdf->setPaper('A4','portrait');
$dompdf->render();

// Simpan & kirim
$file = preg_replace('/[^A-Za-z0-9._-]/','_', $prNumber).'_'.date('Ymd_His').'.pdf';
$pdfPath = $generatedDir . '/' . $file;
file_put_contents($pdfPath, $dompdf->output());

// >>> SIMPAN JEJAK FILE KE DB <<<
$relPath = 'generated/'.$file; // path relatif dari folder public/
$up = $conn->prepare("UPDATE prs SET pdf_path=? WHERE id=?");
$up->bind_param('si', $relPath, $prId);
$up->execute();

if ($isAjax) {
  header('Content-Type: application/json');
  echo json_encode([
    'ok' => true,
    'id' => $prId,
    'pr_number' => $prNumber,
    'file' => $file,
    'url' => $relPath,
    'download' => 'download_pr.php?id='.$prId
  ]);
  exit;
} else {
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="'.$file.'"');
  readfile($pdfPath);
}



