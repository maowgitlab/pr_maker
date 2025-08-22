<?php
date_default_timezone_set('Asia/Jakarta');

$DB_HOST='localhost'; $DB_USER='root'; $DB_PASS=''; $DB_NAME='pr_maker';
$conn = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($conn->connect_errno) { http_response_code(500); die('Gagal konek DB: '.$conn->connect_error); }
