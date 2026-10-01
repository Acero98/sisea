<?php
declare(strict_types=1);

$settings = require (is_file(__DIR__.'/local.php') ? __DIR__.'/local.php' : __DIR__.'/local.example.php');
foreach (['host'=>'SISEA_DB_HOST','port'=>'SISEA_DB_PORT','database'=>'SISEA_DB_NAME','username'=>'SISEA_DB_USER','password'=>'SISEA_DB_PASSWORD','app_url'=>'SISEA_APP_URL','avalon_logistica'=>'AVALON_LOGISTICA_DATA','avalon_planif'=>'AVALON_PLANIF_DATA','avalon_contab'=>'AVALON_CONTAB_DATA','avalon_finanzas'=>'AVALON_FINANZAS_DATA'] as $key=>$env) {
    $value = getenv($env);
    if ($value !== false) $settings[$key] = $value;
}
return $settings;
