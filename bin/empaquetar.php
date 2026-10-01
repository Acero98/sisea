<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
try {
    $root=dirname(__DIR__); $dir=$root.'/dist';
    if(!is_dir($dir)) mkdir($dir,0770,true);
    $path=$dir.'/sisea-'.date('Ymd-His').'.zip';
    $zip=new ZipArchive();
    if($zip->open($path,ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new RuntimeException('No se pudo crear ZIP.');
    foreach(['app','public','bin','config','database/migrations','docs'] as $folder) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS)) as $file) {
            if(!$file->isFile() || $file->isLink()) continue;
            $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
            if(preg_match('~(^|/)\.~',$relative)) continue;
            if($folder==='public' && $relative!=='public/index.php' && (strpos($relative,'public/assets/')!==0 || !in_array(strtolower(pathinfo($relative,PATHINFO_EXTENSION)),['css','js','woff','woff2','ttf','eot','svg','png','jpg','jpeg','ico','webp','map'],true))) continue;
            if($folder==='config' && !in_array(basename($relative),['app.php','database.php','settings.php','local.example.php'],true)) continue;
            if(!$zip->addFile($file->getPathname(),'sisea/'.$relative)) throw new RuntimeException('No se pudo agregar '.$relative);
        }
    }
    foreach(['index.php','.htaccess','README.md','database/schema.sql','database/install.php'] as $file) {
        if(!$zip->addFile($root.'/'.$file,'sisea/'.$file)) throw new RuntimeException('No se pudo agregar '.$file);
    }
    foreach(['storage/cache','storage/sessions','storage/backups'] as $folder) $zip->addEmptyDir('sisea/'.$folder);
    if(!$zip->close()) throw new RuntimeException('ZIP incompleto.');
    file_put_contents($path.'.sha256',hash_file('sha256',$path).'  '.basename($path)."\n");
    echo "Paquete: $path\nSin base de datos, credenciales locales ni sesiones.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
