<?php require_once __DIR__ . '/../config.php'; ?>
<?php
date_default_timezone_set('Asia/Jakarta');

/* =========================
   PARAMETER & FILTER
   ========================= */
$kw    = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$off   = ($page - 1) * $limit;

$where  = '1=1';
$params = [];
$types  = '';

if ($kw !== '') {
  $where .= " AND (pr_number LIKE CONCAT('%',?,'%')
               OR department LIKE CONCAT('%',?,'%')
               OR purpose LIKE CONCAT('%',?,'%'))";
  $params[] = $kw;
  $params[] = $kw;
  $params[] = $kw;
  $types   .= 'sss';
}

/* =========================
   TOTAL ROWS
   ========================= */
$sqlCnt = "SELECT COUNT(*) c FROM prs WHERE $where";
$stmt   = $conn->prepare($sqlCnt);
if ($types) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total  = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

/* =========================
   DATA PAGE INI
   ========================= */
$sql = "SELECT id, pr_number, department, purpose, pr_date, pdf_path, created_at
        FROM prs
        WHERE $where
        ORDER BY id DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($types) {
  $types2  = $types . 'ii';
  $params2 = array_merge($params, [$limit, $off]);   // unpack harus terakhir
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param('ii', $limit, $off);
}
$stmt->execute();
$list  = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pages = max(1, (int)ceil($total / $limit));

/* =========================
   PARTIAL RENDER (AJAX)
   ========================= */
function render_table_fragment(array $list, int $off, int $total, int $page, int $pages, string $kw)
{
  ob_start(); ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-100 text-gray-700">
        <tr>
          <th class="p-2 border">#</th>
          <th class="p-2 border">No PR</th>
          <th class="p-2 border">Department</th>
          <th class="p-2 border">Purpose</th>
          <th class="p-2 border">PR Date</th>
          <th class="p-2 border">Generated</th>
          <th class="p-2 border">File</th>
          <th class="p-2 border">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$list): ?>
          <tr>
            <td colspan="8" class="p-6 text-center text-gray-500">Belum ada data.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($list as $i => $r): ?>
            <tr class="odd:bg-white even:bg-gray-50">
              <td class="p-2 border text-center"><?= ($off + $i + 1) ?></td>
              <td class="p-2 border font-medium"><?= htmlspecialchars($r['pr_number']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($r['department']) ?></td>
              <td class="p-2 border"><?= htmlspecialchars($r['purpose']) ?></td>
              <td class="p-2 border text-center"><?= htmlspecialchars($r['pr_date']) ?></td>
              <td class="p-2 border text-center">
                <?= htmlspecialchars(date('Y-m-d H:i', strtotime($r['created_at']))) ?>
              </td>
              <td class="p-2 border">
                <?php if (!empty($r['pdf_path'])): ?>
                  <a class="text-blue-600 underline" href="<?= htmlspecialchars($r['pdf_path']) ?>" target="_blank">Lihat</a>
                <?php else: ?>
                  <span class="text-gray-400">—</span>
                <?php endif; ?>
              </td>
              <td class="p-2 border">
                <button class="btn btn-danger" onclick="del(<?= (int)$r['id'] ?>,'<?= htmlspecialchars($r['pr_number'], ENT_QUOTES) ?>')">Hapus</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="flex items-center justify-between p-3">
    <div class="text-xs text-gray-500">Total: <?= $total ?> • Halaman <?= $page ?> / <?= $pages ?></div>
    <div class="flex gap-2">
      <?php
      // rakit query base utk pagination ajax
      $qPrev = ['q' => $kw, 'page' => max(1, $page - 1), 'partial' => 1];
      $qNext = ['q' => $kw, 'page' => min($pages, $page + 1), 'partial' => 1];
      ?>
      <?php if ($page > 1): ?>
        <a class="btn btn-ghost pg" data-href="?<?= http_build_query($qPrev) ?>">« Prev</a>
      <?php endif; ?>
      <?php if ($page < $pages): ?>
        <a class="btn btn-ghost pg" data-href="?<?= http_build_query($qNext) ?>">Next »</a>
      <?php endif; ?>
    </div>
  </div>
<?php
  return ob_get_clean();
}

