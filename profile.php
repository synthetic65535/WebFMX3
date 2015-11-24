<?php
/*
    При входе на сервер выполняется этот скрипт, которому GET'ом передаётся UUID игрока в поле 'uuid':
    {
        "uuid" : "UUID"
    }

    Требуется вернуть текущее время, UUID, ник, ссылки на скин и плащ:
    {
        "id"   : "UUID игрока",
        "name" : "Ник игрока",
        "properties": [
            {
                "name"  : "textures",
                "value" : base64_encode(
                    "timestamp"   : "текущее время",
                    "profileId"   : "UUID",
                    "profileName" : "Ник игрока",
					
                    "textures" : "base64_encode({
                        "SKIN" : {
                            "url" : "http://site.ru/folder/skin.png"
                        },
                        "CAPE" : {
                            "url" : "http://site.ru/folder/cloak.png"
                        }
                    })"
                )	
            }
        ]
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
            exit('{"id":"'.$uuid.'","name":"'.$name.'","properties":[{"name":"textures","value":"'.$profileInfo.'"}]}');
        } else {
            exit('{"id":"'.$uuid.'","name":"'.$name.'"}');
        }
    }    

    $uuid = filter_input(INPUT_GET, 'uuid', FILTER_SANITIZE_STRING);

    // Создаём объект соединения с базой:
    $dbWorker = new DatabaseWorker();
    if ($dbWorker === null) {
        SendErrorMessage('dbWorker error!', 'Unable to create dbWorker!');
    }    

    // Подключаемся к базе:
    if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
        SendErrorMessage('dbWorker error!', 'Unable to connect to database!');
    }

    // Получаем имя игрока по его UUID:
    $username = null;
    $queryStatus = $dbWorker->GetUsernameByUUID($tokensTableName, $uuid, $username);

    // Получаем ник в верном регистре:
    $caseValidationStatus = $dbWorker->GetValidCasedLogin($playersTableName, $playersColumnName, $username);
    if (($caseValidationStatus === $dbWorker::STATUS_QUERY_USER_NOT_FOUND) || ($username === null)) {
        SendErrorMessage('Valid case login extraction fault!', 'Unable to extract valid-cased username!');
    }

    switch($queryStatus) {
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Database Error', 'Unknown database error');
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Database error', 'DB object not present');
        case DatabaseWorker::STATUS_QUERY_USER_NOT_FOUND: SendErrorMessage('User not found', 'User not found');
        case DatabaseWorker::STATUS_QUERY_SUCCESS: SendSuccessfulMessage($uuid, $username, GenerateProfileInfo($uuid, $username, $workingFolder, $skinsFolder, $cloaksFolder, $defSkinName, $defCloakName, $cloaksPostfix));
        default: SendErrorMessage('Unknown profile status', 'Unknown profile status');
    }
?>