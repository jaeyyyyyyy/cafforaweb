<?php
// public/karyawan/settings.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['karyawan']);
require_once __DIR__ . '/../../backend/config.php';

$BASE   = BASE_URL;
$userId = (int)($_SESSION['user_id'] ?? 0);
$name   = $_SESSION['user_name']  ?? 'Karyawan';
$email  = $_SESSION['user_email'] ?? '';
$phone  = $_SESSION['user_phone'] ?? '';
$avatar = $_SESSION['user_avatar'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Pengaturan Karyawan</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
  <style>
    :root{
      --ink:#222;
      --muted:#6b7280;
      --gold:#ffd54f;
      --brown:#4b3f36;
      --line:#f2f2f2;
      --bg:#fff;
    }
    *{ box-sizing:border-box; }
    html,body{
      margin:0;
      background:var(--bg);
      color:var(--ink);
      font:clamp(14px,1.1vw,16px)/1.5 "Poppins",system-ui,sans-serif;
    }

    /* ====== TOPBAR (sama gaya customer) ====== */
    .topbar{
      position:sticky;
      top:0;
      z-index:50;
      background:#fff;
      border-bottom:1px solid #efefef;
    }
    .topbar .inner{
      max-width:1200px;
      margin:0 auto;
      padding:12px 24px;
      display:flex;
      align-items:center;
      gap:8px;
      min-height:52px;
    }
        
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--brown);
  text-decoration: none;
  font-weight: 600;
  font-size: 16px;     
  line-height: 1.3;
}


