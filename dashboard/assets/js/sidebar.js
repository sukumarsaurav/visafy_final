document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    
    // Check for stored sidebar state
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Initialize sidebar state based on stored preference
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // Toggle sidebar on menu button click
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // Store sidebar state in localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
    
    // On small screens, show sidebar on hover and hide when mouse leaves
    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', function() {
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('show');
            }
        });
        
        sidebar.addEventListener('mouseleave', function() {
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('show');
            }
        });
    }
            // User dropdown toggle
            document.querySelector('.user-dropdown').addEventListener('click', function(e) {
                e.stopPropagation();
                this.querySelector('.user-dropdown-menu').classList.toggle('show');
            });
            
            // Close user dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const dropdown = document.querySelector('.user-dropdown-menu');
                if (dropdown && dropdown.classList.contains('show') && !dropdown.contains(e.target) && !e.target.closest('.user-dropdown')) {
                    dropdown.classList.remove('show');
                }
            });
}); 
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('collapsed');
        document.querySelector('.main-content').classList.toggle('expanded');
        
        // Save preference in cookie
        const isCollapsed = document.querySelector('.sidebar').classList.contains('collapsed');
        document.cookie = `sidebar_collapsed=${isCollapsed}; path=/; max-age=31536000`;
    });
    
    // Notification dropdown toggle
    document.getElementById('notification-toggle').addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('notification-menu').classList.toggle('show');
    });
    
    // Close notification dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notification-menu');
        if (dropdown.classList.contains('show') && !dropdown.contains(e.target) && e.target.id !== 'notification-toggle') {
            dropdown.classList.remove('show');
        }
    });
    
    // Handle notification click - mark as read
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            if (this.classList.contains('unread')) {
                // AJAX request to mark notification as read
                fetch('ajax/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notificationId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.classList.remove('unread');
                        // Update badge count
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent);
                            if (currentCount > 1) {
                                badge.textContent = currentCount - 1;
                            } else {
                                badge.remove();
                            }
                        }
                    }
                });
            }
        });
    });
    
    // Mark all as read functionality
    const markAllReadBtn = document.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // AJAX request to mark all notifications as read
            fetch('ajax/mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    
                    // Remove notification badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.remove();
                    }
                }
            });
        });
    }
});