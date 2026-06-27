from flask import Flask, render_template, request, redirect, url_for, session, flash, jsonify
from werkzeug.security import generate_password_hash, check_password_hash
from functools import wraps
import datetime
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders
import os

from db_json import db

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'viros_secret_key_2024')

# Konfigurasi SMTP dari Environment Variables
SMTP_HOST = os.environ.get('SMTP_HOST', 'smtp.gmail.com')
SMTP_PORT = int(os.environ.get('SMTP_PORT', 587))
SMTP_USER = os.environ.get('SMTP_USER', 'tokonnso1@gmail.com')
SMTP_PASSWORD = os.environ.get('SMTP_PASSWORD', 'trrdfecr qzbqjrji')
SMTP_USE_TLS = os.environ.get('SMTP_USE_TLS', 'true').lower() == 'true'

# ============================================================
# DECORATOR
# ============================================================
def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if 'user_id' not in session:
            flash('Silakan login terlebih dahulu.', 'warning')
            return redirect(url_for('login'))
        return f(*args, **kwargs)
    return decorated

def role_required(*roles):
    def decorator(f):
        @wraps(f)
        def decorated(*args, **kwargs):
            if session.get('role') not in roles:
                flash('Akses ditolak. Anda tidak memiliki izin.', 'danger')
                return redirect(url_for('dashboard'))
            return f(*args, **kwargs)
        return decorated
    return decorator

# ============================================================
# HELPER
# ============================================================
def save_history(po_id, old_status, new_status, note=''):
    db.insert('po_history', {
        'po_id': po_id,
        'old_status': old_status,
        'new_status': new_status,
        'changed_by': session['user_id'],
        'changed_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'note': note,
    })

def hitung_total(items_subtotal, discount_pct):
    total = float(items_subtotal)
    diskon_amt = round(total * float(discount_pct) / 100, 2)
    after_disc = total - diskon_amt
    pajak_amt = round(after_disc * 11 / 100, 2)
    subtotal_akhir = round(after_disc + pajak_amt, 2)
    return {
        'total': total,
        'diskon_amt': diskon_amt,
        'pajak_amt': pajak_amt,
        'subtotal_akhir': subtotal_akhir
    }

def _username_by_id(user_id):
    u = db.get_by_id('users', user_id)
    return u['username'] if u else None

def _parse_dt(value):
    if not value:
        return None
    if isinstance(value, (datetime.datetime, datetime.date)):
        return value
    for fmt in ('%Y-%m-%d %H:%M:%S', '%Y-%m-%d'):
        try:
            return datetime.datetime.strptime(str(value), fmt)
        except ValueError:
            continue
    return None

_DATE_FIELDS = {
    'purchase_orders': ['order_date', 'created_at', 'updated_at'],
    'users': ['created_at', 'updated_at'],
    'po_history': ['changed_at'],
}

def _hydrate_dates(row, table):
    if row is None:
        return None
    row = dict(row)
    for field in _DATE_FIELDS.get(table, []):
        row[field] = _parse_dt(row.get(field))
    return row

def _attach_created_by_name(po):
    po = _hydrate_dates(po, 'purchase_orders')
    if po is None:
        return None
    po['created_by_name'] = _username_by_id(po.get('created_by'))
    return po

# ============================================================
# AUTH
# ============================================================
@app.route('/')
def index():
    if 'user_id' in session:
        return redirect(url_for('dashboard'))
    return redirect(url_for('login'))

@app.route('/login', methods=['GET', 'POST'])
def login():
    if 'user_id' in session:
        return redirect(url_for('dashboard'))
    if request.method == 'POST':
        email = request.form['email']
        password = request.form['password']

        user = db.find_one('users', lambda u: u['email'] == email and u.get('is_active', 1) in (1, True))

        if user and check_password_hash(user['password'], password):
            session['user_id'] = user['id']
            session['username'] = user['username']
            session['role'] = user['role']
            session['email'] = user['email']
            
            # Remember me (30 hari)
            if request.form.get('remember'):
                session.permanent = True
                app.permanent_session_lifetime = datetime.timedelta(days=30)
            
            flash(f'Selamat datang, {user["username"]}!', 'success')
            return redirect(url_for('dashboard'))
        else:
            flash('Email atau password salah.', 'danger')
    return render_template('login.html')

