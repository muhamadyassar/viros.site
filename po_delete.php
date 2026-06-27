<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
role_required(['admin','manager']);
$po_id=(int)($_POST['po_id']??0);
if (!$po_id || !db_find('purchase_orders',$po_id)) { flash('danger','PO tidak ditemukan.'); header('Location: po_list.php'); exit; }
db_delete_where('po_history','po_id',$po_id);
db_delete_where('po_items','po_id',$po_id);
db_delete('purchase_orders',$po_id);
flash('success','PO berhasil dihapus.');
header('Location: po_list.php'); exit;
