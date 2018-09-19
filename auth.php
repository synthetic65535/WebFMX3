<?php
    header('Content-Type: application/json; charset=utf-8');
    
    include('webUtils/dbUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    //usleep(500000); // Чтобы ограничить скорость перебора паролей
    
    // Отправить сообщение об ошибке и завершить скрипт:
    function SendErrorMessage($reason, $encryptionKey = '') {
        $errorMessage = '{"status":"error","reason":"'.$reason.'"}';
        EncryptRijndael($errorMessage, $encryptionKey);
        echo $errorMessage;
        exit;
    }
    
    $data = file_get_contents("php://input");
    DecryptRijndael($data, $encryptionKey);
    
    if ($data == '') {
        SendErrorMessage('Некорректный запрос!', $encryptionKey);
    }
    
    $json = json_decode($data);
    
    if ($json === null) {
        //SendErrorMessage('Некорректный JSON!', $encryptionKey);
        SendErrorMessage("Пожалуйста, скачайте новую версию лаунчера на сайте\r\n(кнопка Начать Играть).", $encryptionKey);
    }
    
    $action = $json->action;
    $login = $json->login;
    $password = HexDecode($json->password);
    $hwid = $json->hwid;
    $token = $json->token;
    
    if (($action != 'preauth') && ($action != 'auth')) {
        SendErrorMessage('Некорректное действие!', $encryptionKey);
    }
    
    if ($action == 'preauth')
    {
    	// Запретить залогинивание в лаунчере для всех кроме администратора
        //if ($login != 'synthetic')
        //    SendErrorMessage('Извините, идут техработы. Приходите завтра.', $encryptionKey);
        
        if (LoginHasRestrictedSymbols($login)) {
            SendErrorMessage('Логин пустой или содержит недопустимые символы!', $encryptionKey);
        }
        
        // Создаём объект соединения с базой:
        $dbWorker = new DatabaseWorker();
        if ($dbWorker === null) {
            SendErrorMessage('Не удалось создать dbWorker!', $encryptionKey);
        }
        
        // Подключаемся к базе:
        if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
            SendErrorMessage('Не удалось подключиться к БД: '.$dbWorker->GetLastDatabaseError(), $encryptionKey);
        }
        
        // Ищем пользователя в базе:
        $authStatus = $dbWorker->IsPlayerInBase($playersTableName, $login, $password);
        switch ($authStatus) {
            // Проверяем возможные ошибки:
            case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector', $encryptionKey); break;
            case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса IsPlayerInBase: '.$dbWorker->GetLastDatabaseError(), $encryptionKey); break;
            case DatabaseWorker::STATUS_USER_NOT_EXISTS: SendErrorMessage('Неверный логин или пароль!', $encryptionKey); break;
            case DatabaseWorker::STATUS_USER_BANNED: SendErrorMessage('Пользователь забанен', $encryptionKey); break;
        }
        
        // Если такой пользователь существует, то выдаём ему token:
        
        $response = array (
            'status' => 'success',
            'token' => md5($_SERVER['REMOTE_ADDR'].':'.substr(date('Y-m-d-H-i-s'), 0, -1).':'.$tokenSalt)
        );
        
        $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        EncryptRijndael($responseJson, $encryptionKey);
        echo $responseJson;
    } else
    if ($action == 'auth')
    {
        if (LoginHasRestrictedSymbols($login)) {
            SendErrorMessage('Логин пустой или содержит недопустимые символы!', $encryptionKey);
        }
        
        if (strlen($hwid) < 71) { // COM(32 char):CHK(32 char)
            SendErrorMessage('Нет сведений об оборудовании!', $encryptionKey);
        }
        
        // Проверка HWID на наличие изменений
        $hwidСhecksum = substr($hwid, -32);
        $hwid = substr($hwid, 0, -36);
        if (md5($hwid) !== $hwidСhecksum) {
            SendErrorMessage('Неверные сведения об оборудовании!', $encryptionKey);
        }
        
        if (($token != md5($_SERVER['REMOTE_ADDR'].':'.substr(date('Y-m-d-H-i-s'), 0, -1).':'.$tokenSalt)) &&
            ($token != md5($_SERVER['REMOTE_ADDR'].':'.substr(date('Y-m-d-H-i-s', strtotime('-10 seconds')), 0, -1).':'.$tokenSalt))) {
            // Время действия токена: 10-20 секунд. В будущем желательно генерировать случайные токены и хранить их в табличке.
            SendErrorMessage('Токен устарел. Пожалуйста, перезапустите лаунчер.', $encryptionKey);
        }
        
        // Создаём объект соединения с базой:
        $dbWorker = new DatabaseWorker();
        if ($dbWorker === null) {
            SendErrorMessage('Не удалось создать dbWorker!', $encryptionKey);
        }
        
        // Подключаемся к базе:
        if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
            SendErrorMessage('Не удалось подключиться к БД: '.$dbWorker->GetLastDatabaseError(), $encryptionKey);
        }
        
        // Ищем пользователя в базе:
        $authStatus = $dbWorker->IsPlayerInBase($playersTableName, $login, $password);
        switch ($authStatus) {
            // Проверяем возможные ошибки:
            case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector', $encryptionKey); break;
            case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса IsPlayerInBase: '.$dbWorker->GetLastDatabaseError(), $encryptionKey); break;
            case DatabaseWorker::STATUS_USER_NOT_EXISTS: SendErrorMessage('Неверный логин или пароль!', $encryptionKey); break;
            case DatabaseWorker::STATUS_USER_BANNED: SendErrorMessage('Пользователь забанен', $encryptionKey); break;
        }
        
        // Получаем ник в верном регистре:
        $caseValidationStatus = $dbWorker->GetValidCasedLogin($playersTableName, $playersColumnName, $login);
        if (($caseValidationStatus === $dbWorker::STATUS_QUERY_USER_NOT_FOUND) || ($login === null)) {
            SendErrorMessage('Не получилось извлечь логин в верном регистре!', $encryptionKey);
        }
        
        if ($hwid !== null) {
            $banStatus = $dbWorker->IsHwidStrBanned($hwidsTableName, $hwid);
            switch ($banStatus) {
                // Проверяем возможные ошибки:
                case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector', $encryptionKey); break;
                case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса IsHwidBanned: '.$dbWorker->GetLastDatabaseError(), $encryptionKey); break;
                case DatabaseWorker::STATUS_NO_HWID: SendErrorMessage('Нет сведений об оборудовании. Попробуйте запустить лаунчер с правами администратора либо установите другую версию Windows.', $encryptionKey); break; // Если прислан мусор вместо hwid, посылаем
                case DatabaseWorker::STATUS_USER_BANNED:
                    $dbWorker->AddHwidStrInBase($hwidsTableName, $login, $hwid, 1); // Автоматически добавляем и баним баним новые hwid пользователя
                    $dbWorker->SetHwidStrBanStatus($hwidsTableName, $hwid, 1); // И баним все старые hwid
                    SendErrorMessage('Пользователь забанен', $encryptionKey);
                    break;
            }
            
            // Не добавлять HWID этих пользователей в БД
            //if ($login != 'synthetic')
            //    $dbWorker->AddHwidStrInBase($hwidsTableName, $login, $hwid, 0);
        } else SendErrorMessage('Нет сведений об оборудовании!', $encryptionKey); // Если прислан мусор вместо hwid
        
        // Генерируем авторизационные данные:
        $uuid = GenerateUUID($login);
        $accessToken = md5($uuid.rand(0, 32767));
        $serverId = 'null';
        
        // Добавляем игрока в список авторизованных игроков:
        $insertionStatus = $dbWorker->InsertPlayerToAuthorizedPlayersList($tokensTableName, $login, $uuid, $accessToken, $serverId);
        if (!$insertionStatus) {
            SendErrorMessage('Ошибка при внесении игрока в список авторизованных игроков: '.$dbWorker->GetLastDatabaseError(), $encryptionKey);
        }
        
        $dbWorker->CloseDatabase();
        
        // Возвращаем информацию о пользователе, клиентах, джаве и лаунчере:
        
        // Формируем информацию о лаунчере:
        $launcherInfo = array (
            'version' => $launcherMinVersion,
            'link32' => $launcherLink32,
            'link64' => $launcherLink64
        );
        
        // Формируем информацию о пользователе:
        $userInfo = array (
            'login' => $login,
            'uuid' => $uuid,
            'access_token' => $accessToken,
            'server_id' => $serverId
        );
        
        // Добавляем ссылку на скин, если есть:
        if (file_exists($relativeSkinPath = $skinsFolder.'/'.$login.'.png')) {
            $userInfo['skin'] = $workingFolder.'/'.$relativeSkinPath;
        } elseif (file_exists($relativeDefaultSkinPath = $skinsFolder.'/'.$defSkinName)) {
            $userInfo['skin'] = $workingFolder.'/'.$relativeDefaultSkinPath;
        }
        
        // Аналогично - для плаща:
        if (file_exists($relativeCloakPath = $cloaksFolder.'/'.$login.$cloaksPostfix.'.png')) {
            $userInfo['cloak'] = $workingFolder.'/'.$relativeCloakPath;
        } elseif (file_exists($relativeDefaultCloakPath = $cloaksFolder.'/'.$defCloakName)) {
            $userInfo['cloak'] = $workingFolder.'/'.$relativeDefaultCloakPath;
        }
        
        // Общая структура ответа:
        $response = array (
            'status' => 'success',
            'launcher_info' => $launcherInfo,
            'user_info' => $userInfo
        );
        
        // Получаем содержимое файла с настройками клиентов и джавы:
        if (!file_exists($clientsSettingsFilePath)) {
            SendErrorMessage('Не найден JSON-файл настроек клиентов!', $encryptionKey);
        }
        $clientsSettingsJson = file_get_contents($clientsSettingsFilePath);
        $clientsSettingsJson = str_replace(array("\r", "\n", "\t"), '', $clientsSettingsJson);
        $clientsSettings = json_decode($clientsSettingsJson);
        if ($clientsSettings === null) {
            SendErrorMessage('Некорректный формат JSON-файла настроек клиентов!', $encryptionKey);
        }
        $response['servers_info'] = $clientsSettings;
        
        $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Эти пользователи могут устанавливать себе любые моды
        //if (($login == 'synthetic') || ($login == 'Mokko') || ($login == 'Cookiezi') || ($login == 'Diesel') || ($login == 'laza') || ($login == 'MacedoniaN') || ($login == 'PlayMan'))
        //{
        //    $responseJson = str_replace('"mods"', '"dummy"', $responseJson);
        //}
        
        EncryptRijndael($responseJson, $encryptionKey);
        echo $responseJson;
    }
?>
