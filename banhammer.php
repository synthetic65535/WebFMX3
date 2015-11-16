<?php

    include('webUtils/dbUtils.php');
    include('settings.php');
    
    $banType  = strtolower(filter_input(INPUT_GET, 'bantype' , FILTER_SANITIZE_STRING)); // ban | unban
    $dataType = strtolower(filter_input(INPUT_GET, 'datatype', FILTER_SANITIZE_STRING)); // login | hwid
    $data     = strtolower(filter_input(INPUT_GET, 'data'    , FILTER_SANITIZE_STRING));
    
    // Создаём объект соединения с базой:
    $dbWorker = new DatabaseWorker();
    if ($dbWorker === null) {
        exit('Не удалось создать dbWorker!');
    }    
    
    // Подключаемся к базе:
    if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
        exit('Не удалось подключиться к БД: '.$dbWorker->GetLastDatabaseError());
    }
    
    // Всё, что не "unban", отправляет игрока в Вальхаллу:
    $banStatus = $banType !== 'unban';
    
    // Если "datatype" - не "hwid", то "data" воспринимаем как логин:
    $isHwidNeedToBeBanned = $dataType === 'hwid';
    
    // Баним или разбаниваем игрока:
    if ($isHwidNeedToBeBanned) {
        $status = $dbWorker->SetHwidBanStatus($hwidsTableName, $data, $banStatus);
    } else {
        $status = $dbWorker->SetPlayerHwidsBanStatus($hwidsTableName, $data, $banStatus);
    }
    
    // Оцениваем результат бана:
    if ($status) {
        echo 'Успешно!';    
    } else {
        echo 'Не удалось выполнить запрос! '.$dbWorker->GetLastDatabaseError();
    }
    
?>