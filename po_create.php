<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();
role_required(['admin', 'staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendor_name      = trim($_POST['vendor_name']      ?? '');
    $customer_company = trim($_POST['customer_company'] ?? '');
    $order_date       = trim($_POST['order_date']       ?? '');
    $notes            = trim($_POST['notes']            ?? '');
    $terms_conditions = trim($_POST['terms_conditions'] ?? '');
    $discount_pct     = (float)($_POST['discount']      ?? 0);
    $prepared_by      = trim($_POST['prepared_by']      ?? '');
    $approved_by      = trim($_POST['approved_by']      ?? '');

    $item_names  = $_POST['item_name']  ?? [];
    $qtys        = $_POST['qty']        ?? [];
    $units       = $_POST['unit']       ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    // Validasi minimal 1 item
    $valid_items = array_filter($item_names, fn($n) => trim($n) !== '');
    if (empty($valid_items)) {
        flash('danger', 'Minimal satu item harus diisi.');
    } else {
        // Hitung total
        $items_subtotal = 0;
        foreach ($item_names as $i => $name) {
            if (trim($name) !== '') {
                $items_subtotal += (float)($qtys[$i] ?? 0) * (float)($unit_prices[$i] ?? 0);
            }
        }
        $calc         = hitung_total($items_subtotal, $discount_pct);
        $total_amount = $calc['subtotal_akhir'];
        $pajak_amt    = $calc['pajak_amt'];

        $po_number = generate_po_number();

        $po_id = db_insert('purchase_orders', [
            'po_number'        => $po_number,
            'vendor_name'      => $vendor_name,
            'customer_company' => $customer_company,
            'order_date'       => $order_date,
            'notes'            => $notes,
            'discount'         => $discount_pct,
            'tax'              => $pajak_amt,
            'terms_conditions' => $terms_conditions,
            'total_amount'     => $total_amount,
            'status'           => 'pending',
            'created_by'       => current_user_id(),
            'prepared_by'      => $prepared_by,
            'approved_by'      => $approved_by,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => null,
        ]);

        // Simpan items
        foreach ($item_names as $i => $name) {
            if (trim($name) !== '') {
                $subtotal = (float)($qtys[$i] ?? 0) * (float)($unit_prices[$i] ?? 0);
                db_insert('po_items', [
                    'po_id'      => $po_id,
                    'item_name'  => trim($name),
                    'qty'        => (float)($qtys[$i] ?? 0),
                    'unit'       => trim($units[$i] ?? ''),
                    'unit_price' => (float)($unit_prices[$i] ?? 0),
                    'subtotal'   => $subtotal,
                ]);
            }
        }

        // Simpan history
        save_history($po_id, '-', 'pending', 'PO dibuat');

        flash('success', "PO {$po_number} berhasil dibuat!");
        header('Location: po_list.php');
        exit;
    }
}

$page_title   = 'Buat PO Baru — Viros';
$topbar_title = 'Buat Purchase Order Baru';
$current_page = 'po_create';

ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › <a href="po_list.php">Daftar PO</a> › <span>Buat Baru</span></div>
        <div class="page-title">Buat Purchase Order Baru</div>
        <div class="page-sub">Isi form di bawah untuk membuat PO baru</div>
    </div>
    <a href="po_list.php" class="btn btn-outline">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Kembali
    </a>
</div>

<form method="POST">
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

    <!-- Kolom Kiri -->
    <div style="display:flex;flex-direction:column;gap:24px;">

        <!-- Info Umum -->
        <div class="card">
            <div class="card-header"><span class="card-title">Informasi Umum</span></div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Nama Vendor / Pemasok *</label>
                        <input type="text" name="vendor_name" class="form-control" required placeholder="PT. Nama Vendor" value="<?= e($_POST['vendor_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Perusahaan Pemesan *</label>
                        <input type="text" name="customer_company" class="form-control" required placeholder="PT. Viros Prime Solution" value="<?= e($_POST['customer_company'] ?? 'PT. Viros Prime Solution') ?>">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Tanggal Order *</label>
                        <input type="date" name="order_date" class="form-control" required value="<?= e($_POST['order_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Diskon (%)</label>
                        <input type="number" name="discount" class="form-control" min="0" max="100" step="0.01" value="<?= e($_POST['discount'] ?? '0') ?>" id="discountInput">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Disiapkan Oleh</label>
                        <input type="text" name="prepared_by" class="form-control" placeholder="Nama penyiap PO" value="<?= e($_POST['prepared_by'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Disetujui Oleh</label>
                        <input type="text" name="approved_by" class="form-control" placeholder="Nama approver" value="<?= e($_POST['approved_by'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Catatan tambahan..."><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Syarat & Ketentuan</label>
                    <textarea name="terms_conditions" class="form-control" rows="3" placeholder="Syarat pembayaran, pengiriman, dll..."><?= e($_POST['terms_conditions'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Daftar Item -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Daftar Item</span>
                <button type="button" class="btn btn-outline btn-sm" onclick="addItemRow()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Item
                </button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th style="width:35%">Nama Item</th>
                            <th style="width:12%">Qty</th>
                            <th style="width:12%">Satuan</th>
                            <th style="width:20%">Harga Satuan</th>
                            <th style="width:16%">Subtotal</th>
                            <th style="width:5%"></th>
                        </tr></thead>
                        <tbody id="itemsTable">
                            <!-- Baris item diisi JS -->
                        </tbody>
                    </table>
                </div>
                <div style="padding:16px 24px;background:#F9F9F9;border-top:1px solid #E0E0E0;text-align:right;">
                    <span style="font-size:12px;color:#666;text-transform:uppercase;letter-spacing:0.6px;">Subtotal Item: </span>
                    <span style="font-weight:700;" id="subtotalDisplay">Rp 0</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Kolom Kanan: Ringkasan -->
    <div>
        <div class="card" style="position:sticky;top:80px;">
            <div class="card-header"><span class="card-title">Ringkasan Biaya</span></div>
            <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:12px;font-size:14px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#666;">Subtotal Item</span>
                        <span id="sumSubtotal">Rp 0</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#666;">Diskon (<span id="discPct">0</span>%)</span>
                        <span id="sumDiskon" style="color:#E11D48;">- Rp 0</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#666;">PPN (11%)</span>
                        <span id="sumPajak">Rp 0</span>
                    </div>
                    <hr style="border:none;border-top:2px solid #000;margin:4px 0;">
                    <div style="display:flex;justify-content:space-between;font-weight:700;font-size:16px;">
                        <span>Total</span>
                        <span id="sumTotal">Rp 0</span>
                    </div>
                </div>
                <div style="margin-top:24px;">
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan PO
                    </button>
                    <a href="po_list.php" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:8px;">Batal</a>
                </div>
                <div style="margin-top:16px;padding:12px;background:#F9F9F9;border:1px solid #E0E0E0;font-size:12px;color:#666;">
                    <strong style="display:block;margin-bottom:4px;color:#000;">Status Awal</strong>
                    PO baru akan langsung berstatus <span class="badge badge-pending">Menunggu</span> dan menunggu persetujuan manager.
                </div>
            </div>
        </div>
    </div>

</div>
</form>

<script>
let rowCount = 0;

function fmtRupiah(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function addItemRow(name='', qty='1', unit='pcs', price='0') {
    const tbody = document.getElementById('itemsTable');
    const idx = rowCount++;
    const tr = document.createElement('tr');
    tr.id = 'item-row-' + idx;
    tr.innerHTML = `
        <td><input type="text" name="item_name[]" class="form-control" style="font-size:13px;padding:8px;" placeholder="Nama item..." value="${name}" required></td>
        <td><input type="number" name="qty[]" class="form-control item-qty" style="font-size:13px;padding:8px;" min="0.01" step="0.01" value="${qty}" oninput="recalc()" required></td>
        <td><input type="text" name="unit[]" class="form-control" style="font-size:13px;padding:8px;" placeholder="pcs" value="${unit}"></td>
        <td><input type="number" name="unit_price[]" class="form-control item-price" style="font-size:13px;padding:8px;" min="0" step="1" value="${price}" oninput="recalc()" required></td>
        <td class="item-subtotal" style="font-weight:600;font-size:13px;">${fmtRupiah(parseFloat(qty)*parseFloat(price)||0)}</td>
        <td><button type="button" onclick="removeRow('item-row-${idx}')" style="background:none;border:none;cursor:pointer;color:#999;padding:4px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button></td>
    `;
    tbody.appendChild(tr);
    recalc();
}

function removeRow(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
    recalc();
}

function recalc() {
    const rows = document.querySelectorAll('#itemsTable tr');
    let subtotal = 0;
    rows.forEach(row => {
        const q = parseFloat(row.querySelector('.item-qty')?.value || 0);
        const p = parseFloat(row.querySelector('.item-price')?.value || 0);
        const s = q * p;
        subtotal += s;
        const cell = row.querySelector('.item-subtotal');
        if (cell) cell.textContent = fmtRupiah(s);
    });
    const disc = parseFloat(document.getElementById('discountInput')?.value || 0);
    const diskon = Math.round(subtotal * disc / 100);
    const afterDisc = subtotal - diskon;
    const pajak = Math.round(afterDisc * 0.11);
    const total = afterDisc + pajak;

    document.getElementById('subtotalDisplay').textContent = fmtRupiah(subtotal);
    document.getElementById('sumSubtotal').textContent = fmtRupiah(subtotal);
    document.getElementById('discPct').textContent = disc;
    document.getElementById('sumDiskon').textContent = '- ' + fmtRupiah(diskon);
    document.getElementById('sumPajak').textContent = fmtRupiah(pajak);
    document.getElementById('sumTotal').textContent = fmtRupiah(total);
}

document.getElementById('discountInput')?.addEventListener('input', recalc);

// Tambah 1 baris default saat load
addItemRow();
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
