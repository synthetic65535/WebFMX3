<?php
    
    //-----------------------------------------------------
    
    function dirToArray($dir)
    {
        $result = array();
        $cdir = scandir($dir);
        foreach ($cdir as $key => $value)
        {
            if (!in_array($value,array(".","..")))
                if (!(is_dir($dir.DIRECTORY_SEPARATOR.$value)))
                    $result[] = $dir.DIRECTORY_SEPARATOR.$value;
        }
        return $result;
    }
    
    //-----------------------------------------------------
    
    header('Content-Type: application/json; charset=utf-8');
    
    include('webUtils/dbUtils.php');
    include('webUtils/auxUtils.php');
    include('settings.php');
    
    //usleep(500000); // Чтобы ограничить скорость перебора паролей
    
    // Максимальные размеры изображения: // было 300 кб, стало 100
    const MAX_SIZE = 1024 * 100;
    const MAX_SCALING_COEFF = 4; // 16 для 1024x512
    const STD_WIDTH  = 64;
    const STD_HEIGHT = 32;
    const MAX_WIDTH  = STD_WIDTH  * MAX_SCALING_COEFF;
    const MAX_HEIGHT = STD_HEIGHT * MAX_SCALING_COEFF;
    const CHECK_COPYRIGHT = true; // Запрещать ставить два одинаковых скина
    const ALLOW_MINECRAFT_1_8_SKINS = true; // Разрешить скины стандарта Minecraft 1.8+
    const DIFFERENCE_THRESHOLD = 100; // Сколько пикселей у двух разных скинов могут совпадать
    
    // Индексы в массиве getimagesize:
    const WIDTH      = 0;
    const HEIGHT     = 1;
    const IMAGE_TYPE = 2;
    
    function SendErrorMessage($errorReason, $encryptionKey = '') {
        $errorMessage = '{"status":"error","reason":"'.$errorReason.'"}';
        EncryptRijndael($errorMessage, $encryptionKey);
        echo $errorMessage;
        exit;
    }
    
    function SendSuccessfulMessage($encryptionKey = '', $encodedImage = null) {
        if ($encodedImage === null) {
            $successMessage = '{"status":"success"}';
        } else {
            $successMessage = '{"status":"success","image":"'.$encodedImage.'"}';
        }
        EncryptRijndael($successMessage, $encryptionKey);
        echo $successMessage;
        exit;
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
    $login    = HexDecode($encodedLogin);
    $password = HexDecode($encodedPassword);
    DecryptRijndael($login, $encryptionKey);
    DecryptRijndael($password, $encryptionKey);
    
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
    
    // Получаем ник в верном регистре:
    $caseValidationStatus = $dbWorker->GetValidCasedLogin($playersTableName, $playersColumnName, $login);
    if (($caseValidationStatus === $dbWorker::STATUS_QUERY_USER_NOT_FOUND) || ($login === null)) {
        SendErrorMessage('Не получилось извлечь логин в верном регистре!', $encryptionKey);
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
        default: SendErrorMessage('Неизвестный тип рабочего объекта!', $encryptionKey);
    }
    
    
    switch ($action) {
        case 'setup':
            $uploadedFile = $_FILES['image'];
            if ($uploadedFile === null) {SendErrorMessage('Изображение не загружено!', $encryptionKey);}
            if (!is_uploaded_file($uploadedFile['tmp_name'])) {SendErrorMessage('Полученное изображение - не загруженный объект!', $encryptionKey);}
            if ($uploadedFile['size'] > MAX_SIZE) {SendErrorMessage('Изображение превышает максимальный допустимый размер! Максимальный размер '.(MAX_SIZE / 1024).' КБ.', $encryptionKey);}
            
            // Проверяем, действительно ли загрузили PNG:
            $imageInfo = getimagesize($uploadedFile['tmp_name']);
            if ($imageInfo === null) {SendErrorMessage('Информация об изображении не получена!', $encryptionKey);}
            if ($imageInfo[IMAGE_TYPE] !== IMAGETYPE_PNG) {SendErrorMessage('Полученный файл - не PNG!', $encryptionKey);}
            
            // Проверяем соотношения сторон и габариты:
            $width  = $imageInfo[WIDTH];
            $height = $imageInfo[HEIGHT];
            
            $aspectRatio = $width / $height;
            
            if ($imageType == 'skin')
            {
                $validSize = false;
                for ($coeff = 1; $coeff <= MAX_SCALING_COEFF; $coeff++)
                {
                    // Стандартные скины
                    if (($width == ($coeff * STD_WIDTH)) && ($height == ($coeff * STD_HEIGHT)))
                    {
                        $validSize = true;
                    }
                    
                    // Скины Minecraft 1.8+
                    if ((ALLOW_MINECRAFT_1_8_SKINS) && (($width == ($coeff * STD_WIDTH)) && ($height == ($coeff * STD_WIDTH))))
                    {
                        $validSize = true;
                    }
                }
                
                if (!$validSize)
                {
                    SendErrorMessage('Изображение скина имеет неверные размеры!', $encryptionKey);
                }
            } else 
            if ($imageType == 'cloak')
            {
                $validSize = false;
                
                if ($aspectRatio !== VALID_ASPECT_RATIO) // Сюда вписать всевозможные сочетания ширины и высоты изображения плаща
                {
                    $validSize = true;
                }
                
                if (!$validSize)
                {
                    SendErrorMessage('Изображение плаща имеет неверные размеры!', $encryptionKey);
                }
            } else {
                SendErrorMessage('Не выбрана цель: скин или плащ.', $encryptionKey);
            }
            
            if (($width > MAX_WIDTH) || ($height > MAX_HEIGHT)) {SendErrorMessage('Превышен максимальный размер изображения! Максимальный размер '.MAX_WIDTH.'x'.MAX_HEIGHT, $encryptionKey);}
            
            // Проверяем есть ли уже такой скин на сервере
            if (CHECK_COPYRIGHT)
            {
                $png2 = imagecreatefrompng($uploadedFile['tmp_name']);
                $ratio2 = $width / STD_WIDTH;
                $pixels2 = array();
                for ($x = 0; $x < STD_WIDTH; $x ++)
                    for ($y = 0; $y < STD_HEIGHT; $y ++)
                        $pixels2[$x * STD_WIDTH + $y] = imagecolorat($png2, $x * $ratio2, $y * $ratio2);
                
                $all_skins = dirToArray($workingFolder);
                foreach ($all_skins as $skin)
                {
                    $imageInfo1 = getimagesize($skin);
                    $width1  = $imageInfo1[WIDTH];
                    $png1 = imagecreatefrompng($skin);
                    $difference_sum = 0;
                    $ratio1 = $width1 / STD_WIDTH;
                    
                    $pixels1 = array();
                    for ($x = 0; $x < STD_WIDTH; $x ++)
                        for ($y = 0; $y < STD_HEIGHT; $y ++)
                            $pixels1[$x * STD_WIDTH + $y] = imagecolorat($png1, $x * $ratio1, $y * $ratio1);
                    
                    for ($x = 0; $x < STD_WIDTH-8; $x += 1) // Правой полосой из 8 пикселей можно пренебречь
                        for ($y = 8; $y < STD_HEIGHT; $y += 1) { // Верхней полосой из 8 пикселей можно пренебречь
                            $difference_sum += (($pixels1[$x * STD_WIDTH + $y] == $pixels2[$x * STD_WIDTH + $y]) ? 0 : 1);
                            if ($difference_sum >= DIFFERENCE_THRESHOLD)
                                break 2;
                        }
                    
                    if ($difference_sum < DIFFERENCE_THRESHOLD) // Могут совпадать 100 пикселей из 2048
                    {
                        $playerName = str_replace($workingFolder.'/','',str_replace('.png','',$skin));
                        if ($playerName != $login)
                            SendErrorMessage('Такой скин уже установил себе игрок '.$playerName.'. Попробуйте поискать другой.', $encryptionKey);
                    }
                }
            }
            
            // Все проверки пройдены, сохраняем скин/плащ:
            move_uploaded_file($uploadedFile['tmp_name'], $workingFolder.'/'.$objectName);
            SendSuccessfulMessage($encryptionKey);
            break;
            
        case 'delete':
            $objectPath = $workingFolder.'/'.$objectName;
            if (file_exists($objectPath)) {unlink($objectPath);}
            GetImage($workingFolder, $objectName, $defObjectName, $encodedImage);
            SendSuccessfulMessage($encryptionKey, $encodedImage);
            break;
            
        case 'download':
            if (GetImage($workingFolder, $objectName, $defObjectName, $encodedImage, false)) {
                SendSuccessfulMessage($encryptionKey, $encodedImage);
            } else {
                switch ($imageType) {
                    case 'skin'  : SendErrorMessage('Скин не установлен!', $encryptionKey);
                    case 'cloak' : SendErrorMessage('Плащ не установлен!', $encryptionKey);
                }
            }
            break;
        
        default:
            SendErrorMessage('Неизвестное действие!', $encryptionKey);
            break;
    }
?>
