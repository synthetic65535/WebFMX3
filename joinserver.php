<?php
/*
    При подключении к серверу клиент посылает этому скрипту GET'ом 3 поля::
      "session"  : "[]",
      "user"     : "Ник игрока",
      "serverId" : "-5dd86675917cd161b0d011aec899f236b3878c42"

    Требуется сравнить сгенерированные при авторизации session (accessToken) и user (ник игрока) с полученными в запросе,
    если успешно - записать в базу serverId для данного игрока и вернуть 'OK' если всё нормально и 'Bad login', если данные не совпали.
*/

    header('Content-Type: text/plain; charset=utf-8');

    include('webUtils/dbUtils.php');
    include('settings.php');

    function SendSuccessfulMessage() {
        exit('OK');
    }
    
    function SendBadLoginMessage() {
        exit('Bad login');
    }
    
    function SendErrorMessage($reason) {
        exit($reason);
    }

    // Получаем данные:
    $accessToken = filter_input(INPUT_GET, 'sessionId' , FILTER_SANITIZE_STRING);
    $username    = filter_input(INPUT_GET, 'user'    , FILTER_SANITIZE_STRING);
    $serverId    = filter_input(INPUT_GET, 'serverId', FILTER_SANITIZE_STRING);   
    
    // Создаём объект соединения с базой:
    $dbWorker = new DatabaseWorker();
    if ($dbWorker === null) {
        SendErrorMessage('Не удалось создать dbWorker!');
    }
    
    // Подключаемся к базе:
    if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
        SendErrorMessage('Не удалось подключиться к БД: '.$dbWorker->GetLastDatabaseError());
    }    
    
    $checkServerStatus = $dbWorker->DoJoinServer($tokensTableName, $accessToken, $username, $serverId);
    $dbWorker->CloseDatabase();
    
    switch ($checkServerStatus) {
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Database Error: Unknown database error');
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Database error: DB object not present');
        case DatabaseWorker::STATUS_CHECK_SERVER_USER_NOT_FOUND: SendBadLoginMessage();
        case DatabaseWorker::STATUS_CHECK_SERVER_SUCCESS: SendSuccessfulMessage();
        default: SendErrorMessage('Unknown checkserver status');
    }
?>