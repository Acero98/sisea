# Instalación en Windows / Laragon

## Qué se traslada

El ZIP contiene el sistema y los recursos visuales compilados. No necesita ejecutar Node ni Tailwind en el servidor.
El archivo de configuración comprimido conserva usuarios, hashes de contraseñas, permisos, alcances y responsabilidades presupuestales. No transporta sesiones.
Las tablas de caché y seguimiento actual se reconstruyen desde Avalon. **El historial antiguo de cambios y consultas no se conserva en este modo**: para conservarlo use el respaldo completo descrito abajo.

La copia actual tiene aproximadamente 920 MB solo en las dos tablas principales de caché. La base volverá a crecer al sincronizar. Reserve espacio para la base completa, sus índices, archivos temporales, Avalon y respaldos; el ZIP pequeño no reduce el almacenamiento final.

## En el equipo actual

Desde la terminal de Laragon, en la carpeta del proyecto:

~~~powershell
php bin/exportar_configuracion.php
php bin/empaquetar.php
~~~

Traslade el ZIP de dist y el archivo json.gz de storage/backups por separado. Este último contiene información privada de las cuentas. No lo coloque en una carpeta pública.
Guarde además un respaldo completo antes de retirar el equipo original.

## En el nuevo equipo

1. Prepare Apache, MySQL y PHP con pdo_mysql, mbstring, iconv, json y zlib. Las herramientas se han comprobado con PHP 7.4.29; pruebe la versión elegida antes de producción. ZIP solo es necesario para generar paquetes.
2. Extraiga sisea en C:/laragon/www. Preferiblemente configure el sitio Apache con DocumentRoot en C:/laragon/www/sisea/public. Si utiliza localhost/sisea/public, active mod_rewrite y AllowOverride para que el .htaccess proteja carpetas internas.
3. Copie la configuración de ejemplo:

~~~powershell
Copy-Item config/local.example.php config/local.php
~~~

Edite local.php: conexión MySQL, nombre de una **base nueva vacía**, URL y las cuatro rutas de Avalon. app_url será / para un sitio cuyo DocumentRoot sea public, o /sisea/public/ para la ruta de Laragon.
No copie las credenciales del servidor anterior automáticamente.

Las rutas pueden ser locales o UNC. La cuenta que ejecuta Apache y las tareas debe poder leerlas. Proporcione una copia consistente de las carpetas DBF de Logistica, Planif, contab y Finanzas con sus archivos asociados, o acceso de lectura al origen. El sistema no escribe en Avalon.
Variables opcionales que sobrescriben local.php: SISEA_DB_HOST, SISEA_DB_PORT, SISEA_DB_NAME, SISEA_DB_USER, SISEA_DB_PASSWORD, SISEA_APP_URL y AVALON_LOGISTICA_DATA, AVALON_PLANIF_DATA, AVALON_CONTAB_DATA, AVALON_FINANZAS_DATA.

4. Instale conservando las cuentas:

~~~powershell
php database/install.php --portable=C:/traslado/config.json.gz
~~~

O para una instalación sin cuentas anteriores:

~~~powershell
php database/install.php
~~~

El instalador muestra una contraseña aleatoria para admin. Guárdela en privado. Opcionalmente puede proporcionar SISEA_ADMIN_PASSWORD (mínimo 12 caracteres). Nunca hay una clave predeterminada compartida.
Si la instalación falla después de crear tablas, corrija la causa y use otra base vacía. El instalador no elimina tablas ni repara bases existentes.

5. Verifique y cargue la copia inicial:

~~~powershell
php bin/diagnostico.php
php -d memory_limit=1024M bin/sincronizar_seguimiento.php
~~~

La primera sincronización puede tardar considerablemente. Hágala desde terminal, sin límites de una solicitud del navegador. 1024M es un punto de partida, no una garantía: ajuste según la memoria del servidor y el volumen. Conserve la salida del proceso y compruebe que termina correctamente. La sincronización recorre las fuentes y procesa notas por lotes; algunos conjuntos se materializan en memoria.
Si se interrumpe, puede ejecutar nuevamente; revise antes el diagnóstico y espacio disponible. No ejecute sincronizaciones simultáneas.

6. Abra el sistema y pruebe acceso, permisos, órdenes, presupuesto y documentos con un usuario administrador y uno limitado. No retire el servidor original hasta comparar conteos y casos representativos.
Después puede programar el comando con el Programador de tareas de Windows usando rutas absolutas a php.exe, al script y una cuenta con acceso a Avalon. Evite horarios de modificación de los archivos de origen.

## Respaldo completo: conservar todos los registros

Use las herramientas de MySQL, no el importador del navegador. Para tablas InnoDB, desde la terminal de Laragon:

~~~powershell
mysqldump --host=127.0.0.1 --user=root --password --single-transaction --quick --hex-blob --routines --triggers --result-file=C:/traslado/sisea-completo.sql avalon_seguimiento
~~~

Cambie usuario, nombre de base y ruta. La clave se solicita sin escribirla en el comando. No cambie estructuras durante el respaldo.
Puede comprimir el archivo después con una herramienta de archivos; no pase el SQL por una tubería de texto de PowerShell.

En el destino cree una base vacía y abra el cliente MySQL:

~~~powershell
mysql --host=127.0.0.1 --user=root --password nombre_base_nueva
~~~

Dentro del cliente:

~~~sql
SOURCE C:/traslado/sisea-completo.sql;
~~~

Este modo reemplaza el paso de database/install.php, conserva historial y evita reconstruir todo inicialmente. Nunca importe sobre la base de trabajo existente. Compruebe versiones compatibles de MySQL y suficiente espacio antes de restaurar.
El traslado liviano requiere acceso a los DBF: si solo dispone del SQL grande, utilice este respaldo/restauración completo.

## Verificación y seguridad operativa

Mantenga config/local.php y los respaldos fuera del acceso web. Use DocumentRoot public; pruebe que /config/local.php, /database/schema.sql y /storage/backups no son accesibles.
La configuración comprimida no está cifrada. Limite su acceso y retírela del servidor web tras importar.
El ZIP incluye un archivo SHA-256 para comprobar su integridad durante el traslado.
