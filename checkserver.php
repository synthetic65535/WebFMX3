<?php

/*
    При подключении клиента сервер посылает этому скрипту GET'ом 2 поля:
      "user"     : "Ник игрока",
      "serverId" : "-5dd86675917cd161b0d011aec899f236b3878c42"
  
    Требуется проверить, есть ли в базе для данного ника данный serverId.
    Если есть, возвращаем 'YES', если нет - 'NO'
*/

    header('Content-Type: text/plain; charset=utf-8');

    include('webUtils/dbUtils.php');
    include('settings.php');

    function SendSuccessfulMessage() {
        exit('YES');
    }
    
    function SendBadLoginMessage() {
        exit('NO');
    }
    
    function SendErrorMessage($reason) {
        exit($reason);
    }
       
    // Получаем данные:
    $username = filter_input(INPUT_GET, 'user'    , FILTER_SANITIZE_STRING);
    $serverId = filter_input(INPUT_GET, 'serverId', FILTER_SANITIZE_STRING);

    // Создаём объект соединения с базой:
    $dbWorker = new DatabaseWorker();
    if ($dbWorker === null) {
        SendErrorMessage('Не удалось создать dbWorker!');
    }
    
    // Подключаемся к базе:
    if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
        SendErrorMessage('Не удалось подключиться к БД: '.$dbWorker->GetLastDatabaseError());
    }    
    
    $checkServerStatus = $dbWorker->DoCheckServer($tokensTableName, $username, $serverId);
    $dbWorker->CloseDatabase();
    
    switch ($checkServerStatus) {
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Database Error: Unknown database error');
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Database error: DB object not present');
        case DatabaseWorker::STATUS_CHECK_SERVER_USER_NOT_FOUND: SendBadLoginMessage();
        case DatabaseWorker::STATUS_CHECK_SERVER_SUCCESS: SendSuccessfulMessage();
        default: SendErrorMessage('Unknown checkserver status');
    }
    
?>