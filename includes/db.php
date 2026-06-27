<?php
define('DATA_DIR', __DIR__ . '/../data/');

function db_init() {
    $files = [
        'users.json'           => [],
        'purchase_orders.json' => [],
        'po_items.json'        => [],
        'po_history.json'      => [],
        'sequence.json'        => ['users'=>1,'purchase_orders'=>1,'po_items'=>1,'po_history'=>1],
    ];
    foreach ($files as $file => $default) {
        $path = DATA_DIR . $file;
        if (!file_exists($path))
            file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
    $users = db_read('users');
    if (empty($users)) {
        $defaults = [
            ['username'=>'Administrator',    'email'=>'admin@viros.co.id',     'password'=>password_hash('admin123',     PASSWORD_DEFAULT), 'role'=>'admin',     'is_active'=>1, 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>null],
            ['username'=>'Manager Pengadaan','email'=>'manager@viros.co.id',   'password'=>password_hash('manager123',   PASSWORD_DEFAULT), 'role'=>'manager',   'is_active'=>1, 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>null],
            ['username'=>'Staf Operasional', 'email'=>'staff@viros.co.id',     'password'=>password_hash('staff123',     PASSWORD_DEFAULT), 'role'=>'staff',     'is_active'=>1, 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>null],
            ['username'=>'Direktur Utama',   'email'=>'direktur@viros.co.id',  'password'=>password_hash('direktur123',  PASSWORD_DEFAULT), 'role'=>'direktur',  'is_active'=>1, 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>null],
            ['username'=>'Komisaris Utama',  'email'=>'komisaris@viros.co.id', 'password'=>password_hash('komisaris123', PASSWORD_DEFAULT), 'role'=>'komisaris', 'is_active'=>1, 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>null],
        ];
        foreach ($defaults as $u) db_insert('users', $u);
    }
}

function db_read(string $table): array {
    $path = DATA_DIR . $table . '.json';
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function db_write(string $table, array $data): void {
    file_put_contents(DATA_DIR.$table.'.json', json_encode(array_values($data), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function db_next_id(string $table): int {
    $path = DATA_DIR.'sequence.json';
    $seq  = json_decode(file_get_contents($path), true);
    $id   = $seq[$table] ?? 1;
    $seq[$table] = $id + 1;
    file_put_contents($path, json_encode($seq, JSON_PRETTY_PRINT), LOCK_EX);
    return $id;
}

function db_insert(string $table, array $row): int {
    $id = db_next_id($table);
    $data = db_read($table);
    $row['id'] = $id;
    $data[] = $row;
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
    db_write($table, array_values(array_filter($data, fn($r) => $r[$field] != $value)));
}

function db_find(string $table, int $id): ?array {
    foreach (db_read($table) as $row)
        if ((int)$row['id'] === $id) return $row;
    return null;
}

function db_find_where(string $table, string $field, $value): ?array {
    foreach (db_read($table) as $row)
        if ($row[$field] === $value) return $row;
    return null;
}

function db_where(string $table, array $conditions): array {
    return array_values(array_filter(db_read($table), function($row) use ($conditions) {
        foreach ($conditions as $k => $v)
            if (!isset($row[$k]) || $row[$k] != $v) return false;
        return true;
    }));
}

function db_count(string $table, array $cond = []): int {
    return count(empty($cond) ? db_read($table) : db_where($table, $cond));
}

function db_sum(string $table, string $col, array $cond = []): float {
    $rows = empty($cond) ? db_read($table) : db_where($table, $cond);
    return (float)array_sum(array_column($rows, $col));
}
