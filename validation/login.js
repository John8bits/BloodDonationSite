document.addEventListener("DOMContentLoaded", function () {

    //event sources

    const loginForm = document.getElementById("loginForm");
    const loginButton = document.getElementById("loginButton");

    const loginInput = document.getElementById("login");
    const passwordInput = document.getElementById("password");
    const loginMessage = document.getElementById("loginMessage");

    //event handler
    function handleLoginSubmit(event) {

        loginMessage.textContent = "Submitting login...";
        loginMessage.className = "form-message success-message";

        loginButton.disabled = true;

        /*console.log("Login form submitted.");
        console.log("Event source: loginForm");
        console.log("Event type: submit");
        console.log("Event handler: handleLoginSubmit");*/
    }

    // event listener for login
    loginForm.addEventListener("submit",handleLoginSubmit);

    function handleLoginInput(event) {
        console.log("Input event:", event.target.id );
    }

    loginInput.addEventListener("input",handleLoginInput);
    passwordInput.addEventListener("input",handleLoginInput);

    function handleLoginButtonClick() {
        console.log("Login button clicked.");
    }
    loginButton.addEventListener("click",handleLoginButtonClick);

});