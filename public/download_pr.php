<?php
require_once __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$stmt = $conn->prepare("SELECT pdf_path, pr_number FROM prs WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row || empty($row['pdf_path'])) {
    http_response_code(404);
    exit('Not found');
}

$rel = str_replace('\\', '/', $row['pdf_path']);
$rel = ltrim($rel, '/');
if (strpos($rel, 'generated/') !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

$abs = __DIR__ . '/' . $rel; // public/generated/...
if (!is_file($abs)) {
    http_response_code(404);
    exit('File missing');
}

$fname = preg_replace('/[^A-Za-z0-9._-]/', '_', ($row['pr_number'] ?? 'PR')) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($abs));
header('Content-Disposition: attachment; filename="' . $fname . '"');
readfile($abs);
