<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();

$id = (int)($_GET['id'] ?? 0);
$po = get_po_with_user($id);
if (!$po) {
    flash('danger', 'PO tidak ditemukan.');
    header('Location: po_list.php');
    exit;
}

$items   = get_po_items($id);
$history = get_po_history($id);

$items_subtotal = items_subtotal($items);
$calc = hitung_total($items_subtotal, (float)($po['discount'] ?? 0));
$role = current_role();

$page_title   = 'Detail PO ' . $po['po_number'] . ' — Viros';
$topbar_title = 'Detail Purchase Order';
$current_page = 'po_detail';

ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › <a href="po_list.php">Daftar PO</a> › <span><?= e($po['po_number']) ?></span></div>
        <div class="page-title"><?= e($po['po_number']) ?></div>
        <div class="page-sub"><?= e($po['vendor_name']) ?> — <?= badge_status($po['status']) ?></div>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <?php if (in_array($role, ['admin','staff'])): ?>
        <a href="po_edit.php?id=<?= $id ?>" class="btn btn-outline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit PO
        </a>
        <?php endif; ?>
        <a href="po_print.php?id=<?= $id ?>" class="btn btn-outline" target="_blank">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print PO
        </a>
        <?php if (in_array($role, ['admin','manager']) && !in_array($po['status'], ['completed','rejected'])): ?>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modalStatus').classList.add('show')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Ubah Status
        </button>
        <?php endif; ?>
        <?php if (in_array($role, ['admin','manager'])): ?>
        <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('modalDelete').classList.add('show')">Hapus</button>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

    <!-- Kolom Kiri -->
    <div style="display:flex;flex-direction:column;gap:24px;">

        <!-- Info PO -->
        <div class="card">
            <div class="card-header"><span class="card-title">Informasi Purchase Order</span></div>
            <div class="card-body">
                <div class="form-grid-2" style="gap:16px;">
                    <div>
                        <div class="form-label">Nomor PO</div>
                        <div style="font-weight:700;font-family:monospace;font-size:15px;"><?= e($po['po_number']) ?></div>
                    </div>
                    <div>
                        <div class="form-label">Status</div>
                        <div><?= badge_status($po['status']) ?></div>
                    </div>
                    <div>
                        <div class="form-label">Vendor / Pemasok</div>
                        <div style="font-weight:600;"><?= e($po['vendor_name']) ?></div>
                    </div>
                    <div>
                        <div class="form-label">Perusahaan Pemesan</div>
                        <div><?= e($po['customer_company'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="form-label">Tanggal Order</div>
                        <div><?= tgl_id($po['order_date'] ?? '') ?></div>
                    </div>
                    <div>
                        <div class="form-label">Dibuat Oleh</div>
                        <div><?= e($po['created_by_name']) ?></div>
                    </div>
                    <?php if (!empty($po['prepared_by'])): ?>
                    <div>
                        <div class="form-label">Disiapkan Oleh</div>
                        <div><?= e($po['prepared_by']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($po['approved_by'])): ?>
                    <div>
                        <div class="form-label">Disetujui Oleh</div>
                        <div><?= e($po['approved_by']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($po['notes'])): ?>
                <hr class="divider">
                <div>
                    <div class="form-label">Catatan</div>
                    <div style="font-size:14px;color:#444;line-height:1.6;"><?= nl2br(e($po['notes'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($po['terms_conditions'])): ?>
                <div style="margin-top:16px;">
                    <div class="form-label">Syarat & Ketentuan</div>
                    <div style="font-size:14px;color:#444;line-height:1.6;"><?= nl2br(e($po['terms_conditions'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Daftar Item -->
        <div class="card">
            <div class="card-header"><span class="card-title">Daftar Item</span><span style="font-size:12px;color:#666;"><?= count($items) ?> item</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>#</th><th>Nama Item</th><th>Qty</th><th>Satuan</th><th>Harga Satuan</th><th>Subtotal</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td style="font-weight:600;"><?= e($item['item_name']) ?></td>
                        <td><?= number_format((float)$item['qty'], 2, ',', '.') ?></td>
                        <td><?= e($item['unit'] ?? '-') ?></td>
                        <td><?= rupiah((float)$item['unit_price']) ?></td>
                        <td style="font-weight:600;"><?= rupiah((float)$item['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="6"><div class="empty-state">Belum ada item</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Kolom Kanan -->
    <div style="display:flex;flex-direction:column;gap:24px;">

        <!-- Ringkasan Biaya -->
        <div class="card">
            <div class="card-header"><span class="card-title">Ringkasan Biaya</span></div>
            <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:12px;font-size:14px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#666;">Subtotal Item</span>
                        <span><?= rupiah($calc['total']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#666;">Diskon (<?= (float)($po['discount'] ?? 0) ?>%)</span>
                        <span style="color:#E11D48;">- <?= rupiah($calc['diskon_amt']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#666;">PPN (11%)</span>
                        <span><?= rupiah($calc['pajak_amt']) ?></span>
                    </div>
                    <hr style="border:none;border-top:2px solid #000;margin:4px 0;">
                    <div style="display:flex;justify-content:space-between;font-weight:700;font-size:16px;">
                        <span>Total</span>
                        <span><?= rupiah($calc['subtotal_akhir']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Riwayat Status -->
        <div class="card">
            <div class="card-header"><span class="card-title">Riwayat Status</span></div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($history)): ?>
                <div class="empty-state" style="padding:24px;">Belum ada riwayat</div>
                <?php else: ?>
                <?php foreach ($history as $h): ?>
                <div style="padding:16px 24px;border-bottom:1px solid #E0E0E0;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                        <div style="display:flex;align-items:center;gap:6px;font-size:13px;">
                            <?= badge_status($h['old_status'] ?? '-') ?>
                            <span style="color:#999;">→</span>
                            <?= badge_status($h['new_status'] ?? '-') ?>
                        </div>
                    </div>
                    <div style="font-size:12px;color:#666;margin-top:4px;">
                        <?= e($h['changed_by_name']) ?> — <?= e($h['changed_at']) ?>
                    </div>
                    <?php if (!empty($h['note'])): ?>
                    <div style="font-size:12px;color:#444;margin-top:4px;font-style:italic;"><?= e($h['note']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Modal Ubah Status -->
<div id="modalStatus" class="modal-overlay">
    <div class="modal">
        <div class="modal-accent"></div>
        <div class="modal-header">
            <span class="modal-title">Ubah Status PO</span>
            <button class="modal-close" onclick="document.getElementById('modalStatus').classList.remove('show')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="po_update_status.php">
            <input type="hidden" name="po_id" value="<?= $id ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Status Baru</label>
                    <select name="status" class="form-control">
                        <?php
                        $allowed = [
                            'admin'   => ['draft','pending','approved','rejected','completed','revision'],
                            'manager' => ['pending','approved','rejected','completed'],
                        ];
                        $status_labels = ['draft'=>'Draft','pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi'];
                        foreach ($allowed[$role] ?? [] as $s):
                        ?>
                        <option value="<?= $s ?>" <?= $po['status'] === $s ? 'selected' : '' ?>><?= $status_labels[$s] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea name="note" class="form-control" rows="3" placeholder="Alasan perubahan status..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalStatus').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus -->
<div id="modalDelete" class="modal-overlay">
    <div class="modal">
        <div class="modal-accent"></div>
        <div class="modal-header">
            <span class="modal-title">Hapus Purchase Order</span>
            <button class="modal-close" onclick="document.getElementById('modalDelete').classList.remove('show')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            Apakah Anda yakin ingin menghapus PO <strong><?= e($po['po_number']) ?></strong>? Tindakan ini tidak dapat dibatalkan.
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('modalDelete').classList.remove('show')">Batal</button>
            <form method="POST" action="po_delete.php" style="display:inline;">
                <input type="hidden" name="po_id" value="<?= $id ?>">
                <button type="submit" class="btn btn-danger">Hapus PO</button>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
