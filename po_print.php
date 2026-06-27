# Viros PO System — PHP + JSON

Versi PHP dari sistem Purchase Order Viros, menggunakan flat-file JSON sebagai database.

## Struktur Folder

```
viros_php/
├── index.php              # Redirect ke login/dashboard
├── login.php              # Halaman login
├── logout.php             # Logout
├── dashboard.php          # Dashboard utama
├── po_list.php            # Daftar Purchase Order
├── po_create.php          # Buat PO baru
├── po_detail.php          # Detail PO
├── po_edit.php            # Edit PO
├── po_update_status.php   # Aksi update status PO
├── po_delete.php          # Aksi hapus PO
├── po_riwayat.php         # Riwayat perubahan status
├── po_print.php           # Halaman cetak PO
├── user_management.php    # Manajemen pengguna (admin only)
├── includes/
│   ├── db.php             # Fungsi baca/tulis JSON (pengganti MySQL)
│   ├── auth.php           # Session & role check
│   ├── helpers.php        # Kalkulasi, format, utilitas
│   └── layout.php         # Layout HTML utama
├── data/                  # File JSON (otomatis dibuat)
│   ├── users.json
│   ├── purchase_orders.json
│   ├── po_items.json
│   ├── po_history.json
│   └── sequence.json
└── static/img/
    └── logo_viros.png
```

## Cara Instalasi

1. Letakkan folder `viros_php/` di dalam folder web server (misal: `htdocs/` atau `www/`)
2. Buka browser, akses `http://localhost/viros_php/`
3. Data JSON akan **otomatis dibuat** di folder `data/` saat pertama kali diakses

## Akun Default

| Email                   | Password      | Role      |
|-------------------------|---------------|-----------|
| admin@viros.co.id       | admin123      | Admin     |
| manager@viros.co.id     | manager123    | Manager   |
| staff@viros.co.id       | staff123      | Staff     |
| direktur@viros.co.id    | direktur123   | Direktur  |
| komisaris@viros.co.id   | komisaris123  | Komisaris |

## Syarat Server

- PHP 7.4 atau lebih baru
- Ekstensi: `json`, `session` (sudah aktif secara default)
- Folder `data/` harus bisa ditulis (writeable) oleh PHP

## Keamanan File JSON

File `.htaccess` di folder `data/` sudah memblokir akses langsung dari browser.
Pastikan web server Anda menghormati `.htaccess` (Apache dengan `AllowOverride All`).
