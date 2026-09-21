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
    (function () {
        var panel=document.getElementById('sync-global-status');
        var detail=document.getElementById('sync-global-detail');
        var endpoint=<?= json_encode(url('estado_sincronizacion'), JSON_UNESCAPED_SLASHES) ?>;
        var waitingTimer=null;
        function requestStatus(){
            return fetch(endpoint,{cache:'no-store',credentials:'same-origin'}).then(function(response){
                if(response.redirected){window.location.href=response.url;throw new Error('redirect');}
                if(!response.ok)throw new Error('status');
                return response.json();
            });
        }
        function waitUntilFree(button,original){
            if(waitingTimer)window.clearTimeout(waitingTimer);
            waitingTimer=window.setTimeout(function(){
                requestStatus().then(function(data){
                    if(data.en_curso){
                        var who=data.usuario_nombre||'Otro usuario';
                        var what=data.descripcion||'Información del sistema';
                        detail.textContent=what+' · iniciada por '+who+(data.iniciado_en?' a las '+data.iniciado_en:'')+'. Verificando nuevamente…';
                        waitUntilFree(button,original);
                        return;
                    }
                    waitingTimer=null;
                    panel.hidden=true;
                    button.disabled=false;
                    button.textContent=original;
                }).catch(function(error){
                    if(error.message==='redirect')return;
                    detail.textContent='No se pudo verificar el estado. Se intentará nuevamente…';
                    waitUntilFree(button,original);
                });
            },3000);
        }
        document.querySelectorAll('[data-sync-form]').forEach(function(form){
            form.addEventListener('submit',function(event){
                event.preventDefault();
                var button=form.querySelector('[data-sync-button]');
                if(!button||button.disabled)return;
                var original=button.textContent;
                button.disabled=true;
                button.textContent='Verificando disponibilidad…';
                requestStatus()
                    .then(function(data){
                        if(data.en_curso){
                            var who=data.usuario_nombre||'Otro usuario';
                            var what=data.descripcion||'Información del sistema';
                            panel.hidden=false;
                            detail.textContent=what+' · iniciada por '+who+(data.iniciado_en?' a las '+data.iniciado_en:'')+'. Verificando nuevamente…';
                            button.disabled=true;
                            button.textContent='Actualización en proceso';
                            waitUntilFree(button,original);
                            return;
                        }
                        panel.hidden=false;
                        detail.textContent='La sesión y la disponibilidad fueron verificadas. Iniciando…';
                        button.textContent='Iniciando actualización…';
                        HTMLFormElement.prototype.submit.call(form);
                    })
                    .catch(function(error){
                        if(error.message==='redirect')return;
                        panel.hidden=false;
                        detail.textContent='No se pudo verificar la disponibilidad. Inténtelo nuevamente.';
                        button.disabled=false;
                        button.textContent=original;
                    });
            });
        });
    }());
    </script>
</body>
</html>

