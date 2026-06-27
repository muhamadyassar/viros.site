<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();
role_required(['admin']);

// ─── CREATE USER ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role']     ?? 'staff');

    if ($username && $email && $password) {
        // Cek email duplikat
        $existing = db_find_where('users', 'email', $email);
        if ($existing) {
            flash('danger', 'Email sudah digunakan.');
        } else {
            db_insert('users', [
                'username'   => $username,
                'email'      => $email,
                'password'   => password_hash($password, PASSWORD_DEFAULT),
                'role'       => $role,
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => null,
            ]);
            flash('success', "Pengguna {$username} berhasil ditambahkan.");
        }
    } else {
        flash('danger', 'Semua field wajib diisi.');
    }
    header('Location: user_management.php');
    exit;
}

// ─── TOGGLE AKTIF/NONAKTIF ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $user = db_find('users', $uid);
    if ($user) {
        db_update('users', $uid, ['is_active' => $user['is_active'] ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')]);
        flash('success', 'Status pengguna diperbarui.');
    }
    header('Location: user_management.php');
    exit;
}

// ─── DELETE USER ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid === current_user_id()) {
        flash('danger', 'Tidak bisa menghapus akun sendiri.');
    } else {
        db_delete('users', $uid);
        flash('success', 'Pengguna berhasil dihapus.');
    }
    header('Location: user_management.php');
    exit;
}

$all_users = db_read('users');
usort($all_users, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$total    = count($all_users);
$active   = count(array_filter($all_users, fn($u) => (int)$u['is_active'] === 1));
$admins   = count(array_filter($all_users, fn($u) => $u['role'] === 'admin'));
$inactive = count(array_filter($all_users, fn($u) => (int)$u['is_active'] === 0));

$page_title   = 'Manajemen Pengguna — Viros';
$topbar_title = 'Manajemen Pengguna';
$current_page = 'user_management';

ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> › <span>Manajemen Pengguna</span></div>
        <div class="page-title">Manajemen Pengguna</div>
        <div class="page-sub"><?= $total ?> pengguna terdaftar</div>
    </div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('modalCreate').classList.add('show')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Pengguna
    </button>
</div>

<!-- Stat Cards -->
<div class="stats-grid" style="margin-bottom:32px;">
    <div class="stat-card featured"><div class="stat-label">Total</div><div class="stat-value"><?= $total ?></div></div>
    <div class="stat-card"><div class="stat-label">Aktif</div><div class="stat-value"><?= $active ?></div></div>
    <div class="stat-card"><div class="stat-label">Admin</div><div class="stat-value"><?= $admins ?></div></div>
    <div class="stat-card"><div class="stat-label">Non-Aktif</div><div class="stat-value"><?= $inactive ?></div></div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header"><span class="card-title">Daftar Pengguna</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Nama Pengguna</th><th>Email</th><th>Role</th><th>Status</th><th>Terdaftar</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($all_users as $u): ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= e($u['username']) ?></div>
                    <?php if ($u['id'] === current_user_id()): ?>
                    <div style="font-size:11px;color:#666;">(Anda)</div>
                    <?php endif; ?>
                </td>
                <td><?= e($u['email']) ?></td>
                <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                <td>
                    <?php if ((int)$u['is_active'] === 1): ?>
                    <span class="badge-active"><span class="dot"></span> Aktif</span>
                    <?php else: ?>
                    <span class="badge-active" style="color:#999;"><span class="dot dot-off"></span> Non-Aktif</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#666;"><?= e($u['created_at'] ?? '-') ?></td>
                <td style="white-space:nowrap;">
                    <!-- Toggle Aktif -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-xs" title="<?= (int)$u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                            <?= (int)$u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                    </form>
                    <?php if ($u['id'] !== current_user_id()): ?>
                    <button type="button" class="btn btn-danger btn-xs" style="margin-left:4px;"
                        onclick="if(confirm('Hapus pengguna <?= e($u['username']) ?>?')) { document.getElementById('delForm<?= $u['id'] ?>').submit(); }">
                        Hapus
                    </button>
                    <form id="delForm<?= $u['id'] ?>" method="POST" style="display:none;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Pengguna -->
<div id="modalCreate" class="modal-overlay">
    <div class="modal">
        <div class="modal-accent"></div>
        <div class="modal-header">
            <span class="modal-title">Tambah Pengguna Baru</span>
            <button class="modal-close" onclick="document.getElementById('modalCreate').classList.remove('show')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Nama Pengguna *</label>
                    <input type="text" name="username" class="form-control" required placeholder="Nama lengkap">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required placeholder="email@viros.co.id">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Min. 8 karakter">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-control">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                        <option value="direktur">Direktur</option>
                        <option value="komisaris">Komisaris</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalCreate').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Pengguna</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
