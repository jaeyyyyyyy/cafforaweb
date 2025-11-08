   /* =========================
   Caffora Front JS (DB-integrated) — CLEAN URL
   ========================= */

/* ===== Base paths & endpoints (CLEAN) ===== */
const ORIGIN = window.location.origin;

// kandidat fallback menu statis (jika API gagal)
const MENU_JSON_CANDIDATES = [
  "/menu.json",
  "/assets/menu.json",
  "/public/menu.json",      // legacy fallback
  "/caffora-app1/public/menu.json", // legacy dev
];

// API endpoint (BACKEND, via clean route; ada fallback ke legacy)
const MENU_API         = "/api/menu";
const AUTH_STATUS_MAIN = "/auth/status";               // kalau sudah di-route di server
const AUTH_STATUS_FALL = "/backend/auth_status.php";   // fallback legacy (kalau masih ada)
const AUTH_STATUS_URL  = AUTH_STATUS_MAIN;

const LOGIN_URL = "/login";

/* ===== State ===== */
let RAW_MENU = [];
let CURRENT_FILTER = "all";
let CURRENT_QUERY = "";

// default limit: 12 desktop / 4 mobile
function getInitialLimit() {
  return matchMedia("(min-width: 992px)").matches ? 12 : 4;
}
let INITIAL_LIMIT = getInitialLimit();
let CURRENT_LIMIT = INITIAL_LIMIT;

// Kunci “Tampilkan Semua”
let FORCE_SHOW_ALL = false;

// Auth cache
let AUTH_OK = null;
let AUTH_CHECK_AT = 0;
const AUTH_TTL_MS = 60_000;

/* ===== Helpers ===== */
function escapeHtml(s) {
  return (s ?? "").replace(
    /[&<>"']/g,
    (m) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m])
  );
}
function ensureToastHost() {
  if (!document.getElementById("toastHost")) {
    const host = document.createElement("div");
    host.id = "toastHost";
    host.style.position = "fixed";
    host.style.zIndex = "1080";
    host.style.right = "16px";
    host.style.bottom = "16px";
    document.body.appendChild(host);
  }
}
function showToastDark(msg) {
  ensureToastHost();
  const wrap = document.createElement("div");
  wrap.className = "toast align-items-center text-bg-dark border-0 show";
  wrap.setAttribute("role", "alert");
  wrap.style.minWidth = "280px";
  wrap.style.boxShadow = "0 8px 24px rgba(0,0,0,.12)";
  wrap.innerHTML = `<div class="d-flex">
    <div class="toast-body">${msg}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
  </div>`;
  document.getElementById("toastHost").appendChild(wrap);
  setTimeout(() => {
    wrap.classList.remove("show");
    setTimeout(() => wrap.remove(), 300);
  }, 1800);
}

/* Format Rupiah: 12000 -> "Rp 12.000" */
const fmtIDR = (v) =>
  "Rp " +
  new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(
    Number(v || 0)
  );

/* ===== API & JSON fetch ===== */
async function fetchFromAPI(params = {}) {
  const url = new URL(MENU_API, ORIGIN);
  url.searchParams.set("status", params.status || "Ready");
  if (params.q) url.searchParams.set("q", params.q);
  if (params.category) url.searchParams.set("category", params.category);

  let res = await fetch(url.toString(), {
    cache: "no-store",
    credentials: "same-origin",
  });

  // jika clean route belum tersedia (404/403), coba legacy path otomatis
  if (!res.ok && res.status !== 200) {
    const legacy = new URL("/backend/api/menu.php", ORIGIN);
    legacy.search = url.search;
    res = await fetch(legacy.toString(), {
      cache: "no-store",
      credentials: "same-origin",
    });
  }

  if (!res.ok) throw new Error("API menu tidak tersedia");
  const data = await res.json();
  return Array.isArray(data?.items) ? data.items : [];
}

async function fetchJSON(cands) {
  for (const u of cands) {
    try {
      const res = await fetch(u, { cache: "no-store" });
      if (res.ok) return await res.json();
    } catch {}
  }
  throw new Error("menu.json tidak ditemukan.");
}

