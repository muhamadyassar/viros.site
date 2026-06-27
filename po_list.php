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

// Filter
$pos = array_filter($all_po, function($p) use ($search, $status_filter) {
    if ($status_filter && $p['status'] !== $status_filter) return false;
    if ($search) {
        $s = strtolower($search);
        $haystack = strtolower(($p['po_number'] ?? '') . ' ' . ($p['vendor_name'] ?? '') . ' ' . ($p['customer_company'] ?? ''));
        if (strpos($haystack, $s) === false) return false;
    }
    return true;
});
usort($pos, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

foreach ($pos as &$p) {
    $p['created_by_name'] = $user_map[$p['created_by'] ?? 0] ?? '-';
}
unset($p);

// Statistik
$total     = count($all_po);
$pending   = count(array_filter($all_po, fn($p) => $p['status'] === 'pending'));
$total_val = array_sum(array_column($all_po, 'total_amount'));
$revision  = count(array_filter($all_po, fn($p) => $p['status'] === 'revision'));

$page_title   = 'Daftar PO — Viros';
$topbar_title = 'Daftar Purchase Order';
$current_page = 'po_list';

ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › <span>Daftar PO</span></div>
        <div class="page-title">Daftar Purchase Order</div>
        <div class="page-sub"><?= count($pos) ?> PO ditemukan</div>
    </div>
    <div style="display:flex;gap:12px;">
        <?php if (in_array(current_role(), ['admin','staff'])): ?>
        <a href="po_create.php" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Buat PO Baru
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid" style="margin-bottom:32px;">
    <div class="stat-card featured">
        <div class="stat-label">Total PO</div>
        <div class="stat-value"><?= $total ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Menunggu Proses</div>
        <div class="stat-value"><?= $pending ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Nilai</div>
        <div class="stat-value" style="font-size:22px;">Rp <?= number_format($total_val/1000000,1,',','.') ?>M</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Perlu Revisi</div>
        <div class="stat-value"><?= $revision ?></div>
    </div>
</div>

<!-- Filter & Search -->
<div class="card" style="margin-bottom:0;">
    <div style="padding:20px 24px;border-bottom:1px solid #E0E0E0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="search-bar" style="position:relative;">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Cari nomor PO, vendor, perusahaan..." class="form-control" style="padding-left:40px;width:280px;">
            </div>
            <select name="status" class="form-control" style="width:160px;" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <?php foreach (['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi','draft'=>'Draft'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $status_filter === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Cari</button>
            <?php if ($search || $status_filter): ?>
            <a href="po_list.php" class="btn btn-ghost btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>No. PO</th><th>Vendor</th><th>Perusahaan</th>
                <th>Tanggal</th><th>Total</th><th>Status</th><th>Dibuat Oleh</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if (empty($pos)): ?>
            <tr><td colspan="8">
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <div class="empty-title">Tidak ada Purchase Order</div>
                    <div style="font-size:13px;margin-top:8px;color:#666;">
                        <?= $search || $status_filter ? 'Coba ubah filter pencarian Anda.' : 'Belum ada PO yang dibuat.' ?>
                    </div>
                </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($pos as $p): ?>
            <tr>
                <td><a href="po_detail.php?id=<?= $p['id'] ?>" style="font-weight:600;color:#000;text-decoration:none;font-family:monospace;"><?= e($p['po_number']) ?></a></td>
                <td><?= e($p['vendor_name']) ?></td>
                <td><?= e($p['customer_company'] ?? '-') ?></td>
                <td style="white-space:nowrap;"><?= e($p['order_date'] ?? '-') ?></td>
                <td style="white-space:nowrap;"><?= rupiah((float)($p['total_amount'] ?? 0)) ?></td>
                <td><?= badge_status($p['status']) ?></td>
                <td><?= e($p['created_by_name']) ?></td>
                <td style="white-space:nowrap;">
                    <a href="po_detail.php?id=<?= $p['id'] ?>" class="action-icon" title="Detail">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                    <?php if (in_array(current_role(), ['admin','staff'])): ?>
                    <a href="po_edit.php?id=<?= $p['id'] ?>" class="action-icon" title="Edit" style="margin-left:4px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <?php endif; ?>
                    <a href="po_print.php?id=<?= $p['id'] ?>" class="action-icon" title="Print" style="margin-left:4px;" target="_blank">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
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
