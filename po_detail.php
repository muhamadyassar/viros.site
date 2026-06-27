<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();
db_init();

$role     = current_role();
$user     = get_session_user();
$now      = date('Y-m-d H:i:s');

// ─── STATISTIK UTAMA ─────────────────────────────────────────────────────────
$all_po      = db_read('purchase_orders');
$total_po    = count($all_po);
$pending_po  = count(array_filter($all_po, fn($p) => $p['status'] === 'pending'));
$approved_po = count(array_filter($all_po, fn($p) => $p['status'] === 'approved'));
$completed_po= count(array_filter($all_po, fn($p) => $p['status'] === 'completed'));
$rejected_po = count(array_filter($all_po, fn($p) => $p['status'] === 'rejected'));
$revision_po = count(array_filter($all_po, fn($p) => $p['status'] === 'revision'));
$total_nilai = array_sum(array_column($all_po, 'total_amount'));

// ─── PO TERBARU (10 terakhir) ────────────────────────────────────────────────
$all_users = db_read('users');
$user_map  = [];
foreach ($all_users as $u) $user_map[$u['id']] = $u['username'];

$sorted_po = $all_po;
usort($sorted_po, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$recent_pos = array_slice($sorted_po, 0, 10);
foreach ($recent_pos as &$p) {
    $p['created_by_name'] = $user_map[$p['created_by'] ?? 0] ?? '-';
}
unset($p);

// ─── TREN BULANAN 12 BULAN ───────────────────────────────────────────────────
$tren = [];
for ($i = 11; $i >= 0; $i--) {
    $key   = date('Y-m', strtotime("-$i months"));
    $label = date('M', strtotime("-$i months"));
    $tren[$key] = ['label' => $label, 'total' => 0];
}
foreach ($all_po as $p) {
    $key = substr($p['order_date'] ?? '', 0, 7);
    if (isset($tren[$key])) {
        $tren[$key]['total'] += (float)($p['total_amount'] ?? 0);
    }
}
$tren_labels = json_encode(array_column(array_values($tren), 'label'));
$tren_values = json_encode(array_column(array_values($tren), 'total'));

// ─── TOP 5 VENDOR ────────────────────────────────────────────────────────────
$vendor_totals = [];
foreach ($all_po as $p) {
    $v = $p['vendor_name'] ?? 'Unknown';
    $vendor_totals[$v] = ($vendor_totals[$v] ?? 0) + (float)($p['total_amount'] ?? 0);
}
arsort($vendor_totals);
$top_vendors   = array_slice($vendor_totals, 0, 5, true);
$vendor_labels = json_encode(array_keys($top_vendors));
$vendor_values = json_encode(array_values($top_vendors));

// ─── DONUT CHART STATUS ──────────────────────────────────────────────────────
$donut_values = json_encode([$approved_po, $pending_po, $rejected_po, $completed_po, $revision_po]);
$donut_labels = json_encode(['Disetujui', 'Proses', 'Ditolak', 'Selesai', 'Revisi']);

// ─── RATA-RATA WAKTU APPROVAL ────────────────────────────────────────────────
$histories = db_read('po_history');
$approval_diffs = [];
foreach ($histories as $h) {
    if ($h['new_status'] === 'approved') {
        $po = db_find('purchase_orders', (int)$h['po_id']);
        if ($po && !empty($po['created_at']) && !empty($h['changed_at'])) {
            $diff = (strtotime($h['changed_at']) - strtotime($po['created_at'])) / 86400;
            if ($diff >= 0) $approval_diffs[] = $diff;
        }
    }
}
$avg_approval = count($approval_diffs) > 0 ? round(array_sum($approval_diffs) / count($approval_diffs), 1) : 0;

// ─── TOPBAR TITLE BERDASARKAN ROLE ───────────────────────────────────────────
$topbar_titles = [
    'komisaris' => 'Dashboard Komisaris',
    'direktur'  => 'Dashboard Direktur',
    'manager'   => 'Ringkasan Eksekutif',
    'admin'     => 'Panel Administrasi',
];
$topbar_title = $topbar_titles[$role] ?? 'Dashboard Pengadaan';
$page_title   = 'Dashboard — Viros PO System';
$current_page = 'dashboard';

// ─── RENDER ──────────────────────────────────────────────────────────────────
ob_start(); ?>

<!-- Page Header -->
<div style="margin-bottom:32px;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
            <div class="breadcrumb"><a href="dashboard.php">Sistem PO Keluar</a> › <span>Dashboard</span></div>
            <div class="page-title">
                <?php if ($role === 'komisaris'): ?>Dashboard Komisaris
                <?php elseif ($role === 'direktur'): ?>Dashboard Direktur
                <?php elseif ($role === 'manager'): ?>Ringkasan Eksekutif
                <?php elseif ($role === 'admin'): ?>Panel Administrasi
                <?php else: ?>Dashboard Pengadaan<?php endif; ?>
            </div>
            <div class="page-sub">Pantau performa pengadaan secara real-time — <?= date('d F Y') ?></div>
        </div>
        <div style="display:flex;gap:12px;">
            <a href="po_list.php" class="btn btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Daftar PO
            </a>
            <?php if (in_array($role, ['admin','staff'])): ?>
            <a href="po_create.php" class="btn btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Buat PO Baru
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="stats-grid" style="margin-bottom:32px;">
    <div class="stat-card featured">
        <div class="stat-label">Total PO</div>
        <div class="stat-value"><?= $total_po ?></div>
        <div class="stat-sub">Semua Purchase Order</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Nilai</div>
        <div class="stat-value" style="font-size:22px;"><?= 'Rp ' . number_format($total_nilai/1000000, 1, ',', '.') ?>M</div>
        <div class="stat-sub">Akumulasi <?= date('Y') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Menunggu</div>
        <div class="stat-value"><?= $pending_po ?></div>
        <div class="stat-sub"><?= $revision_po ?> butuh revisi</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Disetujui</div>
        <div class="stat-value"><?= $approved_po ?></div>
        <div class="stat-sub"><?= $rejected_po ?> ditolak</div>
    </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px;margin-bottom:28px;">
    <!-- Tren Bulanan -->
    <div class="card">
        <div class="card-header"><span class="card-title">Tren Pengeluaran Bulanan</span></div>
        <div class="card-body"><canvas id="chartTren" height="80"></canvas></div>
    </div>
    <!-- Donut Status -->
    <div class="card">
        <div class="card-header"><span class="card-title">Distribusi Status PO</span></div>
        <div class="card-body" style="display:flex;align-items:center;gap:24px;">
            <div style="position:relative;width:140px;height:140px;flex-shrink:0;">
                <canvas id="chartDonut" width="140" height="140"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <span style="font-size:22px;font-weight:700;"><?= $total_po ?></span>
                    <span style="font-size:10px;text-transform:uppercase;color:#666;letter-spacing:0.5px;">Total</span>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php
                $donut_colors = ['#000','#555','#999','#333','#777'];
                $donut_data   = [['Disetujui',$approved_po],['Proses',$pending_po],['Ditolak',$rejected_po],['Selesai',$completed_po],['Revisi',$revision_po]];
                foreach ($donut_data as $i => [$lbl,$val]):
                ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $donut_colors[$i] ?>;flex-shrink:0;"></div>
                    <?= e($lbl) ?> — <strong><?= $val ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Vendor & Avg Approval -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Top 5 Vendor</span></div>
        <div class="card-body">
            <?php
            $max_vendor = max(array_values($top_vendors) ?: [1]);
            foreach ($top_vendors as $vname => $vtotal):
                $pct = $max_vendor > 0 ? round($vtotal / $max_vendor * 100) : 0;
            ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
                    <span style="font-weight:600;"><?= e($vname) ?></span>
                    <span style="color:#666;font-size:12px;"><?= rupiah($vtotal) ?></span>
                </div>
                <div style="height:5px;background:#F3F4F6;border-radius:99px;overflow:hidden;">
                    <div style="height:100%;width:<?= $pct ?>%;background:#000;border-radius:99px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($top_vendors)): ?>
            <div class="empty-state">Belum ada data vendor</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Ringkasan Performa</span></div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:20px;">
                <div>
                    <div style="font-size:12px;color:#666;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:4px;">Rata-rata Waktu Approval</div>
                    <div style="font-size:28px;font-weight:700;"><?= $avg_approval ?> <span style="font-size:14px;font-weight:400;color:#666;">hari</span></div>
                </div>
                <hr style="border:none;border-top:1px solid #E0E0E0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:4px;">Selesai</div>
                        <div style="font-size:20px;font-weight:700;"><?= $completed_po ?></div>
                    </div>
                    <div>
                        <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:4px;">Ditolak</div>
                        <div style="font-size:20px;font-weight:700;"><?= $rejected_po ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel PO Terbaru -->
<div class="card">
    <div class="card-header">
        <span class="card-title">PO Terbaru</span>
        <a href="po_list.php" class="btn btn-outline btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>No. PO</th><th>Vendor</th><th>Perusahaan</th>
                <th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if (empty($recent_pos)): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-title">Belum ada Purchase Order</div></div></td></tr>
            <?php else: ?>
            <?php foreach ($recent_pos as $p): ?>
            <tr>
                <td><a href="po_detail.php?id=<?= $p['id'] ?>" style="font-weight:600;color:#000;text-decoration:none;"><?= e($p['po_number']) ?></a></td>
                <td><?= e($p['vendor_name']) ?></td>
                <td><?= e($p['customer_company'] ?? '-') ?></td>
                <td><?= e($p['order_date'] ?? '-') ?></td>
                <td><?= rupiah((float)($p['total_amount'] ?? 0)) ?></td>
                <td><?= badge_status($p['status']) ?></td>
                <td>
                    <a href="po_detail.php?id=<?= $p['id'] ?>" class="action-icon" title="Detail">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Tren Chart
new Chart(document.getElementById('chartTren'), {
    type: 'bar',
    data: {
        labels: <?= $tren_labels ?>,
        datasets: [{
            label: 'Total (Rp)', data: <?= $tren_values ?>,
            backgroundColor: '#000', borderRadius: 0,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'M' } } } }
});
// Donut Chart
new Chart(document.getElementById('chartDonut'), {
    type: 'doughnut',
    data: {
        labels: <?= $donut_labels ?>,
        datasets: [{ data: <?= $donut_values ?>, backgroundColor: ['#000','#555','#999','#333','#777'], borderWidth: 0 }]
    },
    options: { responsive: false, plugins: { legend: { display: false } }, cutout: '65%' }
});
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
