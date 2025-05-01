// Dynamically load AOS library and initialize it
(function() {
    function loadAOS() {
        // Create link element for CSS
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css';
        document.head.appendChild(link);
        
        // Create script element for JS
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js';
        script.onload = function() {
            // Initialize AOS once script is loaded
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 800,
                    easing: 'ease-in-out',
                    once: true,
                    mirror: false
                });
                console.log('AOS loaded and initialized successfully');
            }
        };
        script.onerror = function() {
            console.warn('Failed to load AOS library. Animations will not work.');
        };
        document.head.appendChild(script);
    }
    
    // Make utility functions globally available
    window.utils = {
        loadAOS: loadAOS
    };
    
    // Load AOS when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAOS);
    } else {
        loadAOS();
    }
})(); 