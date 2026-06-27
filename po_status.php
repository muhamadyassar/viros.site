<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
role_required(['admin','manager','staff']);

$id = (int)($_GET['id']??0);
$po = get_po_with_user($id);
if (!$po) { flash('danger','PO tidak ditemukan.'); header('Location: po_list.php'); exit; }
$role = current_role();
$sl=['draft'=>'Draft','pending'=>'Pending','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi'];

$page_title='Ubah Status PO — '.$po['po_number']; $topbar_title='Ubah Status Purchase Order'; $current_page='po_status';
ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="po_list.php">Daftar PO</a><span class="breadcrumb-sep">›</span><a href="po_detail.php?id=<?= $id ?>"><?= e($po['po_number']) ?></a><span class="breadcrumb-sep">›</span><span style="font-weight:700;color:var(--black);">Ubah Status</span></div>
        <div class="page-title">Ubah Status — <?= e($po['po_number']) ?></div>
    </div>
    <a href="po_detail.php?id=<?= $id ?>" class="btn btn-outline">← Kembali</a>
</div>

<div style="max-width:560px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Informasi PO</span><span class="badge badge-<?= e($po['status']) ?>"><?= e($sl[$po['status']]??$po['status']) ?></span></div>
        <div class="card-body">
            <div class="form-grid-2">
                <div class="detail-item"><div class="detail-label">Nomor PO</div><div class="detail-value"><?= e($po['po_number']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Nama Customer</div><div class="detail-value"><?= e($po['vendor_name']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Perusahaan Customer</div><div class="detail-value"><?= e($po['customer_company']??'-') ?></div></div>
                <div class="detail-item"><div class="detail-label">Total</div><div class="detail-value" style="font-weight:700;">Rp <?= number_format((float)($po['total_amount']??0),0,',','.') ?></div></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header"><span class="card-title">Alur Status</span></div>
        <div class="card-body">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <?php $statuses=[['draft','Draft'],['pending','Pending'],['revision','Revisi'],['approved','Disetujui'],['rejected','Ditolak'],['completed','Selesai']];
                foreach ($statuses as $idx=>[$v,$l]): ?>
                <span class="badge badge-<?= $v ?>" style="<?= $po['status']===$v?'outline:2px solid var(--black);outline-offset:2px;':'' ?>"><?= $l ?></span>
                <?php if ($idx<count($statuses)-1): ?><span style="color:var(--text-muted);font-size:12px;">→</span><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:12px;font-size:12px;color:var(--text-muted);line-height:1.7;">
                <?php if ($role==='staff'): ?>Sebagai <strong>Staff</strong>, Anda hanya dapat mengubah status ke <strong>Pending</strong> atau <strong>Revisi</strong>.
                <?php elseif ($role==='manager'): ?>Sebagai <strong>Manager</strong>, Anda dapat mengubah status ke <strong>Pending</strong>, <strong>Disetujui</strong>, <strong>Ditolak</strong>, atau <strong>Selesai</strong>.
                <?php else: ?>Sebagai <strong>Admin</strong>, Anda dapat mengubah ke semua status.<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header"><span class="card-title">Ubah Status</span></div>
        <div class="card-body">
            <form method="POST" action="po_update_status.php">
                <input type="hidden" name="po_id" value="<?= $id ?>">
                <div class="form-group">
                    <label class="form-label">Status Baru *</label>
                    <select name="status" class="form-control" required>
                        <?php $opts=['staff'=>['pending'=>'Pending','revision'=>'Revisi'],'manager'=>['pending'=>'Pending','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai'],'admin'=>['draft'=>'Draft','pending'=>'Pending','revision'=>'Revision','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai']];
                        foreach ($opts[$role]??[] as $v=>$l): ?><option value="<?= $v ?>" <?= $po['status']===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Catatan <span style="font-weight:400;font-size:11px;">(opsional)</span></label>
                    <textarea name="note" class="form-control" placeholder="Alasan perubahan status, instruksi, atau catatan tambahan..." style="min-height:100px;"></textarea>
                </div>
                <div style="display:flex;gap:12px;margin-top:20px;">
                    <a href="po_detail.php?id=<?= $id ?>" class="btn btn-outline" style="flex:1;text-align:center;">Batal</a>
                    <button type="submit" class="btn btn-primary" style="flex:2;">Simpan Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $content=ob_get_clean(); include 'includes/base.php';
