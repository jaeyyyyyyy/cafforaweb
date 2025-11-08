<?php    
// public/customer/checkout.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']); // wajib login

$nameDefault = $_SESSION['user_name'] ?? '';
$userId      = (int)($_SESSION['user_id']  ?? 0);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Checkout — Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --yellow: #FFD54F;
      --gold:   #FFD54F;
      --gold-soft: #F6D472;
      --brown:  #4B3F36;
      --line:   #e9e3dc;
      --bg:     #fffdf8;
      --card:   #fff;
      --input-border: #E8E2DA;
    }
    * {
      font-family: Poppins, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      box-sizing: border-box;
    }
    body {
      background: var(--bg);
      color: var(--brown);
      overflow-x: hidden;
      margin: 0;
    }
    .topbar{
      background:#fff;
      border-bottom:1px solid rgba(0,0,0,.05);
      position:sticky;
      top:0;
      z-index:20;
    }
    .topbar-inner{
      max-width:1200px;
      margin:0 auto;
      padding:12px 24px;
      min-height:52px;
      display:flex;
      align-items:center;
      gap:10px;
    }
    .back-link{
      display:inline-flex;
      align-items:center;
      gap:10px;
      color:var(--brown);
      text-decoration:none;
      font-weight:600;
      font-size:1rem;
      line-height:1.3;
      cursor:pointer;
    }
    .back-link i{ font-size:1.1rem; }

    .page {
      max-width: 1200px;
      margin: 14px auto 32px;
      padding: 0 24px;
    }
    .item-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 0;
      border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .left-info { display: flex; gap: 12px; align-items: center; flex: 1; min-width: 0; }
    .thumb { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; background: #f3f3f3; flex-shrink: 0; }
    .name { font-weight: 600; font-size: 1rem; line-height: 1.3; color: #2b2b2b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .line-right { flex-shrink: 0; font-weight: 600; font-size: .95rem; color: var(--brown); text-align: right; min-width: 90px; }

    .tot-block{ margin-top: 10px; border-top: 2px dashed rgba(0,0,0,.05); padding-top: 10px; }
    .tot-line{ display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:.93rem; color:#4b3f36; }
    .tot-line strong{ font-weight:600; }
    .tot-grand{ display:flex; justify-content:space-between; align-items:center; margin-top:6px; font-weight:700; font-size:1rem; color:#2b2b2b; }

    .form-label { font-weight: 600; font-size: 1rem; line-height: 1.3; color: #2b2b2b; margin-bottom: 6px; }
    .form-control {
      width: 100%;
      max-width: 100%;
      border-radius: 14px !important;
      padding: 8px 14px;
      font-size: .95rem;
      line-height: 1.3;
      border: 1px solid var(--input-border);
      background-color: #fff;
      transition: border-color .12s ease;
      box-shadow: none;
    }
    .form-control:focus { border-color: var(--gold-soft) !important; box-shadow: none !important; }
    .cf-select { position: relative; width: 100%; }
    .cf-select__trigger {
      width: 100%;
      background: #fff;
      border: 1px solid var(--input-border);
      border-radius: 14px;
      padding: 8px 38px 8px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      cursor: pointer;
      transition: border-color .12s ease;
    }
    .cf-select__trigger:focus-visible,
    .cf-select.is-open .cf-select__trigger { border-color: var(--gold-soft); outline: none; }
    .cf-select__text { font-size: .95rem; color: #2b2b2b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cf-select__icon { flex: 0 0 auto; color: var(--brown); font-size: .9rem; }
    .cf-select__list {
      position: absolute; left: 0; top: calc(100% + 6px); width: 100%;
      background: #fff; border: 1px solid rgba(0,0,0,.02); border-radius: 14px;
      box-shadow: 0 16px 30px rgba(0,0,0,.09);
      overflow: hidden; z-index: 40; display: none; max-height: 260px; overflow-y: auto;
    }
    .cf-select.is-open .cf-select__list { display: block; }
    .cf-select__option { padding: 9px 14px; font-size: .9rem; color: #413731; cursor: pointer; background: #fff; }
    .cf-select__option:hover { background: #FFF2C9; }
    .cf-select__option.is-active { background: #FFEB9B; font-weight: 600; }

    .btn-primary-cf {
      background-color: var(--gold);
      color: var(--brown) !important;
      border: 0;
      border-radius: 14px;
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 600;
      font-size: .88rem;
      padding: 10px 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      white-space: nowrap;
      box-shadow: none;
    }

    .form-line-2 { display: block; gap: 18px; }
    .form-line-2 .flex-1 { width: 100%; }

    @media (min-width: 992px) {
      .form-line-2 { display: flex; gap: 18px; align-items: flex-start; }
      .form-line-2 .flex-1 { flex: 1 1 0; }
      .page { padding-bottom: 80px; }
      #checkoutForm { padding-bottom: 60px; }
    }

    @media (max-width: 600px) {
      .topbar-inner, .page { max-width: 100%; padding: 12px 16px; }
      .page{ margin: 10px auto 90px; }
    }

    @keyframes spin { from{ transform:rotate(0) } to{ transform:rotate(360deg) } }
  </style>
</head>
<body>

  <!-- TOP BAR -->
  <div class="topbar">
    <div class="topbar-inner">
      <a class="back-link" href="./cart.php">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <main class="page">
    <!-- RINGKASAN / RIWAYAT CART YG DIPILIH -->
    <div id="summary"></div>

    <!-- FORM -->
    <form id="checkoutForm" class="mt-4 pb-4">
      <div class="mb-3 field-name">
        <label class="form-label">Nama Customer</label>
        <input
          type="text"
          class="form-control"
          id="customer_name"
          value="<?= htmlspecialchars($nameDefault) ?>"
          required
        >
      </div>

      <!-- 2 kolom -->
      <div class="form-line-2">
        <div class="flex-1">
          <div class="mb-3 field-service">
            <label class="form-label">Tipe Layanan</label>
            <div class="cf-select" data-target="service_type">
              <div class="cf-select__trigger" tabindex="0">
                <span class="cf-select__text" id="service_type_label">Dine In</span>
                <i class="bi bi-chevron-down cf-select__icon"></i>
              </div>
              <div class="cf-select__list">
                <div class="cf-select__option is-active" data-value="dine_in">Dine In</div>
                <div class="cf-select__option" data-value="take_away">Take Away</div>
              </div>
            </div>
            <input type="hidden" id="service_type" value="dine_in">
          </div>

          <div class="mb-3" id="tableWrap">
            <label class="form-label">Nomor Meja</label>
            <input type="text" class="form-control" id="table_no" placeholder="Misal: 05">
          </div>
        </div>

        <div class="mb-4 flex-1 field-payment">
          <label class="form-label">Metode Pembayaran</label>
          <div class="cf-select" data-target="payment_method">
            <div class="cf-select__trigger" tabindex="0">
              <span class="cf-select__text" id="payment_method_label">Cash</span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div class="cf-select__option is-active" data-value="cash">Cash</div>
              <div class="cf-select__option" data-value="bank_transfer">Bank Transfer</div>
              <div class="cf-select__option" data-value="qris">QRIS</div>
              <div class="cf-select__option" data-value="ewallet">E-Wallet</div>
            </div>
          </div>
          <input type="hidden" id="payment_method" value="cash">
        </div>
      </div>

      <div class="d-flex justify-content-end mb-3 field-submit">
        <button type="submit" class="btn-primary-cf">Buat Pesanan</button>
      </div>
    </form>
  </main>

  <script>
  (function(){
    // ====== FIX: BASE DINAMIS (tidak hardcode) ======
    const PUBLIC_SPLIT = '/public/';
    const idxBase = window.location.pathname.indexOf(PUBLIC_SPLIT);
    const APP_BASE = idxBase > -1 ? window.location.pathname.slice(0, idxBase) : '';

    const API_CREATE  = APP_BASE + '/backend/api/orders.php?action=create';
    const HISTORY_URL = APP_BASE + '/public/customer/history.php';

    const KEY_CART   = 'caffora_cart';
    const KEY_SELECT = 'caffora_cart_selected';
    const TAX_RATE   = 0.11;

    const $summary    = document.getElementById('summary');
    const $form       = document.getElementById('checkoutForm');
    const $serviceHid = document.getElementById('service_type');
    const $table      = document.getElementById('table_no');
    const $tableWrap  = document.getElementById('tableWrap');

    const USER_ID = <?= json_encode($userId, JSON_UNESCAPED_SLASHES) ?>;

    const rp  = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');
    const esc = s => String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

    const getCart = () => { try { return JSON.parse(localStorage.getItem(KEY_CART) || '[]'); } catch { return []; } };
    const setCart = items => localStorage.setItem(KEY_CART, JSON.stringify(items));
    const getSelectedIds = () => { try { return JSON.parse(localStorage.getItem(KEY_SELECT) || '[]'); } catch { return []; } };

    function renderSummary(){
      const cart   = getCart();
      const selIds = getSelectedIds().map(String);
      const items  = cart.filter(it => selIds.includes(String(it.id)));

      if (!items.length){
        $summary.innerHTML = '<div class="text-muted">Tidak ada item yang dipilih. Silakan kembali ke keranjang.</div>';
        return;
      }

      let subtotal = 0;
      let html = '';

      items.forEach(it => {
        const qty   = Number(it.qty)||0;
        const price = Number(it.price)||0;
        const img   = it.image || it.image_url || '';
        const lineT = qty * price;
        subtotal += lineT;
        html += `
          <div class="item-row">
            <div class="left-info">
              <img class="thumb" src="${img}" alt="${esc(it.name||'Menu')}">
              <div class="name">${esc(it.name||'')} x ${qty}</div>
            </div>
            <div class="line-right">${rp(lineT)}</div>
          </div>`;
      });

      const tax   = Math.round(subtotal * TAX_RATE);
      const total = subtotal + tax;

      html += `
        <div class="tot-block">
          <div class="tot-line"><span>Subtotal</span><span>${rp(subtotal)}</span></div>
          <div class="tot-line"><span>Pajak 11%</span><span>${rp(tax)}</span></div>
          <div class="tot-grand"><span>Total</span><span>${rp(total)}</span></div>
        </div>`;
      $summary.innerHTML = html;

      $summary.dataset.subtotal = subtotal;
      $summary.dataset.tax      = tax;
      $summary.dataset.total    = total;
    }
    renderSummary();

    (function initCfSelect(){
      const selects = document.querySelectorAll('.cf-select');
      const closeAll = () => selects.forEach(s=>s.classList.remove('is-open'));

      selects.forEach(sel => {
        const targetId = sel.dataset.target;
        const trigger  = sel.querySelector('.cf-select__trigger');
        const list     = sel.querySelector('.cf-select__list');
        const label    = sel.querySelector('.cf-select__text');

        trigger.addEventListener('click', (e)=>{
          e.stopPropagation();
          const isOpen = sel.classList.contains('is-open');
          closeAll();
          if (!isOpen) sel.classList.add('is-open');
        });

        list.querySelectorAll('.cf-select__option').forEach(opt => {
          opt.addEventListener('click', ()=>{
            const val  = opt.dataset.value;
            const text = opt.textContent.trim();
            label.textContent = text;
            const hid = document.getElementById(targetId);
            if (hid) hid.value = val;
            list.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active'));
            opt.classList.add('is-active');
            if (targetId === 'service_type') syncTableField(val);
            sel.classList.remove('is-open');
          });
        });
      });

      document.addEventListener('click', ()=>closeAll());
    })();

    function syncTableField(valNow){
      const v = valNow ?? $serviceHid.value;
      if (v === 'dine_in'){
        $tableWrap.style.display = '';
        $table.removeAttribute('disabled');
      } else {
        $tableWrap.style.display = 'none';
        $table.value = '';
        $table.setAttribute('disabled', 'disabled');
      }
    }
    syncTableField();

    function showBtnLoading(btn, on){
      if (on){
        btn.disabled = true;
        btn.dataset.text = btn.innerHTML;
        btn.innerHTML =
          '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-right-color:transparent;border-radius:50%;margin-right:8px;vertical-align:middle;animation:spin .7s linear infinite;"></span>Memproses...';
      } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.text || 'Buat Pesanan';
      }
    }

    $form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const btn = $form.querySelector('.btn-primary-cf');
      showBtnLoading(btn, true);

      const cart   = getCart();
      const selIds = getSelectedIds().map(String);
      const itemsSel = cart.filter(it => selIds.includes(String(it.id)));

      if (!itemsSel.length){
        alert('Tidak ada item yang dipilih.');
        showBtnLoading(btn, false);
        return;
      }

      const subtotal = Number($summary.dataset.subtotal || 0);
      const tax      = Number($summary.dataset.tax || 0);
      const grand    = Number($summary.dataset.total || 0);

      const payload = {
        user_id: USER_ID,
        customer_name: document.getElementById('customer_name').value.trim(),
        service_type:  document.getElementById('service_type').value,
        table_no:      ($table.value || '').trim() || null,
        payment_method:document.getElementById('payment_method').value,
        payment_status:'pending',
        subtotal: subtotal,
        tax_amount: tax,
        grand_total: grand,
        items: itemsSel.map(it => ({
          menu_id: Number(it.id),
          qty:     Number(it.qty)||0,
          price:   Number(it.price)||0
        }))
      };

      try{
        const res = await fetch(API_CREATE, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          credentials:'same-origin',
          body: JSON.stringify(payload)
        });

        // Robust parser: coba JSON, kalau gagal baca teks mentah
        let js;
        try { js = await res.json(); }
        catch {
          const txt = await res.text();
          throw new Error(txt ? txt.substring(0, 300) : 'Invalid JSON');
        }

        if (!res.ok || !js.ok) throw new Error(js.error || ('HTTP '+res.status));

        const remaining = cart.filter(it => !selIds.includes(String(it.id)));
        setCart(remaining);
        localStorage.removeItem(KEY_SELECT);

        alert('Pesanan berhasil dibuat! Invoice: ' + (js.invoice_no || ''));
        window.location.href = HISTORY_URL;
      } catch(err){
        alert('Checkout gagal: ' + (err?.message || err));
      } finally {
        showBtnLoading(btn, false);
      }
    });

  })();
  </script>
</body>
</html>
