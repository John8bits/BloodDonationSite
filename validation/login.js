document.addEventListener("DOMContentLoaded", function () {

    // Event sources
    const loginForm = document.getElementById("loginForm");
    const loginButton = document.getElementById("loginButton");

    const loginInput = document.getElementById("login");
    const passwordInput = document.getElementById("password");

    const loginMessage = document.getElementById("loginMessage");


    // Submit event
    async function handleLoginSubmit(event) {

        event.preventDefault();

        loginButton.disabled = true;

        loginMessage.textContent = "";
        loginMessage.className = "form-message";

        // Get form data
        const formData = new FormData(loginForm);

        try {

            // Send login data to PHP
            const response = await fetch(
                "login_process.php",
                {
                    method: "POST",
                    body: formData
                }
            );


            const result = await response.json();

            if (result.success) {

                loginMessage.textContent = "Submitting login...";
                loginMessage.className =
                    "form-message success-message";

                setTimeout(function () {

                    window.location.href = result.redirect;

                }, 1000);


                return;
            }

            if (result.validation) {

                loginButton.disabled = false;

                if (result.errors.email) {

                    loginMessage.textContent =
                        result.errors.email;

                    loginMessage.className =
                        "form-message error-message";

                } else if (result.errors.password) {

                    loginMessage.textContent =
                        result.errors.password;

                    loginMessage.className =
                        "form-message error-message";
                }


                return;
            }

            loginMessage.textContent =
                result.message;

            loginMessage.className =
                "form-message error-message";


            loginButton.disabled = false;


        } catch (error) {

            console.error("Login error:", error);


            loginMessage.textContent =
                "Something went wrong. Please try again.";

            loginMessage.className =
                "form-message error-message";


            loginButton.disabled = false;
        }
    }


    // Submit listener
    loginForm.addEventListener(
        "submit",
        handleLoginSubmit
    );


    // Input events
    function handleLoginInput(event) {

        console.log(
            "Input event:",
            event.target.id
        );
    }


    loginInput.addEventListener(
        "input",
        handleLoginInput
    );


    passwordInput.addEventListener(
        "input",
        handleLoginInput
    );

    function handleLoginButtonClick() {

        console.log("Login button clicked.");
    }


    loginButton.addEventListener(
        "click",
        handleLoginButtonClick
    );

});