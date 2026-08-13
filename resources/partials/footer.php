<!-- Global Dark Mode Script & Bootstrap Bundle -->
    <script>
        // 1. Immediately apply theme on every page load to prevent white flashing
        (function() {
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();

        // 2. Global Toggle Function
        function toggleTheme() {
            const htmlElement = document.documentElement;
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('bs-theme', newTheme);
            updateButtonLabel(newTheme);
        }

        // 3. Update button label safely (runs only if button exists on the page)
        function updateButtonLabel(theme) {
            const themeToggleBtn = document.getElementById('themeToggle');
            if (themeToggleBtn) {
                if (theme === 'dark') {
                    themeToggleBtn.innerHTML = '☀️ Light Mode';
                    themeToggleBtn.classList.replace('btn-outline-secondary', 'btn-outline-warning');
                } else {
                    themeToggleBtn.innerHTML = '🌙 Dark Mode';
                    themeToggleBtn.classList.replace('btn-outline-warning', 'btn-outline-secondary');
                }
            }
        }

        // 4. Sync button label on page load
        document.addEventListener('DOMContentLoaded', () => {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            updateButtonLabel(currentTheme);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>