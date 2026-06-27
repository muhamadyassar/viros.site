<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
role_required(['admin','staff']);

$id   = (int)($_GET['id']??0);
$po   = db_find('purchase_orders',$id);
if (!$po) { flash('danger','PO tidak ditemukan.'); header('Location: po_list.php'); exit; }
$items = get_po_items($id);
$role  = current_role();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $vendor_name=$_POST['vendor_name']??''; $customer_company=$_POST['customer_company']??'';
    $order_date=$_POST['order_date']??''; $notes=$_POST['notes']??'';
    $terms_conditions=$_POST['terms_conditions']??''; $discount_pct=(float)($_POST['discount']??0);
    $new_status=$_POST['status']??$po['status'];
    $allowed=['staff'=>['pending','revision'],'admin'=>['draft','pending','revision','approved','rejected','completed']];
    if (!in_array($new_status,$allowed[$role]??[])) $new_status=$po['status'];
    $item_names=$_POST['item_name']??[]; $qtys=$_POST['qty']??[]; $units=$_POST['unit']??[]; $unit_prices=$_POST['unit_price']??[];
    $valid=array_filter($item_names,fn($n)=>trim($n)!=='');
    if (empty($valid)) { flash('danger','Minimal satu item harus diisi.'); }
    else {
        $sub=0; foreach($item_names as $i=>$n) if(trim($n)!=='') $sub+=(float)($qtys[$i]??0)*(float)($unit_prices[$i]??0);
        $calc=hitung_total($sub,$discount_pct); $old=$po['status'];
        db_update('purchase_orders',$id,['vendor_name'=>$vendor_name,'customer_company'=>$customer_company,'order_date'=>$order_date,'notes'=>$notes,'discount'=>$discount_pct,'tax'=>$calc['pajak_amt'],'terms_conditions'=>$terms_conditions,'status'=>$new_status,'total_amount'=>$calc['subtotal_akhir'],'updated_at'=>date('Y-m-d H:i:s')]);
        db_delete_where('po_items','po_id',$id);
        foreach($item_names as $i=>$n) if(trim($n)!=='') { $s=(float)($qtys[$i]??0)*(float)($unit_prices[$i]??0); db_insert('po_items',['po_id'=>$id,'item_name'=>trim($n),'qty'=>(float)($qtys[$i]??0),'unit'=>trim($units[$i]??''),'unit_price'=>(float)($unit_prices[$i]??0),'subtotal'=>$s]); }
        if ($old!==$new_status) save_history($id,$old,$new_status,'Status diubah saat edit PO');
        save_history($id,$new_status,$new_status,'Data PO diperbarui');
        flash('success','Purchase Order berhasil diperbarui.'); header('Location: po_detail.php?id='.$id); exit;
    }
    $po=array_merge($po,$_POST);
    $items=[]; foreach($_POST['item_name']??[] as $i=>$n) if(trim($n)!=='') $items[]=['item_name'=>$n,'qty'=>$_POST['qty'][$i]??1,'unit'=>$_POST['unit'][$i]??'','unit_price'=>$_POST['unit_price'][$i]??0,'subtotal'=>((float)($_POST['qty'][$i]??0))*((float)($_POST['unit_price'][$i]??0))];
}

$sub  = items_subtotal($items);
$calc = hitung_total($sub,(float)($po['discount']??0));
$role_opts=['staff'=>['pending'=>'Pending','revision'=>'Revision'],'admin'=>['draft'=>'Draft','pending'=>'Pending','revision'=>'Revision','approved'=>'Disetujui','rejected'=>'Ditolak','completed'=>'Selesai']];

$page_title='Edit PO — Viros PO System'; $topbar_title='Edit Purchase Order'; $current_page='po_edit';
$extra_css='<style>.summary-footer{background:#F3F3F3;border-top:1px solid var(--border);padding:32px;display:flex;justify-content:flex-end;}.summary-inner{width:288px;display:flex;flex-direction:column;gap:12px;}.summary-row{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text-muted);}.summary-total{border-top:1px solid #CFC4C5;padding-top:12px;margin-top:4px;}.summary-total-label{font-weight:700;font-size:12px;letter-spacing:0.6px;text-transform:uppercase;color:var(--black);}.summary-total-val{font-weight:600;font-size:24px;letter-spacing:-0.24px;color:var(--black);}.item-table td input,.item-table td select{border:none;border-bottom:1px solid var(--border);padding:8px 0;font-size:14px;font-family:var(--font);background:transparent;width:100%;outline:none;}.item-table td input:focus,.item-table td select:focus{border-bottom-color:var(--black);}.item-table td input[readonly]{color:var(--black);font-weight:700;}.del-btn{background:none;border:none;cursor:pointer;color:var(--text-muted);padding:8px;display:flex;align-items:center;justify-content:center;}.del-btn:hover{color:#BA1A1A;}</style>';
ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="po_list.php">Daftar PO</a><span class="breadcrumb-sep">›</span><a href="po_detail.php?id=<?= $id ?>"><?= e($po['po_number']) ?></a><span class="breadcrumb-sep">›</span><span style="font-weight:700;color:var(--black);">Edit</span></div>
        <div class="page-title">Edit PO — <?= e($po['po_number']) ?></div>
    </div>
    <div style="display:flex;gap:16px;">
        <a href="po_detail.php?id=<?= $id ?>" class="btn btn-outline">Batal</a>
        <button type="submit" form="poForm" class="btn btn-primary">Simpan Perubahan</button>
    </div>
</div>