.back-link .bi {
  font-size: 18px !important;  
  width: 18px;
  height: 18px;
  line-height: 1;              
  display: inline-flex;       
  align-items: center;
  justify-content: center;
}

    /* ====== KONTEN ====== */
    main{
      max-width:1200px;
      margin:0 auto;
      padding:14px 18px 50px;
    }

    /* ====== HEADER PROFIL ====== */
    .profile-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:18px 26px;
      margin:4px 0 10px;
    }
    .profile-left{
      display:flex;
      align-items:center;
      gap:18px;
      min-width:0;
    }
    .avatar{
      width:64px;
      height:64px;
      border-radius:50%;
      background:#eee center/cover no-repeat;
      flex-shrink:0;
    }
    .who{min-width:0;}
    .who .name{
      font-weight:600;
      font-size:1.02rem;
      margin-bottom:2px;
    }
    .who .mail{
      color:var(--muted);
      font-size: 0.8rem;
      word-break:break-word;
    }

    /* ====== LIST PENGATURAN ====== */
    .profile-box{
      background:transparent;
      border:0;
      border-radius:0;
      max-width:100%;
      margin-top:4px;
    }
    .row{
      display:flex;
      align-items:center;
      gap:12px;
      padding:16px 0;
      border-bottom:1px solid #f5f5f5;
    }
    .row:last-child{ border-bottom:none; }

    .label{
      font-weight:500;
      font-size:.95rem;
    }
    .value{
      margin-left:auto;
      margin-right:4px;
      color:var(--muted);
      font-size:.9rem;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
      max-width:55%;
      text-align:right;
    }
    .chev-btn{
      background:none;
      border:none;
      color:#9aa0a6;
      font-size:18px;
      line-height:1;
      cursor:pointer;
      padding:4px;
      margin-left:2px;
    }

    /* ====== BUTTON ====== */
    .btn{
      border:none;
      padding:10px 14px;
      border-radius:10px;
      font-weight:600;
      cursor:pointer;
      font-size:.95rem;
    }
    .btn.primary{
      background:var(--gold);
      color:var(--brown);
    }
    .btn.secondary{
      background:#f3f4f6;
      color:#333;
    }

    /* ====== BOTTOM SHEET ====== */
    .sheet[hidden]{display:none;}
    .sheet{
      position:fixed;
      inset:0;
      display:flex;
      align-items:flex-end;
      background:rgba(0,0,0,.35);
      z-index:50;
    }
    .panel{
      width:100%;
      background:#fff;
      border-top-left-radius:16px;
      border-top-right-radius:16px;
      padding:18px;
      max-height:75vh;
      overflow:auto;
    }
    .panel h3{
      margin:0 0 12px;
      font-size:1.05rem;
      font-weight:600;
    }
    .field{
      display:flex;
      flex-direction:column;
      margin-bottom:12px;
    }
    label{
      font-size:.9rem;
      color:#555;
      margin-bottom:6px;
    }
    input.text{
      padding:11px;
      border:1px solid #ddd;
      border-radius:8px;
      font:inherit;
      width:100%;
      outline:none;
      box-shadow:none;
    }
    input.text:focus,
    input.text:focus-visible{
      outline:none !important;
      box-shadow:none !important;
      border-color:#ddd !important;
    }
    .actions{
      display:flex;
      justify-content:flex-end;
      gap:10px;
      margin-top:12px;
    }

    /* desktop sheet */
    @media (min-width: 992px){
      .sheet{align-items:center;justify-content:center;}
      .panel{max-width:520px;border-radius:12px;}
    }

    /* RESPONSIVE */
    @media (max-width: 700px){
      .topbar .inner,
      main{
        max-width:100%;
        padding:10px 14px;
      }
      .profile-head{
        flex-wrap:wrap;
        margin-bottom:6px;
      }
      .row{
        padding:14px 0;
      }
      .value{ max-width:50%; }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="inner">
      <a class="back-link" id="goBack">
        <i class="bi bi-arrow-left chev"></i>
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <main>
    <?php
      // kalau avatar disimpan /public/... maka gabungin dengan BASE
      $raw = $avatar ?: '/public/assets/img/avatar-placeholder.png';
      $avatarUrl = str_starts_with($raw, '/public/') ? $BASE . $raw : $raw;
    ?>
    <section class="profile-head">
      <div class="profile-left">
        <div id="avatar" class="avatar" style="background-image:url('<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>')"></div>
        <div class="who">
          <div class="name" id="whoName"><?= htmlspecialchars($name) ?></div>
          <div class="mail"><?= htmlspecialchars($email) ?></div>
        </div>
      </div>
      <button class="btn primary" id="btnPhoto">Upload</button>
      <input type="file" id="fileAvatar" accept="image/png,image/jpeg" hidden>
    </section>

    <section class="profile-box" aria-label="Akun karyawan">
      <div class="row">
        <span class="label">Nama</span>
        <span class="value" id="valName"><?= htmlspecialchars($name) ?></span>
        <button class="chev-btn" data-open="sheetName">›</button>
      </div>
      <div class="row">
        <span class="label">No. HP</span>
        <span class="value" id="valPhone"><?= $phone ? htmlspecialchars($phone) : 'Belum diisi' ?></span>
        <button class="chev-btn" data-open="sheetPhone">›</button>
      </div>
      <div class="row">
        <span class="label">Ganti Password</span>
        <span class="value">Keamanan akun</span>
        <button class="chev-btn" data-open="sheetPassword">›</button>
      </div>
    </section>
  </main>

  <!-- sheet: nama -->
  <div class="sheet" id="sheetName" hidden>
    <div class="panel">
      <h3>Ubah Nama</h3>
      <div class="field">
        <label for="name">Nama lengkap</label>
        <input id="name" class="text" type="text" value="<?= htmlspecialchars($name) ?>">
      </div>
      <div class="actions">
        <button class="btn secondary" data-close>Batalkan</button>
        <button class="btn primary" id="saveName">Simpan</button>
      </div>
    </div>
  </div>

  <!-- sheet: no hp -->
  <div class="sheet" id="sheetPhone" hidden>
    <div class="panel">
      <h3>Ubah No. HP</h3>
      <div class="field">
        <label for="phone">Nomor HP</label>
        <input id="phone" class="text" type="tel" placeholder="08xxxxxxxxxx"
               value="<?= htmlspecialchars($phone) ?>" autocomplete="tel">
      </div>
      <div class="actions">
        <button class="btn secondary" data-close>Batalkan</button>
        <button class="btn primary" id="savePhone">Simpan</button>
      </div>
    </div>
  </div>

  <!-- sheet: password -->
  <div class="sheet" id="sheetPassword" hidden>
    <div class="panel">
      <h3>Ganti Password</h3>
      <div class="field">
        <label for="oldpass">Password Lama</label>
        <input id="oldpass" class="text" type="password" placeholder="••••••">
      </div>
      <div class="field">
        <label for="newpass">Password Baru</label>
        <input id="newpass" class="text" type="password" placeholder="Minimal 6 karakter">
      </div>
      <div class="field">
        <label for="confpass">Konfirmasi Password</label>
        <input id="confpass" class="text" type="password" placeholder="Ulangi password baru">
      </div>
      <div class="actions">
        <button class="btn secondary" data-close>Batalkan</button>
        <button class="btn primary" id="savePass">Perbarui</button>
      </div>
    </div>
  </div>

  <script>
    const BASE = '<?= $BASE ?>';
    // kita pakai endpoint yang sama kayak customer
    const API  = BASE + '/backend/api/profile_update.php';

    // back ke dashboard karyawan
    document.getElementById('goBack').onclick = () => {
      location.href = BASE + '/public/karyawan/index.php';
    };

    // buka / tutup sheet
    document.querySelectorAll('[data-open]').forEach(btn => {
      btn.onclick = () => document.getElementById(btn.dataset.open).hidden = false;
    });
    document.querySelectorAll('.sheet').forEach(s => {
      s.addEventListener('click', e => { if (e.target === s) s.hidden = true; });
      s.querySelectorAll('[data-close]').forEach(c => c.onclick = () => s.hidden = true);
    });

    async function safeJson(res) {
      try {
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) return { success:false, message:'Respon tidak valid dari server.' };
        return await res.json();
      } catch {
        return { success:false, message:'Respon tidak valid dari server.' };
      }
    }

    // upload foto
    const fileInput = document.getElementById('fileAvatar');
    const btnUpload = document.getElementById('btnPhoto');
    const avatarEl  = document.getElementById('avatar');

    btnUpload.onclick = () => fileInput.click();

    fileInput.onchange = async () => {
      const file = fileInput.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) return alert('Ukuran maksimum 2MB');

      const fd = new FormData();
      fd.append('profile_picture', file);

      const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
      const js  = await safeJson(res);
      if (js.success) {
        const path = js.data?.profile_picture;
        if (path) {
          const finalUrl = path.startsWith('http')
            ? path
            : (BASE + (path.startsWith('/') ? path : '/' + path));
          avatarEl.style.backgroundImage = `url('${finalUrl}')`;
        }
        alert(js.message || 'Foto profil berhasil diperbarui');
      } else {
        alert(js.message || 'Gagal memperbarui foto profil');
      }
    };

    // simpan nama
    document.getElementById('saveName').onclick = async () => {
      const name = document.getElementById('name').value.trim();
      if (!name) return alert('Nama tidak boleh kosong');

      const fd = new FormData();
      fd.append('name', name);

      const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
      const js  = await safeJson(res);

      if (js.success) {
        document.getElementById('valName').textContent = name;
        document.getElementById('whoName').textContent = name;
        document.getElementById('sheetName').hidden = true;
      } else alert(js.message || 'Gagal memperbarui nama');
    };

    // simpan no hp
    document.getElementById('savePhone').onclick = async () => {
      const phone = document.getElementById('phone').value.trim();

      const fd = new FormData();
      fd.append('phone', phone);

      const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
      const js  = await safeJson(res);

      if (js.success) {
        document.getElementById('valPhone').textContent = phone || 'Belum diisi';
        document.getElementById('sheetPhone').hidden = true;
      } else alert(js.message || 'Gagal memperbarui nomor HP');
    };

    // simpan password
    document.getElementById('savePass').onclick = async () => {
      const oldpass  = document.getElementById('oldpass').value;
      const newpass  = document.getElementById('newpass').value;
      const confpass = document.getElementById('confpass').value;

      if (!oldpass || !newpass) return alert('Isi semua kolom password');
      if (newpass.length < 6) return alert('Password minimal 6 karakter');
      if (newpass !== confpass) return alert('Konfirmasi tidak cocok');

      const fd = new FormData();
      fd.append('old_password', oldpass);
      fd.append('password', newpass);

      const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
      const js  = await safeJson(res);

      if (js.success) {
        alert(js.message || 'Password berhasil diperbarui');
        document.getElementById('sheetPassword').hidden = true;
      } else alert(js.message || 'Gagal memperbarui password');
    };
  </script>
</body>
</html>
