// public/assets/js/customer.js
(function () {
  const listEl = document.getElementById("catalog-list");
  const qEl = document.getElementById("q");
  const btnCari = document.getElementById("btnCari");

  let MENUS = [];

  function rupiah(n) {
    return "Rp " + (n || 0).toLocaleString("id-ID");
  }

  function render(items) {
    if (!listEl) return;
    listEl.innerHTML = "";

    if (!items || items.length === 0) {
      listEl.innerHTML = (
        <div class="col-12 text-center text-muted">
          Tidak ada menu ditemukan.
        </div>
      );
      return;
    }

    items.forEach((m) => {
      const col = document.createElement("div");
      col.className = "col-6 col-md-4 col-lg-3";

      col.innerHTML = `
        <div class="card h-100 shadow-sm">
          <img src="${
            m.image || "../assets/img/placeholder.png"
          }" class="card-img-top" alt="${m.name}">
          <div class="card-body text-center">
            <h6 class="card-title">${m.name}</h6>
            <p class="price mb-2">${rupiah(m.price)}</p>
            <button class="btn btn-primary btn-sm" data-id="${
              m.id
            }">Tambah</button>
          </div>
        </div>
      `;
      listEl.appendChild(col);
    });
  }

  function search() {
    const q = (qEl?.value || "").toLowerCase();
    if (!q) return render(MENUS);
    const filtered = MENUS.filter(
      (m) =>
        (m.name || "").toLowerCase().includes(q) ||
        (m.category || "").toLowerCase().includes(q)
    );
    render(filtered);
  }

  // events
  if (btnCari) btnCari.addEventListener("click", search);
  if (qEl)
    qEl.addEventListener("keydown", (e) => {
      if (e.key === "Enter") search();
    });

  // fetch data dari ../menu.json
  fetch("../menu.json", { cache: "no-store" })
    .then((r) => r.json())
    .then((data) => {
      // Expektasi: array of { id, name, price, image, category }
      MENUS = Array.isArray(data) ? data : data.items || [];
      render(MENUS);
    })
    .catch(() => {
      listEl.innerHTML = (
        <div class="col-12 text-center text-muted">Gagal memuat katalog.</div>
      );
    });
})();
