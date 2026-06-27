<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

login_required();

$po_id     = (int)($_POST['po_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');
$note       = trim($_POST['note']   ?? '');
$role       = current_role();

$allowed = [
    'staff'   => ['pending', 'revision'],
    'manager' => ['pending', 'approved', 'rejected', 'completed'],
    'admin'   => ['draft', 'pending', 'approved', 'rejected', 'completed', 'revision'],
];

if (!in_array($new_status, $allowed[$role] ?? [])) {
    flash('danger', 'Anda tidak diizinkan mengubah ke status ini.');
    header('Location: po_detail.php?id=' . $po_id);
    exit;
}

$po = db_find('purchase_orders', $po_id);
if (!$po) {
    flash('danger', 'PO tidak ditemukan.');
    header('Location: po_list.php');
    exit;
}

$old_status = $po['status'];
db_update('purchase_orders', $po_id, [
    'status'     => $new_status,
    'updated_at' => date('Y-m-d H:i:s'),
]);
save_history($po_id, $old_status, $new_status, $note ?: 'Status diperbarui');

flash('success', 'Status PO berhasil diperbarui.');
header('Location: po_detail.php?id=' . $po_id);
exit;
