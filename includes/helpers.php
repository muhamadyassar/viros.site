<?php
/**
 * helpers.php — Fungsi utilitas: kalkulasi PO, simpan riwayat, format, dll.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// ─── KALKULASI TOTAL PO ───────────────────────────────────────────────────────

function hitung_total(float $items_subtotal, float $discount_pct): array {
    $total          = $items_subtotal;
    $diskon_amt     = round($total * $discount_pct / 100, 2);
    $after_disc     = $total - $diskon_amt;
    $pajak_amt      = round($after_disc * 11 / 100, 2);
    $subtotal_akhir = round($after_disc + $pajak_amt, 2);
    return [
        'total'          => $total,
        'diskon_amt'     => $diskon_amt,
        'pajak_amt'      => $pajak_amt,
        'subtotal_akhir' => $subtotal_akhir,
    ];
}

// ─── SIMPAN RIWAYAT PO ───────────────────────────────────────────────────────

function save_history(int $po_id, string $old_status, string $new_status, string $note = ''): void {
    db_insert('po_history', [
        'po_id'      => $po_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'changed_by' => current_user_id(),
        'changed_at' => date('Y-m-d H:i:s'),
        'note'       => $note,
    ]);
}

// ─── GENERATE NOMOR PO ───────────────────────────────────────────────────────

function generate_po_number(): string {
    $count = db_count('purchase_orders') + 1;
    return 'PO-' . date('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// ─── FORMAT RUPIAH ───────────────────────────────────────────────────────────

function rupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// ─── FORMAT TANGGAL INDONESIA ─────────────────────────────────────────────────

function tgl_id(string $date): string {
    if (empty($date)) return '-';
    $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($date);
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// ─── BADGE STATUS ────────────────────────────────────────────────────────────

function badge_status(string $status): string {
    $labels = [
        'pending'   => 'Menunggu',
        'approved'  => 'Disetujui',
        'rejected'  => 'Ditolak',
        'completed' => 'Selesai',
        'revision'  => 'Revisi',
        'draft'     => 'Draft',
    ];
    $label = $labels[$status] ?? $status;
    return "<span class=\"badge badge-{$status}\">" . htmlspecialchars($label) . "</span>";
}

// ─── ESCAPE HTML ─────────────────────────────────────────────────────────────

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ─── AMBIL PO LENGKAP (dengan nama pembuat) ──────────────────────────────────

function get_po_with_user(int $po_id): ?array {
    $po = db_find('purchase_orders', $po_id);
    if (!$po) return null;
    if (!empty($po['created_by'])) {
        $u = db_find('users', (int)$po['created_by']);
        $po['created_by_name'] = $u['username'] ?? '-';
    } else {
        $po['created_by_name'] = '-';
    }
    return $po;
}

// ─── AMBIL ITEMS PO ──────────────────────────────────────────────────────────

function get_po_items(int $po_id): array {
    return db_where('po_items', ['po_id' => $po_id]);
}

// ─── AMBIL HISTORY PO (dengan nama pengubah, urut terbaru) ───────────────────

function get_po_history(int $po_id): array {
    $histories = db_where('po_history', ['po_id' => $po_id]);
    foreach ($histories as &$h) {
        if (!empty($h['changed_by'])) {
            $u = db_find('users', (int)$h['changed_by']);
            $h['changed_by_name'] = $u['username'] ?? '-';
        } else {
            $h['changed_by_name'] = '-';
        }
    }
    // Urut terbaru dulu
    usort($histories, fn($a, $b) => strcmp($b['changed_at'], $a['changed_at']));
    return $histories;
}

// ─── KALKULASI SUBTOTAL ITEMS ─────────────────────────────────────────────────

function items_subtotal(array $items): float {
    return array_sum(array_map(fn($i) => (float)($i['subtotal'] ?? 0), $items));
}
