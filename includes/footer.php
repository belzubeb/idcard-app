        </main>
    </div>
</div>
<script>
(function () {
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (!toggle || !sidebar || !overlay) return;
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
    }
    toggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('visible');
    });
    overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
