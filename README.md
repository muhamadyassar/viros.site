# Viros PO System — PHP + JSON

## Cara Instalasi

1. Ekstrak zip ke folder web server: `htdocs/viros_php/` (XAMPP) atau `www/viros_php/` (WAMP)
2. Pastikan folder `data/` bisa ditulis oleh PHP:
   - Windows (XAMPP): biasanya sudah otomatis
   - Linux: `chmod 755 data/`
3. Buka browser: `http://localhost/viros_php/`
4. File JSON akan dibuat otomatis di folder `data/`

## Akun Default

| Email                   | Password      | Role      |
|-------------------------|---------------|-----------|
| admin@viros.co.id       | admin123      | Admin     |
| manager@viros.co.id     | manager123    | Manager   |
| staff@viros.co.id       | staff123      | Staff     |
| direktur@viros.co.id    | direktur123   | Direktur  |
| komisaris@viros.co.id   | komisaris123  | Komisaris |

## Jika Login Gagal

Buka halaman diagnostik: `http://localhost/viros_php/setup.php`

Halaman ini akan:
- Menampilkan status semua file JSON
- Memverifikasi apakah hash password valid
- Menyediakan tombol **Reset Semua Data** untuk membuat ulang user

Setelah sistem berjalan normal, **hapus file `setup.php`**.

## Syarat Server

- PHP 7.4+ (direkomendasikan PHP 8.x)
- Web server: Apache/Nginx/XAMPP/WAMP/Laragon
- Ekstensi PHP: `json`, `session` (sudah aktif secara default)

## Kirim Email

Fitur kirim email di halaman Cetak PO menggunakan `mail()` PHP bawaan.
Untuk Gmail/SMTP, install PHPMailer:
```
composer require phpmailer/phpmailer
```
Lalu edit `po_send_email.php` untuk menggunakan PHPMailer.
