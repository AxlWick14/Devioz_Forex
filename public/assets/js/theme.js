(() => {
    const storageKey = 'devioz-theme';
    const root = document.documentElement;
    const themes = ['dark', 'sunset', 'light'];
    const labels = {
        dark: 'Modo nocturno',
        sunset: 'Atardecer carmesí',
        light: 'Modo diurno'
    };
    const icons = {
        dark: '<path d="M20.5 13.1A8.5 8.5 0 0 1 10.9 3.5a8.5 8.5 0 1 0 9.6 9.6Z"/>',
        sunset: '<path d="M3 18h18M5 18a7 7 0 0 1 14 0M12 3v3m-8.5.5 2.1 2.1m12.8 0 2.1-2.1M2 13h2m16 0h2"/>',
        light: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>'
    };
    let theme = 'dark';

    try {
        const savedTheme = localStorage.getItem(storageKey);
        if (themes.includes(savedTheme)) theme = savedTheme;
    } catch (_) {
        // El cambio sigue funcionando si el navegador bloquea el almacenamiento.
    }
    root.dataset.theme = theme;

    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('themeToggle');
        const icon = button && button.querySelector('.theme-icon');
        if (!button || !icon) return;

        const updateButton = () => {
            const currentTheme = root.dataset.theme;
            const nextTheme = themes[(themes.indexOf(currentTheme) + 1) % themes.length];
            button.dataset.theme = currentTheme;
            icon.innerHTML = icons[currentTheme];
            button.setAttribute('aria-label', `Cambiar tema. Modo actual: ${labels[currentTheme]}`);
            button.title = `Cambiar a ${labels[nextTheme].toLowerCase()}`;
        };

        updateButton();
        button.addEventListener('click', () => {
            const currentIndex = themes.indexOf(root.dataset.theme);
            root.dataset.theme = themes[(currentIndex + 1) % themes.length];
            try {
                localStorage.setItem(storageKey, root.dataset.theme);
            } catch (_) {}
            updateButton();
        });
    });
})();
