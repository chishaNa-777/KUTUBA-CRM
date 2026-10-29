alert("login.js is working!");

document.getElementById("loginForm").addEventListener("submit", async function (event) {

    event.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const message = document.getElementById("message");

    message.textContent = "Logging in...";

    const formData = new FormData();

    formData.append("email", email);
    formData.append("password", password);

    try {

        const response = await fetch("../auth/login.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (result.success) {

    message.style.color = "green";
    message.textContent = result.message || "Login successful!";

    setTimeout(() => {
        window.location.href = "dashboard.html";
    }, 500);

}

    } catch (error) {

        console.error(error);

        message.style.color = "red";
        message.textContent = "Unable to connect to the server.";

    }

});