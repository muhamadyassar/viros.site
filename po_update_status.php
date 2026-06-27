<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
$po_id=$_POST['po_id']??0; $new=$_POST['status']??''; $note=$_POST['note']??'';
$allowed=['staff'=>['pending','revision'],'manager'=>['pending','approved','rejected','completed'],'admin'=>['draft','pending','revision','approved','rejected','completed']];
$role=current_role();
if (!in_array($new,$allowed[$role]??[])) { flash('danger','Anda tidak diizinkan mengubah ke status ini.'); header('Location: po_detail.php?id='.$po_id); exit; }
$po=db_find('purchase_orders',(int)$po_id);
if (!$po) { flash('danger','PO tidak ditemukan.'); header('Location: po_list.php'); exit; }
db_update('purchase_orders',(int)$po_id,['status'=>$new,'updated_at'=>date('Y-m-d H:i:s')]);
save_history((int)$po_id,$po['status'],$new,$note?:'Status diperbarui');
flash('success','Status PO berhasil diperbarui.');
header('Location: po_detail.php?id='.$po_id); exit;