/* ===== Normalisasi item ===== */
function normalizeItem(d, i = 0) {
  const cat = String(d.category || "").toLowerCase();
  const priceInt = Number(d.price_int ?? d.price ?? 0) || 0;

  let img = d.image_url || d.image || d.img || "";
  if (!img) {
    img =
      "https://picsum.photos/seed/caffora" +
      Math.random().toString(36).slice(2, 6) +
      "/600/600";
  } else if (!/^https?:\/\//i.test(img) && !img.startsWith("data:")) {
    // RELATIF → jadikan absolut dari root (tanpa memaksa /public)
    img = "/" + img.replace(/^\/+/, "");
  }

  return {
    id:
      d.id ??
      (d.name ? d.name.toLowerCase().replace(/\s+/g, "-") + "-" + i : i),
    name: d.name || "Menu",
    price: priceInt,
    price_int: priceInt,
    category: cat,
    image: img,
  };
}

/* ===== Card renderer ===== */
function cardHTML(item) {
  const priceStr = fmtIDR(item.price);
  return `
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card-menu h-100">
      <img class="thumb" src="${item.image}" alt="${escapeHtml(
        item.name
      )}" loading="lazy" style="aspect-ratio:1/1;object-fit:cover;">
      <div class="title">${escapeHtml(item.name)}</div>
      <div class="price">${priceStr}</div>
      <button class="btn-add" aria-label="Tambah ke keranjang" data-id="${
        item.id
      }">
        <span class="plus">+</span>
      </button>
    </div>
  </div>`;
}

/* ===== Auth ===== */
async function checkAuth(force = false) {
  const now = Date.now();
  if (!force && AUTH_OK !== null && now - AUTH_CHECK_AT < AUTH_TTL_MS)
    return AUTH_OK;

  async function hit(url) {
    const r = await fetch(url, { credentials: "same-origin", cache: "no-store" });
    return r.ok ? (await r.json().catch(() => ({}))) : {};
  }

  try {
    let js = await hit(AUTH_STATUS_MAIN);
    // fallback ke legacy bila clean route belum ada/403
    if (!("logged_in" in js)) js = await hit(AUTH_STATUS_FALL);

    AUTH_OK = !!js.logged_in;
  } catch {
    AUTH_OK = false;
  }
  AUTH_CHECK_AT = now;
  return AUTH_OK;
}

/* ===== Cart ===== */
function updateCartBadge() {
  const badge = document.getElementById("cartBadge");
  if (!badge) return;
  const cart = JSON.parse(localStorage.getItem("caffora_cart") || "[]");
  const total = cart.reduce((a, c) => a + (c.qty || 0), 0);
  badge.style.display = total > 0 ? "inline-block" : "none";
  if (total > 0) badge.textContent = String(total);
}
async function addToCart(id) {
  const ok = await checkAuth();
  if (!ok) {
    showToastDark("Silakan login terlebih dahulu.");
    // bisa arahkan ke halaman login bersih
    setTimeout(() => (window.location.href = LOGIN_URL), 600);
    return;
  }
  const item = RAW_MENU.find((m) => String(m.id) === String(id));
  if (!item) return;
  const key = "caffora_cart";
  const cart = JSON.parse(localStorage.getItem(key) || "[]");
  const i = cart.findIndex((c) => String(c.id) === String(id));
  if (i > -1) cart[i].qty += 1;
  else cart.push({ id: item.id, name: item.name, price: item.price, qty: 1 });
  localStorage.setItem(key, JSON.stringify(cart));
  updateCartBadge();
  showToastDark("Ditambahkan ke keranjang.");
}

