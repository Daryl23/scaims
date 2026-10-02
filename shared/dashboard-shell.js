document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (!menuToggle || !sidebar || !overlay) {
        return;
    }

    function closeSidebar() {
        menuToggle.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function openSidebar() {
        menuToggle.classList.add('active');
        menuToggle.setAttribute('aria-expanded', 'true');
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    menuToggle.addEventListener('click', function () {
        if (sidebar.classList.contains('active')) {
            closeSidebar();
            return;
        }

        openSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
            menuToggle.focus();
        }
    });

    document.querySelectorAll('.menu-item').forEach(function (item) {
        item.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
});
