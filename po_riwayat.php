<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();

$search = trim($_GET['search'] ?? '');

$histories = db_read('po_history');
$all_po    = db_read('purchase_orders');
$all_users = db_read('users');

$po_map   = [];
foreach ($all_po    as $p) $po_map[$p['id']]   = $p;
$user_map = [];
foreach ($all_users as $u) $user_map[$u['id']] = $u['username'];

// Gabungkan data
$result = [];
foreach ($histories as $h) {
    $po = $po_map[$h['po_id']] ?? null;
    if (!$po) continue;
    if ($search) {
        $s   = strtolower($search);
        $hay = strtolower(($po['po_number'] ?? '') . ' ' . ($po['vendor_name'] ?? '') . ' ' . ($user_map[$h['changed_by']] ?? ''));
        if (strpos($hay, $s) === false) continue;
    }
    $h['po_number']       = $po['po_number']       ?? '-';
    $h['vendor_name']     = $po['vendor_name']      ?? '-';
    $h['customer_company']= $po['customer_company'] ?? '-';
    $h['current_status']  = $po['status']           ?? '-';
    $h['total_amount']    = $po['total_amount']     ?? 0;
    $h['changed_by_name'] = $user_map[$h['changed_by'] ?? 0] ?? '-';
    $result[] = $h;
}

// Urut terbaru dulu, ambil maks 100
usort($result, fn($a, $b) => strcmp($b['changed_at'] ?? '', $a['changed_at'] ?? ''));
$result    = array_slice($result, 0, 100);
$total_log = count(db_read('po_history'));

$page_title   = 'Riwayat PO — Viros';
$topbar_title = 'Riwayat Purchase Order';
$current_page = 'po_riwayat';

ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › <span>Riwayat PO</span></div>
        <div class="page-title">Riwayat Purchase Order</div>
        <div class="page-sub">Total <?= $total_log ?> entri log perubahan</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:32px;">
    <div class="stat-card featured">
        <div class="stat-label">Total Log</div>
        <div class="stat-value"><?= $total_log ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Ditampilkan</div>
        <div class="stat-value"><?= count($result) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total PO</div>
        <div class="stat-value"><?= count($all_po) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Pengguna</div>
        <div class="stat-value"><?= count($all_users) ?></div>
    </div>
</div>

<div class="card">
    <div style="padding:20px 24px;border-bottom:1px solid #E0E0E0;display:flex;gap:12px;align-items:center;">
        <form method="GET" style="display:flex;gap:12px;align-items:center;">
            <div style="position:relative;">
                <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#666;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Cari nomor PO, vendor, pengguna..." class="form-control" style="padding-left:40px;width:300px;">
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Cari</button>
            <?php if ($search): ?><a href="po_riwayat.php" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Waktu</th><th>Nomor PO</th><th>Vendor</th>
                <th>Perubahan Status</th><th>Catatan</th><th>Oleh</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if (empty($result)): ?>
            <tr><td colspan="7">
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <div class="empty-title">Belum ada riwayat</div>
                </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($result as $h): ?>
            <tr>
                <td style="white-space:nowrap;font-size:12px;color:#666;"><?= e($h['changed_at'] ?? '-') ?></td>
                <td>
                    <a href="po_detail.php?id=<?= (int)$h['po_id'] ?>" style="font-weight:600;color:#000;text-decoration:none;font-family:monospace;">
                        <?= e($h['po_number']) ?>
                    </a>
                </td>
                <td><?= e($h['vendor_name']) ?></td>
                <td style="white-space:nowrap;">
                    <?= badge_status($h['old_status'] ?? '-') ?>
                    <span style="color:#999;margin:0 4px;">→</span>
                    <?= badge_status($h['new_status'] ?? '-') ?>
                </td>
                <td style="font-size:12px;color:#666;max-width:200px;"><?= e($h['note'] ?? '-') ?></td>
                <td style="font-size:13px;"><?= e($h['changed_by_name']) ?></td>
                <td>
                    <a href="po_detail.php?id=<?= (int)$h['po_id'] ?>" class="action-icon" title="Detail PO">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
