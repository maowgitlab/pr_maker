<?php require_once __DIR__ . '/../config.php'; ?>
<?php
date_default_timezone_set('Asia/Jakarta');
$todayDisplay = date('d-M-y');   // contoh: 16-Aug-25
$todayCompact = date('dmY');     // contoh: 16082025

// cari suffix terakhir utk referensi (tidak dipakai default, hanya sebagai hint)
$lastSuffix = 0;
$stmt = $conn->prepare("
  SELECT SUBSTRING_INDEX(pr_number,'-',-1) AS suf
  FROM prs
  WHERE pr_number LIKE CONCAT('EBM.PR.LOGISTIK.', ?, '-%')
  ORDER BY id DESC LIMIT 1
");
$stmt->bind_param('s', $todayCompact);
$stmt->execute();
if ($r = $stmt->get_result()->fetch_assoc()) $lastSuffix = (int)$r['suf'];
$recommendSuffix = max(1, $lastSuffix + 1); // hanya rekomendasi (placeholder)

// base PR fix + tanggal
$prBase = "EBM.PR.LOGISTIK.{$todayCompact}-";
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PR Maker - EBM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Panel modal fleksibel & scrollable */
        .modal-panel {
            max-height: 90svh;
            /* pakai svh supaya aman di mobile browser */
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* biar header/footer sticky rapi */
        }

        /* area konten di dalam modal yang bisa discroll */
        .modal-body {
            overflow-y: auto;
        }

        /* cegah body scroll ketika modal terbuka */
        .body-locked {
            height: 100vh;
            overflow: hidden;
        }

        .container {
            max-width: 1000px;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
        }

        .input,
        .select {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: .6rem .9rem;
            outline: none;
            width: 100%;
        }

        .input:focus,
        .select:focus {
            border-color: #111827;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, .08);
        }

        .btn {
            border-radius: 12px;
            padding: .65rem 1rem;
            font-weight: 600;
        }

        .btn-primary {
            background: #111827;
            color: #fff;
        }

        .btn-primary:hover {
            background: #0b1220;
        }

        .btn-ghost {
            background: #f3f4f6;
            color: #111827;
        }

        .btn-ghost:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: #b91c1c;
            color: #fff;
        }

        .btn-danger:hover {
            background: #991b1b;
        }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .45);
            z-index: 50
        }

        .modal.open {
            display: flex
        }

        th,
        td {
            vertical-align: middle;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800">
    <!-- Header -->
    <header class="border-b border-gray-200 bg-white">
        <div class="container mx-auto px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:gap-4 gap-2">
            <!-- Logo -->
            <div class="flex justify-center sm:justify-start">
                <img src="./assets/logo.png" class="h-10 w-auto" alt="EBM">
            </div>

            <!-- Title & Subtitle -->
            <div class="flex-1 text-center sm:text-left">
                <h1 class="text-lg font-semibold tracking-tight">Purchase Request Maker</h1>
                <p class="text-sm text-gray-500">PT Evalinda Berkah Mandiri</p>
            </div>

            <!-- Right Section -->
            <div class="flex items-center justify-center sm:justify-end gap-2">
                <a href="history.php" class="btn btn-ghost">History</a>
                <span class="text-xs text-gray-500">v1.0</span>
            </div>
        </div>
    </header>


    <!-- Main -->
    <main class="container mx-auto px-4 py-8 space-y-6">
        <!-- Top Summary -->
        <section class="card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold">Buat Purchase Request</h2>
                    <p class="text-sm text-gray-500">Isi informasi PR, tambahkan item via modal, lalu generate PDF.</p>
                </div>
                <div class="text-sm text-gray-500">
                    Tanggal: <span class="font-medium"><?= htmlspecialchars($todayDisplay) ?></span>
                </div>
            </div>
        </section>

        <!-- Form -->
        <form id="prForm" action="save_pr.php" method="post" class="card p-6 space-y-8">
            <!-- Info Header -->
            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nomor Dokumen</label>
                    <input value="F.SOP.PURC-01-4" disabled class="input bg-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal (auto)</label>
                    <input name="date_display" value="<?= htmlspecialchars($todayDisplay) ?>" readonly class="input bg-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">No PR</label>
                    <div class="flex gap-2">
                        <input id="prBase" value="<?= htmlspecialchars($prBase) ?>" readonly class="input bg-gray-100 flex-1">
                        <!-- defaultnya KOSONG; placeholder isi rekomendasi -->
                        <input id="prSuffix" type="number" min="1" step="1" value="" placeholder="<?= $recommendSuffix ?>" class="input w-28 text-center">
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">Isi angka belakang (suffix) manual. Contoh saran: <?= $recommendSuffix ?>.</p>
                </div>
            </div>

            <!-- Dept & Purpose -->
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Requesting Department</label>
                    <input name="department" value="Divisi Logistik" class="input" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Purpose</label>
                    <input name="purpose" value="Stokis Banjarmasin" class="input" required>
                </div>
            </div>

            <!-- Items List -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Daftar Item</h3>
                    <div class="flex gap-2">
                        <button type="button" id="btnAdd" class="btn btn-primary">+ Tambah Item</button>
                        <button type="button" id="btnClear" class="btn btn-ghost">Bersihkan</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-2 border">No</th>
                                <th class="p-2 border">Item</th>
                                <th class="p-2 border">Description</th>
                                <th class="p-2 border">Unit</th>
                                <th class="p-2 border">Qty</th>
                                <th class="p-2 border">SOH</th>
                                <th class="p-2 border">Delivery</th>
                                <th class="p-2 border">Action</th>
                            </tr>
                        </thead>
                        <tbody id="listBody"></tbody>
                    </table>
                    <p class="text-xs text-gray-500 mt-2">Tambah/edit item lewat modal untuk pengalaman terbaik di HP.</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" id="btnPreview" class="btn btn-ghost">Preview Data</button>
                <button id="btnGenerate" class="btn btn-primary">Generate PR (PDF)</button>
            </div>

            <!-- hidden -->
            <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">
            <input type="hidden" id="prNumber" name="pr_number" value="">
        </form>
    </main>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-panel bg-white w-[95%] max-w-xl rounded-2xl border border-gray-200 shadow-xl">
            <!-- Header sticky -->
            <div class="px-5 py-4 flex items-center justify-between border-b sticky top-0 bg-white z-10">
                <h3 id="modalTitle" class="text-base font-semibold">Tambah Item</h3>
                <button id="btnClose" class="btn btn-ghost px-3 py-2">Tutup</button>
            </div>

            <!-- Body scrollable -->
            <div class="modal-body p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Item</label>
                    <input id="fItem" class="input" placeholder="Nama item">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Unit</label>
                    <input id="fUnit" class="input" placeholder="PCS/DUS">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description / Specification</label>
                    <input id="fDesc" class="input" placeholder="Spesifikasi">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Qty</label>
                    <input id="fQty" type="number" min="0" class="input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Stock on Hand</label>
                    <input id="fSOH" type="number" min="0" class="input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Delivery Date</label>
                    <input id="fDelivery" type="date" class="input">
                </div>
                <div class="sm:col-span-2 mb-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Additional Information</label>
                    <input id="fAdd" class="input" placeholder="">
                </div>
            </div>

            <!-- Footer sticky -->
            <div class="px-5 pb-5 pt-3 flex items-center justify-end gap-2 border-t sticky bottom-0 bg-white z-10">
                <button id="btnCancel" class="btn btn-ghost">Batal</button>
                <button id="btnSave" class="btn btn-primary">Simpan</button>
            </div>

            <input type="hidden" id="editingIndex" value="-1">
        </div>
    </div>

        <!-- Loading overlay -->
    <div id="genOverlay" class="fixed inset-0 hidden items-center justify-center bg-black/40 z-50">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-[90%] max-w-sm text-center">
            <div class="mx-auto mb-4 h-12 w-12 rounded-full border-4 border-gray-200 border-t-gray-800 animate-spin"></div>
            <h3 class="text-base font-semibold">Generating PDF…</h3>
            <p class="text-sm text-gray-500">Mohon tunggu, sedang memproses dokumen Anda.</p>
        </div>
    </div>

    <script>
        // ====== Helpers ======
        const modal = document.getElementById('modal');
        const openModal = () => {
            modal.classList.add('open');
            document.body.classList.add('body-locked'); // kunci scroll halaman
            // optional: fokus pertama
            setTimeout(() => document.getElementById('fItem')?.focus(), 50);
        };
        const closeModal = () => {
            modal.classList.remove('open');
            document.body.classList.remove('body-locked'); // buka lagi
            clearModal();
        };

        const fItem = document.getElementById('fItem');
        const fUnit = document.getElementById('fUnit');
        const fDesc = document.getElementById('fDesc');
        const fQty = document.getElementById('fQty');
        const fSOH = document.getElementById('fSOH');
        const fDel = document.getElementById('fDelivery');
        const fAdd = document.getElementById('fAdd');
        const editIdx = document.getElementById('editingIndex');
        const listBody = document.getElementById('listBody');

        function clearModal() {
            fItem.value = fUnit.value = fDesc.value = fAdd.value = '';
            fQty.value = fSOH.value = '';
            fDel.value = '';
            editIdx.value = -1;
            document.getElementById('modalTitle').innerText = 'Tambah Item';
        }

        function escapeHtml(s) {
            return (s || '').replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [m]));
        }

        function escapeAttr(s) {
            return escapeHtml(s);
        }

        // ====== Empty state ======
        function renderEmptyState() {
            if (listBody.children.length === 0) {
                const tr = document.createElement('tr');
                tr.id = 'emptyRow';
                tr.innerHTML = `<td colspan="8" class="border p-6 text-center text-gray-500">Belum ada item. Klik <b>+ Tambah Item</b> untuk menambahkan.</td>`;
                listBody.appendChild(tr);
            }
        }

        function clearEmptyState() {
            const er = document.getElementById('emptyRow');
            if (er) er.remove();
        }

        // ====== Tabel baris ======
        function addRow(data) {
            clearEmptyState();
            const idx = listBody.children.length;
            const tr = document.createElement('tr');
            tr.className = 'odd:bg-white even:bg-gray-50';
            tr.innerHTML = `
      <td class="border p-2 text-center">${idx+1}</td>
      <td class="border p-2">${escapeHtml(data.item)}</td>
      <td class="border p-2">${escapeHtml(data.description)}</td>
      <td class="border p-2 text-center">${escapeHtml(data.unit)}</td>
      <td class="border p-2 text-center">${data.qty||0}</td>
      <td class="border p-2 text-center">${data.soh||0}</td>
      <td class="border p-2 text-center">${data.delivery||''}</td>
      <td class="border p-2">
        <div class="flex gap-2">
          <button type="button" class="btn btn-ghost px-3 py-1 text-xs" onclick="editRow(${idx})">Edit</button>
          <button type="button" class="btn btn-danger px-3 py-1 text-xs" onclick="removeRow(${idx})">Hapus</button>
        </div>
      </td>

      <!-- hidden inputs untuk submit -->
      <input type="hidden" name="item[]" value="${escapeAttr(data.item)}">
      <input type="hidden" name="description[]" value="${escapeAttr(data.description)}">
      <input type="hidden" name="unit[]" value="${escapeAttr(data.unit)}">
      <input type="hidden" name="qty[]" value="${data.qty||0}">
      <input type="hidden" name="stock[]" value="${data.soh||0}">
      <input type="hidden" name="delivery_date[]" value="${data.delivery||''}">
      <input type="hidden" name="additional_info[]" value="${escapeAttr(data.add||'')}">
    `;
            listBody.appendChild(tr);
            renumberRows();
        }

        function renumberRows() {
            [...listBody.querySelectorAll('tr')].forEach((tr, i) => {
                const noCell = tr.querySelector('td:first-child');
                if (noCell) noCell.textContent = i + 1;
            });
        }

        window.removeRow = function(idx) {
            if (idx < 0 || idx >= listBody.children.length) return;
            listBody.removeChild(listBody.children[idx]);
            // setelah hapus, row index tombol edit/hapus bisa berubah → reset semua onclick
            [...listBody.querySelectorAll('tr')].forEach((tr, i) => {
                const actions = tr.querySelectorAll('button');
                if (actions.length >= 2) {
                    actions[0].setAttribute('onclick', `editRow(${i})`);
                    actions[1].setAttribute('onclick', `removeRow(${i})`);
                }
            });
            renumberRows();
            if (listBody.children.length === 0) renderEmptyState();
        }

        window.editRow = function(idx) {
            const tr = listBody.children[idx];
            if (!tr) return;
            const get = name => tr.querySelector(`input[name="${name}[]"]`)?.value || '';
            fItem.value = get('item');
            fDesc.value = get('description');
            fUnit.value = get('unit');
            fQty.value = get('qty');
            fSOH.value = get('stock');
            fDel.value = get('delivery_date');
            fAdd.value = get('additional_info');
            editIdx.value = idx;
            document.getElementById('modalTitle').innerText = 'Edit Item';
            openModal();
        }

        function updateRow(idx, data) {
            const tr = listBody.children[idx];
            if (!tr) return;
            tr.children[1].textContent = data.item;
            tr.children[2].textContent = data.description;
            tr.children[3].textContent = data.unit;
            tr.children[4].textContent = data.qty || 0;
            tr.children[5].textContent = data.soh || 0;
            tr.children[6].textContent = data.delivery || '';
            const set = (name, val) => {
                tr.querySelector(`input[name="${name}[]"]`).value = val;
            }
            set('item', data.item);
            set('description', data.description);
            set('unit', data.unit);
            set('qty', data.qty || 0);
            set('stock', data.soh || 0);
            set('delivery_date', data.delivery || '');
            set('additional_info', data.add || '');
        }

        // Modal events
        document.getElementById('btnAdd').onclick = () => {
            clearModal();
            openModal();
        }
        document.getElementById('btnClose').onclick = closeModal;
        document.getElementById('btnCancel').onclick = (e) => {
            e.preventDefault();
            closeModal();
        }

        document.getElementById('btnSave').onclick = (e) => {
            e.preventDefault();
            const data = {
                item: fItem.value.trim(),
                unit: fUnit.value.trim(),
                description: fDesc.value.trim(),
                qty: parseInt(fQty.value || '0', 10) || 0,
                soh: parseInt(fSOH.value || '0', 10) || 0,
                delivery: fDel.value,
                add: fAdd.value.trim()
            };
            if (!data.item && !data.description) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Lengkapi minimal Item atau Description'
                });
                return;
            }
            const idx = parseInt(editIdx.value, 10);
            if (idx >= 0) {
                updateRow(idx, data);
            } else {
                addRow(data);
            }
            closeModal();
        };

        // ====== PREVIEW (tetap sama dengan versi sebelumnya) ======
        function buildPreviewHTML() {
            const dept = document.querySelector('input[name="department"]').value || '-';
            const purpose = document.querySelector('input[name="purpose"]').value || '-';
            const date = document.querySelector('input[name="date_display"]').value || '-';
            const base = document.getElementById('prBase').value || '';
            const suf = (document.getElementById('prSuffix').value || '').trim();
            const prno = base + (suf ? suf : '…');

            const items = [...document.querySelectorAll('input[name="item[]"]')].map(i => i.value);
            const descs = [...document.querySelectorAll('input[name="description[]"]')].map(i => i.value);
            const units = [...document.querySelectorAll('input[name="unit[]"]')].map(i => i.value);
            const qtys = [...document.querySelectorAll('input[name="qty[]"]')].map(i => i.value);
            const sohs = [...document.querySelectorAll('input[name="stock[]"]')].map(i => i.value);
            const dels = [...document.querySelectorAll('input[name="delivery_date[]"]')].map(i => i.value);
            const adds = [...document.querySelectorAll('input[name="additional_info[]"]')].map(i => i.value);

            let rowsHtml = '';
            let count = 0;
            for (let i = 0; i < items.length; i++) {
                const has = (items[i] || descs[i] || units[i] || qtys[i] || sohs[i] || dels[i] || adds[i]);
                if (!has) continue;
                count++;
                rowsHtml += `
        <tr class="odd:bg-white even:bg-gray-50">
            <td class="border p-2 text-center">${count}</td>
            <td class="border p-2">${escapeHtml(items[i]||'')}</td>
            <td class="border p-2">${escapeHtml(descs[i]||'')}</td>
            <td class="border p-2 text-center">${escapeHtml(units[i]||'')}</td>
            <td class="border p-2 text-center">${qtys[i]||0}</td>
            <td class="border p-2 text-center">${sohs[i]||0}</td>
            <td class="border p-2 text-center">${dels[i]||''}</td>
            <td class="border p-2">${escapeHtml(adds[i]||'')}</td>
        </tr>`;
            }
            if (!rowsHtml) {
                rowsHtml = `<tr><td colspan="8" class="border p-4 text-center text-gray-500">Belum ada item.</td></tr>`;
            }

            // NOTE: min-w-[1000px] bikin tabel nggak “kepentok” dan bisa di-scroll horizontal.
            return `
        <div class="text-left">
        <div class="mb-3">
            <div class="text-[13px] text-gray-500">No PR</div>
            <div class="font-semibold">${escapeHtml(prno)}</div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div>
            <div class="text-[13px] text-gray-500">Requesting Department</div>
            <div class="font-medium">${escapeHtml(dept)}</div>
            </div>
            <div>
            <div class="text-[13px] text-gray-500">Date</div>
            <div class="font-medium">${escapeHtml(date)}</div>
            </div>
            <div class="sm:col-span-2">
            <div class="text-[13px] text-gray-500">Purpose</div>
            <div class="font-medium">${escapeHtml(purpose)}</div>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto max-h-[60vh]">
            <table class="w-full min-w-[1000px] text-[13px]">
                <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="p-2 border w-14">No</th>
                    <th class="p-2 border w-56">Item</th>
                    <th class="p-2 border w-[360px]">Description</th>
                    <th class="p-2 border w-24">Unit</th>
                    <th class="p-2 border w-24">Qty</th>
                    <th class="p-2 border w-28">SOH</th>
                    <th class="p-2 border w-36">Delivery</th>
                    <th class="p-2 border w-64">Additional</th>
                </tr>
                </thead>
                <tbody>${rowsHtml}</tbody>
            </table>
            </div>
        </div>
        </div>
    `;
        }


        document.getElementById('btnPreview').onclick = () => {
            Swal.fire({
                title: 'Preview PR',
                html: buildPreviewHTML(),
                width: Math.min(window.innerWidth * 0.95, 1000), // lebih lega
                confirmButtonText: 'Tutup',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'btn btn-primary'
                }
            });
        };


        // === AJAX submit untuk stabil di mobile + animasi ===
        const form = document.getElementById('prForm');
        const overlay = document.getElementById('genOverlay');
        const btnGen = document.getElementById('btnGenerate');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // validasi yang sudah ada
            const base = document.getElementById('prBase').value || '';
            let suf = (document.getElementById('prSuffix').value || '').trim();
            if (!suf) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Suffix No PR wajib diisi'
                });
                return;
            }
            if (!/^[0-9]+$/.test(suf) || parseInt(suf, 10) < 1) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Suffix harus angka >= 1'
                });
                return;
            }
            document.getElementById('prNumber').value = base + suf;

            const items = [...document.querySelectorAll('input[name="item[]"]')].map(i => i.value.trim());
            const descs = [...document.querySelectorAll('input[name="description[]"]')].map(i => i.value.trim());
            const hasOne = items.some((v, i) => v !== '' || (descs[i] || '') !== '');
            if (!hasOne) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tambahkan minimal 1 item sebelum generate PDF'
                });
                return;
            }

            // tampilkan overlay & disable tombol
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            btnGen?.setAttribute('disabled', 'disabled');
            btnGen?.classList.add('opacity-60', 'cursor-not-allowed');

            try {
                const fd = new FormData(form);
                fd.append('ajax', '1'); // minta JSON dari save_pr.php

                const res = await fetch('save_pr.php', {
                    method: 'POST',
                    body: fd
                });
                if (!res.ok) throw new Error('Gagal memproses');
                const j = await res.json();

                if (j && j.ok && j.download) {
                    // trigger unduh via GET endpoint (stabil di mobile)
                    window.location.href = j.download;

                    // sembunyikan overlay & info
                    setTimeout(() => {
                        overlay.classList.add('hidden');
                        overlay.classList.remove('flex');
                        Swal.fire({
                            icon: 'success',
                            title: 'PDF siap diunduh',
                            html: `No PR: <b>${(j.pr_number||'')}</b><br><a class="text-blue-600 underline" href="history.php">Buka History</a>`,
                            confirmButtonText: 'Tutup'
                        });
                    }, 1200);
                } else {
                    throw new Error((j && j.msg) || 'Gagal generate');
                }
            } catch (err) {
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: err.message || 'Terjadi kesalahan'
                });
            } finally {
                btnGen?.removeAttribute('disabled');
                btnGen?.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });


        // Inisialisasi: tampilkan empty state (tanpa baris awal)
        renderEmptyState();

        // Clear list → kembali ke empty state
        document.getElementById('btnClear').onclick = () => {
            listBody.innerHTML = '';
            renderEmptyState();
        };
    </script>

</body>

</html>