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
        let switching = false;
        let interruptTransition = null;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const applyNextTheme = () => {
            const currentIndex = themes.indexOf(root.dataset.theme);
            root.dataset.theme = themes[(currentIndex + 1) % themes.length];
            try {
                localStorage.setItem(storageKey, root.dataset.theme);
            } catch (_) {}
            updateButton();
        };

        button.addEventListener('click', async () => {
            if (switching) {
                interruptTransition?.();
                applyNextTheme();
                return;
            }
            if (reducedMotion.matches || typeof document.startViewTransition !== 'function') {
                applyNextTheme();
                return;
            }

            const rect = button.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top + rect.height / 2;
            const radius = Math.hypot(
                Math.max(x, window.innerWidth - x),
                Math.max(y, window.innerHeight - y)
            ) * 1.1;
            const duration = 1050;
            const points = (cx, cy, size, rotation = 0) => Array.from({ length: 120 }, (_, index) => {
                const angle = index / 120 * Math.PI * 2;
                const wave = 1 + 0.035 * Math.sin(angle * 6) + 0.018 * Math.sin(angle * 11 + 0.8);
                return [cx + Math.cos(angle + rotation) * size * wave, cy + Math.sin(angle + rotation) * size * wave];
            });
            const frames = Array.from({ length: 33 }, (_, index) => {
                const progress = index / 32;
                const outline = points(x, y, radius * progress, progress * 0.24);
                return { offset: progress, clipPath: `polygon(${outline.map(([px, py]) => `${px.toFixed(2)}px ${py.toFixed(2)}px`).join(',')})` };
            });
            switching = true;
            button.setAttribute('aria-busy', 'true');
            root.classList.add('theme-reveal');

            let transition;
            let waveOverlay;
            let interrupted = false;
            const animations = [];
            interruptTransition = () => {
                interrupted = true;
                if (transition) transition.skipTransition();
                animations.forEach(animation => animation.cancel());
            };
            try {
                // Keep the old page behind the new theme until the circle covers the viewport.
                transition = document.startViewTransition(() => {
                    applyNextTheme();
                    waveOverlay = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    waveOverlay.setAttribute('viewBox', '0 0 200 200');
                    waveOverlay.setAttribute('aria-hidden', 'true');
                    waveOverlay.classList.add('theme-wave-overlay');
                    const outline = points(100, 100, 80).map(([px, py]) => `${px.toFixed(2)},${py.toFixed(2)}`).join(' ');
                    waveOverlay.innerHTML = `<defs>
                        <linearGradient id="themeWaveColor"><stop stop-color="#66e8ff"/><stop offset=".5" stop-color="#ac8bff"/><stop offset="1" stop-color="#ff99ce"/></linearGradient>
                        <filter id="themeWaveGlow" x="-25%" y="-25%" width="150%" height="150%"><feGaussianBlur stdDeviation="0.8"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                    </defs><polygon points="${outline}" fill="none" stroke="url(#themeWaveColor)" stroke-width=".55" filter="url(#themeWaveGlow)"/>
                    <polygon points="${outline}" fill="none" stroke="#83e8ff" stroke-width=".3" opacity=".45" transform="translate(100 100) scale(.94) translate(-100 -100)"/>`;
                    document.body.appendChild(waveOverlay);
                });
                await transition.ready;
                if (interrupted) return;
                const reveal = root.animate(frames, {
                    duration,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    fill: 'forwards',
                    pseudoElement: '::view-transition-new(root)'
                });
                animations.push(reveal);
                const halo = root.animate(Array.from({ length: 33 }, (_, index) => {
                    const progress = index / 32;
                    return {
                        offset: progress,
                        transform: `translate(${x - 100}px, ${y - 100}px) scale(${radius / 80 * progress}) rotate(${progress * 0.24}rad)`,
                        opacity: progress < 0.12 ? progress / 0.12 * 0.8 : Math.max(0, (1 - progress) / 0.88) * 0.8
                    };
                }), {
                    duration,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    fill: 'forwards',
                    pseudoElement: '::view-transition-new(theme-wave)'
                });
                animations.push(halo);
                await reveal.finished;
                waveOverlay.remove();
                animations.forEach(animation => animation.cancel());
                await transition.finished;
            } catch (_) {
                // If a snapshot is interrupted, finish the requested change without locking the button.
                if (transition) {
                    transition.skipTransition();
                    await transition.updateCallbackDone.catch(() => {});
                } else {
                    applyNextTheme();
                }
            } finally {
                if (waveOverlay) waveOverlay.remove();
                animations.forEach(animation => animation.cancel());
                root.classList.remove('theme-reveal');
                button.removeAttribute('aria-busy');
                interruptTransition = null;
                switching = false;
            }
        });
    });
})();