/* ===== Filter/Search/Render ===== */
function filteredData() {
  let data = [...RAW_MENU];
  if (["pastry", "drink", "food"].includes(CURRENT_FILTER)) {
    data = data.filter(
      (d) => (d.category || "").toLowerCase() === CURRENT_FILTER
    );
  }
  if (CURRENT_FILTER === "cheap")
    data.sort((a, b) => (a.price || 0) - (b.price || 0));
  else if (CURRENT_FILTER === "expensive")
    data.sort((a, b) => (b.price || 0) - (a.price || 0));

  const q = (CURRENT_QUERY || "").trim().toLowerCase();
  if (q) {
    data = data.filter(
      (d) =>
        (d.name || "").toLowerCase().includes(q) ||
        (d.category || "").toLowerCase().includes(q)
    );
  }
  return data;
}
function renderMenu() {
  const list = document.getElementById("list");
  if (!list) return;
  const data = filteredData();

  // tampilkan semua jika: FORCE_SHOW_ALL, atau sedang search, atau filter bukan "all"
  const showAll = FORCE_SHOW_ALL || !!CURRENT_QUERY || CURRENT_FILTER !== "all";
  const slice = showAll ? data : data.slice(0, CURRENT_LIMIT);

  list.innerHTML = slice.length
    ? slice.map(cardHTML).join("")
    : '<div class="text-center text-muted py-4">Menu tidak ditemukan.</div>';

  // bind tombol +
  list.querySelectorAll(".btn-add").forEach((btn) => {
    btn.addEventListener(
      "click",
      (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        const id = btn.getAttribute("data-id");
        addToCart(id);
      },
      { passive: false }
    );
  });
}

/* ===== Init (guarded once) ===== */
async function initPage() {
  if (window.__CAFFORA_INIT_ONCE__) return;
  window.__CAFFORA_INIT_ONCE__ = true;

  await checkAuth().catch(() => {});
  updateCartBadge();

  // Ambil menu
  try {
    const apiItems = await fetchFromAPI({});
    RAW_MENU = apiItems.length ? apiItems.map((d, i) => normalizeItem(d, i)) : [];
  } catch (e) {
    try {
      const raw = await fetchJSON(MENU_JSON_CANDIDATES);
      RAW_MENU = (raw || []).map((d, i) => normalizeItem(d, i));
    } catch (e2) {
      console.error("Gagal memuat data menu:", e.message, e2.message);
      RAW_MENU = [];
    }
  }

  renderMenu();

  // Search
  const q = document.getElementById("q");
  const btnCari = document.getElementById("btnCari");
  if (q) {
    q.addEventListener("keydown", (ev) => {
      if (ev.key === "Enter") {
        CURRENT_QUERY = q.value || "";
        FORCE_SHOW_ALL = false;
        renderMenu();
      }
    });
  }
  if (btnCari) {
    btnCari.addEventListener("click", () => {
      CURRENT_QUERY = q?.value || "";
      FORCE_SHOW_ALL = false;
      renderMenu();
    });
  }

  // Filter
  const filterMenu = document.getElementById("filterMenu");
  if (filterMenu) {
    filterMenu.querySelectorAll("[data-filter]").forEach((a) => {
      a.addEventListener("click", (ev) => {
        ev.preventDefault();
        const picked = a.getAttribute("data-filter") || "all";
        if (picked === "all") {
          CURRENT_FILTER = "all";
          CURRENT_QUERY = "";
          FORCE_SHOW_ALL = true;
          const qEl = document.getElementById("q");
          if (qEl) qEl.value = "";
          renderMenu();
          document
            .getElementById("menuSection")
            ?.scrollIntoView({ behavior: "smooth", block: "start" });
        } else {
          CURRENT_FILTER = picked;
          FORCE_SHOW_ALL = true;
          renderMenu();
        }
      });
    });
  }

  // Responsif (hanya untuk homepage tanpa filter/search/show-all)
  const mql = matchMedia("(min-width: 992px)");
  const onChange = () => {
    const newLimit = mql.matches ? 12 : 4;
    if (
      newLimit !== INITIAL_LIMIT &&
      !CURRENT_QUERY &&
      CURRENT_FILTER === "all" &&
      !FORCE_SHOW_ALL
    ) {
      INITIAL_LIMIT = newLimit;
      CURRENT_LIMIT = INITIAL_LIMIT;
      renderMenu();
    }
  };
  if (typeof mql.addEventListener === "function")
    mql.addEventListener("change", onChange);
  else if (typeof mql.addListener === "function") mql.addListener(onChange);

  // Jika index root → kosongkan keranjang
  if (window.location.pathname.endsWith("index.html") || window.location.pathname === "/") {
    try { localStorage.removeItem("caffora_cart"); } catch {}
    updateCartBadge();
  }
}

document.addEventListener("DOMContentLoaded", initPage);
