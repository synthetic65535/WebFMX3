<?php

    header('Content-Type: application/json; charset=utf-8');

    include('webUtils/dbUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    // Отправить сообщение об ошибке и завершить скрипт:
    function SendErrorMessage($reason, $encryptionKey = '') {
        $errorMessage = array (
            'status' => 'error',
            'reason' => $reason
        );
        $encodedMessage = json_encode($errorMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        EncryptDecryptVerrnam($encodedMessage, strlen($encodedMessage), $encryptionKey, strlen($encryptionKey));
        echo $encodedMessage;
        exit;
    }
    
    // Получаем данные:
    $encodedLogin    = filter_input(INPUT_POST, 'login'   , FILTER_SANITIZE_STRING);
    $encodedPassword = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $hwid            = filter_input(INPUT_POST, 'hwid'    , FILTER_SANITIZE_STRING);
    
    // Расшифровываем логин и пароль:
    $login    = base64_decode(RepairBase64($encodedLogin));
    $password = base64_decode(RepairBase64($encodedPassword));
    EncryptDecryptVerrnam($login   , strlen($login)   , $encryptionKey, strlen($encryptionKey));
    EncryptDecryptVerrnam($password, strlen($password), $encryptionKey, strlen($encryptionKey));
    
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
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector', $encryptionKey);    
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса IsPlayerInBase: '.$dbWorker->GetLastDatabaseError(), $encryptionKey);
        case DatabaseWorker::STATUS_USER_NOT_EXISTS: SendErrorMessage('Неверный логин или пароль!', $encryptionKey);
    } 
    
    // Проверяем бан по HWID:
    if ($hwid !== null) {
        $banStatus = $dbWorker->IsHwidBanned($hwidsTableName, $hwid);
        switch ($banStatus) {
            // Проверяем возможные ошибки:
            case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector', $encryptionKey);
            case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса IsHwidBanned: '.$dbWorker->GetLastDatabaseError(), $encryptionKey);
            case DatabaseWorker::STATUS_USER_BANNED: SendErrorMessage('Пользователь забанен', $encryptionKey);
        }
        $dbWorker->AddHwidInBase($hwidsTableName, $login, $hwid);
    }
    
    // Генерируем авторизационные данные:
    $uuid        = GenerateUUID($login);
    $accessToken = md5($uuid.rand(0, 32767));
    $serverId    = 'null';
    
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
        'link32'  => $launcherLink32,
        'link64'  => $launcherLink64
    );
    
    // Формируем информацию о пользователе:
    $userInfo = array (
        'login'        => $login,
        'uuid'         => $uuid,
        'access_token' => $accessToken,
        'server_id'    => $serverId
    );
    
    $lowerLogin = strtolower($login); // Приводим логин к нижнему регистру
    
    // Добавляем ссылку на скин, если есть:
    if (file_exists($relativeSkinPath = $skinsFolder.'/'.$lowerLogin.'.png')) {
        $userInfo['skin'] = $workingFolder.'/'.$relativeSkinPath;
    } elseif (file_exists($relativeDefaultSkinPath = $skinsFolder.'/'.$defSkinName)) {
        $userInfo['skin'] = $workingFolder.'/'.$relativeDefaultSkinPath;
    }
    
    // Аналогично - для плаща:
    if (file_exists($relativeCloakPath = $cloaksFolder.'/'.$lowerLogin.$cloaksPostfix.'.png')) {
        $userInfo['cloak'] = $workingFolder.'/'.$relativeCloakPath;
    } elseif (file_exists($relativeDefaultCloakPath = $cloaksFolder.'/'.$defCloakName)) {
        $userInfo['cloak'] = $workingFolder.'/'.$relativeDefaultCloakPath;
    }
    
    // Общая структура ответа:
    $response = array (
        'status'        => 'success',
        'launcher_info' => $launcherInfo,
        'user_info'     => $userInfo
    );
    
    // Получаем содержимое файла с настройками клиентов и джавы:
    if (!file_exists($clientsSettingsFilePath)) {
        SendErrorMessage('Не найден JSON-файл настроек клиентов!', $encryptionKey);
    }
    $clientsSettingsJson = file_get_contents($clientsSettingsFilePath);
    $clientsSettings = json_decode($clientsSettingsJson);
    if ($clientsSettings === null) {
        SendErrorMessage('Некорректный формат JSON-файла настроек клиентов!', $encryptionKey);
    }
    $response['servers_info'] = $clientsSettings;
    
    $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    EncryptDecryptVerrnam($responseJson, strlen($responseJson), $encryptionKey, strlen($encryptionKey));
    echo $responseJson;
?>