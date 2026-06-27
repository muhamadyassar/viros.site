<?php
/**
 * db.php — JSON flat-file database engine
 * DATA_DIR selalu relatif dari lokasi file ini: includes/ → naik satu level → data/
 */

define('DATA_DIR', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR);

function db_init(): void {
    // Pastikan folder data/ ada dan writeable
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }

    $files = [
        'users.json'           => '[]',
        'purchase_orders.json' => '[]',
        'po_items.json'        => '[]',
        'po_history.json'      => '[]',
        'sequence.json'        => '{"users":1,"purchase_orders":1,"po_items":1,"po_history":1}',
    ];

    foreach ($files as $file => $default) {
        $path = DATA_DIR . $file;
        if (!file_exists($path)) {
            file_put_contents($path, $default);
        }
    }

    // Seed user default jika users.json kosong
    $users = db_read('users');
    if (empty($users)) {
        $seeds = [
            ['username' => 'Administrator',    'email' => 'admin@viros.co.id',     'password' => password_hash('admin123',     PASSWORD_BCRYPT), 'role' => 'admin',     'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null],
            ['username' => 'Manager Pengadaan','email' => 'manager@viros.co.id',   'password' => password_hash('manager123',   PASSWORD_BCRYPT), 'role' => 'manager',   'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null],
            ['username' => 'Staf Operasional', 'email' => 'staff@viros.co.id',     'password' => password_hash('staff123',     PASSWORD_BCRYPT), 'role' => 'staff',     'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null],
            ['username' => 'Direktur Utama',   'email' => 'direktur@viros.co.id',  'password' => password_hash('direktur123',  PASSWORD_BCRYPT), 'role' => 'direktur',  'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null],
            ['username' => 'Komisaris Utama',  'email' => 'komisaris@viros.co.id', 'password' => password_hash('komisaris123', PASSWORD_BCRYPT), 'role' => 'komisaris', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null],
        ];
        foreach ($seeds as $u) {
            db_insert('users', $u);
        }
    }
}

function db_read(string $table): array {
    $path = DATA_DIR . $table . '.json';
    if (!file_exists($path)) return [];
    $raw  = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function db_write(string $table, array $data): void {
    $path = DATA_DIR . $table . '.json';
    file_put_contents($path, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function db_next_id(string $table): int {
    $path = DATA_DIR . 'sequence.json';
    $seq  = json_decode(file_get_contents($path), true) ?: [];
    $id   = (int)($seq[$table] ?? 1);
    $seq[$table] = $id + 1;
    file_put_contents($path, json_encode($seq, JSON_PRETTY_PRINT), LOCK_EX);
    return $id;
}

function db_insert(string $table, array $row): int {
    $id        = db_next_id($table);
    $data      = db_read($table);
    $row['id'] = $id;
    $data[]    = $row;
    db_write($table, $data);
    return $id;
}

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

function db_delete(string $table, int $id): bool {
    $data = db_read($table);
    $new  = array_values(array_filter($data, fn($r) => (int)$r['id'] !== $id));
    if (count($new) === count($data)) return false;
    db_write($table, $new);
    return true;
}

function db_delete_where(string $table, string $field, $value): void {
    $data = db_read($table);
    db_write($table, array_values(array_filter($data, fn($r) => (string)$r[$field] !== (string)$value)));
}

function db_find(string $table, int $id): ?array {
    foreach (db_read($table) as $row)
        if ((int)$row['id'] === $id) return $row;
    return null;
}

function db_find_where(string $table, string $field, string $value): ?array {
    foreach (db_read($table) as $row)
        if ((string)($row[$field] ?? '') === $value) return $row;
    return null;
}

function db_where(string $table, array $conditions): array {
    return array_values(array_filter(db_read($table), function ($row) use ($conditions) {
        foreach ($conditions as $k => $v)
            if ((string)($row[$k] ?? '') !== (string)$v) return false;
        return true;
    }));
}

function db_count(string $table, array $cond = []): int {
    return count(empty($cond) ? db_read($table) : db_where($table, $cond));
}

function db_sum(string $table, string $col, array $cond = []): float {
    $rows = empty($cond) ? db_read($table) : db_where($table, $cond);
    return (float) array_sum(array_column($rows, $col));
}
