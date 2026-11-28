document.addEventListener("click", function (event) {
    const toggle = event.target.closest("[data-password-toggle]");
    if (!toggle) return;

    const wrapper = toggle.closest(".input-wrapper");
    const input = wrapper ? wrapper.querySelector(".password-input") : null;
    if (!input) return;

    const showPassword = input.type === "password";
    input.type = showPassword ? "text" : "password";
    toggle.setAttribute("aria-pressed", showPassword ? "true" : "false");
    toggle.setAttribute("aria-label", showPassword ? "Hide password" : "Show password");
    toggle.setAttribute("title", showPassword ? "Hide password" : "Show password");
});
