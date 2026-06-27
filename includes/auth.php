<?php
/**
 * auth.php — Manajemen sesi dan kontrol akses berbasis role
 */

function auth_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function login_required(): void {
    auth_start();
    if (!is_logged_in()) {
        flash('warning', 'Silakan login terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
}

function role_required(array $roles): void {
    login_required();
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        flash('danger', 'Akses ditolak. Anda tidak memiliki izin.');
        header('Location: dashboard.php');
        exit;
    }
}

function get_session_user(): array {
    return [
        'id'       => $_SESSION['user_id']  ?? 0,
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role']     ?? '',
        'email'    => $_SESSION['email']    ?? '',
    ];
}

function current_role(): string {
    return $_SESSION['role'] ?? '';
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

// ─── FLASH MESSAGE ───────────────────────────────────────────────────────────

function flash(string $type, string $message): void {
    auth_start();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    auth_start();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function render_flashes(): void {
    foreach (get_flashes() as $f) {
        $type = htmlspecialchars($f['type']);
        $msg  = htmlspecialchars($f['message']);
        echo "<div class=\"alert alert-{$type}\">";
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        echo "<span>{$msg}</span></div>";
    }
}
