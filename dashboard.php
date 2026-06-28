<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
db_init();

$role     = current_role();
$all_po   = db_read('purchase_orders');
$all_users= db_read('users');
$user_map = [];
foreach ($all_users as $u) $user_map[$u['id']] = $u['username'];

$total_po    = count($all_po);
$pending_po  = count(array_filter($all_po, fn($p)=>$p['status']==='pending'));
$approved_po = count(array_filter($all_po, fn($p)=>$p['status']==='approved'));
$completed_po= count(array_filter($all_po, fn($p)=>$p['status']==='completed'));
$rejected_po = count(array_filter($all_po, fn($p)=>$p['status']==='rejected'));
$revision_po = count(array_filter($all_po, fn($p)=>$p['status']==='revision'));
$total_nilai = (float)array_sum(array_column($all_po,'total_amount'));

$sorted = $all_po;
usort($sorted, fn($a,$b)=>strcmp($b['created_at']??'',$a['created_at']??''));
$recent_pos = array_slice($sorted, 0, 10);
foreach ($recent_pos as &$p) $p['created_by_name'] = $user_map[$p['created_by']??0]??'-';
unset($p);

// Tren 12 bulan
$tren = [];
for ($i=11;$i>=0;$i--) {
    $key=$date_key=date('Y-m',strtotime("-$i months"));
    $tren[$key]=['label'=>date('M',strtotime("-$i months")),'total'=>0];
}
foreach ($all_po as $p) {
    $k=substr($p['order_date']??'',0,7);
    if (isset($tren[$k])) $tren[$k]['total']+=(float)($p['total_amount']??0);
}
$tren_labels = json_encode(array_column(array_values($tren),'label'));
$tren_values = json_encode(array_column(array_values($tren),'total'));

// Top 5 vendor
$vt=[];
foreach ($all_po as $p) { $v=$p['vendor_name']??''; $vt[$v]=($vt[$v]??0)+(float)($p['total_amount']??0); }
arsort($vt);
$top5=$arr=array_slice($vt,0,5,true);
$vendor_labels=json_encode(array_keys($top5));
$vendor_values=json_encode(array_values($top5));

// Donut
$donut_values=json_encode([$approved_po,$pending_po,$rejected_po,$completed_po,$revision_po]);
$donut_labels=json_encode(['Disetujui','Proses','Ditolak','Selesai','Revisi']);

// Avg approval
$histories=db_read('po_history');
$diffs=[];
foreach ($histories as $h) {
    if ($h['new_status']==='approved') {
        $po=db_find('purchase_orders',(int)$h['po_id']);
        if ($po && !empty($po['created_at']) && !empty($h['changed_at'])) {
            $d=(strtotime($h['changed_at'])-strtotime($po['created_at']))/86400;
            if ($d>=0) $diffs[]=$d;
        }
    }
}
$avg_approval = count($diffs)>0 ? round(array_sum($diffs)/count($diffs),1) : 0;
$now_year = date('Y');

$topbar_titles=['komisaris'=>'Dashboard Komisaris','direktur'=>'Dashboard Direktur','manager'=>'Ringkasan Eksekutif','admin'=>'Panel Administrasi'];
$topbar_title  = $topbar_titles[$role] ?? 'Dashboard Pengadaan';
$page_title    = 'Dashboard — Viros PO System';
$current_page  = 'dashboard';

$pct_approved  = $total_po>0 ? round($approved_po/$total_po*100) : 0;
$pct_pending   = $total_po>0 ? round($pending_po/$total_po*100)  : 0;
$pct_rejected  = $total_po>0 ? round($rejected_po/$total_po*100) : 0;
$pct_completed = $total_po>0 ? round($completed_po/$total_po*100): 0;

