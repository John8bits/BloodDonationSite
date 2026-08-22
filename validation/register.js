document.addEventListener("DOMContentLoaded", function () {

    //event sources
    const registerForm = document.getElementById("registerForm");
    const registerButton = document.getElementById("registerButton");
    const firstName = document.getElementById("first_name");
    const lastName = document.getElementById("last_name");
    const email = document.getElementById("email");
    const bloodType = document.getElementById("blood_type");
    const phone = document.getElementById("phone");
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");
    const terms =document.getElementById("terms");
    const registerMessage = document.getElementById("registerMessage");

    function handleRegisterSubmit(event) {

        registerMessage.textContent ="Creating your account...";
        registerMessage.className ="form-message success-message";
        registerButton.disabled = true;
    }

    registerForm.addEventListener("submit",handleRegisterSubmit);

    //input handler
    function handleInput(event) {
        console.log("Input event:",event.target.id);
    }

    //event listeners
    firstName.addEventListener("input",handleInput);

    lastName.addEventListener("input",handleInput);

    email.addEventListener("input",handleInput);

    phone.addEventListener( "input",handleInput);

    password.addEventListener("input",handleInput);

    confirmPassword.addEventListener("input",handleInput);

    function handleChange(event) {

        console.log(
            "Change event:",
            event.target.id,
            "Value:",
            event.target.value
        );
    }

    bloodType.addEventListener(
        "change",
        handleChange
    );

    terms.addEventListener(
        "change",
        function () {

            console.log(
                "Terms checkbox changed."
            );

        }
    );

    registerButton.addEventListener(
        "click",
        function () {

            console.log(
                "Create Account button clicked."
            );

        }
    );


});