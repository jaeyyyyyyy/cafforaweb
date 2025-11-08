document.getElementById("verifyForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const email = localStorage.getItem("pendingEmail");
  const otp = document.getElementById("otp").value;

  const res = await fetch("../backend/verify_otp.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email, otp }),
  });
  const data = await res.json();

  if (data.status === "success") {
    alert("Akun berhasil diverifikasi!");
    localStorage.removeItem("pendingEmail");
    window.location.href = "login.html";
  } else {
    alert(data.message);
  }
});
