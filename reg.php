<?php

    header('Content-Type: application/json; charset=utf-8');

    include('webUtils/dbUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    function SendErrorMessage($errorReason) {
        exit('{"status":"error","reason":"'.$errorReason.'"}');
    }
    
    function SendSuccessfulMessage() {
        exit('{"status":"success"}');
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
    
    if (HasRestrictedSymbols($login.$password)) {
        SendErrorMessage('Логин и/или пароль пустые или содержат недопустимые символы!');
    }
    
    // Создаём объект соединения с базой:
    $dbWorker = new DatabaseWorker();
    if ($dbWorker === null) {
        SendErrorMessage('Не удалось создать dbWorker!');
    }
    
    // Подключаемся к базе:
    if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
        SendErrorMessage('Не удалось подключиться к БД: '.$dbWorker->GetLastDatabaseError());
    }

    // Проверяем бан по HWID:
    if ($hwid !== null) {
        $banStatus = $dbWorker->IsHwidStrBanned($hwidsTableName, $hwid);
        switch ($banStatus) {
            // Проверяем возможные ошибки:
            case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector');
            case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса IsHwidBanned: '.$dbWorker->GetLastDatabaseError());
            case DatabaseWorker::STATUS_NO_HWID: SendErrorMessage('Нет сведений об оборудовании. Возможно у вас старый лаунчер, скачайте новый.', $encryptionKey); break; //если прислан мусор вместо hwid, посылаем
            case DatabaseWorker::STATUS_USER_BANNED: SendErrorMessage('HWID забанен!');
        }
        $dbWorker->AddHwidStrInBase($hwidsTableName, $login, $hwid, 0);
    }
    else SendErrorMessage('Нет сведений об оборудовании. Возможно у вас старый лаунчер, скачайте новый.', $encryptionKey); //если прислан мусор вместо hwid, посылаем
    
    
    $regStatus = $dbWorker->InsertPlayerInBase($playersTableName, $login, $password);
    $dbWorker->CloseDatabase();
    switch ($regStatus) {
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector');    
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса InsertPlayerInBase: '.$dbWorker->GetLastDatabaseError());
        case DatabaseWorker::STATUS_REG_USER_ALREADY_EXISTS: SendErrorMessage('Пользователь уже есть в базе!');
        case DatabaseWorker::STATUS_REG_SUCCESS: SendSuccessfulMessage();
    }
?>
