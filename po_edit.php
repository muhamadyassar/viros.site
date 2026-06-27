<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();
role_required(['admin', 'staff']);

$id = (int)($_GET['id'] ?? 0);
$po = db_find('purchase_orders', $id);
if (!$po) {
    flash('danger', 'PO tidak ditemukan.');
    header('Location: po_list.php');
    exit;
}
$items = get_po_items($id);
$role  = current_role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendor_name      = trim($_POST['vendor_name']      ?? '');
    $customer_company = trim($_POST['customer_company'] ?? '');
    $order_date       = trim($_POST['order_date']       ?? '');
    $notes            = trim($_POST['notes']            ?? '');
    $terms_conditions = trim($_POST['terms_conditions'] ?? '');
    $discount_pct     = (float)($_POST['discount']      ?? 0);
    $prepared_by      = trim($_POST['prepared_by']      ?? '');
    $approved_by      = trim($_POST['approved_by']      ?? '');
    $new_status       = trim($_POST['status']           ?? $po['status']);

    // Validasi role vs status
    $allowed_status = [
        'staff' => ['pending', 'revision'],
        'admin' => ['draft','pending','approved','rejected','completed','revision'],
    ];
    if (!in_array($new_status, $allowed_status[$role] ?? [])) {
        $new_status = $po['status'];
    }

    $item_names  = $_POST['item_name']  ?? [];
    $qtys        = $_POST['qty']        ?? [];
    $units       = $_POST['unit']       ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    $valid_items = array_filter($item_names, fn($n) => trim($n) !== '');
    if (empty($valid_items)) {
        flash('danger', 'Minimal satu item harus diisi.');
    } else {
        $items_subtotal = 0;
        foreach ($item_names as $i => $name) {
            if (trim($name) !== '') {
                $items_subtotal += (float)($qtys[$i] ?? 0) * (float)($unit_prices[$i] ?? 0);
            }
        }
        $calc        = hitung_total($items_subtotal, $discount_pct);
        $old_status  = $po['status'];

        db_update('purchase_orders', $id, [
            'vendor_name'      => $vendor_name,
            'customer_company' => $customer_company,
            'order_date'       => $order_date,
            'notes'            => $notes,
            'discount'         => $discount_pct,
            'tax'              => $calc['pajak_amt'],
            'terms_conditions' => $terms_conditions,
            'status'           => $new_status,
            'total_amount'     => $calc['subtotal_akhir'],
            'prepared_by'      => $prepared_by,
            'approved_by'      => $approved_by,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        // Hapus items lama, insert baru
        db_delete_where('po_items', 'po_id', $id);
        foreach ($item_names as $i => $name) {
            if (trim($name) !== '') {
                $subtotal = (float)($qtys[$i] ?? 0) * (float)($unit_prices[$i] ?? 0);
                db_insert('po_items', [
                    'po_id'      => $id,
                    'item_name'  => trim($name),
                    'qty'        => (float)($qtys[$i] ?? 0),
                    'unit'       => trim($units[$i] ?? ''),
                    'unit_price' => (float)($unit_prices[$i] ?? 0),
                    'subtotal'   => $subtotal,
                ]);
            }
        }

        if ($old_status !== $new_status) {
            save_history($id, $old_status, $new_status, 'Status diubah saat edit PO');
        }
        save_history($id, $new_status, $new_status, 'Data PO diperbarui');

        flash('success', 'Purchase Order berhasil diperbarui.');
        header('Location: po_detail.php?id=' . $id);
        exit;
    }
    // Kalau gagal validasi, refresh items dari POST
    $items = [];
    foreach ($item_names as $i => $name) {
        if (trim($name) !== '') {
            $items[] = [
                'item_name'  => $name,
                'qty'        => $qtys[$i] ?? 1,
                'unit'       => $units[$i] ?? '',
                'unit_price' => $unit_prices[$i] ?? 0,
                'subtotal'   => (float)($qtys[$i] ?? 0) * (float)($unit_prices[$i] ?? 0),
            ];
        }
    }
    $po['vendor_name']      = $_POST['vendor_name']      ?? $po['vendor_name'];
    $po['customer_company'] = $_POST['customer_company'] ?? $po['customer_company'];
    $po['order_date']       = $_POST['order_date']       ?? $po['order_date'];
    $po['notes']            = $_POST['notes']            ?? $po['notes'];
    $po['terms_conditions'] = $_POST['terms_conditions'] ?? $po['terms_conditions'];
    $po['discount']         = $_POST['discount']         ?? $po['discount'];
    $po['prepared_by']      = $_POST['prepared_by']      ?? $po['prepared_by'];
    $po['approved_by']      = $_POST['approved_by']      ?? $po['approved_by'];
}

$items_subtotal = items_subtotal($items);
$calc = hitung_total($items_subtotal, (float)($po['discount'] ?? 0));

$page_title   = 'Edit PO ' . $po['po_number'] . ' — Viros';
$topbar_title = 'Edit Purchase Order';
$current_page = 'po_edit';

$status_options = [
    'staff' => ['pending'=>'Menunggu','revision'=>'Revisi'],
    'admin' => ['draft'=>'Draft','pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi'],
];

ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › <a href="po_list.php">Daftar PO</a> › <a href="po_detail.php?id=<?= $id ?>"><?= e($po['po_number']) ?></a> › <span>Edit</span></div>
        <div class="page-title">Edit <?= e($po['po_number']) ?></div>
        <div class="page-sub">Perbarui data Purchase Order</div>
    </div>
    <a href="po_detail.php?id=<?= $id ?>" class="btn btn-outline">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Kembali
    </a>
</div>

<form method="POST">
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

    <div style="display:flex;flex-direction:column;gap:24px;">
        <div class="card">
            <div class="card-header"><span class="card-title">Informasi Umum</span></div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Vendor / Pemasok *</label>
                        <input type="text" name="vendor_name" class="form-control" required value="<?= e($po['vendor_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Perusahaan Pemesan *</label>
                        <input type="text" name="customer_company" class="form-control" required value="<?= e($po['customer_company'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Tanggal Order *</label>
                        <input type="date" name="order_date" class="form-control" required value="<?= e($po['order_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Diskon (%)</label>
                        <input type="number" name="discount" id="discountInput" class="form-control" min="0" max="100" step="0.01" value="<?= e($po['discount'] ?? '0') ?>">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Disiapkan Oleh</label>
                        <input type="text" name="prepared_by" class="form-control" value="<?= e($po['prepared_by'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Disetujui Oleh</label>
                        <input type="text" name="approved_by" class="form-control" value="<?= e($po['approved_by'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status PO</label>
                    <select name="status" class="form-control">
                        <?php foreach ($status_options[$role] ?? [] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $po['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="3"><?= e($po['notes'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Syarat & Ketentuan</label>
                    <textarea name="terms_conditions" class="form-control" rows="3"><?= e($po['terms_conditions'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Daftar Item</span>
                <button type="button" class="btn btn-outline btn-sm" onclick="addItemRow()">+ Tambah Item</button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>Nama Item</th><th>Qty</th><th>Satuan</th><th>Harga Satuan</th><th>Subtotal</th><th></th>
                        </tr></thead>
                        <tbody id="itemsTable">
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><input type="text" name="item_name[]" class="form-control" style="font-size:13px;padding:8px;" value="<?= e($item['item_name']) ?>" required></td>
                            <td><input type="number" name="qty[]" class="form-control item-qty" style="font-size:13px;padding:8px;" step="0.01" value="<?= e($item['qty']) ?>" oninput="recalc()" required></td>
                            <td><input type="text" name="unit[]" class="form-control" style="font-size:13px;padding:8px;" value="<?= e($item['unit'] ?? '') ?>"></td>
                            <td><input type="number" name="unit_price[]" class="form-control item-price" style="font-size:13px;padding:8px;" step="1" value="<?= e($item['unit_price']) ?>" oninput="recalc()" required></td>
                            <td class="item-subtotal" style="font-weight:600;font-size:13px;">Rp <?= number_format((float)$item['subtotal'], 0, ',', '.') ?></td>
                            <td><button type="button" onclick="this.closest('tr').remove();recalc()" style="background:none;border:none;cursor:pointer;color:#999;padding:4px;">✕</button></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Ringkasan -->
    <div>
        <div class="card" style="position:sticky;top:80px;">
            <div class="card-header"><span class="card-title">Ringkasan Biaya</span></div>
            <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:12px;font-size:14px;">
                    <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Subtotal Item</span><span id="sumSubtotal"><?= rupiah($calc['total']) ?></span></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:#666;">Diskon (<span id="discPct"><?= (float)($po['discount'] ?? 0) ?></span>%)</span><span id="sumDiskon" style="color:#E11D48;">- <?= rupiah($calc['diskon_amt']) ?></span></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:#666;">PPN (11%)</span><span id="sumPajak"><?= rupiah($calc['pajak_amt']) ?></span></div>
                    <hr style="border:none;border-top:2px solid #000;">
                    <div style="display:flex;justify-content:space-between;font-weight:700;font-size:16px;"><span>Total</span><span id="sumTotal"><?= rupiah($calc['subtotal_akhir']) ?></span></div>
                </div>
                <div style="margin-top:24px;">
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Simpan Perubahan</button>
                    <a href="po_detail.php?id=<?= $id ?>" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:8px;">Batal</a>
                </div>
            </div>
        </div>
    </div>

</div>
</form>

<script>
let rowCount = 100;
function fmtRupiah(n){ return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

function addItemRow(name='', qty='1', unit='pcs', price='0') {
    const idx = rowCount++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="item_name[]" class="form-control" style="font-size:13px;padding:8px;" value="${name}" required></td>
        <td><input type="number" name="qty[]" class="form-control item-qty" style="font-size:13px;padding:8px;" step="0.01" value="${qty}" oninput="recalc()" required></td>
        <td><input type="text" name="unit[]" class="form-control" style="font-size:13px;padding:8px;" value="${unit}"></td>
        <td><input type="number" name="unit_price[]" class="form-control item-price" style="font-size:13px;padding:8px;" step="1" value="${price}" oninput="recalc()" required></td>
        <td class="item-subtotal" style="font-weight:600;font-size:13px;">Rp 0</td>
        <td><button type="button" onclick="this.closest('tr').remove();recalc()" style="background:none;border:none;cursor:pointer;color:#999;padding:4px;">✕</button></td>
    `;
    document.getElementById('itemsTable').appendChild(tr);
    recalc();
}

function recalc() {
    let subtotal = 0;
    document.querySelectorAll('#itemsTable tr').forEach(row => {
        const q = parseFloat(row.querySelector('.item-qty')?.value||0);
        const p = parseFloat(row.querySelector('.item-price')?.value||0);
        const s = q*p; subtotal += s;
        const cell = row.querySelector('.item-subtotal');
        if(cell) cell.textContent = fmtRupiah(s);
    });
    const disc = parseFloat(document.getElementById('discountInput')?.value||0);
    const diskon = Math.round(subtotal*disc/100);
    const afterDisc = subtotal-diskon;
    const pajak = Math.round(afterDisc*0.11);
    const total = afterDisc+pajak;
    document.getElementById('sumSubtotal').textContent = fmtRupiah(subtotal);
    document.getElementById('discPct').textContent = disc;
    document.getElementById('sumDiskon').textContent = '- '+fmtRupiah(diskon);
    document.getElementById('sumPajak').textContent = fmtRupiah(pajak);
    document.getElementById('sumTotal').textContent = fmtRupiah(total);
}
document.getElementById('discountInput')?.addEventListener('input', recalc);
recalc();
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
