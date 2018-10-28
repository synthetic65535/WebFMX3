<?php
    /*
    Перед подключением к серверу клиент посылает этому скрипту JSON следующего содержания:
    {
        "accessToken":"[]",
        "selectedProfile":"00000000000000000000000000000000",
        "serverId":"-5dd86675917cd161b0d011aec899f236b3878c42"
    }
    
    Требуется сравнить сгенерированные при авторизации accessToken и selectedProfile (он же UUID) с полученными в JSON'e,
    если успешно - записать в базу serverId для данного игрока и вернуть JSON следующего содержания:
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
    include('settings.php');
    
    function SendErrorMessage($error, $errorMessage) {
        exit('{"error":"'.$error.'","errorMessage":"'.$errorMessage.'"}');
    }
    
    function SendSuccessfulMessage($uuid, $name) {
        exit('{"id":"'.$uuid.'","name":"'.$name.'"}');
    }
    
    $json = json_decode(file_get_contents('php://input'));
    if ($json === null) {
        SendErrorMessage('Invalid JSON', 'join.php received invalid JSON');
    }
    
    if (isset($json->accessToken)) $accessToken = $json->accessToken;
    if (isset($json->selectedProfile)) $uuid = $json->selectedProfile;
    if (isset($json->serverId)) $serverId = $json->serverId;
    $username = null;
    
    // Создаём объект соединения с базой:
    $dbWorker = new DatabaseWorker();
    if ($dbWorker === null) {
        SendErrorMessage('dbWorker error!', 'Unable to create dbWorker!');
    }
    
    // Подключаемся к базе:
    if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
        SendErrorMessage('dbWorker error!', 'Unable to connect to database!');
    }
    
    $joinStatus = $dbWorker->DoJoin($tokensTableName, $accessToken, $uuid, $serverId, $username);
    
    // Получаем ник в верном регистре:
    $caseValidationStatus = $dbWorker->GetValidCasedLogin($playersTableName, $playersColumnName, $username);
    if (($caseValidationStatus === $dbWorker::STATUS_QUERY_USER_NOT_FOUND) || ($username === null)) {
        SendErrorMessage('Valid case login extraction fault!', "Не удалось извлечь логин в верном регистре.\nТакое бывает если запустить два лаунчера одновременно.\nПерезапустите лаунчер.");
    }
    
    $dbWorker->CloseDatabase();
    
    switch ($joinStatus) {
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Database Error', 'Unknown database error');
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Database error', 'DB object not present');
        case DatabaseWorker::STATUS_JOIN_USER_NOT_FOUND: SendErrorMessage('Bad login', 'Bad login');
        case DatabaseWorker::STATUS_JOIN_SUCCESS: SendSuccessfulMessage($uuid, $username);
        default: SendErrorMessage('Unknown join status', 'Unknown join status');
    }
?>