@app.route('/logout')
def logout():
    session.clear()
    flash('Berhasil logout.', 'info')
    return redirect(url_for('login'))

# ============================================================
# DASHBOARD
# ============================================================
@app.route('/dashboard')
@login_required
def dashboard():
    now = datetime.datetime.now()

    all_pos = db.all('purchase_orders')

    total_po = len(all_pos)
    pending_po = sum(1 for p in all_pos if p.get('status') == 'pending')
    approved_po = sum(1 for p in all_pos if p.get('status') == 'approved')
    completed_po = sum(1 for p in all_pos if p.get('status') == 'completed')
    rejected_po = sum(1 for p in all_pos if p.get('status') == 'rejected')
    total_nilai = sum(float(p.get('total_amount') or 0) for p in all_pos)

    recent_pos = sorted(
        all_pos, key=lambda p: _parse_dt(p.get('created_at')) or datetime.datetime.min,
        reverse=True
    )[:10]
    recent_pos = [_attach_created_by_name(p) for p in recent_pos]

    # Tren pengeluaran bulanan
    batas_12bln = now - datetime.timedelta(days=365)
    bulan_map = {}
    for p in all_pos:
        od = _parse_dt(p.get('order_date'))
        if od is None or od < batas_12bln:
            continue
        key = od.strftime('%Y-%m')
        label = od.strftime('%b')
        bulan_map.setdefault(key, {'label': label, 'total': 0.0})
        bulan_map[key]['total'] += float(p.get('total_amount') or 0)

    bulan_keys_sorted = sorted(bulan_map.keys())
    tren_labels = [bulan_map[k]['label'] for k in bulan_keys_sorted]
    tren_values = [bulan_map[k]['total'] for k in bulan_keys_sorted]

    # Nilai PO per pemasok top 5
    vendor_map = {}
    for p in all_pos:
        v = p.get('vendor_name')
        if v:
            vendor_map[v] = vendor_map.get(v, 0.0) + float(p.get('total_amount') or 0)
    vendor_sorted = sorted(vendor_map.items(), key=lambda kv: kv[1], reverse=True)[:5]
    vendor_labels = [v for v, _ in vendor_sorted]
    vendor_values = [val for _, val in vendor_sorted]

    # Distribusi status
    status_map = {}
    for p in all_pos:
        s = p.get('status')
        status_map[s] = status_map.get(s, 0) + 1

    donut_labels = ['Disetujui', 'Proses', 'Ditolak', 'Selesai', 'Revisi']
    donut_values = [
        status_map.get('approved', 0),
        status_map.get('pending', 0),
        status_map.get('rejected', 0),
        status_map.get('completed', 0),
        status_map.get('revision', 0),
    ]

    # Rata-rata waktu approval
    all_history = db.all('po_history')
    po_by_id = {p['id']: p for p in all_pos}
    durasi_jam = []
    for h in all_history:
        if h.get('new_status') != 'approved':
            continue
        po = po_by_id.get(h.get('po_id'))
        if not po:
            continue
        created = _parse_dt(po.get('created_at'))
        changed = _parse_dt(h.get('changed_at'))
        if created and changed:
            durasi_jam.append((changed - created).total_seconds() / 3600.0)
    avg_approval = round((sum(durasi_jam) / len(durasi_jam) / 24.0) if durasi_jam else 0.0, 1)

    return render_template('dashboard.html',
        now=now,
        total_po=total_po,
        pending_po=pending_po,
        approved_po=approved_po,
        completed_po=completed_po,
        rejected_po=rejected_po,
        total_nilai=total_nilai,
        recent_pos=recent_pos,
        avg_approval=avg_approval,
        tren_labels=tren_labels,
        tren_values=tren_values,
        vendor_labels=vendor_labels,
        vendor_values=vendor_values,
        donut_labels=donut_labels,
        donut_values=donut_values,
    )

