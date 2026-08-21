document.addEventListener("DOMContentLoaded", function () {
    // event source
    const loginForm = document.getElementById("loginForm");
    const loginButton = document.getElementById("loginButton");

    const loginInput = document.getElementById("login");
    const passwordInput = document.getElementById("password");

    const loginError = document.getElementById("loginError");
    const passwordError = document.getElementById("passwordError");

    const loginMessage = document.getElementById("loginMessage");
    const passwordHint = document.getElementById("passwordHint");

    // event handler
    function handleLogin(event) {

        event.preventDefault();
        clearMessages();

        const login = loginInput.value.trim();
        const password = passwordInput.value;

        let hasError = false;

        if(login==="" && password==="") {
            loginMessage.textContent ="All Fields must not be empty";
        }

        if (login === "") {

            loginError.textContent = "Please enter your email.";
            hasError = true;

        }

        if (password === "") {

            passwordError.textContent ="Please enter your password.";
            hasError = true;
        }
        else if (password.length < 6) {

            passwordError.textContent = "Password must be at least 6 characters.";
            hasError = true;

        }

        if (hasError) {

            // loginMessage.textContent ="All Fields must not be empty";
            loginMessage.className = "form-message error-message";

            return;
        }

        loginMessage.textContent = "Validating credentials...";
        loginMessage.className = "form-message success-message";

        //submit php
        setTimeout(function () {

            loginForm.submit();

        }, 300);

    }

    // MOUSE EVENT
    // Event Source -> Login button
    // Event Listener -> Click event
    // Event Handler -> handleLogin

    /*loginButton.addEventListener("click", function (event) {

        handleLogin(event);

    });*/

    loginForm.addEventListener("submit", function (event) {

        handleLogin(event);

    });

    passwordInput.addEventListener("keydown", function (event) {

        if (event.key === "Enter") {

            event.preventDefault();

            loginForm.requestSubmit();

        }

    });

    passwordInput.addEventListener("focus", function () {

        passwordHint.style.display = "block";

    });

    passwordInput.addEventListener("blur", function () {

        passwordHint.style.display = "none";

    });


    loginInput.addEventListener("input", function () {

        loginError.textContent = "";
        loginMessage.textContent = "";

    });


    passwordInput.addEventListener("input", function () {

        passwordError.textContent = "";
        loginMessage.textContent = "";

    });

    function clearMessages() {

        loginError.textContent = "";
        passwordError.textContent = "";
        loginMessage.textContent = "";

        loginMessage.className = "form-message";

    }

});