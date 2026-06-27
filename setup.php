<?php
/**
 * setup.php — Halaman diagnostik & reset data
 * Akses: http://localhost/viros_php/setup.php
 * HAPUS file ini setelah sistem berjalan normal!
 */

define('DATA_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR);

$action = $_GET['action'] ?? '';

// ─── RESET USERS ─────────────────────────────────────────────────────────────
if ($action === 'reset_users') {
    // Hapus users.json dan sequence, biarkan db_init() seed ulang
    @unlink(DATA_DIR . 'users.json');
    @unlink(DATA_DIR . 'sequence.json');

    // Hapus semua file JSON agar benar-benar fresh
    foreach (['purchase_orders.json','po_items.json','po_history.json'] as $f) {
        @unlink(DATA_DIR . $f);
    }

    // Panggil db_init dari db.php
    require_once __DIR__ . '/includes/db.php';
    db_init();

    $users = db_read('users');
    echo '<h2 style="color:green">✓ Reset berhasil! ' . count($users) . ' user dibuat ulang.</h2>';
    echo '<pre>';
    foreach ($users as $u) {
        echo 'ID:'.$u['id'].' | '.$u['email'].' | role:'.$u['role'].' | hash_algo:'.password_get_info($u['password'])['algoName']."\n";
        // Verifikasi password
        $passwords = ['admin123','manager123','staff123','direktur123','komisaris123'];
        $role_pw   = ['admin'=>'admin123','manager'=>'manager123','staff'=>'staff123','direktur'=>'direktur123','komisaris'=>'komisaris123'];
        $pw        = $role_pw[$u['role']] ?? '';
        $ok        = password_verify($pw, $u['password']) ? 'OK ✓' : 'GAGAL ✗';
        echo "  password_verify('{$pw}'): {$ok}\n";
    }
    echo '</pre>';
    echo '<p><a href="setup.php">← Kembali ke diagnostik</a> | <a href="login.php">→ Ke Login</a></p>';
    exit;
}

// ─── DIAGNOSTIK ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/includes/db.php';

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
db_init();

$users = db_read('users');
$all_po = db_read('purchase_orders');

$data_writeable = is_writable(DATA_DIR);
$files_ok = [];
foreach (['users.json','purchase_orders.json','po_items.json','po_history.json','sequence.json'] as $f) {
    $files_ok[$f] = file_exists(DATA_DIR . $f);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Setup & Diagnostik — Viros PO</title>
<style>
body{font-family:monospace;background:#f9f9f9;padding:32px;max-width:800px;margin:0 auto;}
h1{font-size:20px;margin-bottom:24px;}
h2{font-size:15px;margin:24px 0 8px;border-bottom:2px solid #000;padding-bottom:4px;}
.ok{color:#16A34A;font-weight:700;}
.err{color:#DC2626;font-weight:700;}
.warn{color:#D97706;font-weight:700;}
table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;}
th,td{padding:8px 12px;text-align:left;border:1px solid #ddd;}
th{background:#f3f3f3;font-weight:700;}
.btn{display:inline-block;padding:10px 20px;background:#000;color:#fff;text-decoration:none;font-size:13px;margin-right:8px;}
.btn-danger{background:#DC2626;}
pre{background:#f0f0f0;padding:12px;font-size:12px;overflow-x:auto;}
</style>
</head>
<body>
<h1>🔧 Setup & Diagnostik — Viros PO System</h1>
<p style="background:#FEF3C7;padding:10px;border:1px solid #F59E0B;font-size:13px;">
    ⚠️ <strong>Hapus file ini setelah sistem berjalan normal!</strong> File ini bisa diakses siapa saja.
</p>

<h2>1. Lingkungan PHP</h2>
<table>
    <tr><th>Item</th><th>Status</th><th>Nilai</th></tr>
    <tr>
        <td>Versi PHP</td>
        <td class="<?= version_compare(PHP_VERSION,'7.4','>=') ? 'ok' : 'err' ?>">
            <?= version_compare(PHP_VERSION,'7.4','>=') ? '✓' : '✗' ?>
        </td>
        <td><?= PHP_VERSION ?></td>
    </tr>
    <tr>
        <td>Ekstensi json</td>
        <td class="<?= extension_loaded('json') ? 'ok' : 'err' ?>"><?= extension_loaded('json') ? '✓ Ada' : '✗ Tidak ada' ?></td>
        <td></td>
    </tr>
    <tr>
        <td>Ekstensi session</td>
        <td class="<?= extension_loaded('session') ? 'ok' : 'err' ?>"><?= extension_loaded('session') ? '✓ Ada' : '✗ Tidak ada' ?></td>
        <td></td>
    </tr>
    <tr>
        <td>password_hash()</td>
        <td class="ok">✓ Tersedia</td>
        <td>Algo: <?= password_get_info(password_hash('test', PASSWORD_BCRYPT))['algoName'] ?></td>
    </tr>
</table>

<h2>2. Folder & File Data</h2>
<table>
    <tr><th>Item</th><th>Status</th></tr>
    <tr>
        <td>Folder data/ (<?= DATA_DIR ?>)</td>
        <td class="<?= $data_writeable ? 'ok' : 'err' ?>"><?= $data_writeable ? '✓ Ada & writeable' : '✗ TIDAK writeable — jalankan: chmod 755 data/' ?></td>
    </tr>
    <?php foreach ($files_ok as $fname => $exists): ?>
    <tr>
        <td><?= $fname ?></td>
        <td class="<?= $exists ? 'ok' : 'warn' ?>"><?= $exists ? '✓ Ada' : '○ Belum ada (akan dibuat otomatis)' ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>3. Data User</h2>
<?php if (empty($users)): ?>
<p class="err">✗ Tidak ada user! Klik tombol Reset di bawah.</p>
<?php else: ?>
<table>
    <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Aktif</th><th>Hash Valid?</th></tr>
    <?php
    $role_pw = ['admin'=>'admin123','manager'=>'manager123','staff'=>'staff123','direktur'=>'direktur123','komisaris'=>'komisaris123'];
    foreach ($users as $u):
        $pw  = $role_pw[$u['role']] ?? '';
        $ok  = password_verify($pw, $u['password']);
        $algo= password_get_info($u['password'])['algoName'];
    ?>
    <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td><?= $u['is_active'] ? '✓' : '✗' ?></td>
        <td class="<?= $ok ? 'ok' : 'err' ?>">
            <?= $ok ? "✓ OK ({$algo})" : "✗ GAGAL — hash format salah (algo: {$algo})" ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php
$any_fail = false;
foreach ($users as $u) {
    $pw = $role_pw[$u['role']] ?? '';
    if (!password_verify($pw, $u['password'])) { $any_fail = true; break; }
}
if ($any_fail): ?>
<p class="err">⚠️ Ada hash yang tidak valid! Ini penyebab login gagal. Klik <strong>Reset Data</strong> di bawah.</p>
<?php else: ?>
<p class="ok">✓ Semua password hash valid — login seharusnya berfungsi.</p>
<?php endif; ?>
<?php endif; ?>

<h2>4. Akun Default (setelah reset)</h2>
<table>
    <tr><th>Email</th><th>Password</th><th>Role</th></tr>
    <tr><td>admin@viros.co.id</td><td>admin123</td><td>Admin</td></tr>
    <tr><td>manager@viros.co.id</td><td>manager123</td><td>Manager</td></tr>
    <tr><td>staff@viros.co.id</td><td>staff123</td><td>Staff</td></tr>
    <tr><td>direktur@viros.co.id</td><td>direktur123</td><td>Direktur</td></tr>
    <tr><td>komisaris@viros.co.id</td><td>komisaris123</td><td>Komisaris</td></tr>
</table>

<h2>5. Aksi</h2>
<a href="setup.php?action=reset_users" class="btn btn-danger"
   onclick="return confirm('Reset SEMUA data? Semua PO akan terhapus!')">
   🔄 Reset Semua Data (hapus PO + buat ulang user)
</a>
<a href="login.php" class="btn">→ Ke Halaman Login</a>

<h2>6. Informasi Debug</h2>
<pre>
DATA_DIR  : <?= DATA_DIR ?>

data/ exists    : <?= is_dir(DATA_DIR) ? 'Ya' : 'Tidak' ?>

data/ writeable : <?= is_writable(DATA_DIR) ? 'Ya' : 'Tidak' ?>

Total PO        : <?= count($all_po) ?>

Total Users     : <?= count($users) ?>

PHP SAPI        : <?= php_sapi_name() ?>

</pre>
</body>
</html>
