<?php
function hitung_total(float $items_subtotal, float $disc_pct): array {
    $total      = $items_subtotal;
    $diskon_amt = round($total * $disc_pct / 100, 2);
    $after_disc = $total - $diskon_amt;
    $pajak_amt  = round($after_disc * 11 / 100, 2);
    return [
        'total'          => $total,
        'diskon_amt'     => $diskon_amt,
        'pajak_amt'      => $pajak_amt,
        'subtotal_akhir' => round($after_disc + $pajak_amt, 2),
    ];
}

function save_history(int $po_id, string $old, string $new, string $note=''): void {
    db_insert('po_history', [
        'po_id'=>$po_id, 'old_status'=>$old, 'new_status'=>$new,
        'changed_by'=>current_user_id(), 'changed_at'=>date('Y-m-d H:i:s'), 'note'=>$note,
    ]);
}

function generate_po_number(): string {
    return 'PO-'.date('Ym').'-'.str_pad(db_count('purchase_orders')+1, 4, '0', STR_PAD_LEFT);
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function fmt_rp(float $n): string { return 'Rp '.number_format($n, 0, ',', '.'); }

function tgl_id(?string $date): string {
    if (!$date) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($date);
    return date('j',$ts).' '.$bulan[(int)date('n',$ts)].' '.date('Y',$ts);
}

function tgl_short(?string $date): string {
    if (!$date) return '-';
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ts = strtotime($date);
    return date('d',$ts).' '.$bulan[(int)date('n',$ts)].' '.date('Y',$ts);
}

function get_po_with_user(int $id): ?array {
    $po = db_find('purchase_orders', $id);
    if (!$po) return null;
    $u = db_find('users', (int)($po['created_by'] ?? 0));
    $po['created_by_name'] = $u['username'] ?? '-';
    return $po;
}

function get_po_items(int $po_id): array {
    return db_where('po_items', ['po_id'=>$po_id]);
}

function get_po_history(int $po_id): array {
    $rows = db_where('po_history', ['po_id'=>$po_id]);
    foreach ($rows as &$h) {
        $u = db_find('users', (int)($h['changed_by'] ?? 0));
        $h['changed_by_name'] = $u['username'] ?? 'Sistem';
    }
    usort($rows, fn($a,$b) => strcmp($b['changed_at']??'',$a['changed_at']??''));
    return $rows;
}

function items_subtotal(array $items): float {
    return (float)array_sum(array_column($items, 'subtotal'));
}
