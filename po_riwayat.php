<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();

$search = trim($_GET['search']??'');
$histories = db_read('po_history');
$all_po    = db_read('purchase_orders');
$all_users = db_read('users');
$po_map=[];  foreach($all_po    as $p) $po_map[$p['id']]=$p;
$user_map=[]; foreach($all_users as $u) $user_map[$u['id']]=$u['username'];

$result=[];
foreach ($histories as $h) {
    $po=$po_map[$h['po_id']]??null; if(!$po) continue;
    if ($search) {
        $s=strtolower($search);
        $hay=strtolower(($po['po_number']??'').' '.($po['vendor_name']??'').' '.($user_map[$h['changed_by']??0]??''));
        if(strpos($hay,$s)===false) continue;
    }
    $h['po_number']       = $po['po_number']??'-';
    $h['vendor_name']     = $po['vendor_name']??'-';
    $h['customer_company']= $po['customer_company']??'-';
    $h['changed_by_name'] = $user_map[$h['changed_by']??0]??'Sistem';
    $result[]=$h;
}
usort($result, fn($a,$b)=>strcmp($b['changed_at']??'',$a['changed_at']??''));
$result    = array_slice($result,0,100);
$total_log = count(db_read('po_history'));

$page_title='Riwayat PO — Viros PO System'; $topbar_title='Riwayat Purchase Order'; $current_page='po_riwayat';

function badge_rw(string $s): string {
    $m=['draft'=>'Draft','pending'=>'Pending','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi'];
    return '<span class="badge badge-'.htmlspecialchars($s).'" style="font-size:10px;">'.htmlspecialchars($m[$s]??$s).'</span>';
}
ob_start(); ?>

<div class="page-header">
    <div>
        <div class="page-title">Riwayat PO</div>
        <div class="page-sub">Log seluruh perubahan status Purchase Order</div>
    </div>
</div>

<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-label">Total Log</div><div class="stat-value"><?= $total_log ?></div></div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:200px;">
                <label class="form-label">Cari Riwayat</label>
                <input type="text" name="search" class="form-control" placeholder="Nomor PO, nama customer, nama user..." value="<?= e($search) ?>">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="po_riwayat.php" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th style="width:140px;">Nomor PO</th><th>Nama Customer</th><th>Perusahaan Customer</th>
                <th style="width:180px;">Perubahan Status</th><th style="width:130px;">Diubah Oleh</th>
                <th style="width:150px;">Waktu</th><th>Catatan</th>
            </tr></thead>
            <tbody>
            <?php if (empty($result)): ?>
            <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--text-muted);">
                <?php if ($search): ?>Tidak ada riwayat yang sesuai pencarian. <a href="po_riwayat.php" style="color:var(--black);">Reset</a>
                <?php else: ?>Belum ada riwayat perubahan PO.<?php endif; ?>
            </td></tr>
            <?php else: foreach ($result as $h): ?>
            <tr>
                <td><?php if($h['po_id']): ?><a href="po_detail.php?id=<?= (int)$h['po_id'] ?>" style="font-weight:700;color:var(--black);text-decoration:none;"><?= e($h['po_number']) ?></a><?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?></td>
                <td><?= e($h['vendor_name']) ?></td>
                <td style="color:var(--text-muted);"><?= e($h['customer_company']) ?></td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <?php if (!empty($h['old_status']) && $h['old_status']!=='-'): ?><?= badge_rw($h['old_status']) ?><span style="font-size:11px;color:var(--text-muted);">→</span><?php endif; ?>
                        <?= badge_rw($h['new_status']??'') ?>
                    </div>
                </td>
                <td style="font-size:13px;"><?= e($h['changed_by_name']) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= e(substr($h['changed_at']??'',0,16)) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= e($h['note']??'-') ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($result)): ?>
    <div style="padding:14px 20px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted);">
        Menampilkan <?= count($result) ?> log<?= $search?' (difilter dari '.$total_log.' total log)':' terakhir' ?>
    </div>
    <?php endif; ?>
</div>

<?php $content=ob_get_clean(); include 'includes/base.php';
