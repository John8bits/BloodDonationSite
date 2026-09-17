document.addEventListener("DOMContentLoaded", function () {

    const registerForm = document.getElementById("registerForm");
    const registerButton = document.getElementById("registerButton");

    const firstName = document.getElementById("first_name");
    const lastName = document.getElementById("last_name");
    const email = document.getElementById("email");
    const bloodType = document.getElementById("blood_type");
    const phone = document.getElementById("phone");
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");
    const terms = document.getElementById("terms");
    const registerMessage = document.getElementById("registerMessage");

    // const REGISTRATION_EVENT = "lifeblood:registrationSubmitted";

    // Handler 1 
    function handleRegistrationLog(event) {

        const data = event.detail;

        console.log("Handler 1: Registration event received.");
        /*console.log("  Name:", data.firstName, data.lastName);
        console.log("  Email:", data.email);
        console.log("  Blood Type:", data.bloodType);
        console.log("  Registration Time:", data.registrationTime);*/
    }

    // 2 
    function handleRegistrationMessage(event) {

        const data = event.detail;
        registerMessage.textContent = `Creating account for ${data.firstName}.`;
        registerMessage.className = "form-message success-message";
        console.log("Handler 2: Registration status message updated.");

    }

    // 3 
    function handleRegistrationActivity(event) {

        const data = event.detail;  
        const activity = { event: REGISTRATION_EVENT,bloodType: data.bloodType, registrationTime: data.registrationTime };
        sessionStorage.setItem("lastRegistrationEvent", JSON.stringify(activity));
        console.log("Handler 3: Registration activity recorded in session storage.");

    }

    const registrationHandlers = [
        handleRegistrationLog,
        handleRegistrationMessage,
        handleRegistrationActivity
    ];

    registrationHandlers.forEach(function (handler) {

        document.addEventListener(REGISTRATION_EVENT,handler);

    });


    //arrow function
    document.addEventListener( REGISTRATION_EVENT, (event) => {
            
            console.log("registration detected.",event.detail.email);

        }
    );

    const REGISTRATION_EVENT = "lifeblood:registrationSubmitted";

    function handleRegisterSubmit(event) {

        event.preventDefault();

        registerButton.disabled = true;

        const registrationData = {
            firstName: firstName.value.trim(),
            lastName: lastName.value.trim(),
            email: email.value.trim(),
            bloodType: bloodType.value,
            phone: phone.value.trim(),
            registrationTime: new Date().toLocaleString()
        };

        console.log("Event: User submitted the registration form.");
        console.log("Event Data:",registrationData);

        const registrationEvent = new CustomEvent(
            REGISTRATION_EVENT,
            {
                detail: registrationData
            }
        );

        document.dispatchEvent(registrationEvent);

        setTimeout(function () {

            //console.log("Registration event completed.");
            registerForm.submit();

        }, 300);
    }

    registerForm.addEventListener("submit",handleRegisterSubmit);

    function handleInput(event) {

        console.log("Input event:",event.target.id);
        
    }


    firstName.addEventListener("input", handleInput);
    lastName.addEventListener("input", handleInput);
    email.addEventListener("input", handleInput);
    phone.addEventListener("input", handleInput);
    password.addEventListener("input", handleInput);
    confirmPassword.addEventListener("input", handleInput);


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

            console.log("Create Acc button clicked.");

        }
    );

});
