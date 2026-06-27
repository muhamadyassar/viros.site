"""
Jalankan script ini untuk membuat/reset kelima akun default
(Admin, Manager, Staff, Direktur, Komisaris) di database JSON.

Tidak perlu membuat database SQL apapun lagi — data tersimpan
otomatis di file data/db.json (dibuat otomatis oleh db_json.py).

Cara pakai:
    python setup_admin.py
"""

from werkzeug.security import generate_password_hash
import datetime

from db_json import db

# Daftar akun default
users = [
    {
        'username': 'Administrator',
        'email':    'admin@viros.co.id',
        'password': 'admin123',
        'role':     'admin',
    },
    {
        'username': 'Manager',
        'email':    'manager@viros.co.id',
        'password': 'manager123',
        'role':     'manager',
    },
    {
        'username': 'Staff',
        'email':    'staff@viros.co.id',
        'password': 'staff123',
        'role':     'staff',
    },
    {
        'username': 'Direktur',
        'email':    'direktur@viros.co.id',
        'password': 'direktur123',
        'role':     'direktur',
    },
    {
        'username': 'Komisaris',
        'email':    'komisaris@viros.co.id',
        'password': 'komisaris123',
        'role':     'komisaris',
    },
]

print("=" * 50)
print("  VIROS PO SYSTEM — Setup Akun Default (JSON DB)")
print("=" * 50)

for u in users:
    # Hapus jika sudah ada, lalu buat ulang
    db.delete_many('users', lambda row, email=u['email']: row['email'] == email)
    hashed = generate_password_hash(u['password'])
    db.insert('users', {
        'username': u['username'],
        'email': u['email'],
        'password': hashed,
        'role': u['role'],
        'is_active': 1,
        'created_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'updated_at': None,
    })
    print(f"  \u2705 {u['role'].upper():10s} | {u['email']:25s} | {u['password']}")

print("=" * 50)
print("  Semua akun berhasil dibuat!")
print("=" * 50)
print("  Jalankan app : python app.py")
print("  Buka browser : http://localhost:5000")
print("=" * 50)
