<?php
/*
    При подключении клиента сервер посылает этому скрипту JSON следующего содержания:
    {
        "username":"Ник игрока",
        "serverId":"-5dd86675917cd161b0d011aec899f236b3878c42"
    }

    Требуется проверить, есть ли в базе для данного ника данный serverId.
    Если есть, возвращаем JSON следующего содержания и делаем serverId невалидным:
    {
        "id":"UUID игрока",
        "name":"Ник игрока"
    }
    Если неуспешно - вернуть следующий JSON:
    {
        "error" : "Bad login",
        "errorMessage" : "Сообщение об ошибке"
    }
*/

    header('Content-Type: application/json; charset=utf-8');

    include('webUtils/dbUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    function SendErrorMessage($error, $errorMessage) {
        exit('{"error":"'.$error.'","errorMessage":"'.$errorMessage.'"}');
    }
    
    function SendSuccessfulMessage($uuid, $name, $profileInfo) {
        if ($profileInfo !== null) {
            exit('{"id":"'.$uuid.'","name":"'.$name.'","properties":[{"name":"textures","value":"'.$profileInfo.'","signature":"Cg=="}]}'); 
        } else {
            exit('{"id":"'.$uuid.'","name":"'.$name.'"}');
        }
    }

    // Получаем данные:
    $username = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING);
    $serverId = filter_input(INPUT_GET, 'serverId', FILTER_SANITIZE_STRING);
    $uuid     = null;

    // Создаём объект соединения с базой:
    $dbWorker = new DatabaseWorker();
    if ($dbWorker === null) {
        SendErrorMessage('dbWorker error!', 'Unable to create dbWorker!');
    }    

    // Подключаемся к базе:
    if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
        SendErrorMessage('dbWorker error!', 'Unable to connect to database!');
    }
    
    $hasJoinedStatus = $dbWorker->DoHasJoined($tokensTableName, $username, $serverId, $uuid);
    $dbWorker->CloseDatabase();
    
    switch ($hasJoinedStatus) {
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Database Error', 'Unknown database error');
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Database error', 'DB object not present');
        case DatabaseWorker::STATUS_HAS_JOINED_USER_NOT_FOUND: SendErrorMessage('Bad login', 'Bad login');
        case DatabaseWorker::STATUS_HAS_JOINED_SUCCESS: SendSuccessfulMessage($uuid, $username, GenerateProfileInfo($uuid, $username, $workingFolder, $skinsFolder, $cloaksFolder, $defSkinName, $defCloakName, $cloaksPostfix));
        default: SendErrorMessage('Unknown has joined status', 'Unknown has joined status');
    }    
?>