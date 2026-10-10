document.addEventListener("DOMContentLoaded", function () {
    const registerForm = document.getElementById("registerForm");
    const registerButton = document.getElementById("registerButton");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirm_password");
    const registerMessage = document.getElementById("registerMessage");

    if (!registerForm || !registerButton) {
        return;
    }

    function clearMessage() {
        if (!registerMessage) {
            return;
        }

        registerMessage.textContent = "";
        registerMessage.className = "form-message";
    }

    registerForm.addEventListener("submit", function (event) {
        const email = (emailInput ? emailInput.value.trim() : "");
        const password = passwordInput ? passwordInput.value : "";
        const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : "";

        if (!email || !password || !confirmPassword) {
            event.preventDefault();
            if (registerMessage) {
                registerMessage.textContent = "Please complete all fields.";
                registerMessage.className = "form-message error-message";
            }
            return;
        }

        if (password.length < 8) {
            event.preventDefault();
            if (registerMessage) {
                registerMessage.textContent = "Password must be at least 8 characters long.";
                registerMessage.className = "form-message error-message";
            }
            return;
        }

        if (password !== confirmPassword) {
            event.preventDefault();
            if (registerMessage) {
                registerMessage.textContent = "Passwords do not match.";
                registerMessage.className = "form-message error-message";
            }
        }
    });

    [emailInput, passwordInput, confirmPasswordInput].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener("input", clearMessage);
    });
});