$extra_css = <<<CSS
<style>
.dash-header { margin-bottom: 32px; }
.dash-header-top { display: flex; align-items: flex-start; justify-content: space-between; }
.dash-breadcrumb { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
.dash-breadcrumb a { color: var(--text-muted); text-decoration: none; }
.dash-breadcrumb a:hover { color: var(--black); }
.dash-breadcrumb span { margin: 0 6px; }
.dash-title { font-weight: 700; font-size: 32px; letter-spacing: -0.64px; color: var(--black); line-height: 40px; }
.dash-sub { font-size: 14px; color: var(--text-muted); margin-top: 4px; }
.dash-actions { display: flex; align-items: center; gap: 12px; }
.design-note { display: flex; align-items: flex-start; gap: 12px; background: #EFF6FF; border: 1px solid #BFDBFE; padding: 14px 18px; margin-bottom: 28px; font-size: 13px; color: #1E40AF; line-height: 1.6; }
.design-note svg { flex-shrink: 0; margin-top: 1px; color: #3B82F6; }
.design-note strong { color: #1E3A8A; }
.date-range-btn { display: flex; align-items: center; gap: 8px; border: 1px solid var(--border); background: var(--white); padding: 10px 16px; font-size: 13px; font-family: var(--font); color: var(--text); cursor: pointer; }
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; margin-bottom: 32px; }
.kpi-card { background: var(--white); border: 1px solid var(--border); padding: 24px 28px; position: relative; }
.kpi-card:not(:first-child) { margin-left: -1px; }
.kpi-card.featured { border: 2px solid var(--black); z-index: 1; }
.kpi-label { font-size: 12px; font-weight: 400; letter-spacing: 0.6px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; }
.kpi-icon { position: absolute; top: 20px; right: 20px; color: var(--text-muted); opacity: 0.4; }
.kpi-value { font-weight: 700; font-size: 36px; letter-spacing: -1px; color: var(--black); line-height: 1; margin-bottom: 8px; }
.kpi-sub { font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
.kpi-sub .up   { color: #16A34A; font-weight: 700; font-size: 12px; }
.kpi-sub .down { color: #16A34A; font-weight: 700; font-size: 12px; }
.kpi-sub .warn { color: #DC2626; font-size: 12px; }
.kpi-progress { margin-top: 12px; }
.kpi-progress-bar { height: 5px; background: #E5E7EB; border-radius: 99px; overflow: hidden; }
.kpi-progress-fill { height: 100%; background: #16A34A; border-radius: 99px; }
.kpi-progress-label { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
.chart-row { display: grid; gap: 24px; margin-bottom: 28px; }
.chart-row-2 { grid-template-columns: 1fr 1fr; }
.chart-row-3 { grid-template-columns: 3fr 2fr; }
.chart-card { background: var(--white); border: 1px solid var(--border); padding: 24px; }
.chart-title { font-weight: 600; font-size: 15px; color: var(--black); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
.vendor-list { display: flex; flex-direction: column; gap: 14px; }
.vendor-item { display: flex; flex-direction: column; gap: 5px; }
.vendor-header { display: flex; justify-content: space-between; font-size: 13px; }
.vendor-name { font-weight: 600; color: var(--black); }
.vendor-val { color: var(--text-muted); font-size: 12px; }
.vendor-bar-bg { height: 5px; background: #F3F4F6; border-radius: 99px; overflow: hidden; }
.vendor-bar-fill { height: 100%; background: var(--black); border-radius: 99px; }
.donut-wrap { display: flex; align-items: center; gap: 32px; }
.donut-canvas-wrap { position: relative; flex-shrink: 0; }
.donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.donut-center-val { font-size: 22px; font-weight: 700; color: var(--black); }
.donut-center-lbl { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
.donut-legend { display: flex; flex-direction: column; gap: 10px; }
.donut-legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text); }
.donut-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.table-section { background: var(--white); border: 1px solid var(--border); }
.table-section-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.table-section-title { font-weight: 600; font-size: 16px; color: var(--black); }
.table-section-sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.table-search { display: flex; align-items: center; gap: 10px; border: 1px solid var(--border); padding: 8px 14px; background: var(--white); min-width: 220px; }
.table-search svg { color: var(--text-muted); flex-shrink: 0; }
.table-search input { border: none; outline: none; font-size: 13px; font-family: var(--font); background: transparent; width: 100%; color: var(--text); }
.table-search input::placeholder { color: var(--text-muted); }
.filter-btn { display: flex; align-items: center; gap: 6px; border: 1px solid var(--border); background: var(--white); padding: 8px 14px; font-size: 12px; font-family: var(--font); font-weight: 600; cursor: pointer; color: var(--text); }
.filter-btn:hover { border-color: var(--black); }
.action-card { background: var(--black); padding: 32px; }
.action-card h3 { font-weight: 700; font-size: 24px; line-height: 32px; letter-spacing: -0.24px; color: var(--white); margin-bottom: 16px; }
.action-card p { font-size: 16px; line-height: 24px; color: rgba(255,255,255,0.8); margin-bottom: 32px; }
.action-card .btn-white { background: var(--white); color: var(--black); padding: 16px 0; width: 100%; display: flex; justify-content: center; font-size: 12px; letter-spacing: 1.2px; border: none; font-family: var(--font); font-weight: 700; cursor: pointer; text-decoration: none; }
</style>
CSS;

ob_start();

// ── Helper badge inline ──
function badge_s(string $s): string {
    $m=['draft'=>'Draft','pending'=>'Proses','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai','revision'=>'Revisi'];
    return '<span class="badge badge-'.e($s).'">'.e($m[$s]??$s).'</span>';
}
function badge_s2(string $s): string { // versi uppercase dengan label berbeda
    $m=['pending'=>'PROSES','approved'=>'DISETUJUI','rejected'=>'✕ DITOLAK','completed'=>'SELESAI','revision'=>'REVISI','draft'=>'DRAFT'];
    return '<span class="badge badge-'.e($s).'" style="font-size:10px;letter-spacing:0.5px;">'.e($m[$s]??strtoupper($s)).'</span>';
}

// ═══════════════════════════════════════════
// DASHBOARD KOMISARIS
// ═══════════════════════════════════════════
if ($role === 'komisaris'):
?>
<div class="dash-header">
    <div class="dash-header-top">
        <div>
            <div class="dash-breadcrumb"><a href="#">Sistem PO Keluar</a><span>›</span><span>Dashboard Komisaris</span></div>
            <div class="dash-title">Dashboard Komisaris</div>
            <div class="dash-sub">Pantau performa pengadaan barang secara real-time.</div>
        </div>
        <div class="dash-actions">
            <a href="po_list.php" class="btn btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Ekspor Laporan
            </a>
        </div>
    </div>
</div>
<div class="design-note">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div><strong>Catatan Desain:</strong> Role Komisaris bersifat pengawasan — tidak ada tombol ubah data, approve/reject, atau kirim email di seluruh sistem. Hanya melihat &amp; mengekspor data.</div>
</div>
<div class="kpi-grid">
    <div class="kpi-card featured">
        <div class="kpi-label">Total PO</div>
        <div class="kpi-value"><?= $total_po ?></div>
        <div class="kpi-sub"><span class="up">↑ 12%</span> dari bulan lalu</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Nilai PO</div>
        <div class="kpi-value" style="font-size:24px;">Rp <?= number_format($total_nilai/1000000,0,',','.') ?> M</div>
        <div class="kpi-sub">Total akumulasi tahun <?= $now_year ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">PO Disetujui</div>
        <div class="kpi-value"><?= $approved_po ?></div>
        <div class="kpi-progress">
            <div class="kpi-progress-bar"><div class="kpi-progress-fill" style="width:<?= $pct_approved ?>%"></div></div>
            <div class="kpi-progress-label"><?= $approved_po ?> / <?= $total_po ?></div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">PO Ditolak</div>
        <div class="kpi-value"><?= $rejected_po ?></div>
        <div class="kpi-sub"><?= $rejected_po>0 ? '<span class="warn">Perlu evaluasi vendor</span>' : 'Tidak ada penolakan' ?></div>
    </div>
</div>
<div class="chart-row chart-row-2">
    <div class="chart-card">
        <div class="chart-title">Grafik Nilai PO per Pemasok (Bar Chart)</div>
        <canvas id="chartVendorKomisaris" height="200"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-title">Grafik Distribusi Status PO (Donut Chart)</div>
        <div class="donut-wrap">
            <div class="donut-canvas-wrap">
                <canvas id="chartDonutKomisaris" width="160" height="160"></canvas>
                <div class="donut-center">
                    <div class="donut-center-val"><?= $total_po ?></div>
                    <div class="donut-center-lbl">TOTAL PO</div>
                </div>
            </div>
            <div class="donut-legend">
                <div class="donut-legend-item"><div class="donut-legend-dot" style="background:#16A34A"></div>Disetujui (<?= $pct_approved ?>%)</div>
                <div class="donut-legend-item"><div class="donut-legend-dot" style="background:#F59E0B"></div>Proses (<?= $pct_pending ?>%)</div>
                <div class="donut-legend-item"><div class="donut-legend-dot" style="background:#EF4444"></div>Ditolak (<?= $pct_rejected ?>%)</div>
                <div class="donut-legend-item"><div class="donut-legend-dot" style="background:#334155"></div>Selesai (<?= $pct_completed ?>%)</div>
            </div>
        </div>
    </div>
</div>
<div class="table-section">
    <div class="table-section-header">
        <div class="table-section-title">Seluruh PO (Read Only)</div>
        <div class="table-search">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Cari No PO..." oninput="filterTable(this,'tblKomisaris')">
        </div>
    </div>
    <div class="table-wrap">
        <table id="tblKomisaris">
            <thead><tr><th>NO PO</th><th>CUSTOMER</th><th>TANGGAL</th><th>PERUSAHAAN CUSTOMER</th><th>TOTAL</th><th>STATUS</th><th style="text-align:center;">AKSI</th></tr></thead>
            <tbody>
            <?php if (empty($recent_pos)): ?>
            <tr><td colspan="7"><div class="empty-state" style="padding:32px;"><div class="empty-state-icon">📋</div><div class="empty-title">Belum ada data PO</div></div></td></tr>
            <?php else: foreach ($recent_pos as $po): ?>
            <tr>
                <td><strong><?= e($po['po_number']) ?></strong></td>
                <td><?= e($po['vendor_name']) ?></td>
                <td style="color:var(--text-muted);font-size:12px;"><?= tgl_short($po['order_date']??'') ?></td>
                <td><?= e($po['customer_company']??'-') ?></td>
                <td><strong>Rp <?= number_format((float)($po['total_amount']??0),0,',','.') ?></strong></td>
                <td><?= badge_s($po['status']) ?></td>
                <td style="text-align:center;">
                    <a href="po_detail.php?id=<?= $po['id'] ?>" class="action-icon" title="Lihat"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                    <a href="po_print.php?id=<?= $po['id'] ?>" class="action-icon" title="Cetak"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:14px 24px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted);">Menampilkan <?= count($recent_pos) ?> dari <?= $total_po ?> entri</div>
</div>
<?php

// ═══════════════════════════════════════════
// DASHBOARD DIREKTUR
// ═══════════════════════════════════════════
elseif ($role === 'direktur'):
$max_vendor = !empty($top5) ? max(array_values($top5)) : 1;
?>
<div class="dash-header">
    <div class="dash-header-top">
        <div>
            <div class="dash-title">Dashboard Direktur</div>
            <div class="dash-sub">Ringkasan aktivitas pengadaan dan performa operasional.</div>
        </div>
        <div class="dash-actions">
            <button class="date-range-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                01 Jan – 31 Des <?= $now_year ?>
            </button>
            <a href="po_list.php" class="btn btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Ekspor Laporan
            </a>
        </div>
    </div>
</div>
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total PO</div>
        <div class="kpi-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg></div>
        <div class="kpi-value"><?= $total_po ?></div>
        <div class="kpi-sub"><span class="up">↑ +12%</span>&nbsp;bln lalu</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Nilai</div>
        <div class="kpi-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
        <div class="kpi-value" style="font-size:24px;">Rp <?= number_format($total_nilai/1000000,1,',','.') ?> M</div>
        <div class="kpi-sub"><span class="up">↑ +8.4%</span>&nbsp;bln lalu</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Avg Approval</div>
        <div class="kpi-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="kpi-value"><?= $avg_approval ?> <span style="font-size:18px;font-weight:400;">Hari</span></div>
        <div class="kpi-sub"><span class="down">↓ -0.2d</span>&nbsp;efisiensi</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">PO Selesai</div>
        <div class="kpi-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div class="kpi-value"><?= $completed_po ?></div>
        <div class="kpi-sub"><?= $pct_completed ?>% dari total PO</div>
    </div>
</div>
<div class="chart-row chart-row-3">
    <div class="chart-card">
        <div class="chart-title">
            Tren Pengeluaran Bulanan
            <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:400;color:var(--text-muted);"><span style="display:inline-block;width:12px;height:12px;background:var(--black);"></span> Nilai PO</span>
        </div>
        <canvas id="chartTrenDirektur" height="180"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-title">Nilai PO per Pemasok (Top 5)</div>
        <div class="vendor-list">
            <?php foreach ($top5 as $vname => $vtotal): $pct=round($vtotal/$max_vendor*100); ?>
            <div class="vendor-item">
                <div class="vendor-header">
                    <span class="vendor-name"><?= e($vname) ?></span>
                    <span class="vendor-val">Rp <?= number_format($vtotal/1000000,0,',','.') ?> Jt</span>
                </div>
                <div class="vendor-bar-bg"><div class="vendor-bar-fill" style="width:<?= $pct ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="table-section">
    <div class="table-section-header">
        <div>
            <div class="table-section-title">Seluruh PO (Read Only)</div>
            <div class="table-section-sub">Tampilan data lengkap pesanan pembelian.</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="table-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Cari No PO atau Pemasok..." oninput="filterTable(this,'tblDirektur')">
            </div>
            <button class="filter-btn"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>Filter</button>
        </div>
    </div>
    <div class="table-wrap">
        <table id="tblDirektur">
            <thead><tr><th>NO PO</th><th>CUSTOMER</th><th>TANGGAL</th><th>PERUSAHAAN CUSTOMER</th><th>TOTAL</th><th style="text-align:center;">STATUS</th><th style="text-align:center;">AKSI</th></tr></thead>
            <tbody>
            <?php if (empty($recent_pos)): ?>
            <tr><td colspan="7"><div class="empty-state" style="padding:32px;"><div class="empty-state-icon">📋</div><div class="empty-title">Belum ada data PO</div></div></td></tr>
            <?php else: foreach ($recent_pos as $po): ?>
            <tr>
                <td><strong><?= e($po['po_number']) ?></strong></td>
                <td><strong><?= e($po['vendor_name']) ?></strong></td>
                <td style="color:var(--text-muted);font-size:12px;"><?= tgl_short($po['order_date']??'') ?></td>
                <td><?= e($po['customer_company']??'-') ?></td>
                <td><strong>Rp <?= number_format((float)($po['total_amount']??0),0,',','.') ?></strong></td>
                <td style="text-align:center;"><?= badge_s2($po['status']) ?></td>
                <td style="text-align:center;">
                    <a href="po_detail.php?id=<?= $po['id'] ?>" class="action-icon" title="Lihat"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                    <a href="po_print.php?id=<?= $po['id'] ?>" class="action-icon" title="Cetak"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:14px 24px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted);">Menampilkan 1–<?= count($recent_pos) ?> dari <?= $total_po ?> Purchase Orders</div>
</div>
<?php

// ═══════════════════════════════════════════
// DASHBOARD ADMIN / MANAGER / STAFF
// ═══════════════════════════════════════════
else:
?>
<div class="page-header">
    <div>
        <div class="page-title">
            <?php if ($role==='manager'): ?>Ringkasan Eksekutif
            <?php elseif ($role==='admin'): ?>Panel Administrasi
            <?php else: ?>Dashboard Pengadaan<?php endif; ?>
        </div>
        <div class="page-sub">Selamat datang, <strong><?= e(current_username()) ?></strong>. Berikut status pengadaan hari ini.</div>
    </div>
    <?php if (in_array($role,['admin','staff'])): ?>
    <a href="po_create.php" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Buat PO Baru
    </a>
    <?php endif; ?>
</div>

<div class="kpi-grid">
    <div class="kpi-card featured">
        <div class="kpi-label">Total PO</div>
        <div class="kpi-value"><?= $total_po ?></div>
        <div class="kpi-sub"><span class="up">↑ 12.5%</span>&nbsp;dari bulan lalu</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Menunggu Persetujuan</div>
        <div class="kpi-value"><?= $pending_po ?></div>
        <div class="kpi-sub">Perlu tindakan</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Disetujui</div>
        <div class="kpi-value"><?= $approved_po ?></div>
        <div class="kpi-sub"><span class="up">↑ 8.2%</span>&nbsp;bulan ini</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Selesai</div>
        <div class="kpi-value"><?= $completed_po ?></div>
        <div class="kpi-sub">Telah diproses</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 286px;gap:40px;margin-bottom:40px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Purchase Orders Terbaru</span>
            <a href="po_list.php" class="btn btn-ghost btn-sm" style="letter-spacing:0;text-transform:none;font-weight:400;font-size:12px;color:var(--text-muted);">Lihat Semua <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>No. PO</th><th>Vendor</th><th>Tanggal</th><th>Total</th><th style="text-align:center;">Status</th><th style="text-align:right;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($recent_pos)): ?>
                <tr><td colspan="6"><div class="empty-state" style="padding:32px;"><div class="empty-state-icon">📋</div><div class="empty-title">Belum ada data PO</div></div></td></tr>
                <?php else: foreach ($recent_pos as $po): ?>
                <tr>
                    <td><strong><?= e($po['po_number']) ?></strong></td>
                    <td><?= e($po['vendor_name']) ?></td>
                    <td style="color:var(--text-muted);font-size:12px;"><?= tgl_short($po['order_date']??'') ?></td>
                    <td><strong>Rp <?= number_format((float)($po['total_amount']??0),0,',','.') ?></strong></td>
                    <td style="text-align:center;"><?= badge_s($po['status']) ?></td>
                    <td style="text-align:right;"><a href="po_detail.php?id=<?= $po['id'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:40px;">
        <?php if ($role==='manager'): ?>
        <div class="action-card">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" style="margin-bottom:16px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <h3><?= $pending_po ?> PO Menunggu Persetujuan</h3>
            <p>Terdapat PO yang memerlukan tinjauan dan keputusan Anda segera.</p>
            <a href="po_list.php" class="btn-white">Review PO Pending</a>
        </div>
        <?php else: ?>
        <div class="action-card">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" style="margin-bottom:16px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            <h3>Ekspor Laporan Kuartal Lengkap</h3>
            <p>Hasilkan CSV atau PDF komprehensif dari semua aktivitas pengadaan termasuk analisis pengeluaran dan metrik kinerja pemasok.</p>
            <a href="po_list.php" class="btn-white">Ekspor Laporan</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$scripts = <<<JS
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trenLabels   = {$tren_labels};
const trenValues   = {$tren_values};
const vendorLabels = {$vendor_labels};
const vendorValues = {$vendor_values};
const donutLabels  = {$donut_labels};
const donutValues  = {$donut_values};
const totalPO      = {$total_po};
const donutColors  = ['#16A34A','#F59E0B','#EF4444','#334155','#8B5CF6'];

const role = '{$role}';
if (role === 'komisaris') {
    const ctxVK = document.getElementById('chartVendorKomisaris');
    if (ctxVK) new Chart(ctxVK, {
        type:'bar', data:{ labels:vendorLabels, datasets:[{label:'Nilai PO (Rp)',data:vendorValues,backgroundColor:'#1A1C1C',borderRadius:2}] },
        options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{callback:v=>'Rp '+(v/1000000).toFixed(0)+' Jt',font:{size:11}},grid:{color:'#F3F3F3'}}, x:{ticks:{font:{size:11}},grid:{display:false}} } }
    });
    const ctxDK = document.getElementById('chartDonutKomisaris');
    if (ctxDK) new Chart(ctxDK, {
        type:'doughnut', data:{ labels:donutLabels, datasets:[{data:donutValues,backgroundColor:donutColors,borderWidth:0,hoverOffset:4}] },
        options:{ responsive:false, cutout:'72%', plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>` \${ctx.label}: \${ctx.raw} PO (\${totalPO>0?Math.round(ctx.raw/totalPO*100):0}%)`}} } }
    });
}
if (role === 'direktur') {
    const ctxTD = document.getElementById('chartTrenDirektur');
    if (ctxTD) new Chart(ctxTD, {
        type:'bar', data:{ labels:trenLabels, datasets:[{label:'Nilai PO',data:trenValues,backgroundColor:trenLabels.map((_,i)=>i===trenLabels.length-1?'#000000':'#D1D5DB'),borderRadius:2}] },
        options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{callback:v=>'Rp '+(v/1000000).toFixed(0)+' Jt',font:{size:11}},grid:{color:'#F3F4F6'}}, x:{ticks:{font:{size:11}},grid:{display:false}} } }
    });
}
function filterTable(input, tableId) {
    const q = input.value.toLowerCase();
    const tbl = document.getElementById(tableId);
    if (!tbl) return;
    tbl.querySelectorAll('tbody tr').forEach(row => { row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none'; });
}
</script>
JS;
include 'includes/base.php';
