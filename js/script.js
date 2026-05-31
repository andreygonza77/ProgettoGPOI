(function () {
    const THEME_KEY = 'moneytracker_theme';
    const USER_KEY = 'moneytracker_username';

    function applySavedTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = saved || (prefersDark ? 'dark' : 'light');
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add(theme === 'dark' ? 'theme-dark' : 'theme-light');
    }

    function toggleTheme() {
        const isDark = document.body.classList.contains('theme-dark');
        const next = isDark ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, next);
        applySavedTheme();
    }

    function bindThemeToggle() {
        const btn = document.getElementById('theme-toggle');
        if (!btn) return;
        btn.addEventListener('click', toggleTheme);
    }

    function bindLogoutConfirm() {
        document.querySelectorAll('a.logout-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                const ok = window.confirm('Vuoi uscire dall’account?');
                if (!ok) {
                    e.preventDefault();
                }
            });
        });
    }

    function initRememberMeLogin() {
        // "Ricordami" salva solo lo username: la password resta fuori dal browser.
        const form = document.getElementById('login-form');
        const userInput = document.getElementById('username');
        const remember = document.getElementById('remember_me');

        if (!form || !userInput) return;

        const savedUser = localStorage.getItem(USER_KEY);
        if (savedUser) {
            userInput.value = savedUser;
            if (remember) remember.checked = true;
        }

        form.addEventListener('submit', function () {
            if (!remember) return;
            if (remember.checked) {
                localStorage.setItem(USER_KEY, userInput.value.trim());
            } else {
                localStorage.removeItem(USER_KEY);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        applySavedTheme();
        bindThemeToggle();
        bindLogoutConfirm();
        initRememberMeLogin();
    });
})();
