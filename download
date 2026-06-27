<?php
/**
 * db.php — Pengganti database MySQL dengan JSON flat-file
 * Setiap tabel disimpan sebagai file JSON terpisah di folder /data/
 */

define('DATA_DIR', __DIR__ . '/../data/');

// ─── INISIALISASI FILE JSON JIKA BELUM ADA ───────────────────────────────────

function db_init() {
    $files = [
        'users.json'            => [],
        'purchase_orders.json'  => [],
        'po_items.json'         => [],
        'po_history.json'       => [],
        'sequence.json'         => ['users' => 1, 'purchase_orders' => 1, 'po_items' => 1, 'po_history' => 1],
    ];

    foreach ($files as $file => $default) {
        $path = DATA_DIR . $file;
        if (!file_exists($path)) {
            file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // Seed user default jika users.json kosong
    $users = db_read('users');
    if (empty($users)) {
        $defaults = [
            ['username' => 'Administrator',    'email' => 'admin@viros.co.id',     'password' => password_hash('admin123',     PASSWORD_DEFAULT), 'role' => 'admin',     'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')],
            ['username' => 'Manager Pengadaan','email' => 'manager@viros.co.id',   'password' => password_hash('manager123',   PASSWORD_DEFAULT), 'role' => 'manager',   'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')],
            ['username' => 'Staf Operasional', 'email' => 'staff@viros.co.id',     'password' => password_hash('staff123',     PASSWORD_DEFAULT), 'role' => 'staff',     'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')],
            ['username' => 'Direktur Utama',   'email' => 'direktur@viros.co.id',  'password' => password_hash('direktur123',  PASSWORD_DEFAULT), 'role' => 'direktur',  'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')],
            ['username' => 'Komisaris Utama',  'email' => 'komisaris@viros.co.id', 'password' => password_hash('komisaris123', PASSWORD_DEFAULT), 'role' => 'komisaris', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')],
        ];
        foreach ($defaults as $u) {
            db_insert('users', $u);
        }
    }
}

// ─── BACA SEMUA DATA DARI TABEL ──────────────────────────────────────────────

function db_read(string $table): array {
    $path = DATA_DIR . $table . '.json';
    if (!file_exists($path)) return [];
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// ─── TULIS SEMUA DATA KE TABEL ───────────────────────────────────────────────

function db_write(string $table, array $data): void {
    $path = DATA_DIR . $table . '.json';
    file_put_contents($path, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ─── AMBIL AUTO-INCREMENT ID BERIKUTNYA ──────────────────────────────────────

function db_next_id(string $table): int {
    $path = DATA_DIR . 'sequence.json';
    $seq  = json_decode(file_get_contents($path), true);
    $id   = $seq[$table] ?? 1;
    $seq[$table] = $id + 1;
    file_put_contents($path, json_encode($seq, JSON_PRETTY_PRINT), LOCK_EX);
    return $id;
}

// ─── INSERT BARIS BARU ───────────────────────────────────────────────────────

function db_insert(string $table, array $row): int {
    $id      = db_next_id($table);
    $data    = db_read($table);
    $row['id'] = $id;
    $data[]  = $row;
    db_write($table, $data);
    return $id;
}

// ─── UPDATE BARIS BERDASARKAN ID ─────────────────────────────────────────────

function db_update(string $table, int $id, array $changes): bool {
    $data = db_read($table);
    foreach ($data as &$row) {
        if ((int)$row['id'] === $id) {
            foreach ($changes as $k => $v) $row[$k] = $v;
            db_write($table, $data);
            return true;
        }
    }
    return false;
}

// ─── HAPUS BARIS BERDASARKAN ID ──────────────────────────────────────────────

function db_delete(string $table, int $id): bool {
    $data    = db_read($table);
    $filtered = array_filter($data, fn($r) => (int)$r['id'] !== $id);
    if (count($filtered) === count($data)) return false;
    db_write($table, array_values($filtered));
    return true;
}

// ─── HAPUS BARIS BERDASARKAN KONDISI (field => value) ────────────────────────

function db_delete_where(string $table, string $field, $value): void {
    $data     = db_read($table);
    $filtered = array_values(array_filter($data, fn($r) => $r[$field] !== $value));
    db_write($table, $filtered);
}

// ─── CARI SATU BARIS BERDASARKAN ID ──────────────────────────────────────────

function db_find(string $table, int $id): ?array {
    foreach (db_read($table) as $row) {
        if ((int)$row['id'] === $id) return $row;
    }
    return null;
}

// ─── CARI SATU BARIS DENGAN KONDISI (field => value) ─────────────────────────

function db_find_where(string $table, string $field, $value): ?array {
    foreach (db_read($table) as $row) {
        if ($row[$field] === $value) return $row;
    }
    return null;
}

// ─── FILTER BARIS DENGAN KONDISI ─────────────────────────────────────────────

function db_where(string $table, array $conditions): array {
    $data = db_read($table);
    return array_values(array_filter($data, function($row) use ($conditions) {
        foreach ($conditions as $k => $v) {
            if (!isset($row[$k]) || $row[$k] != $v) return false;
        }
        return true;
    }));
}

// ─── HITUNG JUMLAH BARIS ─────────────────────────────────────────────────────

function db_count(string $table, array $conditions = []): int {
    if (empty($conditions)) return count(db_read($table));
    return count(db_where($table, $conditions));
}

// ─── JUMLAHKAN KOLOM ─────────────────────────────────────────────────────────

function db_sum(string $table, string $column, array $conditions = []): float {
    $rows = empty($conditions) ? db_read($table) : db_where($table, $conditions);
    return array_sum(array_column($rows, $column));
}
