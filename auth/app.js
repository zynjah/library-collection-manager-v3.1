const authLoading = document.getElementById("authLoading");
const loginPanel = document.getElementById("loginPanel");
const signupPanel = document.getElementById("signupPanel");

function showAuthLoading() {
    if (!authLoading) return;

    authLoading.classList.add("show");
    authLoading.setAttribute("aria-hidden", "false");
}

// Fade between access modes so the form does not switch abruptly.
document.querySelectorAll("[data-auth-mode]").forEach((button) => {
    button.addEventListener("click", () => {
        const mode = button.dataset.authMode;
        const showLogin = mode === "login";
        const activePanel = showLogin ? loginPanel : signupPanel;
        const hiddenPanel = showLogin ? signupPanel : loginPanel;

        hiddenPanel.classList.remove("is-visible");
        hiddenPanel.classList.add("is-leaving");

        window.setTimeout(() => {
            hiddenPanel.hidden = true;
            hiddenPanel.classList.remove("is-leaving");
            activePanel.hidden = false;
            requestAnimationFrame(() => activePanel.classList.add("is-visible"));
        }, 160);

        document.querySelectorAll(".choice-button").forEach((choice) => {
            const active = choice.dataset.authMode === mode;
            choice.classList.toggle("active", active);
            choice.setAttribute("aria-selected", active ? "true" : "false");
        });
    });
});

document.querySelector("#loginPanel form")?.addEventListener("submit", () => {
    showAuthLoading();
});
