$(function(){
    var language={search:'Buscar:',lengthMenu:'Mostrar _MENU_',info:'Mostrando _START_ a _END_ de _TOTAL_',infoEmpty:'No hay registros',zeroRecords:'No se encontraron resultados',paginate:{previous:'Anterior',next:'Siguiente'}};
    if($('#tabla-usuarios').length)$('#tabla-usuarios').DataTable({pageLength:10,columnDefs:[{orderable:false,targets:[3,5]}],language:language});
    if($('#tabla-roles').length)$('#tabla-roles').DataTable({pageLength:10,columnDefs:[{orderable:false,targets:[4]}],language:language});
    var last=null,opened=null,rolesNode=document.getElementById('available-roles'),roles=rolesNode?JSON.parse(rolesNode.textContent):[];
    function open(modal,button){last=button;opened=modal;modal.hidden=false;document.body.classList.add('modal-open');modal.querySelector('.access-editor-body').scrollTop=0;modal.querySelector('.modal-close').focus();}
    function close(){if(!opened)return;$(opened).find('.select2-hidden-accessible').select2('close');opened.hidden=true;var password=opened.querySelector('input[type=password]');if(password)password.value='';document.body.classList.remove('modal-open');opened=null;if(last)last.focus();}
    function roleSummary(){
        var role=roles.find(function(r){return String(r.id)===$('#modal-role').val();}),list=document.getElementById('assigned-role-permissions');
        if(!list)return;list.replaceChildren();$('#assigned-role-description').text(role?role.descripcion:'Selecciona un rol para ver sus permisos.');
        if(!role)return;
        var scopeNames={PROPIO:'Usuario',UNIDAD:'Unidad',GLOBAL:'Global'};
        [['permiso_seguimiento','Mis notas y seguimiento','alcance_seguimiento'],['permiso_ordenes','Órdenes','alcance_ordenes'],['permiso_patrimonio','Patrimonio'],['permiso_presupuesto','Presupuesto','alcance_presupuesto'],['permiso_responsabilidades','Responsabilidades'],['permiso_usuarios','Seguridad']].forEach(function(item){
            if(!role.activo||!role.permisos[item[0]])return;
            var badge=document.createElement('span');badge.textContent=item[1]+(item[2]?': '+scopeNames[role.permisos[item[2]]]:'');list.appendChild(badge);
        });
        if(!list.childNodes.length){var badge=document.createElement('span');badge.textContent=role.activo?'Sin permisos habilitados':'Rol inactivo: sin acceso';list.appendChild(badge);}
    }
    $(document).on('click','.edit-user-button',function(){
        var u=JSON.parse(this.dataset.user),modal=document.getElementById('access-modal');document.getElementById('access-form').reset();
        $('#modal-user-id').val(u.id);$('#modal-user-name').text(u.usuario);$('#modal-user-meta').text(u.codigo);$('#modal-user-unit').text(u.unidad);$('#modal-avatar').text((u.usuario||'U').charAt(0));
        $('#modal-role').val(String(u.rol_id)).trigger('change.select2');$('#modal-active').prop('checked',u.activo);$('#modal-password-status').text(u.tiene_clave?'La cuenta ya tiene una contraseña configurada.':'Necesita una contraseña para iniciar sesión.');roleSummary();open(modal,this);
    });
    $('#modal-role').on('change',roleSummary);
    function permissionsState(){
        $('[data-permission-item]').each(function(){
            var on=$(this).find('[data-permission-main]').prop('checked');this.classList.toggle('is-enabled',on);
            $(this).find('[data-permission-settings]').prop('hidden',!on).find('input,select').prop('disabled',!on);
            $(this).find('select').trigger('change.select2');
        });
    }
    function editRole(role,button,permissions){
        var modal=document.getElementById('role-modal'),form=document.getElementById('role-form');form.reset();
        $('#role-id').val(role.id||0);$('#role-name').val(role.nombre||'');$('#role-description').val(role.descripcion||'');$('#role-active').prop('checked',role.activo!==false);
        $('#role-modal-title').text(role.id?'Configurar rol: '+role.nombre:'Crear rol');
        $('#role-modal-impact').text(role.id?'Los cambios se aplican a '+role.usuarios+' cuenta(s) asignada(s).':'Crea un rol y luego asígnalo desde Usuarios.');
        $(form).find('[data-permission-main], .role-extra input').prop('checked',false);
        $(form).find('select').val('UNIDAD').trigger('change.select2');
        Object.keys(role.permisos||{}).forEach(function(field){var input=form.elements.namedItem(field);if(!input)return;if(input.type==='checkbox')input.checked=!!role.permisos[field];else $(input).val(role.permisos[field]).trigger('change.select2');});
        permissionsState();open(modal,button);
        if(permissions){
            var body=modal.querySelector('.access-editor-body'),target=document.getElementById('role-permissions');
            body.scrollTop+=target.getBoundingClientRect().top-body.getBoundingClientRect().top-14;
        }
    }
    $('#new-role').on('click',function(){editRole({activo:true,permisos:{}},this,false);});
    $(document).on('click','.edit-role',function(){editRole(JSON.parse(this.dataset.role),this,this.hasAttribute('data-open-permissions'));});
    $('[data-permission-main]').on('change',permissionsState);
    $(document).on('click','[data-close-modal]',close).on('keydown',function(e){
        if(!opened)return;
        if(e.key==='Escape'&&!$(opened).find('.select2-container--open').length)close();
        if(e.key==='Tab'&&!document.querySelector('.swal2-container')){
            var items=$(opened).find('button,input,select,textarea,a[href],[tabindex]').filter(':visible:not(:disabled):not([tabindex="-1"])').toArray(),first=items[0],end=items[items.length-1];
            if(e.shiftKey&&document.activeElement===first){e.preventDefault();end.focus();}else if(!e.shiftKey&&document.activeElement===end){e.preventDefault();first.focus();}
        }
    });
});
