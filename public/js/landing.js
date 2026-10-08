const nav = document.querySelector('[data-landing-nav]');
const menuButton = document.querySelector('[data-menu-button]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

if (nav) {
    const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 16);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

if (menuButton && mobileMenu) {
    menuButton.addEventListener('click', () => {
        const open = mobileMenu.classList.toggle('open');
        menuButton.setAttribute('aria-expanded', String(open));
    });

    mobileMenu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('open');
            menuButton.setAttribute('aria-expanded', 'false');
        });
    });
}
