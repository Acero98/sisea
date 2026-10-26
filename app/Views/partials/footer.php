    </main>
    <script src="<?= e(APP_URL) ?>assets/js/navegacion.js?v=20261025a"></script>
    <footer class="app-footer">Avalon Seguimiento · Consulta de solo lectura</footer>
    <script src="<?= e(APP_URL) ?>assets/vendor/select2/select2.full.min.js"></script>
    <script src="<?= e(APP_URL) ?>assets/vendor/select2/es.js"></script>
    <script src="<?= e(APP_URL) ?>assets/js/sweetalert2.all.min.js"></script>
    <script>
    (function () {
        $('.js-select2, .budget-filter select, #modal-role, .role-permission-settings select').each(function () {
            var $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) return;
            var $modal = $select.closest('.access-modal');
            $select.select2({
                width:'100%',
                language:'es',
                minimumResultsForSearch:$select.is('[data-searchable]') ? 0 : Infinity,
                matcher:function(params, data) {
                    var match = $.fn.select2.defaults.defaults.matcher(params, data);
                    if (match || !$select.is('[data-searchable]')) return match;
                    var term = (params.term || '').replace(/[.\s]/g, '');
                    return /^\d+$/.test(term) && (data.text || '').replace(/[.\s]/g, '').indexOf(term) !== -1 ? data : null;
                },
                dropdownParent:$modal.length ? $modal : $(document.body)
            });
        });

        var flash = document.querySelector('[data-toast-message]');
        if (flash && window.Swal) {
            var toast = Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:4800, timerProgressBar:true, showClass:{popup:'swal2-show'}, hideClass:{popup:'swal2-hide'}, didOpen:function(node){node.addEventListener('mouseenter',Swal.stopTimer);node.addEventListener('mouseleave',Swal.resumeTimer);}});
            toast.fire({icon:flash.dataset.toastType === 'error' ? 'error' : 'success', title:flash.dataset.toastMessage});
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.matches('form[data-confirm]')) return;
            if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
            event.preventDefault();
            var message = form.dataset.confirm || '¿Deseas continuar?';
            var ask = window.Swal ? Swal.fire({icon:'question', title:'Confirmar acción', text:message, showCancelButton:true, confirmButtonText:'Aceptar', cancelButtonText:'Cancelar', reverseButtons:true, focusCancel:true}) : Promise.resolve({isConfirmed:window.confirm(message)});
            ask.then(function (result) { if (result.isConfirmed) { form.dataset.confirmed='1'; if (form.requestSubmit) form.requestSubmit(); else HTMLFormElement.prototype.submit.call(form); } });
        });

        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-confirm]');
            if (!link) return;
            event.preventDefault();
            var message = link.dataset.confirm || '¿Deseas continuar?';
            var ask = window.Swal ? Swal.fire({icon:'warning', title:'Confirmar acción', text:message, showCancelButton:true, confirmButtonText:'Aceptar', cancelButtonText:'Cancelar', reverseButtons:true, focusCancel:true}) : Promise.resolve({isConfirmed:window.confirm(message)});
            ask.then(function (result) { if (result.isConfirmed) window.location.assign(link.href); });
        });
    }());
    </script>
    <script>
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

