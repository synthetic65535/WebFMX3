<?php

    header('Content-Type: application/json; charset=utf-8');
    include('webUtils/filesUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    $generatedFilesList = array();
    GenerateFullFileList($clientsFolder, $generatedFilesList);
    GenerateFullFileList($javaFolder, $generatedFilesList);
    
    $keyLength = strlen($encryptionKey);
    foreach ($generatedFilesList as $fileslistPath) {
        $data = file_get_contents($fileslistPath);
        EncryptDecryptVerrnam($data, strlen($data), $encryptionKey, $keyLength);
        file_put_contents($fileslistPath, $data);
        echo 'Сгенерировано и зашифровано: '.$fileslistPath."\r\n";
    }
    
    echo 'Готово!'
    
?>
