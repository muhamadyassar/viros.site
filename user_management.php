<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
role_required(['admin']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    if ($action==='create') {
        $username=trim($_POST['username']??''); $email=trim($_POST['email']??'');
        $password=trim($_POST['password']??''); $role_new=trim($_POST['role']??'staff');
        if ($username && $email && $password) {
            if (db_find_where('users','email',$email)) { flash('danger','Email sudah digunakan.'); }
            else { db_insert('users',['username'=>$username,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role_new,'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null]); flash('success',"Pengguna {$username} berhasil ditambahkan."); }
        } else { flash('danger','Semua field wajib diisi.'); }
        header('Location: user_management.php'); exit;
    }
    if ($action==='toggle') {
        $uid=(int)($_POST['user_id']??0); $u=db_find('users',$uid);
        if ($u) { db_update('users',$uid,['is_active'=>$u['is_active']?0:1,'updated_at'=>date('Y-m-d H:i:s')]); flash('success','Status pengguna diperbarui.'); }
        header('Location: user_management.php'); exit;
    }
    if ($action==='delete') {
        $uid=(int)($_POST['user_id']??0);
        if ($uid===current_user_id()) { flash('danger','Tidak bisa menghapus akun sendiri.'); }
        else { db_delete('users',$uid); flash('success','Pengguna berhasil dihapus.'); }
        header('Location: user_management.php'); exit;
    }
}

$users = db_read('users');
usort($users,fn($a,$b)=>strcmp($b['created_at']??'',$a['created_at']??''));
$total   = count($users);
$active  = count(array_filter($users,fn($u)=>(int)$u['is_active']===1));
$admins  = count(array_filter($users,fn($u)=>$u['role']==='admin'));
$inactive= count(array_filter($users,fn($u)=>(int)$u['is_active']===0));

$page_title='Manajemen Pengguna — Viros PO System'; $topbar_title='Manajemen Pengguna'; $current_page='user_management';
$extra_css='<style>.user-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:var(--white);flex-shrink:0;}.avatar-admin{background:var(--black);}.avatar-manager{background:#333333;}.avatar-staff{background:#666666;}.avatar-direktur{background:#1A1C1C;}.avatar-komisaris{background:#888888;}</style>';
ob_start(); ?>

<div class="page-header">
    <div>
        <div class="page-title">Manajemen Pengguna</div>
        <div class="page-sub">Kelola akun dan hak akses pengguna sistem.</div>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addUserModal').classList.add('show')">
        <svg width="16" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        Tambah Pengguna Baru
    </button>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label" style="font-size:10px;letter-spacing:1px;">Total Pengguna</div><div class="stat-value"><?= $total ?></div><div class="stat-sub">Akun terdaftar</div></div>
    <div class="stat-card"><div class="stat-label" style="font-size:10px;letter-spacing:1px;">Aktif Sekarang</div><div class="stat-value"><?= $active ?></div><div class="stat-sub">Dapat login</div></div>
    <div class="stat-card"><div class="stat-label" style="font-size:10px;letter-spacing:1px;">Administrator</div><div class="stat-value"><?= $admins ?></div><div class="stat-sub">Hak akses penuh</div></div>
    <div class="stat-card"><div class="stat-label" style="font-size:10px;letter-spacing:1px;">Tidak Aktif</div><div class="stat-value"><?= $inactive ?></div><div class="stat-sub">Akses diblokir</div></div>
</div>

<div class="card" style="margin-top:40px;">
    <div class="card-header">
        <span class="card-title">Daftar Pengguna</span>
        <div style="position:relative;">
            <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="userSearch" class="form-control" placeholder="Cari pengguna..." style="font-size:13px;padding-left:38px;width:240px;" oninput="filterUsers(this.value)">
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Pengguna</th><th>Email</th><th>Role</th><th>Status</th><th>Terdaftar</th><th style="text-align:right;">Aksi</th></tr></thead>
            <tbody id="userTableBody">
            <?php if (empty($users)): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">👤</div><div class="empty-title">Belum ada pengguna</div></div></td></tr>
            <?php else: foreach ($users as $user):
                $av_class='avatar-'.$user['role'];
                $role_badges=['admin'=>'badge-admin','manager'=>'badge-manager','direktur'=>'badge-direktur','komisaris'=>'badge-komisaris'];
                $role_labels=['admin'=>'Admin','manager'=>'Manager','direktur'=>'Direktur','komisaris'=>'Komisaris','staff'=>'Staff'];
            ?>
            <tr class="user-row">
                <td>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="user-avatar <?= e($av_class) ?>"><?= strtoupper(substr($user['username'],0,1)) ?></div>
                        <div>
                            <div style="font-weight:600;font-size:14px;"><?= e($user['username']) ?></div>
                            <?php if ((int)$user['id']===current_user_id()): ?><div style="font-size:10px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;color:var(--text-muted);">(Anda)</div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="color:var(--text-muted);font-size:13px;"><?= e($user['email']) ?></td>
                <td><span class="badge <?= e($role_badges[$user['role']]??'badge-staff') ?>"><?= e($role_labels[$user['role']]??$user['role']) ?></span></td>
                <td>
                    <span class="badge-active">
                        <span class="dot <?= (int)$user['is_active']?'':'dot-off' ?>"></span>
                        <span style="font-size:12px;"><?= (int)$user['is_active']?'Aktif':'Tidak Aktif' ?></span>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--text-muted);"><?= tgl_short($user['created_at']??'') ?></td>
                <td style="text-align:right;">
                    <?php if ((int)$user['id']!==current_user_id()): ?>
                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?= (int)$user['is_active']?'Nonaktifkan':'Aktifkan' ?></button>
                        </form>
                        <button class="btn btn-ghost btn-sm" style="color:var(--text-muted);" onclick="confirmDelete(<?= $user['id'] ?>,'<?= e($user['username']) ?>')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        </button>
                    </div>
                    <?php else: ?><span style="font-size:12px;color:var(--text-light);">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($users)): ?>
    <div style="padding:16px 24px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);background:var(--bg);">
        <span style="font-size:14px;color:var(--text-muted);">Total <strong><?= count($users) ?></strong> pengguna terdaftar</span>
        <div class="pagination"><button class="page-btn">‹</button><button class="page-btn active">1</button><button class="page-btn">›</button></div>
    </div>
    <?php endif; ?>
</div>

<?php
$content=ob_get_clean();
$modals='
<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Tambah Pengguna Baru</div>
            <button class="modal-close" onclick="document.getElementById(\'addUserModal\').classList.remove(\'show\')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <form method="POST" action="user_management.php">
            <input type="hidden" name="action" value="create">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;padding-top:8px;">
                <div class="form-group" style="margin:0;"><label class="form-label">Nama Pengguna *</label><input type="text" name="username" class="form-control" placeholder="Nama lengkap" required></div>
                <div class="form-group" style="margin:0;"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" placeholder="email@viros.co.id" required></div>
                <div class="form-group" style="margin:0;"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" placeholder="Minimal 8 karakter" required></div>
                <div class="form-group" style="margin:0;"><label class="form-label">Role *</label><select name="role" class="form-control" required><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Administrator</option><option value="direktur">Direktur</option><option value="komisaris">Komisaris</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="document.getElementById(\'addUserModal\').classList.remove(\'show\')">Batal</button><button type="submit" class="btn btn-primary">Tambah Pengguna</button></div>
        </form>
        <div class="modal-accent"></div>
    </div>
</div>
<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteUserModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Konfirmasi Hapus Pengguna</div>
            <button class="modal-close" onclick="document.getElementById(\'deleteUserModal\').classList.remove(\'show\')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="modal-body"><p>Anda yakin ingin menghapus pengguna ini?</p><p><strong id="deleteUserName"></strong></p></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById(\'deleteUserModal\').classList.remove(\'show\')">Batal</button>
            <form id="deleteUserForm" method="POST" action="user_management.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId" value="">
                <button type="submit" class="btn btn-danger">Hapus Pengguna</button>
            </form>
        </div>
        <div class="modal-accent"></div>
    </div>
</div>';
$scripts='<script>
function filterUsers(val){val=val.toLowerCase();document.querySelectorAll(".user-row").forEach(row=>{row.style.display=row.textContent.toLowerCase().includes(val)?"":"none";});}
function confirmDelete(userId,username){document.getElementById("deleteUserName").textContent=username;document.getElementById("deleteUserId").value=userId;document.getElementById("deleteUserModal").classList.add("show");}
</script>';
include 'includes/base.php';
