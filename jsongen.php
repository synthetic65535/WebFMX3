<?php
    
    header('Content-Type: application/json; charset=utf-8');
    include('webUtils/filesUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    
    if ($password !== $jsonGenPassword)
    {
        header('Content-type: text/html; charset=utf-8');
	    echo '<html><body><form action="#" method=post>Password: <input type="text" name="password"> <input type="submit" value="Generate JSON"></form></body></html>';
	} else {
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
        
        echo 'Готово!';
	}
?>
