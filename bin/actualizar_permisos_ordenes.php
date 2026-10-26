<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/app.php';
require APP_ROOT.'/config/database.php';
// Same idempotent upgrade performed at sign-in, without editing existing grants.
UsuarioModel::instalarPermisosDocumentales();
echo "Configuración de Órdenes instalada. Se conservan los accesos anteriores.\n";
