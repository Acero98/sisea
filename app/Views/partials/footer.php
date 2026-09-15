    </main>
    <footer class="app-footer">Avalon Seguimiento · Consulta de solo lectura</footer>
    <script>
    (function () {
        var toggle = document.getElementById('menu-toggle');
        if (!toggle) return;
        var collapsed = localStorage.getItem('avalon-menu-contraido') === '1';
        function setCollapsed(value) { document.body.classList.toggle('menu-contraido', value); toggle.setAttribute('aria-expanded', value ? 'false' : 'true'); toggle.setAttribute('aria-label', value ? 'Ampliar menú' : 'Contraer menú'); toggle.textContent = value ? '›' : '☰'; }
        setCollapsed(collapsed);
        toggle.addEventListener('click', function () { collapsed = !document.body.classList.contains('menu-contraido'); localStorage.setItem('avalon-menu-contraido', collapsed ? '1' : '0'); setCollapsed(collapsed); });
    }());
    </script>
</body>
</html>

