<?php
$flash = takeFlash();
$pageTitle = 'Usuarios y accesos';
$pageDescription = 'Cuentas vinculadas a Logística de Avalon. Las contraseñas se administran en este portal.';
$breadcrumb = 'Configuración / Usuarios';
$activeNav = 'usuarios';
$useDataTables = true;
require APP_ROOT . '/app/Views/partials/header.php';
?>
<?php if ($flash): ?><div class="alert"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="card">
    <h2>Sincronizar desde Avalon</h2>
    <p>Importa usuario, perfil, zona, localidad, unidad orgánica, almacén, supervisor y estado activo.</p>
    <form method="post" action="<?= e(url('sincronizar_usuarios')) ?>"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button>Sincronizar usuarios activos y perfiles</button></form>
</section>
<section class="card">
    <h2>Cuentas del portal</h2>
    <div class="table-scroll">
        <table id="tabla-usuarios" class="display">
            <thead>
                <tr>
                    <th>Usuario Avalon</th>
                    <th>Perfil</th>
                    <th>Unidad orgánica</th>
                    <th>Alcance</th>
                    <th>Acceso</th>
                </tr>
            </thead>
            <tbody><?php foreach ($usuarios as $user): ?><tr>
                        <td><b><?= e($user['usuario']) ?></b><br><small><?= e($user['avalon_codigo'] ?: 'Cuenta local') ?></small></td>
                        <td><?= e($user['perfil_nombre'] ?: $user['rol']) ?></td>
                        <td><?= e(($user['unidad_organica'] ?: '—') . ' · ' . ($user['unidad_nombre'] ?: '')) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('guardar_usuario')) ?>"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($user['id']) ?>"><?php if ($user['rol'] === 'ADMIN'): ?><select name="alcance">
                                        <option value="UNIDAD" <?= $user['alcance'] === 'UNIDAD' ? 'selected' : '' ?>>Solo su unidad</option>
                                        <option value="GLOBAL" <?= $user['alcance'] === 'GLOBAL' ? 'selected' : '' ?>>Todas las unidades</option>
                                    </select><?php else: ?><input type="hidden" name="alcance" value="UNIDAD">Solo su unidad<?php endif; ?>
                        </td>
                        <td><input type="password" name="clave" placeholder="Nueva contraseña"><label><input type="checkbox" name="activo" <?= $user['activo'] ? 'checked' : '' ?>> Activo</label><button>Guardar</button></form>
                        </td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<script src="<?= e(APP_URL) ?>assets/vendor/jquery-3.7.1.min.js"></script>
<script src="<?= e(APP_URL) ?>assets/vendor/jquery.dataTables.min.js"></script>
<script>
    $(function() {
        $('#tabla-usuarios').DataTable({
            pageLength: 10,
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ usuarios',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ usuarios',
                infoEmpty: 'No hay usuarios para mostrar',
                paginate: {
                    previous: 'Anterior',
                    next: 'Siguiente'
                },
                zeroRecords: 'No se encontraron usuarios'
            }
        });
    });
</script>
<?php require APP_ROOT . '/app/Views/partials/footer.php'; ?>