<?php

    header('Content-Type: application/json; charset=utf-8');

    include('webUtils/dbUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    // Максимальные размеры изображения: // было 300 кб, стало 100
    const MAX_SIZE = 1024 * 100;
    const MAX_SCALING_COEFF = 4; // 16 для 1024x512
    const STD_WIDTH  = 64;
    const STD_HEIGHT = 32;
    const VALID_ASPECT_RATIO = STD_WIDTH / STD_HEIGHT;
    const ALLOW_MINECRAFT_1_8_SKINS = true;
    const MAX_WIDTH  = STD_WIDTH  * MAX_SCALING_COEFF;
    const MAX_HEIGHT = STD_HEIGHT * MAX_SCALING_COEFF;

    // Индексы в массиве getimagesize:
    const WIDTH      = 0;
    const HEIGHT     = 1;
    const IMAGE_TYPE = 2;
    
    function SendErrorMessage($errorReason) {
        exit('{"status":"error","reason":"'.$errorReason.'"}');
    }
    
    function SendSuccessfulMessage($encodedImage = null) {
        if ($encodedImage === null) {
            exit('{"status":"success"}');
        } else {
            exit('{"status":"success","image":"'.$encodedImage.'"}');
        }
    }
    
    function GetImage($workingFolder, $objectName, $defObjectName, &$encodedImage, $returnDefaultImage = true) {
        if (file_exists($imagePath = $workingFolder.'/'.$objectName)) {
            $encodedImage = base64_encode(file_get_contents($imagePath));
            return true;
        } elseif ($returnDefaultImage && file_exists($imagePath = $workingFolder.'/'.$defObjectName)) {
            $encodedImage = base64_encode(file_get_contents($imagePath));
            return true;
        } else {
            $encodedImage = null;
            return false;
        }
    }
    
    $encodedLogin    = filter_input(INPUT_POST, 'login'     , FILTER_SANITIZE_STRING);
    $encodedPassword = filter_input(INPUT_POST, 'password'  , FILTER_SANITIZE_STRING);
    $action          = filter_input(INPUT_POST, 'action'    , FILTER_SANITIZE_STRING); // setup/delete/download
    $imageType       = filter_input(INPUT_POST, 'image_type', FILTER_SANITIZE_STRING); // skin/cloak
    
    // Расшифровываем логин и пароль:
    $login    = base64_decode(RepairBase64($encodedLogin));
    $password = base64_decode(RepairBase64($encodedPassword));
    EncryptDecryptVerrnam($login   , strlen($login)   , $encryptionKey, strlen($encryptionKey));
    EncryptDecryptVerrnam($password, strlen($password), $encryptionKey, strlen($encryptionKey));
    
    if (LoginHasRestrictedSymbols($login)) {
        SendErrorMessage('Логин пустой или содержит недопустимые символы!');
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
    
    // Ищем пользователя в базе:
    $authStatus = $dbWorker->IsPlayerInBase($playersTableName, $login, $password);
    switch ($authStatus) {
        // Проверяем возможные ошибки:
        case DatabaseWorker::STATUS_DB_OBJECT_NOT_PRESENT: SendErrorMessage('Не создан объект dbConnector'); break;
        case DatabaseWorker::STATUS_DB_ERROR: SendErrorMessage('Ошибка при выполнении запроса IsPlayerInBase: '.$dbWorker->GetLastDatabaseError()); break;
        case DatabaseWorker::STATUS_USER_NOT_EXISTS: SendErrorMessage('Неверный логин или пароль!'); break;
        case DatabaseWorker::STATUS_USER_BANNED: SendErrorMessage('Пользователь забанен'); break;
    }
    
    // Получаем ник в верном регистре:
    $caseValidationStatus = $dbWorker->GetValidCasedLogin($playersTableName, $playersColumnName, $login);
    if (($caseValidationStatus === $dbWorker::STATUS_QUERY_USER_NOT_FOUND) || ($login === null)) {
        SendErrorMessage('Не получилось извлечь логин в верном регистре!');
    }
    
    $dbWorker->CloseDatabase();
    
    switch ($imageType) {
        case 'skin':
            $workingFolder = $skinsFolder;
            $objectName    = $login.'.png';
            $defObjectName = $defSkinName;
            break;
        case 'cloak':
            $workingFolder = $cloaksFolder;
            $objectName    = $login.$cloaksPostfix.'.png';
            $defObjectName = $defCloakName;
            break;
        default: SendErrorMessage('Неизвестный тип рабочего объекта!');
    }
    
    
    switch ($action) {
        case 'setup':
            $uploadedFile = $_FILES['image'];
            if ($uploadedFile === null) {SendErrorMessage('Изображение не загружено!');}
            if (!is_uploaded_file($uploadedFile['tmp_name'])) {SendErrorMessage('Полученное изображение - не загруженный объект!');}
            if ($uploadedFile['size'] > MAX_SIZE) {SendErrorMessage('Изображение превышает максимальный допустимый размер! Максимальный размер '.(MAX_SIZE / 1024).' КБ.');}
            
            // Проверяем, действительно ли загрузили PNG:
            $imageInfo = getimagesize($uploadedFile['tmp_name']);
            if ($imageInfo === null) {SendErrorMessage('Информация об изображении не получена!');}
            if ($imageInfo[IMAGE_TYPE] !== IMAGETYPE_PNG) {SendErrorMessage('Полученный файл - не PNG!');}
            
            // Проверяем соотношения сторон и габариты:
            $width  = $imageInfo[WIDTH];
            $height = $imageInfo[HEIGHT];
            
            $aspectRatio = $width / $height;
            if ((($imageType == 'skin') && ($aspectRatio !== VALID_ASPECT_RATIO && !(ALLOW_MINECRAFT_1_8_SKINS && $aspectRatio == 1))) ||
                (($imageType == 'cloak') && ($aspectRatio !== VALID_ASPECT_RATIO))) {SendErrorMessage('Неверное соотношение сторон изображения!');}
            
            if (($width > MAX_WIDTH) || ($height > MAX_HEIGHT)) {SendErrorMessage('Превышен максимальный размер изображения! Максимальный размер '.MAX_WIDTH.'x'.MAX_HEIGHT);}
            
            // Все проверки пройдены, сохраняем скин/плащ:
            move_uploaded_file($uploadedFile['tmp_name'], $workingFolder.'/'.$objectName);
            SendSuccessfulMessage();
            break;
            
        case 'delete':
            $objectPath = $workingFolder.'/'.$objectName; 
            if (file_exists($objectPath)) {unlink($objectPath);}
            GetImage($workingFolder, $objectName, $defObjectName, $encodedImage);
            SendSuccessfulMessage($encodedImage);
            break;
            
        case 'download':
            if (GetImage($workingFolder, $objectName, $defObjectName, $encodedImage, false)) {
                SendSuccessfulMessage($encodedImage);
            } else {
                switch ($imageType) {
                    case 'skin'  : SendErrorMessage('Скин не установлен!');
                    case 'cloak' : SendErrorMessage('Плащ не установлен!');
                }
            }
            break;
        
        default:
            SendErrorMessage('Неизвестное действие!');
            break;
    }
?>