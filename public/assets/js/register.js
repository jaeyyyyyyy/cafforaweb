document
  .getElementById("registerForm")
  .addEventListener("submit", async (e) => {
    e.preventDefault();
    const name = document.getElementById("name").value;
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;

    const res = await fetch("../backend/register.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, password }),
    });
    const data = await res.json();
    if (data.status === "success") {
      alert("OTP dikirim ke email!");
      localStorage.setItem("pendingEmail", email);
      window.location.href = "verify-otp.html";
    } else {
      alert(data.message);
    }
  });
