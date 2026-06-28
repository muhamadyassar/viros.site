<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();
role_required(['admin', 'manager', 'staff', 'direktur']); // komisaris tidak boleh

$po_id   = (int)($_POST['po_id']   ?? 0);
$email_to= trim($_POST['email_to'] ?? '');
$subject = trim($_POST['subject']  ?? '');
$body    = trim($_POST['body']     ?? '');

if (!$email_to) {
    flash('danger', 'Email tujuan tidak boleh kosong.');
    header('Location: po_print.php?id=' . $po_id);
    exit;
}

$po = get_po_with_user($po_id);
if (!$po) {
    flash('danger', 'PO tidak ditemukan.');
    header('Location: po_list.php');
    exit;
}

// Konfigurasi SMTP — sesuaikan dengan server Anda
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_user = 'tokonnso1@gmail.com';      // ganti dengan email Anda
$smtp_pass = 'trrdfecr qzbqjrji';        // ganti dengan app password Anda

try {
    // Gunakan mail() PHP bawaan jika tidak ada PHPMailer
    // Untuk produksi, install PHPMailer via Composer: composer require phpmailer/phpmailer

    $headers  = "From: {$smtp_user}\r\n";
    $headers .= "Reply-To: {$smtp_user}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $full_body = $body . "\n\n---\nDikirim dari Sistem PO Keluar PT. Viros Prime Solution";

    $mail_result = mail($email_to, $subject, $full_body, $headers);
    error_log('[po_send_email] mail() result: ' . var_export($mail_result, true) . ' | to=' . $email_to);
    $last_err = error_get_last();
    if ($last_err) {
        error_log('[po_send_email] last PHP error: ' . print_r($last_err, true));
    }

    if ($mail_result) {
        flash('success', "Email berhasil dikirim ke {$email_to}.");
    } else {
        flash('warning', 'Email mungkin tidak terkirim. Pastikan konfigurasi mail server sudah benar. Coba gunakan PHPMailer untuk SMTP Gmail.');
    }
} catch (Exception $e) {
    flash('danger', 'Gagal mengirim email: ' . $e->getMessage());
}

header('Location: po_print.php?id=' . $po_id);
exit;
