# Módulos y roles de SISEA

## Navegación

- Logística: Seguimiento documental, Mis notas y Órdenes de compra y servicio.
- Patrimonio: Consulta de activos.
- Presupuesto: Consulta presupuestal y Responsabilidades.
- Seguridad: Usuarios y Roles. Los permisos se configuran dentro de cada rol; no hay una sección independiente de Permisos.

Los menús solo muestran las secciones habilitadas. Los controladores siguen comprobando permisos y alcances en el servidor. La pantalla inicial se elige entre las secciones permitidas; las cuentas sin permisos ven una indicación para contactar al administrador.

## Administración

En **Usuarios** se asigna un rol, se administra el estado de la cuenta y se configura su contraseña. La identidad y la unidad siguen viniendo de Avalon.

En **Roles** se pueden crear roles, editar su descripción, configurar permisos por módulo y activar o desactivar el rol. Los permisos y alcances se heredan en tiempo de consulta; no son plantillas copiadas a cada usuario. Una sesión existente recibe los cambios en su siguiente solicitud autenticada.

El alcance de Mis notas y Órdenes puede ser propio, por unidad o global. Presupuesto utiliza unidad o global. Patrimonio mantiene su catálogo completo. La administración de Seguridad permite gestionar Usuarios y Roles. Una orden consolidada se autoriza a través de sus pedidos vinculados; el permiso de abrir detalle permite consultar su documento completo.

Desactivar un rol revoca el acceso de sus cuentas. La aplicación impide desactivar, reasignar o quitar los permisos a la última cuenta activa con contraseña capaz de administrar Seguridad. Los roles se desactivan; no se eliminan.

## Actualización y conservación

La actualización idempotente se ejecuta al consultar los datos de sesión. Se puede aplicar explícitamente con:

```powershell
php bin/actualizar_roles.php
```

Se crea `roles_acceso` y se añade `usuarios.rol_id`. Las configuraciones de acceso existentes se agrupan por rol y permisos equivalentes. Si dos usuarios tenían el mismo nombre de rol pero distintos permisos, se crean variantes como «Usuario · Acceso 2» para conservarlos. Conviene revisar sus descripciones y renombrarlas después según el trabajo que representan.

Las columnas antiguas de permisos permanecen como información de compatibilidad para la migración; no son la fuente de autorización una vez asignado el rol. No modificarlas para cambiar accesos: utilizar Seguridad → Roles.

La actualización no modifica archivos FoxPro. La configuración portátil ahora usa `sisea-config-v2` e incluye roles y asignaciones. El instalador continúa aceptando los traslados `sisea-config-v1`, reconstruyendo roles equivalentes al importarlos.

## Verificación

- `tests/roles_acceso.php`: migración, herencia, asignación, revocación y protección de la última administración.
- `tests/permisos_ordenes.php`: alcances y protección de documentos, búsqueda y exportación.
- `tests/traslado_roles.php`: restauración de configuraciones v1 y v2 en bases aisladas.
- `tests/installation_smoke.php`: instalación y rechazo de reinstalaciones sobre datos existentes.

Las pruebas de permisos usan tablas temporales, y las de instalación usan bases de prueba aisladas.
