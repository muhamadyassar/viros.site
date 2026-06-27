<?php
/**
 * layout.php — Layout utama (pengganti base.html Jinja2)
 * Dipanggil dengan: include 'includes/layout.php';
 * Variabel yang diharapkan: $page_title, $topbar_title, $current_page, $extra_css, $content
 */

require_once __DIR__ . '/auth.php';
auth_start();

$_user = get_session_user();
$_role = $_user['role'];
$_username = $_user['username'];
$_current = $current_page ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? 'Viros PO System') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --black: #000000; --white: #FFFFFF; --bg: #F9F9F9;
            --sidebar-bg: #F3F3F3; --border: #E0E0E0; --border-dark: #CFC4C5;
            --text: #1A1C1C; --text-muted: #666666; --text-light: #5F5E5E;
            --active-bg: #E2E2E2; --sidebar-w: 260px;
            --font: 'Inter', sans-serif; --radius: 0px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font); background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }
        .sidebar { position: fixed; left: 0; top: 0; width: var(--sidebar-w); height: 100vh; background: var(--sidebar-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 32px; display: flex; flex-direction: column; gap: 4px; }
        .sidebar-logo { display: flex; align-items: center; gap: 0; margin-bottom: 12px; }
        .sidebar-logo img { height: 48px; }
        .logo-name { font-weight: 600; font-size: 24px; line-height: 30px; letter-spacing: -0.24px; color: var(--black); }
        .logo-sub { font-weight: 400; font-size: 16px; color: var(--text-muted); }
        .sidebar-nav { flex: 1; padding: 16px 0 0; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 32px; color: var(--text-muted); font-size: 14px; font-weight: 400; text-decoration: none; transition: all 0.1s; }
        .nav-link:hover { color: var(--black); background: var(--active-bg); }
        .nav-link.active { color: var(--black); background: var(--active-bg); }
        .nav-link svg { width: 18px; height: 18px; flex-shrink: 0; }
        .sidebar-divider { height: 1px; background: var(--border); margin: 0; }
        .sidebar-footer { padding: 32px; }
        .logout-btn { display: flex; align-items: center; gap: 12px; color: var(--text-muted); font-size: 14px; text-decoration: none; }
        .logout-btn:hover { color: var(--black); }
        .readonly-wrap { padding: 0 32px 16px; }
        .readonly-indicator { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: var(--white); border: 1px solid var(--border); font-size: 10px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: var(--text); }
        .main { margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: var(--white); border-bottom: 1px solid var(--border); padding: 0 40px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .topbar-title { font-weight: 900; font-size: 18px; letter-spacing: 1.8px; text-transform: uppercase; color: var(--black); }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .topbar-user { display: flex; align-items: center; gap: 12px; padding-left: 16px; border-left: 1px solid var(--border); }
        .topbar-user-info { text-align: right; }
        .topbar-user-name { font-weight: 700; font-size: 14px; color: var(--text); display: block; }
        .topbar-user-role { font-size: 10px; color: var(--text-muted); text-transform: uppercase; }
        .topbar-avatar { width: 32px; height: 32px; background: var(--active-bg); border: 1px solid var(--border); border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: var(--text); flex-shrink: 0; }
        .content { padding: 40px; flex: 1; }
        .alert { padding: 12px 16px; margin-bottom: 20px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 10px; border: 1px solid; }
        .alert-success { background: #F0FDF4; color: #166534; border-color: #86EFAC; }
        .alert-danger  { background: #FEF2F2; color: #991B1B; border-color: #FCA5A5; }
        .alert-warning { background: #FFFBEB; color: #92400E; border-color: #FCD34D; }
        .alert-info    { background: #F0F9FF; color: #0C4A6E; border-color: #7DD3FC; }
        .card { background: var(--white); border: 1px solid var(--border); }
        .card-header { padding: 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-weight: 600; font-size: 18px; color: var(--text); }
        .card-body { padding: 24px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; margin-bottom: 40px; }
        .stat-card { background: var(--white); border: 1px solid var(--border); padding: 24px; position: relative; }
        .stat-card:not(:first-child) { margin-left: -1px; }
        .stat-card.featured { border: 2px solid var(--black); z-index: 1; }
        .stat-label { font-size: 16px; font-weight: 400; letter-spacing: 1.6px; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; }
        .stat-value { font-weight: 700; font-size: 32px; letter-spacing: -0.64px; color: var(--black); line-height: 1; margin-bottom: 8px; }
        .stat-sub { font-size: 16px; color: var(--text-muted); display: flex; align-items: center; gap: 4px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { background: #F3F3F3; border-bottom: 1px solid var(--border); }
        th { padding: 16px 24px; text-align: left; font-size: 12px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: var(--black); white-space: nowrap; border-bottom: 1px solid var(--border); }
        td { padding: 18px 24px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tbody tr:nth-child(even) { background: #F3F3F3; }
        tbody tr:hover { background: var(--active-bg); }
        .badge { display: inline-flex; align-items: center; padding: 4px 12px; font-size: 10px; font-weight: 400; text-transform: uppercase; letter-spacing: 0; }
        .badge-pending   { background: var(--white); border: 1px solid var(--black); color: var(--black); }
        .badge-approved  { background: var(--black); color: var(--white); }
        .badge-rejected  { background: var(--white); border: 1px solid var(--black); color: var(--black); }
        .badge-completed { background: #333333; color: var(--white); }
        .badge-revision  { background: var(--white); border: 1px solid var(--black); color: var(--black); }
        .badge-draft     { background: var(--white); border: 1px solid #999; color: #999; }
        .badge-admin     { background: var(--black); color: var(--white); }
        .badge-staff     { background: var(--white); border: 1px solid var(--black); color: var(--black); }
        .badge-manager   { background: var(--white); border: 1px solid var(--black); color: var(--black); }
        .badge-direktur  { background: #333333; color: var(--white); }
        .badge-komisaris { background: var(--white); border: 1px solid var(--black); color: var(--black); }
        .badge-active    { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; color: var(--black); }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--black); }
        .dot-off { background: var(--border); border: 1px solid var(--black); }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; transition: all 0.1s; font-family: var(--font); letter-spacing: 0.6px; text-transform: uppercase; }
        .btn-primary { background: var(--black); color: var(--white); }
        .btn-primary:hover { background: #333333; }
        .btn-outline { background: var(--white); border: 1px solid var(--black); color: var(--black); }
        .btn-outline:hover { background: var(--bg); }
        .btn-danger { background: var(--black); color: var(--white); }
        .btn-danger:hover { background: #BA1A1A; }
        .btn-success { background: var(--black); color: var(--white); }
        .btn-ghost { background: transparent; color: var(--text-muted); border: none; }
        .btn-ghost:hover { color: var(--black); }
        .btn-sm { padding: 8px 16px; font-size: 11px; }
        .btn-xs { padding: 4px 8px; font-size: 10px; }
        .form-group { margin-bottom: 24px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; letter-spacing: 0.6px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border); font-size: 16px; font-family: var(--font); background: var(--white); color: var(--text); transition: border-color 0.1s; outline: none; border-radius: 0; }
        .form-control:focus { border-color: var(--black); }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236B7280' stroke-width='1.5' fill='none'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
        .search-bar { position: relative; }
        .search-bar input { padding-left: 40px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 40px; }
        .page-title { font-weight: 700; font-size: 32px; letter-spacing: -0.64px; color: var(--black); line-height: 40px; }
        .page-sub { color: var(--text-muted); font-size: 16px; margin-top: 4px; }
        .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
        .breadcrumb a { color: var(--text-muted); text-decoration: none; }
        .breadcrumb a:hover { color: var(--black); }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--white); width: 425px; max-width: 95vw; border: 2px solid var(--black); }
        .modal-header { padding: 32px 32px 16px; display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-weight: 600; font-size: 18px; color: var(--black); }
        .modal-close { background: none; border: none; cursor: pointer; color: var(--text-muted); width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
        .modal-close:hover { color: var(--black); }
        .modal-body { padding: 0 32px 16px; font-size: 16px; color: var(--text); line-height: 26px; }
        .modal-footer { padding: 16px 32px 32px; display: flex; justify-content: flex-end; gap: 16px; }
        .modal-accent { height: 4px; background: var(--black); }
        .empty-state { text-align: center; padding: 56px 20px; color: var(--text-muted); }
        .empty-state-icon { font-size: 32px; margin-bottom: 12px; opacity: 0.3; }
        .empty-title { font-weight: 700; font-size: 14px; color: var(--text); margin-bottom: 6px; letter-spacing: 0.4px; text-transform: uppercase; }
        .divider { border: none; border-top: 1px solid var(--border); margin: 20px 0; }
        .page-top-bar { height: 4px; background: var(--black); position: fixed; top: 0; left: 0; right: 0; z-index: 200; }
        .action-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: var(--white); color: var(--text-muted); cursor: pointer; text-decoration: none; transition: all 0.1s; }
        .action-icon:hover { border-color: var(--black); color: var(--black); }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main { margin-left: 0; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
    <?= $extra_css ?? '' ?>
</head>
<body>
<div class="page-top-bar"></div>

<?php if (is_logged_in()): ?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">
            <img src="static/img/logo_viros.png" alt="Viros Logo" style="height:48px;" onerror="this.style.display='none'">
        </div>
        <div class="logo-name">PT. Viros Prime Solution</div>
        <div class="logo-sub">Pengadaan Korporat</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-link <?= $_current === 'dashboard' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="po_list.php" class="nav-link <?= in_array($_current, ['po_list','po_detail','po_edit','po_status']) ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Daftar PO
        </a>
        <?php if (in_array($_role, ['admin', 'staff'])): ?>
        <a href="po_create.php" class="nav-link <?= $_current === 'po_create' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Buat PO Baru
        </a>
        <?php endif; ?>
        <a href="po_riwayat.php" class="nav-link <?= $_current === 'po_riwayat' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Riwayat PO
        </a>
        <?php if ($_role === 'admin'): ?>
        <a href="user_management.php" class="nav-link <?= $_current === 'user_management' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Manajemen Pengguna
        </a>
        <?php endif; ?>
    </nav>
    <?php if (in_array($_role, ['direktur', 'komisaris'])): ?>
    <div class="readonly-wrap">
        <div class="readonly-indicator">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Mode Read-Only
        </div>
    </div>
    <?php endif; ?>
    <div class="sidebar-divider"></div>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar
        </a>
    </div>
</aside>
<?php endif; ?>

<main class="main">
    <?php if (is_logged_in()): ?>
    <div class="topbar">
        <div class="topbar-title"><?= e($topbar_title ?? 'Sistem Informasi PO Keluar') ?></div>
        <div class="topbar-right">
            <div class="topbar-user">
                <div class="topbar-user-info">
                    <span class="topbar-user-name"><?= e($_username) ?></span>
                    <span class="topbar-user-role"><?= e($_role) ?></span>
                </div>
                <div class="topbar-avatar"><?= strtoupper(substr($_username, 0, 1)) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="content">
        <?php render_flashes(); ?>
        <?= $content ?? '' ?>
    </div>
</main>
<?= $modals ?? '' ?>
<?= $scripts ?? '' ?>
</body>
</html>
