<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
role_required(['admin','staff']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $vendor_name      = trim($_POST['vendor_name']??'');
    $customer_company = trim($_POST['customer_company']??'');
    $order_date       = trim($_POST['order_date']??'');
    $notes            = trim($_POST['notes']??'');
    $terms_conditions = trim($_POST['terms_conditions']??'');
    $discount_pct     = (float)($_POST['discount']??0);
    $prepared_by      = trim($_POST['prepared_by']??'');
    $approved_by      = trim($_POST['approved_by']??'');
    $item_names       = $_POST['item_name']??[];
    $qtys             = $_POST['qty']??[];
    $units            = $_POST['unit']??[];
    $unit_prices      = $_POST['unit_price']??[];

    $valid = array_filter($item_names, fn($n)=>trim($n)!=='');
    if (empty($valid)) {
        flash('danger','Minimal satu item harus diisi.');
    } else {
        $sub=0;
        foreach ($item_names as $i=>$n) if (trim($n)!=='') $sub+=(float)($qtys[$i]??0)*(float)($unit_prices[$i]??0);
        $calc  = hitung_total($sub,$discount_pct);
        $po_id = db_insert('purchase_orders',[
            'po_number'=>generate_po_number(),'vendor_name'=>$vendor_name,'customer_company'=>$customer_company,
            'order_date'=>$order_date,'notes'=>$notes,'discount'=>$discount_pct,'tax'=>$calc['pajak_amt'],
            'terms_conditions'=>$terms_conditions,'total_amount'=>$calc['subtotal_akhir'],'status'=>'pending',
            'created_by'=>current_user_id(),'prepared_by'=>$prepared_by,'approved_by'=>$approved_by,
            'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        foreach ($item_names as $i=>$n) if (trim($n)!=='') {
            $s=(float)($qtys[$i]??0)*(float)($unit_prices[$i]??0);
            db_insert('po_items',['po_id'=>$po_id,'item_name'=>trim($n),'qty'=>(float)($qtys[$i]??0),'unit'=>trim($units[$i]??''),'unit_price'=>(float)($unit_prices[$i]??0),'subtotal'=>$s]);
        }
        save_history($po_id,'-','pending','PO dibuat');
        flash('success','PO berhasil dibuat!');
        header('Location: po_list.php'); exit;
    }
}
$today = date('Y-m-d');
$page_title='Buat PO — Viros PO System'; $topbar_title='Buat Purchase Order Baru'; $current_page='po_create';
$extra_css='<style>
.summary-footer{background:#F3F3F3;border-top:1px solid var(--border);padding:32px;display:flex;justify-content:flex-end;}
.summary-inner{width:288px;display:flex;flex-direction:column;gap:12px;}
.summary-row{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text-muted);}
.summary-total{border-top:1px solid #CFC4C5;padding-top:12px;margin-top:4px;}
.summary-total-label{font-weight:700;font-size:12px;letter-spacing:0.6px;text-transform:uppercase;color:var(--black);}
.summary-total-val{font-weight:600;font-size:24px;letter-spacing:-0.24px;color:var(--black);}
.item-table td input,.item-table td select{border:none;border-bottom:1px solid var(--border);padding:8px 0;font-size:14px;font-family:var(--font);background:transparent;width:100%;outline:none;}
.item-table td input:focus,.item-table td select:focus{border-bottom-color:var(--black);}
.item-table td input[readonly]{color:var(--black);font-weight:700;}
.del-btn{background:none;border:none;cursor:pointer;color:var(--text-muted);padding:8px;display:flex;align-items:center;justify-content:center;}
.del-btn:hover{color:#BA1A1A;}
</style>';
ob_start(); ?>

<div class="page-header">
    <div>
        <div class="breadcrumb"><a href="po_list.php">Daftar PO</a><span class="breadcrumb-sep">›</span><span style="font-weight:700;color:var(--black);">Buat Baru</span></div>
        <div class="page-title">Buat PO Baru</div>
    </div>
    <div style="display:flex;gap:16px;">
        <a href="po_list.php" class="btn btn-outline">Batal</a>
        <button type="submit" form="poForm" class="btn btn-primary">Simpan PO</button>
    </div>
</div>

<form id="poForm" method="POST">
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><span class="card-title">Informasi Dasar</span></div>
        <div class="card-body">
            <div class="form-grid-3">
                <div class="form-group"><label class="form-label">Nama Customer *</label><input type="text" name="vendor_name" class="form-control" placeholder="Masukkan nama customer..." required value="<?= e($_POST['vendor_name']??'') ?>"></div>
                <div class="form-group"><label class="form-label">Perusahaan Customer *</label><input type="text" name="customer_company" class="form-control" placeholder="Masukkan nama perusahaan..." required value="<?= e($_POST['customer_company']??'') ?>"></div>
                <div class="form-group"><label class="form-label">Tanggal Order *</label><input type="date" name="order_date" class="form-control" required value="<?= e($_POST['order_date']??$today) ?>"></div>
            </div>
            <div class="form-grid-2" style="margin-top:0;">
                <div class="form-group"><label class="form-label">Disiapkan Oleh *</label><input type="text" name="prepared_by" class="form-control" placeholder="Nama yang menyiapkan PO..." required value="<?= e($_POST['prepared_by']??'') ?>"></div>
                <div class="form-group"><label class="form-label">Disetujui Oleh *</label><input type="text" name="approved_by" class="form-control" placeholder="Nama yang menyetujui PO..." value="<?= e($_POST['approved_by']??'') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" placeholder="Instruksi tambahan atau catatan..." style="min-height:74px;"><?= e($_POST['notes']??'') ?></textarea></div>
            <div class="form-group" style="margin-bottom:0;"><label class="form-label">Syarat &amp; Ketentuan</label><textarea name="terms_conditions" class="form-control" placeholder="Masukkan syarat dan ketentuan PO ini (opsional)..." style="min-height:100px;"><?= e($_POST['terms_conditions']??'') ?></textarea></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="background:#F3F3F3;">
            <span class="card-title" style="letter-spacing:0.9px;text-transform:uppercase;font-size:18px;">Item Pesanan</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addRow()">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tambah Item
            </button>
        </div>
        <div class="table-wrap">
            <table id="itemTable" class="item-table">
                <thead><tr>
                    <th style="width:35%;">Nama Item / SKU</th><th style="width:10%;">Jumlah</th>
                    <th style="width:12%;">Satuan</th><th style="width:18%;">Harga Satuan (Rp)</th>
                    <th style="width:18%;">Subtotal (Rp)</th><th style="width:7%;text-align:center;"></th>
                </tr></thead>
                <tbody id="itemBody">
                    <tr class="item-row">
                        <td><input type="text" name="item_name[]" placeholder="Nama barang / SKU..." required></td>
                        <td><input type="number" name="qty[]" class="qty" placeholder="0" min="0.01" step="0.01" required oninput="calcRow(this)"></td>
                        <td><select name="unit[]"><option>Pcs</option><option>Box</option><option>Kg</option><option>Liter</option><option>Meter</option><option>Set</option><option>Unit</option></select></td>
                        <td><input type="number" name="unit_price[]" class="price" placeholder="0" min="0" step="1" required oninput="calcRow(this)"></td>
                        <td><input type="text" class="subtotal" readonly placeholder="0" style="font-weight:700;"></td>
                        <td style="text-align:center;"><button type="button" onclick="removeRow(this)" class="del-btn"><svg width="16" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="summary-footer">
            <div class="summary-inner">
                <div class="summary-row"><span>Total</span><span id="summTotal">Rp 0</span></div>
                <div class="summary-row" style="align-items:center;gap:8px;">
                    <span style="white-space:nowrap;">Diskon (%)</span>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="number" name="discount" id="discountInput" min="0" max="100" step="0.01" value="0" oninput="calcTotal()" style="width:64px;border:none;border-bottom:1px solid var(--border);padding:2px 4px;font-size:12px;font-family:var(--font);background:transparent;outline:none;text-align:right;">
                        <span>%</span>
                    </div>
                    <span id="summDiskon" style="margin-left:auto;">- Rp 0</span>
                </div>
                <div class="summary-row"><span>Pajak 11%</span><span id="summPajak">Rp 0</span></div>
                <div class="summary-row summary-total">
                    <span class="summary-total-label">Subtotal</span>
                    <span class="summary-total-val" id="summSubtotal">Rp 0</span>
                </div>
            </div>
        </div>
    </div>
</form>
<?php
$content = ob_get_clean();
$scripts = '<script>
function formatRp(n){return "Rp "+Number(n).toLocaleString("id-ID");}
function calcRow(el){const row=el.closest("tr");const qty=parseFloat(row.querySelector(".qty").value)||0;const price=parseFloat(row.querySelector(".price").value)||0;row.querySelector(".subtotal").value=(qty*price).toLocaleString("id-ID");calcTotal();}
function calcTotal(){let total=0;document.querySelectorAll(".item-row").forEach(row=>{const qty=parseFloat(row.querySelector(".qty").value)||0;const price=parseFloat(row.querySelector(".price").value)||0;total+=qty*price;});const discPct=parseFloat(document.getElementById("discountInput").value)||0;const diskonAmt=total*discPct/100;const afterDisc=total-diskonAmt;const pajak=afterDisc*11/100;const subtotal=afterDisc+pajak;document.getElementById("summTotal").textContent=formatRp(Math.round(total));document.getElementById("summDiskon").textContent="- "+formatRp(Math.round(diskonAmt));document.getElementById("summPajak").textContent=formatRp(Math.round(pajak));document.getElementById("summSubtotal").textContent=formatRp(Math.round(subtotal));}
function addRow(){const tbody=document.getElementById("itemBody");const tpl=document.querySelector(".item-row").cloneNode(true);tpl.querySelectorAll("input[type=text],input[type=number]").forEach(i=>i.value="");tbody.appendChild(tpl);}
function removeRow(btn){if(document.querySelectorAll(".item-row").length===1)return;btn.closest("tr").remove();calcTotal();}
document.addEventListener("DOMContentLoaded",()=>{const d=document.querySelector("input[name=order_date]");if(d&&!d.value)d.value=new Date().toISOString().split("T")[0];});
</script>';
include 'includes/base.php';
