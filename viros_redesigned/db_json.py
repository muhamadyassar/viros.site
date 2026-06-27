"""
db_json.py - Database berbasis file JSON
"""
import json
import os
import threading
import datetime

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, 'data')
DB_FILE = os.path.join(DATA_DIR, 'db.json')

_lock = threading.Lock()

TABLES = ['users', 'purchase_orders', 'po_items', 'po_history']

def _now_str():
    return datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')

def _empty_db():
    return {
        'users': [],
        'purchase_orders': [],
        'po_items': [],
        'po_history': [],
        '_next_id': {t: 1 for t in TABLES},
    }

def _ensure_file():
    os.makedirs(DATA_DIR, exist_ok=True)
    if not os.path.exists(DB_FILE):
        with open(DB_FILE, 'w', encoding='utf-8') as f:
            json.dump(_empty_db(), f, ensure_ascii=False, indent=2)

def _load():
    _ensure_file()
    with open(DB_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)
    for t in TABLES:
        data.setdefault(t, [])
    data.setdefault('_next_id', {})
    for t in TABLES:
        data['_next_id'].setdefault(t, 1)
    return data

def _save(data):
    tmp_path = DB_FILE + '.tmp'
    with open(tmp_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2, default=str)
    os.replace(tmp_path, DB_FILE)

class JSONDatabase:
    def all(self, table):
        data = _load()
        return list(data[table])

    def find(self, table, predicate=None):
        rows = self.all(table)
        if predicate is None:
            return rows
        return [r for r in rows if predicate(r)]

    def find_one(self, table, predicate):
        for r in self.find(table, predicate):
            return r
        return None

    def get_by_id(self, table, row_id):
        return self.find_one(table, lambda r: r['id'] == row_id)

    def insert(self, table, record):
        with _lock:
            data = _load()
            new_id = data['_next_id'][table]
            record = dict(record)
            record['id'] = new_id
            data[table].append(record)
            data['_next_id'][table] = new_id + 1
            _save(data)
            return new_id

    def update(self, table, row_id, fields):
        with _lock:
            data = _load()
            for row in data[table]:
                if row['id'] == row_id:
                    row.update(fields)
                    _save(data)
                    return True
            return False

    def update_many(self, table, predicate, fields):
        with _lock:
            data = _load()
            count = 0
            for row in data[table]:
                if predicate(row):
                    row.update(fields)
                    count += 1
            if count:
                _save(data)
            return count

    def delete(self, table, row_id):
        with _lock:
            data = _load()
            before = len(data[table])
            data[table] = [r for r in data[table] if r['id'] != row_id]
            _save(data)
            return before != len(data[table])

    def delete_many(self, table, predicate):
        with _lock:
            data = _load()
            before = len(data[table])
            data[table] = [r for r in data[table] if not predicate(r)]
            _save(data)
            return before - len(data[table])

    def count(self, table, predicate=None):
        return len(self.find(table, predicate))

db = JSONDatabase()

# ============================================================
# SEED DATA
# ============================================================
def seed_if_empty():
    _ensure_file()
    data = _load()
    if data['users']:
        return

    seed_users = [
        {
            'username': 'Administrator', 'email': 'admin@viros.co.id',
            'password': 'scrypt:32768:8:1$YVr2GERHTi0Mxdny$3cd7bc9782bd9f59050b04db62d51f387c2030d0c6882210a7a2afa852c8000b4cbcfd219df6789b3e9bc694ed1485d3c18be76364298cd72f65ca92e6f8e7d8',
            'role': 'admin', 'is_active': 1,
            'created_at': '2026-06-02 05:53:21', 'updated_at': None,
        },
        {
            'username': 'Manager Pengadaan', 'email': 'manager@viros.co.id',
            'password': 'scrypt:32768:8:1$VW1lKw8nRcd9AndL$71115d3287bbc5581b15a368d8fbd65f454f69861eb1cf96bcccf25a036e38326119912ebe9fa3ab143bceaa764c37613a64fcb5d35c4b331067b517e8f8ee39',
            'role': 'manager', 'is_active': 1,
            'created_at': '2026-06-02 05:53:21', 'updated_at': None,
        },
        {
            'username': 'Staf Operasional', 'email': 'staff@viros.co.id',
            'password': 'scrypt:32768:8:1$ad4kkNMlyOlAPKjI$ddae43ef0fbf0ebe4101e5fe030f8fd27ffbfc7ee4ee93ceb1e897d7acbf4d18f6dbf976a524e90fd29e72a0de0b6ef122724c80277fc9fee372e456cacc9c36',
            'role': 'staff', 'is_active': 1,
            'created_at': '2026-06-02 05:53:21', 'updated_at': None,
        },
        {
            'username': 'Direktur Utama', 'email': 'direktur@viros.co.id',
            'password': 'scrypt:32768:8:1$lSFlLLKbw58cn2dj$e2fd81127afe737dad9da9c1329134ab24ef615a94d7793245ca37622a0e82265494a24cec94a3af8fca44fca2b67e6711259cf301b04ceacf5331aec35970e8',
            'role': 'direktur', 'is_active': 1,
            'created_at': '2026-06-02 05:53:21', 'updated_at': None,
        },
        {
            'username': 'Komisaris Utama', 'email': 'komisaris@viros.co.id',
            'password': 'scrypt:32768:8:1$fAuzQag4H2Uxshwb$c9fd64c708014bc69c29e799469f147d90d7777cc563637637d890f90653f7454863b783316a6d71cc81f95c321ecedaaf70fcc47cc8dd8fac819ba0bc597ba5',
            'role': 'komisaris', 'is_active': 1,
            'created_at': '2026-06-02 05:53:21', 'updated_at': None,
        },
    ]
    for u in seed_users:
        db.insert('users', u)

seed_if_empty()
