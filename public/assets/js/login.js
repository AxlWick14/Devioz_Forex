(() => {
    const globe = document.getElementById('loginGlobe');
    const motionButton = document.getElementById('loginMotionToggle');
    const page = document.querySelector('.login-page');
    if (globe && motionButton && page) {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let paused = false;
        const updateMotion = () => {
            const stopped = paused || reducedMotion.matches;
            page.classList.toggle('login-motion-paused', stopped);
            globe.src = stopped ? globe.dataset.staticSrc : globe.dataset.animatedSrc;
            motionButton.hidden = reducedMotion.matches;
            motionButton.textContent = paused ? 'Activar animación' : 'Pausar animación';
            motionButton.setAttribute('aria-pressed', String(paused));
        };
        motionButton.addEventListener('click', () => { paused = !paused; updateMotion(); });
        reducedMotion.addEventListener('change', updateMotion);
        updateMotion();
    }
    const button = document.getElementById('loginPasswordToggle');
    const password = document.getElementById('password');
    if (!button || !password) return;
    button.hidden = false;
    button.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.setAttribute('aria-pressed', String(visible));
    });
})();
