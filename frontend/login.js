document.getElementById("loginForm").addEventListener("submit", async function (event) {

    event.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const message = document.getElementById("message");
    const loginButton = document.querySelector(".login-button");

    // Show logging in message
    message.style.color = "black";
    message.textContent = "Logging in...";

    // Disable button while logging in
    loginButton.disabled = true;
    loginButton.textContent = "Logging in...";

    const formData = new FormData();

    formData.append("email", email);
    formData.append("password", password);

    try {

        const response = await fetch("../backend/auth/login.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (result.success) {

            message.style.color = "green";
            message.textContent = result.message || "Login successful!";

            // Change button
            loginButton.textContent = "✓ Login Successful";

           setTimeout(() => {

    const role = result.user.role.toLowerCase();

    if (role === "admin") {
        window.location.href = "admin-dashboard.html";
    }

    else if (role === "hr_officer") {
        window.location.href = "staff-dashboard.html";
    }

    else if (role === "it") {
        window.location.href = "it-dashboard.html";
    }

    else if (role === "finance_officer") {
    window.location.href = "finance-dashboard.html";
    }

    else {
        message.style.color = "red";
        message.textContent = "Your account has an unrecognized role.";

        loginButton.disabled = false;
        loginButton.textContent = "Login";
    }

}, 700);
        } else {

            message.style.color = "red";
            message.textContent = result.message || "Login failed.";

            // Enable button again
            loginButton.disabled = false;
            loginButton.textContent = "Login";

        }

    } catch (error) {

        console.error(error);

        message.style.color = "red";
        message.textContent = "Unable to connect to the server.";

        // Enable button again
        loginButton.disabled = false;
        loginButton.textContent = "Login";

    }

});