# ============================================================
# PO LIST
# ============================================================
@app.route('/po')
@login_required
def po_list():
    search = request.args.get('search', '')
    status_filter = request.args.get('status', '')

    all_pos = db.all('purchase_orders')

    def match(p):
        if search:
            s = search.lower()
            if not (
                s in (p.get('po_number') or '').lower() or
                s in (p.get('vendor_name') or '').lower() or
                s in (p.get('customer_company') or '').lower()
            ):
                return False
        if status_filter and p.get('status') != status_filter:
            return False
        return True

    pos = [p for p in all_pos if match(p)]
    pos = sorted(pos, key=lambda p: _parse_dt(p.get('created_at')) or datetime.datetime.min, reverse=True)
    pos = [_attach_created_by_name(p) for p in pos]

    total = len(all_pos)
    pending = sum(1 for p in all_pos if p.get('status') == 'pending')
    total_val = sum(float(p.get('total_amount') or 0) for p in all_pos)
    revision = sum(1 for p in all_pos if p.get('status') == 'revision')

    return render_template('po_list.html', pos=pos, search=search,
        status_filter=status_filter, total=total, pending=pending,
        total_val=total_val, revision=revision)

# ============================================================
# PO CREATE
# ============================================================
@app.route('/po/create', methods=['GET', 'POST'])
@login_required
@role_required('admin', 'staff')
def po_create():
    if request.method == 'POST':
        vendor_name = request.form['vendor_name']
        customer_company = request.form['customer_company']
        order_date = request.form['order_date']
        notes = request.form.get('notes', '')
        terms_conditions = request.form.get('terms_conditions', '')
        discount_pct = float(request.form.get('discount', 0) or 0)
        prepared_by = request.form.get('prepared_by', '')
        approved_by = request.form.get('approved_by', '')

        items = request.form.getlist('item_name[]')
        qtys = request.form.getlist('qty[]')
        units = request.form.getlist('unit[]')
        unit_prices = request.form.getlist('unit_price[]')

        if not items or not items[0]:
            flash('Minimal satu item harus diisi.', 'danger')
            return redirect(url_for('po_create'))

        items_subtotal = sum(
            float(qtys[i]) * float(unit_prices[i])
            for i in range(len(items)) if items[i]
        )

        calc = hitung_total(items_subtotal, discount_pct)
        total_amount = calc['subtotal_akhir']
        pajak_amt = calc['pajak_amt']

        count = db.count('purchase_orders') + 1
        po_number = f"PO-{datetime.datetime.now().strftime('%Y%m')}-{str(count).zfill(4)}"

        po_id = db.insert('purchase_orders', {
            'po_number': po_number,
            'vendor_name': vendor_name,
            'customer_company': customer_company,
            'order_date': order_date,
            'notes': notes,
            'discount': discount_pct,
            'tax': pajak_amt,
            'terms_conditions': terms_conditions,
            'total_amount': total_amount,
            'status': 'pending',
            'created_by': session['user_id'],
            'prepared_by': prepared_by,
            'approved_by': approved_by,
            'created_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'updated_at': None,
        })

        for i in range(len(items)):
            if items[i]:
                subtotal = float(qtys[i]) * float(unit_prices[i])
                db.insert('po_items', {
                    'po_id': po_id,
                    'item_name': items[i],
                    'qty': float(qtys[i]),
                    'unit': units[i],
                    'unit_price': float(unit_prices[i]),
                    'subtotal': subtotal,
                })

        save_history(po_id, '-', 'pending', 'PO dibuat')

        flash(f'PO {po_number} berhasil dibuat!', 'success')
        return redirect(url_for('po_list'))

    return render_template('po_create.html')

# ============================================================
# PO DETAIL
# ============================================================
@app.route('/po/<int:po_id>')
@login_required
def po_detail(po_id):
    po = db.get_by_id('purchase_orders', po_id)
    if not po:
        flash('PO tidak ditemukan.', 'danger')
        return redirect(url_for('po_list'))
    po = _attach_created_by_name(po)

    items = db.find('po_items', lambda i: i['po_id'] == po_id)

    history = db.find('po_history', lambda h: h['po_id'] == po_id)
    history = sorted(history, key=lambda h: _parse_dt(h.get('changed_at')) or datetime.datetime.min, reverse=True)
    history = [dict(_hydrate_dates(h, 'po_history'), changed_by_name=_username_by_id(h.get('changed_by'))) for h in history]

    items_subtotal = sum(float(item['subtotal']) for item in items)
    calc = hitung_total(items_subtotal, po['discount'] or 0)

    return render_template('po_detail.html', po=po, items=items,
                           history=history, calc=calc)

