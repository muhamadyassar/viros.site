<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();

$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$all_po    = db_read('purchase_orders');
$all_users = db_read('users');
$user_map  = [];
foreach ($all_users as $u) $user_map[$u['id']] = $u['username'];

$pos = array_filter($all_po, function($p) use ($search, $status_filter) {
    if ($status_filter && $p['status'] !== $status_filter) return false;
    if ($search) {
        $s = strtolower($search);
        $h = strtolower(($p['po_number']??'').' '.($p['vendor_name']??'').' '.($p['customer_company']??''));
        if (strpos($h,$s)===false) return false;
    }
    return true;
});
usort($pos, fn($a,$b)=>strcmp($b['created_at']??'',$a['created_at']??''));
$pos = array_values($pos);

$total     = count($all_po);
$pending   = count(array_filter($all_po,fn($p)=>$p['status']==='pending'));
$total_val = (float)array_sum(array_column($all_po,'total_amount'));
$revision  = count(array_filter($all_po,fn($p)=>$p['status']==='revision'));

$page_title   = 'Daftar PO — Viros PO System';
$topbar_title = 'Daftar Purchase Order';
$current_page = 'po_list';

ob_start(); ?>

<div class="page-header">
    <div>
        <div class="page-title">Purchase Order</div>
        <div class="page-sub">Kelola semua Purchase Order keluar</div>
    </div>
    <?php if (in_array(current_role(),['admin','staff'])): ?>
    <a href="po_create.php" class="btn btn-primary">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Buat PO Baru
    </a>
    <?php endif; ?>
</div>

<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-label">Total PO</div><div class="stat-value"><?= $total ?></div></div>
    <div class="stat-card"><div class="stat-label">Menunggu</div><div class="stat-value"><?= $pending ?></div></div>
    <div class="stat-card"><div class="stat-label">Revisi</div><div class="stat-value"><?= $revision ?></div></div>
    <div class="stat-card"><div class="stat-label">Total Nilai PO</div><div class="stat-value">Rp <?= number_format($total_val,0,',','.') ?></div></div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:200px;">
                <label class="form-label">Cari PO</label>
                <input type="text" name="search" class="form-control" placeholder="Nomor PO, nama customer, perusahaan..." value="<?= e($search) ?>">
            </div>
            <div style="min-width:160px;">
                <label class="form-label">Filter Status</label>
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <?php foreach(['draft'=>'Draft','pending'=>'Pending','revision'=>'Revision','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= $status_filter===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="po_list.php" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th style="width:160px;">Nomor PO</th>
                <th>Nama Customer</th>
                <th>Perusahaan Customer</th>
                <th style="width:120px;">Tanggal</th>
                <th style="width:100px;">Status</th>
                <th style="width:150px;text-align:right;">Total</th>
                <th style="width:120px;text-align:center;">Aksi</th>
            </tr></thead>
            <tbody>
            <?php if (empty($pos)): ?>
            <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--text-muted);">
                <?php if ($search || $status_filter): ?>
                Tidak ada PO yang sesuai filter. <a href="po_list.php" style="color:var(--black);">Reset filter</a>
                <?php else: ?>
                Belum ada Purchase Order.
                <?php if (in_array(current_role(),['admin','staff'])): ?><a href="po_create.php" style="color:var(--black);">Buat PO pertama</a><?php endif; ?>
                <?php endif; ?>
            </td></tr>
            <?php else: foreach ($pos as $po):
                $sl=['draft'=>'Draft','pending'=>'Pending','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi'];
            ?>
            <tr>
                <td><a href="po_detail.php?id=<?= $po['id'] ?>" style="font-weight:700;color:var(--black);text-decoration:none;"><?= e($po['po_number']) ?></a></td>
                <td><?= e($po['vendor_name']) ?></td>
                <td style="color:var(--text-muted);"><?= e($po['customer_company']??'-') ?></td>
                <td style="color:var(--text-muted);"><?= tgl_short($po['order_date']??'') ?></td>
                <td><span class="badge badge-<?= e($po['status']) ?>"><?= e($sl[$po['status']]??$po['status']) ?></span></td>
                <td style="text-align:right;font-weight:600;">Rp <?= number_format((float)($po['total_amount']??0),0,',','.') ?></td>
                <td style="text-align:center;">
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <a href="po_detail.php?id=<?= $po['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
                        <?php if (in_array(current_role(),['admin','staff'])): ?>
                        <a href="po_edit.php?id=<?= $po['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($pos)): ?>
    <div style="padding:14px 20px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted);">
        Menampilkan <?= count($pos) ?> Purchase Order<?= ($search||$status_filter)?' (difilter)':'' ?>
    </div>
    <?php endif; ?>
</div>

<?php $content=ob_get_clean(); include 'includes/base.php';
