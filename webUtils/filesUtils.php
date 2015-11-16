<?php

    const QUERY_FILES   = 1; // Получить только список файлов
    const QUERY_FOLDERS = 2; // Получить только список папок
    const QUERY_ALL     = 3; // Получить список и файлов, и папок

    function GetFilesList($folder, &$fileList, $scanSubfolders = true, $calculateHash = true, $queryType = QUERY_FILES) {
        $dir = opendir($folder); // Открываем дескриптор папки

    	// Читаем содержимое папки:
        while (false !== ($file = readdir($dir))) {
            if ($folder != '.') {
    		$filename = $folder.'/'.$file;
            } else {
                $filename = $file;
            }
            
            if (is_file($filename)) {			
                // Получаем информацию о файле:
                if (($queryType & QUERY_FILES) == QUERY_FILES) {
            
                    if ($calculateHash) {
                        $fileInfo = array (
                            'name' => $file,
                            'size' => filesize($filename),
                            'hash' => md5_file($filename),
                            'path' => $filename
                        );
                    } else {
                        $fileInfo = array (
                            'name' => $file,
                            'size' => filesize($filename),
                            'path' => $filename
                        );					
                    }
                    
                    $fileList[] = $fileInfo;
                }
                
            } elseif ($file != '.' && $file != '..' && is_dir($filename)) {
                
                if (($queryType & QUERY_FOLDERS) == QUERY_FOLDERS) {
                    $directoryInfo = array (
                        'name' => $file,
                        'path' => $filename
                    );
                    $fileList[] = $directoryInfo;
                }

                if ($scanSubfolders) {
                    GetFilesList($filename, $fileList, $scanSubfolders);
                }		
            }				
        }
		
        closedir($dir); // Закрываем дескриптор папки
    }

    
    function GenerateFullFileList($targetDir, &$generatedFilesList = null) {
        // Перечисляем список папок в папке $targetDir:
        $folders = array();
        GetFilesList($targetDir, $folders, false, false, QUERY_FOLDERS);

        // Получаем отдельный список файлов для каждой папки из списка:
        foreach ($folders as $folder) {
            $filesList = array();
            GetFilesList($folder['path'], $filesList);

            // Сохраняем в отдельный файл:
            $files = array(
                'files' => $filesList
            );

            $filename = $targetDir.'/'.$folder['name'].'.json';
            file_put_contents($filename, json_encode($files, JSON_UNESCAPED_SLASHES));
            
            if (isset($generatedFilesList)) {
                $generatedFilesList[] = $filename;
            }
        }
    }
    
    
    function GenerateFileList($targetDir) {
        $filesList = array();
        GetFilesList($targetDir, $filesList);

        // Сохраняем в отдельный файл:
        $files = array(
            'files' => $filesList
        );

        $filename = $targetDir.'.json';
        file_put_contents($filename, json_encode($files, JSON_UNESCAPED_SLASHES));

        return $filename;
    }
    
?>