if (isset($_GET['partial']) && $_GET['partial'] === '1') {
  // kembalikan hanya fragment tabel + pagination
  echo render_table_fragment($list, $off, $total, $page, $pages, $kw);
  exit;
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>History PR - EBM</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .container {
      max-width: 1000px
    }

    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(15, 23, 42, .04)
    }

    .input {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: .6rem .9rem;
      outline: none;
      width: 100%
    }

    .btn {
      border-radius: 12px;
      padding: .55rem .9rem;
      font-weight: 600
    }

    .btn-primary {
      background: #111827;
      color: #fff
    }

    .btn-ghost {
      background: #f3f4f6;
      color: #111827
    }

    .btn-danger {
      background: #b91c1c;
      color: #fff
    }

    .btn:hover {
      opacity: .95
    }

    th,
    td {
      vertical-align: middle
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800">
  <header class="border-b bg-white">
    <div class="container mx-auto px-4 py-4 flex items-center gap-3">
      <a href="index.php" class="btn btn-ghost">← Kembali</a>
      <h1 class="text-lg font-semibold">History Generate PR</h1>
    </div>
  </header>

  <main class="container mx-auto px-4 py-6 space-y-4">
    <!-- Search -->
    <form class="card p-4" method="get" onsubmit="return false;">
      <div class="flex gap-2 items-center">
        <input class="input" name="q" id="searchBox" placeholder="Cari PR Number / Department / Purpose" value="<?= htmlspecialchars($kw) ?>">
        <button class="btn btn-primary" id="btnSearch">Cari</button>
        <?php if ($kw !== ''): ?><a class="btn btn-ghost" href="history.php">Reset</a><?php endif; ?>
      </div>
      <p class="text-xs text-gray-500 mt-2">Live search aktif: ketik, hasil akan muncul otomatis.</p>
    </form>

    <!-- List Container (akan direplace via AJAX) -->
    <section class="card p-0 overflow-hidden" id="listContainer">
      <?= render_table_fragment($list, $off, $total, $page, $pages, $kw) ?>
    </section>
  </main>

  <script>
    // ===== Live search (debounce) =====
    const qInput = document.getElementById('searchBox');
    const btnSearch = document.getElementById('btnSearch');
    const listContainer = document.getElementById('listContainer');

    let tmr = null;

    function fetchList(url) {
      if (!url) {
        const q = (qInput?.value || '').trim();
        const u = new URL(location.href);
        u.searchParams.set('partial', '1');
        u.searchParams.set('page', '1');
        if (q) u.searchParams.set('q', q);
        else u.searchParams.delete('q');
        url = u.pathname + '?' + u.searchParams.toString();
      }
      fetch(url, {
          headers: {
            'X-Requested-With': 'fetch'
          }
        })
        .then(r => r.text())
        .then(html => {
          listContainer.innerHTML = html;
          // re-bind pagination ajax
          listContainer.querySelectorAll('a.pg').forEach(a => {
            a.addEventListener('click', (e) => {
              e.preventDefault();
              const href = a.getAttribute('data-href');
              if (href) fetchList(href);
            });
          });
        })
        .catch(() => {
          /* noop */ });
    }

    if (qInput) {
      qInput.addEventListener('input', () => {
        clearTimeout(tmr);
        tmr = setTimeout(() => fetchList(), 280);
      });
    }
    if (btnSearch) {
      btnSearch.addEventListener('click', () => fetchList());
    }

    // ===== Delete (tanpa reload penuh) =====
    function del(id, pr) {
      Swal.fire({
        title: 'Hapus PR ini?',
        html: `<div class="text-left text-sm">No PR: <b>${pr}</b><br>File PDF dan data terkait akan dihapus.</div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal'
      }).then(async (res) => {
        if (!res.isConfirmed) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
          const r = await fetch('delete_pr.php', {
            method: 'POST',
            body: fd
          });
          const j = await r.json();
          if (j.ok) {
            Swal.fire({
              icon: 'success',
              title: 'Terhapus'
            }).then(() => fetchList());
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Gagal',
              text: j.msg || 'Tidak diketahui'
            });
          }
        } catch (e) {
          Swal.fire({
            icon: 'error',
            title: 'Error jaringan'
          });
        }
      });
    }
    window.del = del; // global
  </script>
</body>

</html>