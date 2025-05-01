
// Mobile menu toggle
const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
const sideDrawer = document.querySelector('.side-drawer');
const drawerOverlay = document.querySelector('.drawer-overlay');
const drawerClose = document.querySelector('.drawer-close');
const drawerItems = document.querySelectorAll('.drawer-item[data-target]');

// Open mobile menu
mobileMenuToggle.addEventListener('click', function() {
    sideDrawer.classList.add('open');
    drawerOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
});

// Close mobile menu
function closeDrawer() {
    sideDrawer.classList.remove('open');
    drawerOverlay.classList.remove('open');
    document.body.style.overflow = '';
}

drawerClose.addEventListener('click', closeDrawer);
drawerOverlay.addEventListener('click', closeDrawer);

// Toggle submenu in mobile drawer
drawerItems.forEach(item => {
    item.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const submenu = document.getElementById(targetId);
        
        // Toggle active class on drawer item
        this.classList.toggle('active');
        
        // Toggle submenu visibility
        if (submenu.classList.contains('open')) {
            submenu.classList.remove('open');
            submenu.style.maxHeight = null;
        } else {
            // Close all other open submenus
            document.querySelectorAll('.drawer-submenu.open').forEach(menu => {
                if (menu.id !== targetId) {
                    menu.classList.remove('open');
                    menu.style.maxHeight = null;
                    const relatedItem = document.querySelector(`.drawer-item[data-target="${menu.id}"]`);
                    if (relatedItem) relatedItem.classList.remove('active');
                }
            });
            
            // Open this submenu
            submenu.classList.add('open');
            submenu.style.maxHeight = submenu.scrollHeight + 'px';
        }
    });
});

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 992 && sideDrawer.classList.contains('open')) {
        closeDrawer();
    }
});
