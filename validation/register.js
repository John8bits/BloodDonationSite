document.addEventListener("DOMContentLoaded", function () {
    //event source
    const registerForm =
        document.getElementById("registerForm");

    const registerButton =
        document.getElementById("registerButton");

    const firstName =
        document.getElementById("first_name");

    const lastName =
        document.getElementById("last_name");

    const email =
        document.getElementById("email");

    const bloodType =
        document.getElementById("blood_type");

    const phone =
        document.getElementById("phone");

    const password =
        document.getElementById("password");

    const confirmPassword =
        document.getElementById("confirm_password");

    const terms =
        document.getElementById("terms");

    const registerMessage =
        document.getElementById("registerMessage");

    const passwordHint =
        document.getElementById("passwordHint");


    //event handler
    function handleRegister(event) {

        event.preventDefault();

        clearErrors();

        let hasError = false;

        const firstNameValue =
            firstName.value.trim();

        if (firstNameValue === "") {

            showError(
                "firstNameError",
                "First name is required."
            );

            hasError = true;

        }
        else if (!/^[A-Za-z\s'-]{2,50}$/.test(firstNameValue)) {

            showError(
                "firstNameError",
                "Please enter a valid first name."
            );

            hasError = true;

        }

        const lastNameValue =
            lastName.value.trim();

        if (lastNameValue === "") {

            showError(
                "lastNameError",
                "Last name is required."
            );

            hasError = true;

        }
        else if (!/^[A-Za-z\s'-]{2,50}$/.test(lastNameValue)) {

            showError(
                "lastNameError",
                "Please enter a valid last name."
            );

            hasError = true;

        }

        const emailValue =
            email.value.trim();

        const emailPattern =
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (emailValue === "") {

            showError(
                "emailError",
                "Email address is required."
            );

            hasError = true;

        }
        else if (!emailPattern.test(emailValue)) {

            showError(
                "emailError",
                "Please enter a valid email address."
            );

            hasError = true;

        }

        if (bloodType.value === "") {

            showError(
                "bloodTypeError",
                "Please select your blood type."
            );

            hasError = true;

        }

        // phone
        const phoneValue =
            phone.value.trim();

        const phonePattern =
            /^09\d{9}$/;

        if (phoneValue === "") {

            showError(
                "phoneError",
                "Phone number is required."
            );

            hasError = true;

        }
        else if (!phonePattern.test(phoneValue)) {

            showError(
                "phoneError",
                "Enter a valid Philippine number (09XXXXXXXXX)."
            );

            hasError = true;

        }

        //pass
        const passwordValue =
            password.value;

        if (passwordValue === "") {

            showError(
                "passwordError",
                "Password is required."
            );

            hasError = true;

        }
        else if (passwordValue.length < 8) {

            showError(
                "passwordError",
                "Password must be at least 8 characters."
            );

            hasError = true;

        }
        else if (!/[A-Z]/.test(passwordValue)) {

            showError(
                "passwordError",
                "Password needs at least one uppercase letter."
            );

            hasError = true;

        }
        else if (!/[a-z]/.test(passwordValue)) {

            showError(
                "passwordError",
                "Password needs at least one lowercase letter."
            );

            hasError = true;

        }
        else if (!/[0-9]/.test(passwordValue)) {

            showError(
                "passwordError",
                "Password needs at least one number."
            );

            hasError = true;

        }

        if (confirmPassword.value === "") {

            showError(
                "confirmPasswordError",
                "Please confirm your password."
            );

            hasError = true;

        }
        else if (
            passwordValue !== confirmPassword.value
        ) {

            showError(
                "confirmPasswordError",
                "Passwords do not match."
            );

            hasError = true;

        }

        if (!terms.checked) {

            showError(
                "termsError",
                "You must agree to the Terms & Conditions."
            );

            hasError = true;

        }

        if (hasError) {

            registerMessage.textContent = "Please correct the errors above.";

            registerMessage.className = "form-message error-message";

            return;

        }

        registerMessage.textContent = "Creating your account...";

        registerMessage.className = "form-message success-message";

        // SEND TO PHP

        setTimeout(function () {

            registerForm.submit();

        }, 300);

    }

    /*registerButton.addEventListener(
        "click",
        function (event) {

            handleRegister(event);

        }
    );*/

    registerForm.addEventListener(
        "submit",
        function (event) {

            handleRegister(event);

        }
    );

    confirmPassword.addEventListener(
        "keydown",
        function (event) {

            if (event.key === "Enter") {

                event.preventDefault();
                registerForm.requestSubmit();

            }

        }
    );


    password.addEventListener(
        "keydown",
        function (event) {

            if (event.key === "Enter") {

                event.preventDefault();
                registerForm.requestSubmit();

            }

        }
    );


    password.addEventListener(
        "focus",
        function () {

            passwordHint.style.display = "block";

        }
    );

    password.addEventListener(
        "blur",
        function () {

            passwordHint.style.display = "none";

        }
    );

    firstName.addEventListener(
        "input",
        function () {

            clearFieldError("firstNameError");

        }
    );


    lastName.addEventListener(
        "input",
        function () {

            clearFieldError("lastNameError");

        }
    );


    email.addEventListener(
        "input",
        function () {

            clearFieldError("emailError");

        }
    );


    phone.addEventListener(
        "input",
        function () {

            clearFieldError("phoneError");

        }
    );

    password.addEventListener(
        "input",
        function () {

            clearFieldError("passwordError");

        }
    );

    confirmPassword.addEventListener(
        "input",
        function () {

            clearFieldError(
                "confirmPasswordError"
            );

        }
    );

    bloodType.addEventListener(
        "change",
        function () {

            clearFieldError("bloodTypeError");

        }
    );

    terms.addEventListener(
        "change",
        function () {

            clearFieldError("termsError");

        }
    );

    function showError(id, message) {

        document.getElementById(id).textContent = message;

    }


    function clearFieldError(id) {

        document.getElementById(id).textContent = "";
        registerMessage.textContent = "";

    }


    function clearErrors() {

        const errors = document.querySelectorAll(".field-error");

        errors.forEach(function (error) {

            error.textContent = "";

        });

        registerMessage.textContent = "";
        registerMessage.className = "form-message";

    }

});