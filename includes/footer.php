<!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ScrollReveal for animations -->
    <script src="https://unpkg.com/scrollreveal"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(dropdown => {
                const dropdownToggle = document.querySelector(`[data-bs-toggle="dropdown"][aria-expanded="true"]`);
                if (dropdownToggle && !dropdownToggle.contains(event.target) && !dropdown.contains(event.target)) {
                    new bootstrap.Dropdown(dropdownToggle).hide();
                }
            });
        });

        // Initialize ScrollReveal
        ScrollReveal().reveal('.dashboard-card', { 
            delay: 200,
            duration: 1000,
            distance: '20px',
            origin: 'bottom',
            interval: 100
        });
    </script>
</body>
</html>