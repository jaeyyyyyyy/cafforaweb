/* =========================
   Caffora Login JS (revised)
   ========================= */

document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const email = document.getElementById("identity").value.trim();
  const password = document.getElementById("password").value;

  if (!email || !password) {
    showToast("Email dan password wajib diisi.");
    return;
  }

  try {
    const res = await fetch("../backend/login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
      credentials: "include", // penting: supaya cookie caffora_auth terset
    });

    const data = await res.json();

    if (data.status === "success") {
      // simpan flag login di localStorage
      localStorage.setItem("caffora_auth", "1");
      localStorage.setItem("authUser", JSON.stringify(data.user || {}));

      showToast("Login berhasil ✅");
      setTimeout(() => {
        window.location.href = "customer/index.php";
      }, 800);
    } else {
      showToast(data.message || "Login gagal ❌");
    }
  } catch (err) {
    console.error("Login error:", err);
    showToast("Terjadi kesalahan koneksi.");
  }
});

/* ===== Toggle password ===== */
document.querySelector(".toggle").addEventListener("click", () => {
  const pass = document.getElementById("password");
  const icon = document.getElementById("eyeIcon");
  if (pass.type === "password") {
    pass.type = "text";
    icon.classList.replace("bi-eye", "bi-eye-slash");
  } else {
    pass.type = "password";
    icon.classList.replace("bi-eye-slash", "bi-eye");
  }
});

/* ===== Toast helper (mini) ===== */
function ensureToastHost() {
  if (document.getElementById("toastHost")) return;
  const host = document.createElement("div");
  host.id = "toastHost";
  host.style.position = "fixed";
  host.style.zIndex = "1080";
  host.style.right = "16px";
  host.style.bottom = "16px";
  document.body.appendChild(host);
}

function showToast(msg) {
  ensureToastHost();
  const wrap = document.createElement("div");
  wrap.className = "toast align-items-center text-bg-light border-0 show";
  wrap.setAttribute("role", "alert");
  wrap.style.minWidth = "280px";
  wrap.style.boxShadow = "0 8px 24px rgba(0,0,0,.12)";
  wrap.innerHTML =
    '<div class="d-flex">' +
    '<div class="toast-body">' +
    msg +
    "</div>" +
    '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
    "</div>";

  document.getElementById("toastHost").appendChild(wrap);
  setTimeout(() => {
    wrap.classList.remove("show");
    wrap.addEventListener("transitionend", () => wrap.remove(), { once: true });
    setTimeout(() => wrap.remove(), 300);
  }, 2500);
}
