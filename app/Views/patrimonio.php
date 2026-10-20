<?php require APP_ROOT.'/app/Views/partials/header.php'; ?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>assets/patrimonio.css?v=20261019">
<section class="card patrimonio-panel">
    <div class="patrimonio-heading"><span class="patrimonio-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span><div><h2>Consulta de activos</h2><p>Busca por código actual o anterior, descripción, serie, marca, modelo o unidad.</p></div></div>
    <?php if($error): ?><p role="alert"><?= e($error) ?></p><?php else: ?>
    <form id="patrimonio-filtros" class="patrimonio-filtros">
        <?php foreach(['unidad'=>'Unidad orgánica','ubicacion'=>'Ubicación','estado'=>'Estado del bien'] as $key=>$label): ?>
        <label for="pat-<?= e($key) ?>"><?= e($label) ?><select id="pat-<?= e($key) ?>" name="<?= e($key) ?>" class="pat-select"><option value="">Todos</option><?php foreach($opciones[$key] as $value=>$text): ?><option value="<?= e($value) ?>"><?= e($text) ?></option><?php endforeach; ?></select></label>
        <?php endforeach; ?>
        <label for="pat-desde">Alta desde<input type="date" id="pat-desde" name="desde"></label>
        <label for="pat-hasta">Alta hasta<input type="date" id="pat-hasta" name="hasta"></label>
        <div class="patrimonio-buttons"><button type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filtrar</button><button type="button" class="button-secondary" id="pat-limpiar">Limpiar</button></div>
    </form>
    <p class="patrimonio-note">Incluye todos los bienes del catálogo, también los dados de baja. El estado corresponde al registrado en Patrimonio.</p>
    <?php endif; ?>
</section>
<?php if(!$error): ?>
<section class="card patrimonio-panel">
    <h2>Activos registrados</h2>
    <p id="pat-error" role="alert" hidden></p>
    <div class="patrimonio-table-scroll"><table id="tabla-patrimonio" class="display" aria-label="Activos de Patrimonio"><thead><tr><th>Código</th><th>Descripción / marca / modelo</th><th>Serie</th><th>Unidad orgánica</th><th>Ubicación</th><th>Estado</th><th>Fecha de alta</th></tr></thead></table></div>
</section>
<?php endif; ?>
<?php if(!$error): ?>
<script src="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.js"></script>
<script>
$(function(){
    $('.pat-select').select2({width:'100%',language:'es'});
    var escape=function(v){return $('<span>').text(v||'').html();};
    var labels=['Código','Descripción / marca / modelo','Serie','Unidad orgánica','Ubicación','Estado','Fecha de alta'];
    var error=document.getElementById('pat-error');
    var table=$('#tabla-patrimonio').DataTable({
        processing:true,serverSide:true,searchDelay:450,pageLength:25,lengthMenu:[10,25,50,100],autoWidth:false,
        ajax:function(request,callback){
            $('#patrimonio-filtros').serializeArray().forEach(function(p){request[p.name]=p.value;});
            $.ajax({url:<?= json_encode(url('patrimonio_datos')) ?>,data:request,dataType:'json'}).done(function(data){error.hidden=true;callback(data);}).fail(function(xhr){
                error.textContent=(xhr.responseJSON&&xhr.responseJSON.error)||'No se pudo cargar el listado. Vuelve a intentar con Filtrar.';error.hidden=false;
                callback({draw:request.draw,recordsTotal:0,recordsFiltered:0,data:[]});
            });
        },
        columns:[
            {data:'codigo',render:function(v,t,r){return t==='display'?'<strong>'+escape(v)+'</strong>'+(r.anterior?'<small>Anterior: '+escape(r.anterior)+'</small>':''):v;}},
            {data:'descripcion',render:function(v,t,r){return t==='display'?escape(v)+'<small>'+escape([r.marca,r.modelo].filter(Boolean).join(' · '))+'</small>':v;}},
            {data:'serie',render:$.fn.dataTable.render.text()},
            {data:'unidad',render:$.fn.dataTable.render.text()},
            {data:'ubicacion',render:$.fn.dataTable.render.text()},
            {data:'estado',render:$.fn.dataTable.render.text()},
            {data:'fecha',render:function(v,t){return t==='display'?(v?v.split('-').reverse().join('/'):'—'):v;}}
        ],
        createdRow:function(row){$('td',row).each(function(i){this.setAttribute('data-label',labels[i]);});},
        language:{processing:'Consultando activos…',search:'Buscar:',searchPlaceholder:'Código, descripción, serie…',lengthMenu:'Mostrar _MENU_ por página',info:'Mostrando _START_ a _END_ de _TOTAL_ activos',infoEmpty:'Sin activos para mostrar',infoFiltered:'(de _MAX_ en total)',zeroRecords:'No se encontraron activos con estos filtros.',emptyTable:'No hay activos disponibles.',paginate:{previous:'Anterior',next:'Siguiente'}}
    });
    $('#patrimonio-filtros').on('submit',function(e){e.preventDefault();table.ajax.reload();});
    $('#pat-limpiar').on('click',function(){$('#pat-desde,#pat-hasta').val('');$('.pat-select').val('').trigger('change.select2');table.search('').draw();});
});
</script>
<?php endif; ?>
<?php require APP_ROOT.'/app/Views/partials/footer.php'; ?>
