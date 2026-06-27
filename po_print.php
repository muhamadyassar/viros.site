<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
login_required();
$id=( int)($_GET['id']??0);
$po=get_po_with_user($id);
if (!$po) { flash('danger','PO tidak ditemukan.'); header('Location: po_list.php'); exit; }
$items=get_po_items($id);
$sub=items_subtotal($items);
$calc=hitung_total($sub,(float)($po['discount']??0));
$now=date('d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak PO <?= e($po['po_number']) ?> — PT. Viros Prime Solution</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F5F5; color: #000; font-size: 12px; }
        html, body { height: 100%; }
        .screen-wrap { padding: 32px; }
        .action-header { max-width: 780px; margin: 0 auto 24px; display: flex; justify-content: space-between; align-items: center; }
        .action-header a, .action-header button { font-family: 'Inter', sans-serif; display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; font-size: 14px; border: 1px solid #000; background: #fff; color: #000; cursor: pointer; text-decoration: none; font-weight: 400; }
        .action-header button.print-btn { background: #000; color: #fff; }
        .po-document { max-width: 780px; margin: 0 auto; background: #fff; border: 1px solid #ddd; padding: 32px 40px 28px; }
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 16px; border-bottom: 1.5px solid #000; margin-bottom: 16px; }
        .header-left { display: flex; align-items: flex-start; gap: 12px; }
        .doc-logo { height: 48px; width: auto; }
        .company-info { display: flex; flex-direction: column; gap: 2px; }
        .company-name { font-weight: 700; font-size: 13px; letter-spacing: 0.5px; text-transform: uppercase; }
        .company-detail { font-size: 10px; color: #444; line-height: 1.5; }
        .header-right { text-align: right; }
        .po-title-big { font-weight: 800; font-size: 28px; letter-spacing: 3px; text-transform: uppercase; line-height: 1.1; color: #000; }
        .po-subtitle { font-size: 10px; color: #555; margin-top: 2px; text-align: right; }
        .po-meta { display: flex; justify-content: space-between; align-items: flex-start; padding: 14px 0 14px; border-bottom: 1.5px solid #000; margin-bottom: 0; }
        .meta-left { display: flex; flex-direction: column; gap: 3px; }
        .meta-section-title { font-size: 9px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: #777; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 5px; }
        .meta-company { font-weight: 700; font-size: 14px; color: #000; }
        .meta-detail { font-size: 11px; color: #444; }
        .meta-right { text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
        .po-number-big { font-weight: 700; font-size: 20px; color: #000; margin-bottom: 8px; }
        .meta-row { display: flex; gap: 32px; align-items: center; justify-content: flex-end; }
        .meta-key { font-size: 10px; color: #555; }
        .meta-val { font-weight: 700; font-size: 11px; color: #000; min-width: 140px; text-align: right; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .items-table thead tr { border-top: 2px solid #000; border-bottom: 2px solid #000; }
        .items-table th { padding: 9px 10px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #000; background: #fff; }
        .items-table th:nth-child(3), .items-table th:nth-child(4), .items-table th:nth-child(5) { text-align: right; }
        .items-table td { padding: 10px 10px; border-bottom: 1px solid #E5E7EB; vertical-align: top; }
        .items-table td:nth-child(3), .items-table td:nth-child(4), .items-table td:nth-child(5) { text-align: right; }
        .items-table tbody { border-bottom: 2px solid #000; }
        .item-name { font-weight: 700; font-size: 12px; color: #000; }
        .totals-section { display: flex; justify-content: flex-end; margin-top: 0; border-bottom: 1.5px solid #000; padding-bottom: 14px; }
        .totals-box { width: 280px; display: flex; flex-direction: column; gap: 6px; font-size: 11px; padding-top: 14px; }
        .total-row { display: flex; justify-content: space-between; }
        .total-row.grand { font-weight: 700; font-size: 14px; border-top: 1.5px solid #000; padding-top: 8px; margin-top: 4px; }
        .terbilang { padding: 12px 0; border-bottom: 1.5px solid #000; }
        .terbilang-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #777; margin-bottom: 3px; }
        .terbilang-text { font-size: 12px; font-style: italic; }
        .notes-section { padding: 12px 0; border-bottom: 1.5px solid #000; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .notes-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #777; margin-bottom: 3px; }
        .notes-text { font-size: 11px; line-height: 1.5; color: #333; }
        .sign-section { padding: 20px 0; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .sign-box { text-align: center; }
        .sign-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #777; margin-bottom: 8px; }
        .sign-name { font-weight: 700; font-size: 11px; margin-top: 36px; border-top: 1px solid #000; padding-top: 6px; }
        .doc-footer { border-top: 1.5px solid #000; padding-top: 10px; display: flex; justify-content: space-between; font-size: 9px; color: #777; }
        @media print {
            body { background: #fff; } .screen-wrap { padding: 0; }
            .action-header { display: none; } .po-document { border: none; padding: 16px; max-width: 100%; }
            @page { margin: 12mm; size: A4; }
        }
    </style>
</head>
<body>
<div class="screen-wrap">
    <div class="action-header">
        <a href="po_detail.php?id=<?= $id ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Kembali ke Detail PO
        </a>
        <div style="display:flex;gap:12px;">
            <button class="print-btn" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak / Save PDF
            </button>
        </div>
    </div>

    <div class="po-document">
        <div class="doc-header">
            <div class="header-left">
                <img src="static/img/logo_viros.png" class="doc-logo" alt="Logo" onerror="this.style.display='none'">
                <div class="company-info">
                    <div class="company-name">PT. Viros Prime Solution</div>
                    <div class="company-detail">Jl. Contoh Bisnis No. 1, Jakarta Selatan<br>Telp: (021) 123-4567 | Fax: (021) 123-4568<br>Email: procurement@viros.co.id</div>
                </div>
            </div>
            <div class="header-right">
                <div class="po-title-big">Purchase<br>Order</div>
                <div class="po-subtitle">Dokumen Resmi Pengadaan</div>
            </div>
        </div>

        <div class="po-meta">
            <div class="meta-left">
                <div class="meta-section-title">Kepada</div>
                <div class="meta-company"><?= e($po['vendor_name']) ?></div>
                <?php if (!empty($po['customer_company'])): ?><div class="meta-detail"><?= e($po['customer_company']) ?></div><?php endif; ?>
            </div>
            <div class="meta-right">
                <div class="po-number-big"><?= e($po['po_number']) ?></div>
                <div class="meta-row"><span class="meta-key">Tanggal PO</span><span class="meta-val"><?= tgl_id($po['order_date']??'') ?></span></div>
                <div class="meta-row"><span class="meta-key">Status</span><span class="meta-val" style="text-transform:uppercase;"><?= e($po['status']) ?></span></div>
                <div class="meta-row"><span class="meta-key">Disiapkan Oleh</span><span class="meta-val"><?= e($po['prepared_by']??$po['created_by_name']) ?></span></div>
            </div>
        </div>

        <table class="items-table">
            <thead><tr>
                <th style="width:5%;">#</th><th style="width:38%;">Nama Barang / Jasa</th>
                <th style="width:10%;text-align:right;">Qty</th><th style="width:10%;text-align:right;">Satuan</th>
                <th style="width:18%;text-align:right;">Harga Satuan</th><th style="width:19%;text-align:right;">Subtotal</th>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $i=>$item): ?>
            <tr>
                <td style="color:#666;"><?= $i+1 ?></td>
                <td><div class="item-name"><?= e($item['item_name']) ?></div></td>
                <td style="text-align:right;"><?= number_format((float)$item['qty'],2,',','.') ?></td>
                <td style="text-align:right;"><?= e($item['unit']??'-') ?></td>
                <td style="text-align:right;">Rp <?= number_format((float)$item['unit_price'],0,',','.') ?></td>
                <td style="text-align:right;font-weight:600;">Rp <?= number_format((float)$item['subtotal'],0,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-box">
                <div class="total-row"><span>Subtotal</span><span>Rp <?= number_format($calc['total'],0,',','.') ?></span></div>
                <div class="total-row"><span>Diskon (<?= (float)($po['discount']??0) ?>%)</span><span>- Rp <?= number_format($calc['diskon_amt'],0,',','.') ?></span></div>
                <div class="total-row"><span>PPN (11%)</span><span>Rp <?= number_format($calc['pajak_amt'],0,',','.') ?></span></div>
                <div class="total-row grand"><span>TOTAL</span><span>Rp <?= number_format($calc['subtotal_akhir'],0,',','.') ?></span></div>
            </div>
        </div>

        <?php if (!empty($po['notes']) || !empty($po['terms_conditions'])): ?>
        <div class="notes-section">
            <?php if (!empty($po['notes'])): ?><div><div class="notes-label">Catatan</div><div class="notes-text"><?= nl2br(e($po['notes'])) ?></div></div><?php endif; ?>
            <?php if (!empty($po['terms_conditions'])): ?><div><div class="notes-label">Syarat &amp; Ketentuan</div><div class="notes-text"><?= nl2br(e($po['terms_conditions'])) ?></div></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="sign-section">
            <div class="sign-box"><div class="sign-label">Disiapkan Oleh</div><div class="sign-name"><?= e($po['prepared_by']??$po['created_by_name']) ?></div></div>
            <div class="sign-box"><div class="sign-label">Disetujui Oleh</div><div class="sign-name"><?= e($po['approved_by']??'-') ?></div></div>
            <div class="sign-box"><div class="sign-label">Diterima Oleh</div><div class="sign-name" style="color:#999;font-style:italic;font-weight:400;">Vendor</div></div>
        </div>

        <div class="doc-footer">
            <span>Dokumen ini diterbitkan secara elektronik oleh PT. Viros Prime Solution</span>
            <span>Dicetak: <?= $now ?></span>
        </div>
    </div>
</div>
</body></html>
