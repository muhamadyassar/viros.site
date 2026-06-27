<?php
/**
 * CEK_DIAGNOSA.PHP — Viros PO System
 * -----------------------------------
 * Upload file ini ke folder utama aplikasi (sejajar dengan login.php),
 * lalu akses lewat browser, contoh:
 *   https://domainkamu.com/cek_diagnosa.php
 *
 * File ini HANYA membaca/menguji, tidak mengubah data penting.
 * SETELAH SELESAI DIAGNOSA, HAPUS FILE INI DARI SERVER (alasan keamanan).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/html; charset=UTF-8');

// Mulai session SEBELUM ada output apapun (harus paling awal)
$session_start_ok = true;
$session_start_error = '';
try {
    if (session_status() === PHP_SESSION_NONE) {
        $session_start_ok = @session_start();
    }
} catch (Throwable $e) {
    $session_start_ok = false;
    $session_start_error = $e->getMessage();
}
$session_id_awal = session_id();
$session_save_path_awal = session_save_path();

function baris($label, $ok, $detail = '') {
    $warna = $ok ? '#166534' : '#991B1B';
    $bg    = $ok ? '#F0FDF4' : '#FEF2F2';
    $tanda = $ok ? '✅ OK' : '❌ BERMASALAH';
    echo "<div style='padding:10px 14px;margin-bottom:6px;border:1px solid {$warna};background:{$bg};color:{$warna};font-family:monospace;font-size:13px;'>";
    echo "<b>{$tanda}</b> — {$label}";
    if ($detail) echo "<br><span style='color:#444;font-size:12px;'>{$detail}</span>";
    echo "</div>";
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnosa Login Viros</title>";
echo "<style>body{font-family:sans-serif;max-width:800px;margin:30px auto;padding:0 16px;background:#fafafa;}h2{margin-top:32px;}</style>";
echo "</head><body>";
echo "<h1>🔍 Diagnosa Sistem Login — Viros PO</h1>";

// 1. Versi PHP
echo "<h2>1. Versi PHP</h2>";
$php_ok = version_compare(PHP_VERSION, '7.4.0', '>=');
baris("PHP version: " . PHP_VERSION, $php_ok, $php_ok ? '' : 'Disarankan PHP 7.4 atau lebih baru.');

// 2. Extension penting
echo "<h2>2. Extension PHP</h2>";
baris("Extension 'json' aktif", extension_loaded('json'));
baris("Extension 'session' aktif", extension_loaded('session'));
baris("Function password_hash() tersedia", function_exists('password_hash'));
baris("Function password_verify() tersedia", function_exists('password_verify'));

// 3. Folder & permission
echo "<h2>3. Folder data/ (database JSON)</h2>";
$data_dir = __DIR__ . '/data/';
$dir_exists = is_dir($data_dir);
baris("Folder 'data/' ada", $dir_exists, $dir_exists ? realpath($data_dir) : "Path yang dicek: {$data_dir}");

if ($dir_exists) {
    $writable = is_writable($data_dir);
    baris("Folder 'data/' writable oleh PHP", $writable,
        $writable ? '' : "Permission saat ini: " . substr(sprintf('%o', fileperms($data_dir)), -4) . ". PHP berjalan sebagai user: " . (function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'tidak diketahui') : 'tidak diketahui') . ". Coba ubah permission folder data/ ke 755 atau 775 lewat File Manager hosting."
    );

    // Coba tulis file test
    $test_file = $data_dir . '_write_test_' . time() . '.tmp';
    $write_ok = @file_put_contents($test_file, 'test') !== false;
    baris("Tes tulis file langsung ke folder data/", $write_ok,
        $write_ok ? 'Berhasil menulis file test.' : 'PHP TIDAK BISA menulis ke folder ini. Ini kemungkinan besar sebab utama login gagal — users.json tidak pernah terbuat.'
    );
    if ($write_ok) @unlink($test_file);

    // Cek users.json
    $users_file = $data_dir . 'users.json';
    $users_exists = file_exists($users_file);
    baris("File 'data/users.json' sudah ada", $users_exists,
        $users_exists ? '' : 'File belum terbuat. Ini akan otomatis terbuat saat folder data/ writable dan login.php diakses sekali.'
    );

    if ($users_exists) {
        $content = @file_get_contents($users_file);
        $decoded = json_decode($content, true);
        $valid_json = is_array($decoded);
        baris("File users.json bisa dibaca & valid JSON", $valid_json,
            $valid_json ? ('Jumlah akun terdaftar: ' . count($decoded)) : 'Isi file corrupt atau bukan JSON valid.'
        );

        if ($valid_json && count($decoded) > 0) {
            echo "<h2>4. Daftar Akun Terdaftar</h2>";
            echo "<table style='border-collapse:collapse;width:100%;font-size:13px;font-family:monospace;'>";
            echo "<tr style='background:#eee;'><th style='padding:6px;border:1px solid #ccc;text-align:left;'>Email</th><th style='padding:6px;border:1px solid #ccc;'>Role</th><th style='padding:6px;border:1px solid #ccc;'>Aktif?</th></tr>";
            foreach ($decoded as $u) {
                $aktif = ((int)($u['is_active'] ?? 0) === 1) ? '✅' : '❌ TIDAK AKTIF';
                echo "<tr><td style='padding:6px;border:1px solid #ccc;'>" . htmlspecialchars($u['email'] ?? '-') . "</td><td style='padding:6px;border:1px solid #ccc;'>" . htmlspecialchars($u['role'] ?? '-') . "</td><td style='padding:6px;border:1px solid #ccc;'>{$aktif}</td></tr>";
            }
            echo "</table>";
        }
    }
}

// 5. Session
echo "<h2>5. Session PHP</h2>";
baris("session_start() berjalan tanpa error", $session_start_ok,
    $session_start_ok ? "Session ID: {$session_id_awal}" : "Error: {$session_start_error}. Ini bisa jadi sebab login gagal — cek konfigurasi session.save_path di php.ini hosting, pastikan folder tersebut writable."
);
baris("Session save path bisa diakses", true, "Path: " . ($session_save_path_awal ?: '(default sistem)'));

if ($session_start_ok) {
    $_SESSION['tes_diagnosa'] = 'berhasil_' . time();
    $tersimpan = isset($_SESSION['tes_diagnosa']);
    baris("Data bisa disimpan ke \$_SESSION", $tersimpan,
        $tersimpan ? 'Variabel session berhasil di-set di request ini.' : 'Gagal menyimpan data ke session.'
    );
}

echo "<h2>6. Cek Form Login (Test Manual)</h2>";
echo "<p>Gunakan kredensial default berikut untuk tes login langsung di <code>login.php</code>:</p>";
echo "<table style='border-collapse:collapse;font-size:13px;font-family:monospace;'>";
echo "<tr style='background:#eee;'><th style='padding:6px;border:1px solid #ccc;'>Email</th><th style='padding:6px;border:1px solid #ccc;'>Password</th><th style='padding:6px;border:1px solid #ccc;'>Role</th></tr>";
echo "<tr><td style='padding:6px;border:1px solid #ccc;'>admin@viros.co.id</td><td style='padding:6px;border:1px solid #ccc;'>admin123</td><td style='padding:6px;border:1px solid #ccc;'>admin</td></tr>";
echo "<tr><td style='padding:6px;border:1px solid #ccc;'>manager@viros.co.id</td><td style='padding:6px;border:1px solid #ccc;'>manager123</td><td style='padding:6px;border:1px solid #ccc;'>manager</td></tr>";
echo "</table>";

echo "<h2 style='color:#991B1B;'>⚠️ Penting</h2>";
echo "<p style='color:#991B1B;font-weight:bold;'>Setelah selesai, HAPUS file cek_diagnosa.php ini dari server. Jangan dibiarkan online karena bisa membuka info teknis ke publik.</p>";

echo "</body></html>";