<form id="poForm" method="POST">
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><span class="card-title">Informasi Dasar</span></div>
        <div class="card-body">
            <div class="form-grid-3">
                <div class="form-group"><label class="form-label">Nama Customer *</label><input type="text" name="vendor_name" class="form-control" value="<?= e($po['vendor_name']) ?>" required></div>
                <div class="form-group"><label class="form-label">Perusahaan Customer *</label><input type="text" name="customer_company" class="form-control" value="<?= e($po['customer_company']??'') ?>" required></div>
                <div class="form-group"><label class="form-label">Tanggal Order *</label><input type="date" name="order_date" class="form-control" value="<?= e($po['order_date']??'') ?>" required></div>
            </div>
            <div class="form-group">
                <label class="form-label">Status PO</label>
                <select name="status" class="form-control">
                    <?php foreach ($role_opts[$role]??[] as $v=>$l): ?><option value="<?= $v ?>" <?= ($po['status']??'')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" style="min-height:74px;"><?= e($po['notes']??'') ?></textarea></div>
            <div class="form-group" style="margin-bottom:0;"><label class="form-label">Syarat &amp; Ketentuan</label><textarea name="terms_conditions" class="form-control" style="min-height:100px;"><?= e($po['terms_conditions']??'') ?></textarea></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="background:#F3F3F3;">
            <span class="card-title" style="letter-spacing:0.9px;text-transform:uppercase;font-size:18px;">Item Pesanan</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addRow()"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Tambah Item</button>
        </div>
        <div class="table-wrap">
            <table id="itemTable" class="item-table">
                <thead><tr><th style="width:35%;">Nama Item / SKU</th><th style="width:10%;">Jumlah</th><th style="width:12%;">Satuan</th><th style="width:18%;">Harga Satuan (Rp)</th><th style="width:18%;">Subtotal (Rp)</th><th style="width:7%;text-align:center;"></th></tr></thead>
                <tbody id="itemBody">
                <?php foreach($items as $item): $us=['Pcs','Box','Kg','Liter','Meter','Set','Unit']; ?>
                <tr class="item-row">
                    <td><input type="text" name="item_name[]" value="<?= e($item['item_name']) ?>" required></td>
                    <td><input type="number" name="qty[]" class="qty" value="<?= e($item['qty']) ?>" min="0.01" step="0.01" required oninput="calcRow(this)"></td>
                    <td><select name="unit[]"><?php foreach($us as $u): ?><option <?= ($item['unit']??'')===$u?'selected':'' ?>><?= $u ?></option><?php endforeach; ?></select></td>
                    <td><input type="number" name="unit_price[]" class="price" value="<?= e($item['unit_price']) ?>" min="0" step="1" required oninput="calcRow(this)"></td>
                    <td><input type="text" class="subtotal" readonly value="<?= number_format((float)$item['subtotal'],0,',','.') ?>" style="font-weight:700;"></td>
                    <td style="text-align:center;"><button type="button" onclick="removeRow(this)" class="del-btn"><svg width="16" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg></button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="summary-footer">
            <div class="summary-inner">
                <div class="summary-row"><span>Total</span><span id="summTotal">Rp 0</span></div>
                <div class="summary-row" style="align-items:center;gap:8px;"><span style="white-space:nowrap;">Diskon (%)</span><div style="display:flex;align-items:center;gap:6px;"><input type="number" name="discount" id="discountInput" min="0" max="100" step="0.01" value="<?= e($po['discount']??0) ?>" oninput="calcTotal()" style="width:64px;border:none;border-bottom:1px solid var(--border);padding:2px 4px;font-size:12px;font-family:var(--font);background:transparent;outline:none;text-align:right;"><span>%</span></div><span id="summDiskon" style="margin-left:auto;">- Rp 0</span></div>
                <div class="summary-row"><span>Pajak 11%</span><span id="summPajak">Rp 0</span></div>
                <div class="summary-row summary-total"><span class="summary-total-label">Subtotal</span><span class="summary-total-val" id="summSubtotal">Rp 0</span></div>
            </div>
        </div>
    </div>
</form>
<?php
$content=ob_get_clean();
$scripts='<script>
function formatRp(n){return "Rp "+Number(n).toLocaleString("id-ID");}
function calcRow(el){const row=el.closest("tr");const qty=parseFloat(row.querySelector(".qty").value)||0;const price=parseFloat(row.querySelector(".price").value)||0;row.querySelector(".subtotal").value=(qty*price).toLocaleString("id-ID");calcTotal();}
function calcTotal(){let total=0;document.querySelectorAll(".item-row").forEach(row=>{const qty=parseFloat(row.querySelector(".qty").value)||0;const price=parseFloat(row.querySelector(".price").value)||0;total+=qty*price;});const discPct=parseFloat(document.getElementById("discountInput").value)||0;const diskonAmt=total*discPct/100;const afterDisc=total-diskonAmt;const pajak=afterDisc*11/100;const subtotal=afterDisc+pajak;document.getElementById("summTotal").textContent=formatRp(Math.round(total));document.getElementById("summDiskon").textContent="- "+formatRp(Math.round(diskonAmt));document.getElementById("summPajak").textContent=formatRp(Math.round(pajak));document.getElementById("summSubtotal").textContent=formatRp(Math.round(subtotal));}
function addRow(){const tbody=document.getElementById("itemBody");const tpl=document.querySelector(".item-row").cloneNode(true);tpl.querySelectorAll("input[type=text],input[type=number]").forEach(i=>i.value="");tbody.appendChild(tpl);}
function removeRow(btn){if(document.querySelectorAll(".item-row").length===1)return;btn.closest("tr").remove();calcTotal();}
document.addEventListener("DOMContentLoaded",calcTotal);
</script>';
include 'includes/base.php';