# ============================================================
# PO EDIT
# ============================================================
@app.route('/po/<int:po_id>/edit', methods=['GET', 'POST'])
@login_required
@role_required('admin', 'staff')
def po_edit(po_id):
    po = db.get_by_id('purchase_orders', po_id)
    if not po:
        flash('PO tidak ditemukan.', 'danger')
        return redirect(url_for('po_list'))
    po = _attach_created_by_name(po)

    items = db.find('po_items', lambda i: i['po_id'] == po_id)
    items = sorted(items, key=lambda i: i['id'])

    if request.method == 'POST':
        vendor_name = request.form['vendor_name']
        customer_company = request.form['customer_company']
        order_date = request.form['order_date']
        notes = request.form.get('notes', '')
        terms_conditions = request.form.get('terms_conditions', '')
        discount_pct = float(request.form.get('discount', 0) or 0)
        new_status = request.form.get('status', po['status'])
        prepared_by = request.form.get('prepared_by', po.get('prepared_by', ''))
        approved_by = request.form.get('approved_by', po.get('approved_by', ''))

        role = session.get('role')
        allowed_status = {
            'staff': ['pending', 'revision'],
            'admin': ['draft', 'pending', 'approved', 'rejected', 'completed', 'revision']
        }
        if new_status not in allowed_status.get(role, []):
            new_status = po['status']

        item_names = request.form.getlist('item_name[]')
        qtys = request.form.getlist('qty[]')
        units = request.form.getlist('unit[]')
        unit_prices = request.form.getlist('unit_price[]')

        if not item_names or not item_names[0]:
            flash('Minimal satu item harus diisi.', 'danger')
            return redirect(url_for('po_edit', po_id=po_id))

        items_subtotal = sum(
            float(qtys[i]) * float(unit_prices[i])
            for i in range(len(item_names)) if item_names[i]
        )

        calc = hitung_total(items_subtotal, discount_pct)
        total_amount = calc['subtotal_akhir']
        pajak_amt = calc['pajak_amt']
        old_status = po['status']

        db.update('purchase_orders', po_id, {
            'vendor_name': vendor_name,
            'customer_company': customer_company,
            'order_date': order_date,
            'notes': notes,
            'discount': discount_pct,
            'tax': pajak_amt,
            'terms_conditions': terms_conditions,
            'status': new_status,
            'total_amount': total_amount,
            'prepared_by': prepared_by,
            'approved_by': approved_by,
            'updated_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        })

        db.delete_many('po_items', lambda i: i['po_id'] == po_id)
        for i in range(len(item_names)):
            if item_names[i]:
                subtotal = float(qtys[i]) * float(unit_prices[i])
                db.insert('po_items', {
                    'po_id': po_id,
                    'item_name': item_names[i],
                    'qty': float(qtys[i]),
                    'unit': units[i],
                    'unit_price': float(unit_prices[i]),
                    'subtotal': subtotal,
                })

        if old_status != new_status:
            save_history(po_id, old_status, new_status, 'Status diubah saat edit PO')

        save_history(po_id, new_status, new_status, 'Data PO diperbarui')

        flash('Purchase Order berhasil diperbarui.', 'success')
        return redirect(url_for('po_detail', po_id=po_id))

    items_subtotal = sum(float(item['subtotal']) for item in items)
    calc = hitung_total(items_subtotal, po['discount'] or 0)
    return render_template('po_edit.html', po=po, items=items, calc=calc)

# ============================================================
# PO STATUS PAGE
# ============================================================
@app.route('/po/<int:po_id>/status-page')
@login_required
@role_required('admin', 'manager', 'staff')
def po_status_page(po_id):
    po = db.get_by_id('purchase_orders', po_id)
    if not po:
        flash('PO tidak ditemukan.', 'danger')
        return redirect(url_for('po_list'))
    po = _attach_created_by_name(po)
    return render_template('po_status.html', po=po)

# ============================================================
# PO UPDATE STATUS
# ============================================================
@app.route('/po/<int:po_id>/status', methods=['POST'])
@login_required
def po_update_status(po_id):
    role = session.get('role')
    new_status = request.form.get('status')
    note = request.form.get('note', '')

    allowed = {
        'staff': ['pending', 'revision'],
        'manager': ['pending', 'approved', 'rejected', 'completed'],
        'admin': ['draft', 'pending', 'approved', 'rejected', 'completed', 'revision']
    }

    if new_status not in allowed.get(role, []):
        flash('Anda tidak diizinkan mengubah ke status ini.', 'danger')
        return redirect(url_for('po_detail', po_id=po_id))

    po = db.get_by_id('purchase_orders', po_id)
    if not po:
        flash('PO tidak ditemukan.', 'danger')
        return redirect(url_for('po_list'))

    old_status = po['status']
    db.update('purchase_orders', po_id, {
        'status': new_status,
        'updated_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    })
    save_history(po_id, old_status, new_status, note)
    flash('Status PO berhasil diperbarui.', 'success')
    return redirect(url_for('po_detail', po_id=po_id))

# ============================================================
# PO DELETE
# ============================================================
@app.route('/po/<int:po_id>/delete', methods=['POST'])
@login_required
@role_required('admin', 'manager')
def po_delete(po_id):
    db.delete_many('po_history', lambda h: h['po_id'] == po_id)
    db.delete_many('po_items', lambda i: i['po_id'] == po_id)
    db.delete('purchase_orders', po_id)
    flash('PO berhasil dihapus.', 'success')
    return redirect(url_for('po_list'))

# ============================================================
# PO PRINT
# ============================================================
@app.route('/po/<int:po_id>/print')
@login_required
def po_print(po_id):
    po = db.get_by_id('purchase_orders', po_id)
    if not po:
        flash('PO tidak ditemukan.', 'danger')
        return redirect(url_for('po_list'))
    po = _attach_created_by_name(po)

    items = db.find('po_items', lambda i: i['po_id'] == po_id)

    items_subtotal = sum(float(item['subtotal']) for item in items)
    calc = hitung_total(items_subtotal, po['discount'] or 0)

    return render_template('po_print.html', po=po, items=items,
                           calc=calc, now=datetime.datetime.now())

# ============================================================
# SEND EMAIL
# ============================================================
@app.route('/po/<int:po_id>/send-email', methods=['POST'])
@login_required
@role_required('admin', 'manager', 'staff', 'direktur')
def po_send_email(po_id):
    email_to = request.form.get('email_to', '').strip()
    subject = request.form.get('subject', '').strip()
    body = request.form.get('body', '').strip()

    if not email_to:
        flash('Email tujuan tidak boleh kosong.', 'danger')
        return redirect(url_for('po_print', po_id=po_id))

    po = db.get_by_id('purchase_orders', po_id)
    if not po:
        flash('PO tidak ditemukan.', 'danger')
        return redirect(url_for('po_list'))
    po = _attach_created_by_name(po)

    try:
        msg = MIMEMultipart()
        msg['From'] = SMTP_USER
        msg['To'] = email_to
        msg['Subject'] = subject

        msg.attach(MIMEText(body, 'plain'))

        # Buat attachment sederhana (text)
        pdf_content = (
            f"Purchase Order {po['po_number']}\n"
            f"Customer: {po['vendor_name']}\n"
            f"Perusahaan: {po['customer_company']}\n"
            f"Total: Rp {po['total_amount']:,.0f}\n\n"
            f"Detail lengkap: {url_for('po_detail', po_id=po_id, _external=True)}"
        ).encode('utf-8')

        part = MIMEBase('application', 'octet-stream')
        part.set_payload(pdf_content)
        encoders.encode_base64(part)
        part.add_header('Content-Disposition',
                        f'attachment; filename="{po["po_number"]}.txt"')
        msg.attach(part)

        server = smtplib.SMTP(SMTP_HOST, SMTP_PORT)
        if SMTP_USE_TLS:
            server.starttls()
        server.login(SMTP_USER, SMTP_PASSWORD)
        server.sendmail(SMTP_USER, email_to, msg.as_string())
        server.quit()

        flash(f'Email berhasil dikirim ke {email_to}.', 'success')

    except Exception as e:
        flash(f'Gagal mengirim email: {str(e)}', 'danger')

    return redirect(url_for('po_print', po_id=po_id))

# ============================================================
# RIWAYAT PO
# ============================================================
@app.route('/riwayat')
@login_required
def po_riwayat():
    search = request.args.get('search', '')

    all_history = db.all('po_history')
    po_by_id = {p['id']: p for p in db.all('purchase_orders')}

    enriched = []
    for h in all_history:
        po = po_by_id.get(h.get('po_id'))
        row = _hydrate_dates(h, 'po_history')
        row['po_number'] = po.get('po_number') if po else None
        row['vendor_name'] = po.get('vendor_name') if po else None
        row['customer_company'] = po.get('customer_company') if po else None
        row['current_status'] = po.get('status') if po else None
        row['total_amount'] = po.get('total_amount') if po else None
        row['changed_by_name'] = _username_by_id(h.get('changed_by'))
        enriched.append(row)

    if search:
        s = search.lower()
        enriched = [
            r for r in enriched
            if s in (r.get('po_number') or '').lower()
            or s in (r.get('vendor_name') or '').lower()
            or s in (r.get('changed_by_name') or '').lower()
        ]

    enriched = sorted(enriched, key=lambda r: _parse_dt(r.get('changed_at')) or datetime.datetime.min, reverse=True)
    histories = enriched[:100]
    total_log = len(all_history)

    return render_template('po_riwayat.html', histories=histories,
                           search=search, total_log=total_log)

# ============================================================
# USER MANAGEMENT
# ============================================================
@app.route('/users')
@login_required
@role_required('admin')
def user_list():
    users = db.all('users')
    users = [_hydrate_dates(u, 'users') for u in users]
    users = sorted(users, key=lambda u: _parse_dt(u.get('created_at')) or datetime.datetime.min, reverse=True)

    total = len(users)
    active = sum(1 for u in users if u.get('is_active') in (1, True))
    admins = sum(1 for u in users if u.get('role') == 'admin')
    inactive = sum(1 for u in users if not u.get('is_active'))

    return render_template('user_management.html', users=users,
        total=total, active=active, admins=admins, inactive=inactive)

@app.route('/users/create', methods=['POST'])
@login_required
@role_required('admin')
def user_create():
    username = request.form['username']
    email = request.form['email']
    password = request.form['password']
    role = request.form['role']
    hashed = generate_password_hash(password)

    if db.find_one('users', lambda u: u['email'] == email):
        flash('Email sudah digunakan.', 'danger')
        return redirect(url_for('user_list'))

    db.insert('users', {
        'username': username,
        'email': email,
        'password': hashed,
        'role': role,
        'is_active': 1,
        'created_at': datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'updated_at': None,
    })
    flash(f'Pengguna {username} berhasil ditambahkan.', 'success')
    return redirect(url_for('user_list'))

@app.route('/users/<int:user_id>/toggle', methods=['POST'])
@login_required
@role_required('admin')
def user_toggle(user_id):
    user = db.get_by_id('users', user_id)
    if user:
        db.update('users', user_id, {'is_active': 0 if user.get('is_active') else 1})
    flash('Status pengguna diperbarui.', 'success')
    return redirect(url_for('user_list'))

@app.route('/users/<int:user_id>/delete', methods=['POST'])
@login_required
@role_required('admin')
def user_delete(user_id):
    if user_id == session['user_id']:
        flash('Tidak bisa menghapus akun sendiri.', 'danger')
        return redirect(url_for('user_list'))
    db.delete('users', user_id)
    flash('Pengguna berhasil dihapus.', 'success')
    return redirect(url_for('user_list'))

# ============================================================
# MAIN
# ============================================================
if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    debug = os.environ.get('FLASK_DEBUG', 'false').lower() == 'true'
    app.run(debug=debug, host='0.0.0.0', port=port)
