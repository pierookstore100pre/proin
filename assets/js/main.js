const menuToggle = document.getElementById('menuToggle');
const mainNav = document.getElementById('mainNav');

if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', () => {
        mainNav.classList.toggle('open');
    });
}

document.querySelectorAll('.thumb').forEach(img => {
    img.addEventListener('click', () => {
        const principal = document.getElementById('imagenPrincipal');
        if (principal) {
            principal.src = img.dataset.src;
        }

        document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
        img.classList.add('active');
    });
});