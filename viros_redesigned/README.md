# 🏢 Sistem Informasi PO Keluar — PT. Viros Prime Solution

Aplikasi web manajemen Purchase Order berbasis Flask (Python) + MySQL (XAMPP) + HTML.

---

## 📁 Struktur Folder

```
viros_po_system/
├── app.py                  ← Backend utama Flask
├── setup_admin.py          ← Script setup akun admin pertama
├── database.sql            ← Schema database MySQL
├── requirements.txt        ← Daftar library Python
├── README.md               ← Panduan ini
├── templates/
│   ├── base.html           ← Template induk (sidebar, navbar)
│   ├── login.html          ← Halaman login
│   ├── dashboard.html      ← Dashboard ringkasan
│   ├── po_list.html        ← Daftar purchase order
│   ├── po_create.html      ← Form buat PO baru
│   ├── po_detail.html      ← Detail & ubah status PO
│   └── user_management.html← Kelola pengguna (admin only)
└── static/
    └── img/
        └── logo.png        ← ⚠️ Letakkan logo Viros di sini!
```

---

## ⚙️ Cara Instalasi & Menjalankan

### LANGKAH 1 — Pastikan XAMPP Berjalan
1. Buka **XAMPP Control Panel**
2. Start **Apache** dan **MySQL**
3. Buka browser → `http://localhost/phpmyadmin`

---

### LANGKAH 2 — Buat Database

Di **phpMyAdmin**:
1. Klik **"New"** di sidebar kiri
2. Buat database bernama: `viros_po_system`
3. Klik tab **SQL**
4. Salin isi file `database.sql` → paste → klik **Go**

---

### LANGKAH 3 — Install Python & Library

Pastikan Python sudah terinstall. Buka **Command Prompt / Terminal**:

```bash
# Masuk ke folder project
cd path/ke/viros_po_system

# Install semua library
pip install Flask Flask-MySQLdb Werkzeug PyMySQL
```

> Jika error `mysqlclient`, coba:
> ```bash
> pip install PyMySQL
> ```
> Lalu tambahkan di `app.py` setelah `import`:
> ```python
> import pymysql
> pymysql.install_as_MySQLdb()
> ```

---

### LANGKAH 4 — Setup Akun Admin

```bash
python setup_admin.py
```

Output yang muncul:
```
✅ Admin berhasil dibuat!
  Email    : admin@viros.co.id
  Password : admin123
  Role     : admin
```

---

### LANGKAH 5 — Jalankan Aplikasi

```bash
python app.py
```

Buka browser: **http://localhost:5000**

---

## 👤 Hak Akses Per Role

| Fitur                   | Staff | Manager | Admin |
|-------------------------|-------|---------|-------|
| Login                   | ✅    | ✅      | ✅    |
| Lihat Dashboard         | ✅    | ✅      | ✅    |
| Lihat Daftar PO         | ✅    | ✅      | ✅    |
| Buat PO Baru            | ✅    | ❌      | ✅    |
| Ubah Status PO          | ✅*   | ✅**    | ✅    |
| Hapus PO                | ❌    | ✅      | ✅    |
| Kelola Pengguna         | ❌    | ❌      | ✅    |
| Logout                  | ✅    | ✅      | ✅    |

> *Staff hanya bisa set: `pending`, `revision`
> **Manager bisa set: `pending`, `approved`, `rejected`, `completed`

---

## 🔧 Konfigurasi Database

Edit di `app.py` jika XAMPP Anda berbeda:

```python
app.config['MYSQL_HOST'] = 'localhost'
app.config['MYSQL_USER'] = 'root'
app.config['MYSQL_PASSWORD'] = ''      # Default XAMPP kosong
app.config['MYSQL_DB'] = 'viros_po_system'
```

---

## 🖼️ Menambahkan Logo

Letakkan file logo dengan nama `logo.png` di folder:
```
static/img/logo.png
```

---

## ❓ Troubleshooting

| Error | Solusi |
|-------|--------|
| `ModuleNotFoundError: flask_mysqldb` | `pip install Flask-MySQLdb` |
| `Access denied for user 'root'` | Cek password MySQL di `app.py` |
| `Unknown database 'viros_po_system'` | Jalankan `database.sql` di phpMyAdmin |
| Port 5000 sudah dipakai | `python app.py` dengan `--port 5001` |

---

## 📞 Akun Default

| Email | Password | Role |
|-------|----------|------|
| admin@viros.co.id | admin123 | Admin |
