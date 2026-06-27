<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();

$id = (int)($_GET['id']??0);
$po = get_po_with_user($id);
if (!$po) { flash('danger','PO tidak ditemukan.'); header('Location: po_list.php'); exit; }

$items   = get_po_items($id);
$history = get_po_history($id);
$sub     = items_subtotal($items);
$calc    = hitung_total($sub,(float)($po['discount']??0));
$role    = current_role();
$sl      = ['draft'=>'Draft','pending'=>'Pending','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi'];

$page_title='Detail PO — '.$po['po_number']; $topbar_title='Detail Purchase Order'; $current_page='po_detail';
ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="po_list.php">Daftar PO</a><span class="breadcrumb-sep">›</span><span style="font-weight:700;color:var(--black);"><?= e($po['po_number']) ?></span></div>
        <div class="page-title"><?= e($po['po_number']) ?></div>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <?php if (in_array($role,['admin','staff'])): ?><a href="po_edit.php?id=<?= $id ?>" class="btn btn-outline">Edit PO</a><?php endif; ?>
        <a href="po_print.php?id=<?= $id ?>" class="btn btn-outline"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Cetak PDF</a>
        <?php if (in_array($role,['admin','manager'])): ?>
        <form method="POST" action="po_delete.php" onsubmit="return confirm('Yakin ingin menghapus PO ini?');" style="display:inline;">
            <input type="hidden" name="po_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-danger">Hapus PO</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start;">
    <!-- KOLOM KIRI -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Informasi PO</span>
                <span class="badge badge-<?= e($po['status']) ?>"><?= e($sl[$po['status']]??$po['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="form-grid-3">
                    <div class="detail-item"><div class="detail-label">Nomor PO</div><div class="detail-value"><?= e($po['po_number']) ?></div></div>
                    <div class="detail-item"><div class="detail-label">Nama Customer</div><div class="detail-value"><?= e($po['vendor_name']) ?></div></div>
                    <div class="detail-item"><div class="detail-label">Perusahaan Customer</div><div class="detail-value"><?= e($po['customer_company']??'-') ?></div></div>
                    <div class="detail-item"><div class="detail-label">Tanggal Order</div><div class="detail-value"><?= tgl_id($po['order_date']??'') ?></div></div>
                    <div class="detail-item"><div class="detail-label">Dibuat Oleh</div><div class="detail-value"><?= e($po['created_by_name']??'-') ?></div></div>
                    <div class="detail-item"><div class="detail-label">Tanggal Dibuat</div><div class="detail-value"><?= e($po['created_at']??'-') ?></div></div>
                </div>
                <?php if (!empty($po['notes'])): ?><div class="detail-item" style="margin-top:12px;"><div class="detail-label">Catatan</div><div class="detail-value"><?= e($po['notes']) ?></div></div><?php endif; ?>
                <?php if (!empty($po['terms_conditions'])): ?><div class="detail-item" style="margin-top:12px;"><div class="detail-label">Syarat &amp; Ketentuan</div><div class="detail-value" style="white-space:pre-line;"><?= e($po['terms_conditions']) ?></div></div><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="background:#F3F3F3;">
                <span class="card-title" style="letter-spacing:0.9px;text-transform:uppercase;font-size:18px;">Item Pesanan</span>
                <span style="font-size:12px;color:var(--text-muted);"><?= count($items) ?> item</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th style="width:40%;">Nama Item</th><th style="width:12%;text-align:right;">Jumlah</th>
                        <th style="width:10%;">Satuan</th><th style="width:18%;text-align:right;">Harga Satuan</th><th style="width:20%;text-align:right;">Subtotal</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['item_name']) ?></td>
                        <td style="text-align:right;"><?= e($item['qty']) ?></td>
                        <td><?= e($item['unit']??'-') ?></td>
                        <td style="text-align:right;">Rp <?= number_format((float)$item['unit_price'],0,',','.') ?></td>
                        <td style="text-align:right;font-weight:600;">Rp <?= number_format((float)$item['subtotal'],0,',','.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="background:#F3F3F3;border-top:1px solid var(--border);padding:24px 32px;display:flex;justify-content:flex-end;">
                <div style="width:288px;display:flex;flex-direction:column;gap:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);"><span>Total</span><span>Rp <?= number_format($calc['total'],0,',','.') ?></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);"><span>Diskon (<?= (float)($po['discount']??0) ?>%)</span><span>- Rp <?= number_format($calc['diskon_amt'],0,',','.') ?></span></div>
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);"><span>Pajak 11%</span><span>Rp <?= number_format($calc['pajak_amt'],0,',','.') ?></span></div>
                    <div style="display:flex;justify-content:space-between;border-top:1px solid #CFC4C5;padding-top:10px;margin-top:4px;">
                        <span style="font-weight:700;font-size:12px;letter-spacing:0.6px;text-transform:uppercase;">Subtotal</span>
                        <span style="font-weight:600;font-size:20px;letter-spacing:-0.24px;">Rp <?= number_format($calc['subtotal_akhir'],0,',','.') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php if (in_array($role,['admin','manager','staff'])): ?>
        <div class="card">
            <div class="card-header"><span class="card-title">Ubah Status</span></div>
            <div class="card-body">
                <form method="POST" action="po_update_status.php">
                    <input type="hidden" name="po_id" value="<?= $id ?>">
                    <div class="form-group">
                        <label class="form-label">Status Baru</label>
                        <select name="status" class="form-control">
                            <?php
                            $opts=['staff'=>['pending','revision'],'manager'=>['pending','approved','rejected','completed'],'admin'=>['draft','pending','revision','approved','rejected','completed']];
                            $labels=['draft'=>'Draft','pending'=>'Pending','revision'=>'Revision','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai'];
                            foreach ($opts[$role]??[] as $v): ?>
                            <option value="<?= $v ?>" <?= $po['status']===$v?'selected':'' ?>><?= $labels[$v] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Catatan Perubahan</label>
                        <textarea name="note" class="form-control" placeholder="Alasan perubahan status (opsional)..." style="min-height:74px;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px;">Simpan Status</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header"><span class="card-title">Ubah Status</span></div>
            <div class="card-body" style="text-align:center;padding:32px 24px;color:var(--text-muted);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="margin-bottom:10px;opacity:0.4;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <div style="font-size:13px;line-height:1.6;">Anda memiliki akses <strong>lihat saja</strong> dan tidak dapat mengubah status PO ini.</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><span class="card-title">Riwayat Status</span></div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($history)): ?>
                <div style="display:flex;flex-direction:column;">
                    <?php foreach ($history as $idx=>$h): ?>
                    <div style="padding:14px 20px;border-bottom:1px solid var(--border);<?= $idx===count($history)-1?'border-bottom:none;':'' ?>">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                            <div style="display:flex;gap:6px;align-items:center;">
                                <?php if (!empty($h['old_status']) && $h['old_status']!=='-'): ?>
                                <span class="badge badge-<?= e($h['old_status']) ?>" style="font-size:10px;"><?= e($h['old_status']) ?></span>
                                <span style="font-size:11px;color:var(--text-muted);">→</span>
                                <?php endif; ?>
                                <span class="badge badge-<?= e($h['new_status']??'') ?>" style="font-size:10px;"><?= e($h['new_status']??'') ?></span>
                            </div>
                            <span style="font-size:11px;color:var(--text-muted);"><?= e(substr($h['changed_at']??'',0,16)) ?></span>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">oleh <strong><?= e($h['changed_by_name']??'Sistem') ?></strong><?= !empty($h['note'])?' — '.e($h['note']):'' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">Belum ada riwayat perubahan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $content=ob_get_clean(); include 'includes/base.php';
