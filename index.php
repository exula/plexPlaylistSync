<?php

require 'vendor/autoload.php';
require 'PlexApi.php';

use exula\PlexApi;

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Existing files

$cmd = 'find '.escapeshellarg($_ENV['SYNC_DESTINATION']).' -type f';
$existingfiles = '';
exec($cmd, $existingfiles);

$client = new PlexApi($_ENV['PLEX_SERVER']);
$client->setAuth($_ENV['PLEX_USERNAME'], $_ENV['PLEX_PASSWORD']);
$client->setToken($_ENV['PLEX_TOKEN']);

$media = $client->getPlaylist($_ENV['SYNC_PLAYLIST']);

$i = 1;
$z = count($media);

$total = 0;
//Total size
foreach($media as $download)
{
    $total += $download['size'];
}

$bytesize = new \Rych\ByteSize\ByteSize;
echo "Requires a total size of: ". $bytesize->format($total)."\n";

foreach($media as $download) {

    $finalDir = $_ENV['SYNC_DESTINATION'] . $download['directory'];

    if(!is_dir($finalDir)) {
        if (!mkdir($finalDir, 0777, true) && !is_dir($finalDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $finalDir));
        }
    }

    unset($existingfiles[array_search($finalDir . "/" . $download['filename'], $existingfiles)]);

    if(!is_file($finalDir.'/'.$download['filename'])) {
        echo "[$i/$z] Downloading ".$download['filename']."...";




        $fh = fopen($finalDir . "/" . $download['filename'], 'w');

        if(!$_ENV['TOUCH_ONLY']) {
            $client->downloadFile($download['download'], $fh);
        } else {
            fwrite($fh, '');
            fclose($fh);
        }
        echo "Done! \n";
    }

    $i++;
}

if(count($existingfiles) > 0) {
    echo "Removing extra files \n";
}

foreach($existingfiles as $key => $value) {
    echo "Removing ".$value."\n";
    unlink($value);
}

