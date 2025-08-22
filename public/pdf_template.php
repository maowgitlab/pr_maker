<?php
// Tersedia dari save_pr.php:
// $head, $rowsForView,
// $logoPath, $signPath  -> path relatif (contoh: assets/logo.png)
// $logoFallback, $signFallback -> data:image/png;base64,... (opsional)

function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Helper: render <img> dengan fallback base64 jika path relatif gagal
function imgTag($src, $fallback, $style='height:40px'){
  // jika src kosong dan ada fallback -> pakai fallback
  if (!$src && $fallback) return '<img src="'.$fallback.'" style="'.$style.'">';
  // tetap kirim src (relative), DOMPDF akan resolve via chroot
  return '<img src="'.$src.'" style="'.$style.'">';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 20px 15px 70px 15px; }
  body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; }
  .topbar { border-top: 2px solid #000; border-bottom: 2px solid #000; height: 6px; margin: 6px 0 12px 0; }
  .title { text-align: center; font-weight: bold; font-size: 16px; margin: 6px 0 8px 0; }
  .meta td { padding: 4px 6px; vertical-align: top; }
  .right { text-align:right; }
  .tbl { width:100%; border-collapse: collapse; }
  .tbl th, .tbl td { border:1px solid #000; padding:5px; }
  .tbl thead th { background:#f7d7b1; }
  .small { font-size:10px; }
  /* ROW tanda tangan tepat setelah tabel */
  .signrow { margin-top: 16px; display: table; width:100%; margin-left: 50px;}
  .signcell { display: table-cell; width:60%; vertical-align: top; }
  .purchcell { display: table-cell; width:40%; vertical-align: top; text-align:center; }
  .line { border-bottom:1px solid #000; margin: 120px 5px 4px; width:160px; }
</style>
</head>
<body>
  <table width="100%">
    <tr>
      <td><?= imgTag($logoPath ?? '', $logoFallback ?? '', 'height:40px') ?></td>
      <td class="right"><b>Nomor Dokumen : F.SOP.PURC-01-4</b></td>
    </tr>
  </table>
  <div class="topbar"></div>
  <div class="title">Purchase Request</div>

  <table class="meta" width="100%">
    <tr>
      <td width="50%"><b>Requesting Department</b> : <?= safe($head['department'] ?? '') ?></td>
      <td width="50%" class="right"><b>Date</b> : <?= safe($head['date_display'] ?? '') ?></td>
    </tr>
    <tr>
      <td><b>Purpose</b> : <?= safe($head['purpose'] ?? '') ?></td>
      <td class="right"><b>No PR</b> : <?= safe($head['pr_number'] ?? '') ?></td>
    </tr>
  </table>

  <table class="tbl small" style="margin-top:8px">
    <thead>
      <tr>
        <th width="4%">No</th>
        <th width="17%">Item</th>
        <th width="32%">Description / Specification</th>
        <th width="7%">Unit</th>
        <th width="7%">Qty</th>
        <th width="13%">Stock on Hand</th>
        <th width="12%">Delivery Date</th>
        <th width="18%">Additional Information</th>
      </tr>
    </thead>
    <tbody>
      <?php $no=1; foreach (($rowsForView ?? []) as $r): ?>
        <tr>
          <td align="center"><?= $no++ ?></td>
          <td><?= safe($r['item'] ?? '') ?></td>
          <td><?= safe($r['description'] ?? '') ?></td>
          <td align="center"><?= safe($r['unit'] ?? '') ?></td>
          <td align="center"><?= (int)($r['qty'] ?? 0) ?></td>
          <td align="center"><?= (int)($r['stock_on_hand'] ?? 0) ?></td>
          <td align="center"><?= safe($r['delivery_display'] ?? '') ?></td>
          <td><?= safe($r['additional_info'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php for($k=$no; $k<=13; $k++): ?>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
      <?php endfor; ?>
    </tbody>
  </table>

<div class="signrow">
  <div class="signcell">
    <?= imgTag($signPath ?? '', $signFallback ?? '', 'height:150px') ?>
  </div>

  <div class="purchcell">
    <div class="line"></div>
    <div class="small" style="margin-right: 135px;">Purchasing</div>
  </div>
</div>

</body>
</html>
