<?php
    
    // !!! Раскомментировать, если используется WordPress:
    //include_once('WordPressPasswordHash.php'); // Оригинальное название "class-phpass.php"
    
    class dbConnector {
        private $_dbHandle = null;
        private $_lastPDOError = '';
        
        public function GetDatabaseHandle() {
            return $this->_dbHandle;
        }
        
        public function GetLastDatabaseError() {
            return $this->_lastPDOError;
        }
        
        public function dbDisconnect() {
            $this->_dbHandle = null;
            $this->_lastPDOError = '';
        }
        
        public function dbConnect($dbHost, $dbName, $dbUser, $dbPassword) {
            $this->dbDisconnect();
            try {
                $this->_dbHandle = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
                return isset($this->_dbHandle);
            } catch (PDOException $pdoException) {
                $this->_lastPDOError = $pdoException->getMessage();
                return false;
            }
        }
        
        public function ExecutePreparedRequest($request, $arguments, &$preparedRequest) {
            
            $this->_lastPDOError = '';
            
            try {
                $preparedRequest = $this->_dbHandle->prepare($request);
                if ($preparedRequest === null) {
                    $this->_lastPDOError = 'Unable to prepare request.';
                    return false;
                }
                
                return $preparedRequest->execute($arguments);
                
            } catch (PDOException $pdoException) {
                $preparedRequest = null;
                $this->_lastPDOError = $pdoException->getMessage();
                return false;
            }
        }
        
        public function ClosePreparedRequest(&$preparedRequest) {
            $preparedRequest = null;
        }
    }
    
    
    class DatabaseWorker {
        
        // Результат InsertPlayerInBase:
        const STATUS_REG_SUCCESS             = 1;
        const STATUS_REG_USER_ALREADY_EXISTS = 0;
        
        // Результат IsPlayerInBase:
        const STATUS_USER_NOT_EXISTS = 0;
        const STATUS_USER_EXISTS     = 1;
        const STATUS_USER_BANNED     = 2;
        
        // Результат DoJoin:
        const STATUS_JOIN_SUCCESS        = 1;
        const STATUS_JOIN_USER_NOT_FOUND = 0;
        
        // Результат DoHasJoined:
        const STATUS_HAS_JOINED_SUCCESS        = 1;
        const STATUS_HAS_JOINED_USER_NOT_FOUND = 0;
        
        // Результат DoCheckServer:
        const STATUS_CHECK_SERVER_SUCCESS        = 1;
        const STATUS_CHECK_SERVER_USER_NOT_FOUND = 0;
        
        // Результат DoJoinServer:
        const STATUS_JOIN_SERVER_SUCCESS        = 1;
        const STATUS_JOIN_SERVER_USER_NOT_FOUND = 0;
        
        // Результат GetUsernameByUUID и GetValidCasedLogin:
        const STATUS_QUERY_SUCCESS        = 1;
        const STATUS_QUERY_USER_NOT_FOUND = 0;
        
        // Результат IsHwidBanned:
        const STATUS_USER_NOT_BANNED = 0;
        const STATUS_NO_HWID         = 1;
        //const STATUS_USER_BANNED   = 2; такая константа уже есть
        
        // Ошибки подключения к БД:
        const STATUS_DB_OBJECT_NOT_PRESENT = -1;
        const STATUS_DB_ERROR              = -2;
        
        
        // Варианты CMS:
        const CMS_CUSTOM        = 'Custom.php';
        const CMS_DLE           = 'DLE.php'; // Старые версии DLE
        const CMS_DLE_112       = 'DLE_112.php'; // DLE 11.2
        const CMS_DLE_113       = 'DLE_113.php'; // DLE 11.3
        const CMS_DLE_UNIVERSAL = 'DLE_Universal.php'; // DLE 11.3
        const CMS_WEBMCR        = 'WebMCR.php';
        const CMS_WORDPRESS     = 'WordPress.php';
        const CMS_PUNBB         = 'PunBB.php';
        const CMS_AUTHME        = 'AuthMe.php';
        const CMS_VBULLETIN     = 'vBulletin.php';
        const CMS_IPB3          = 'IPBoard3.php';
        const CMS_IPB4          = 'IPBoard4.php';
        const CMS_XENFORO       = 'XenForo.php';
        const CMS_MCRSHOP       = 'CMSMinecraftShop.php';
        
        const CMS_TYPE = DatabaseWorker::CMS_PUNBB; // <-- Здесь менять используемую CMS!
        
        private $_dbConnector = null;
        
        public function SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword) {
            $this->CloseDatabase();
            $this->_dbConnector = new dbConnector();
            return $this->_dbConnector->dbConnect($dbHost, $dbName, $dbUser, $dbPassword);
        }
        
        public function CloseDatabase() {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            $this->_dbConnector->dbDisconnect();
            unset($this->_dbConnector);
        }
        
        public function GetLastDatabaseError() {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            return $this->_dbConnector->GetLastDatabaseError();
        }
        
        public function IsPlayerInBase($playersTableName, $login, $password) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            $authStatus = false;
            include('CMS/'.$this::CMS_TYPE);
            return $authStatus;
        }
        
        
        public function GetValidCasedLogin($playersTableName, $playersColumnName, &$login) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $request = "SELECT {$playersColumnName} FROM `{$playersTableName}` WHERE `{$playersColumnName}`=:login";
            $arguments = array (
                'login' => $login
            );
            
            $preparedRequest = null;
            $result = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$result) {
                return $this::STATUS_DB_ERROR;
            }
            
            $login = $preparedRequest->fetch(PDO::FETCH_ASSOC)[$playersColumnName];
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            
            return $login !== null ? $this::STATUS_QUERY_SUCCESS : $this::STATUS_QUERY_USER_NOT_FOUND;
        }
        
        public function InsertPlayerInBase($playersTableName, $login, $password) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $request = "INSERT INTO `{$playersTableName}` (`login`, `password`) VALUES (:login, :password)";
            $arguments = array (
                'login'    => $login,
                'password' => $password
            ); 
            
            $preparedRequest = null;
            $insertionStatus = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest)) {
                return $this::STATUS_DB_ERROR;
            }
            $regStatus = (($preparedRequest->rowCount() > 0) && $insertionStatus) ? $this::STATUS_REG_SUCCESS : $this::STATUS_REG_USER_ALREADY_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $regStatus;
        }
        
        
        public function InsertPlayerToAuthorizedPlayersList($tokensTableName, $login, $uuid, $accessToken, $serverId) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $request = "INSERT INTO `{$tokensTableName}` (`username`, `uuid`, `accessToken`, `serverId`) ".
                       "VALUES (:login, :uuid, :accessToken, :serverId) ".
                       "ON DUPLICATE KEY UPDATE `uuid`=:uuid, `accessToken`=:accessToken, `serverId`=:serverId";
            $arguments = array (
                'login'       => $login,
                'uuid'        => $uuid,
                'accessToken' => $accessToken,
                'serverId'    => $serverId
            );
            
            $preparedRequest = null;
            $insertionStatus = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $insertionStatus;
        }
        
        public function DoJoin($tokensTableName, $accessToken, $uuid, $serverId, &$username) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $arguments = array (
                'serverId'    => $serverId,
                'uuid'        => $uuid,
                'accessToken' => $accessToken
            );
            
            // Обновляем serverId у нужного игрока:
            $updatingRequest = "UPDATE `{$tokensTableName}` ".
                               "SET `serverId`=:serverId ".
                               "WHERE `uuid`=:uuid AND `accessToken`=:accessToken ".
                               "LIMIT 1";
            
            $updatingPreparedRequest = null;
            
            $updatingResult = $this->_dbConnector->ExecutePreparedRequest($updatingRequest, $arguments, $updatingPreparedRequest);
            if (!isset($updatingPreparedRequest) || !$updatingResult) {
                return $this::STATUS_DB_ERROR;
            }
            
            $updatingStatus = $updatingPreparedRequest->rowCount() > 0;
            $this->_dbConnector->ClosePreparedRequest($updatingPreparedRequest);
            
            // Получаем ник игрока:
            if ($updatingStatus) {
                $queryRequest = "SELECT username ".
                                "FROM `{$tokensTableName}` ".
                                "WHERE `uuid`=:uuid AND `accessToken`=:accessToken AND `serverId`=:serverId";
                
                $queryPreparedRequest = null;
                $queryResult = $this->_dbConnector->ExecutePreparedRequest($queryRequest, $arguments, $queryPreparedRequest);
                if (!isset($queryPreparedRequest) || !$queryResult) {
                    $joinStatus = $this::STATUS_DB_ERROR;
                }
                
                $username = $queryPreparedRequest->fetch(PDO::FETCH_ASSOC)['username'];
                $joinStatus = $username !== null ? $this::STATUS_JOIN_SUCCESS : $this::STATUS_JOIN_USER_NOT_FOUND;
                
                $this->_dbConnector->ClosePreparedRequest($queryPreparedRequest);
            } else {
                $joinStatus = $this::STATUS_JOIN_USER_NOT_FOUND;
            }
            return $joinStatus;
        }
        
        public function DoHasJoined($tokensTableName, $username, $serverId, &$uuid) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $queryArguments = array (
                'username' => $username,
                'serverId' => $serverId
            );
            
            // Получаем UUID игрока:
            $queryRequest = "SELECT uuid ".
                            "FROM `{$tokensTableName}` ".
                            "WHERE `username`=:username AND `serverId`=:serverId";
            
            $queryPreparedRequest = null;
            $queryResult = $this->_dbConnector->ExecutePreparedRequest($queryRequest, $queryArguments, $queryPreparedRequest);
            if (!isset($queryPreparedRequest) || !$queryResult) {
                $hasJoinedStatus = $this::STATUS_DB_ERROR;
            }
            
            $uuid = $queryPreparedRequest->fetch(PDO::FETCH_ASSOC)['uuid'];
            $this->_dbConnector->ClosePreparedRequest($queryPreparedRequest);
            
            // Делаем невалидным serverId:
            if ($uuid !== null) {
                $updatingRequest = "UPDATE `{$tokensTableName}` ".
                                   "SET `serverId`='null' ".
                                   "WHERE `serverId`=:serverId AND `username`=:username AND `uuid`=:uuid ".
                                   "LIMIT 1";
                $updatingArguments = array (
                    'serverId'    => $serverId,
                    'username'    => $username,
                    'uuid'        => $uuid
                );
                
                $updatingPreparedRequest = null;
                $updatingResult = $this->_dbConnector->ExecutePreparedRequest($updatingRequest, $updatingArguments, $updatingPreparedRequest);
                if (!isset($updatingPreparedRequest) || !$updatingResult) {
                    return $this::STATUS_DB_ERROR;
                }
                
                $hasJoinedStatus = $updatingPreparedRequest->rowCount() > 0 ? $this::STATUS_HAS_JOINED_SUCCESS : $this::STATUS_HAS_JOINED_USER_NOT_FOUND;
                $this->_dbConnector->ClosePreparedRequest($updatingPreparedRequest);
                
            } else {
                $hasJoinedStatus = $this::STATUS_HAS_JOINED_USER_NOT_FOUND;
            }
            
            return $hasJoinedStatus;
        }
        
        // profile.php:
        public function GetUsernameByUUID($tokensTableName, $uuid, &$username) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $request = "SELECT username FROM `{$tokensTableName}` WHERE `uuid`=:uuid";
            $arguments = array (
                'uuid' => $uuid
            );
            
            $preparedRequest = null;
            $result = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$result) {
                return $this::STATUS_DB_ERROR;
            }
            
            $username = $preparedRequest->fetch(PDO::FETCH_ASSOC)['username'];
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            
            return $username !== null ? $this::STATUS_QUERY_SUCCESS : $this::STATUS_QUERY_USER_NOT_FOUND;
        }
        
        public function DoJoinServer($tokensTableName, $accessToken, $username, $serverId) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $insertingArguments = array (
                'accessToken' => $accessToken,
                'username'    => $username,
                'serverId'    => $serverId
            );
            
            // Обновляем serverId у нужного игрока::
            $insertingRequest = "UPDATE `{$tokensTableName}` ".
                                "SET `serverId`=:serverId ".
                                "WHERE `accessToken`=:accessToken AND `username`=:username ".
                                "LIMIT 1";
            
            $insertingPreparedRequest = null;
            $insertingResult = $this->_dbConnector->ExecutePreparedRequest($insertingRequest, $insertingArguments, $insertingPreparedRequest);
            if (!isset($insertingPreparedRequest) || !$insertingResult) {
                $this->_dbConnector->ClosePreparedRequest($insertingPreparedRequest);
                return $this::STATUS_DB_ERROR;
            }
            
            $joinServerStatus = ($insertingPreparedRequest->rowCount() > 0) ? $this::STATUS_JOIN_SERVER_SUCCESS : $this::STATUS_JOIN_SERVER_USER_NOT_FOUND;
            $this->_dbConnector->ClosePreparedRequest($insertingPreparedRequest);
            
            return $joinServerStatus;
        }
        
        public function DoCheckServer($tokensTableName, $username, $serverId) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $queryArguments = array (
                'username' => $username,
                'serverId' => $serverId
            );
            
            // Проверяем, есть ли игрок в базе авторизованных игроков:
            $queryRequest = "SELECT COUNT(1) ".
                            "FROM `{$tokensTableName}` ".
                            "WHERE `username`=:username AND `serverId`=:serverId";
                
            $queryPreparedRequest = null;
            $queryResult = $this->_dbConnector->ExecutePreparedRequest($queryRequest, $queryArguments, $queryPreparedRequest);
            if (!isset($queryPreparedRequest) || !$queryResult) {
                $this->_dbConnector->ClosePreparedRequest($queryPreparedRequest);
                return $this::STATUS_DB_ERROR;
            }
            
            $checkServerStatus = ($queryPreparedRequest->fetchColumn()) ? $this::STATUS_CHECK_SERVER_SUCCESS : $this::STATUS_CHECK_SERVER_USER_NOT_FOUND;
            $this->_dbConnector->ClosePreparedRequest($queryPreparedRequest);
            
            return $checkServerStatus;
        }
        
        
        // Является ли id-шник флешкой
        function IsItFlashDrive($hwid) {
            return
                (preg_match('/^AA000000000[0-9]*$/', $hwid)) || // AA00000000000485, AA00000000000489, AA00000000012108
                (strpos($hwid, '058F') === 0) || // 058F63666433 058F312D81B 058F312D81B 058F63666485 058F63666485 058F312D81B 058F63666485 058F63626370 058F63626371 058F63626372 058F63626373 058F0O1111B1 058F63666485 058F0O1111B1 058F0O1111B1
                ($hwid === '801130168383') || // https://www.google.ru/webhp#q=801130168383
                (preg_match('/^20[0-9]{10}00000$/', $hwid)) || // 20090516388200000, 20071114173400000
                ($hwid === '130818V01') || // https://www.google.ru/webhp#q=130818V01
                ($hwid === '000000000563') || // https://www.google.ru/webhp#q=000000000563
                ($hwid === '105000000000') ||
                (preg_match('/^0*([0-9]?|123)$/', $hwid)) // 00000000000006, 0000000000000001, 00000000000123
                ;
        }
        
        // Плохой ID-шник, появляется одинаковый у реально разных пользователей
        function IsItBadHwid($hwid) {
            return
                ($hwid === '1171') ||
                ($hwid === '1172') ||
                ($hwid === '0123456789ABCDE0') ||
                ($hwid === '12345678123456781234567812345678') ||
                ($hwid === '0123456789ABCDE1') ||
                ($hwid === 'COM6F7FDF7AD81B634A0FB73AD5439A7A41')
                ;
        }
        
        // Функция для разворачивания hwid в массив
        function ExplodeHwid($hwid) {
            
            //отрезаем излишне длинные hwid
            $hwid = substr($hwid, 0, 1024);
            
            //все символы переводим в верхний регистр для определённости
            $hwid = strtoupper($hwid);
            
            //избавляемся от инородных символов
            $whitelist = '/[^a-zA-Z0-9:]/';
            $hwid = preg_replace($whitelist, '', $hwid);
            
            //предварительно разделяем строку
            $pre_result = explode(':', $hwid );
            
            $result = array();
            //в результат попадают только строки длиной 3 и более символов не содержащие UNKNOWN
            foreach ($pre_result as $item)
                if ( (strlen($item) >= 3) && (strpos($item, 'UNKNOWN') === false) &&
                !($this->IsItFlashDrive($item)) && !($this->IsItBadHwid($item)) )
                    $result[] = $item;
            
            return $result;
        }
        
        
        // Функция проверяет на бан отдельно каждый hwid из строки
        public function IsHwidStrBanned($hwidsTableName, $hwidstr) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $hwids = $this->ExplodeHwid($hwidstr);
            
            $hwid_conditions = '';
            $arguments = array ();
            $hwids_count = count($hwids);
            $i = 0;
            
            // Если нет ни одного нормального hwid то выдаём ошибку
            
            if ($hwids_count == 0)
                return $this::STATUS_NO_HWID;
            
            foreach ($hwids as $item)
            {
                $i++;
                
                if ($i == $hwids_count)
                    $hwid_conditions .= '`hwid`=:hwid'.$i;
                else
                    $hwid_conditions .= '`hwid`=:hwid'.$i.' OR ';
                
                $arguments += array (
                'hwid'.$i => $item
                );
            
            }
            
            $request = "SELECT COUNT(1) FROM `{$hwidsTableName}` WHERE ($hwid_conditions) AND `banned`=true";
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
            $bannedStatus = $preparedRequest->fetchColumn() ? $this::STATUS_USER_BANNED : $this::STATUS_USER_NOT_BANNED;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $bannedStatus;
        }
        
        
        // Теперь функция добавляет только один hwid в базу
        function AddOneHwidInBase($hwidsTableName, $login, $hwid, $banned) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $insertRequest = "INSERT INTO `{$hwidsTableName}` (`login`, `hwid`, `banned`) VALUES (:login, :hwid, :banned)";
            $arguments = array (
                'login' => $login,
                'hwid'  => $hwid,
                'banned'  => $banned
            );
            
            $preparedRequest = null;
            $insertionStatus = $this->_dbConnector->ExecutePreparedRequest($insertRequest, $arguments, $preparedRequest);
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $insertionStatus;
        }
        
        //функция добавляет по-отдельности каждый hwid в базу
        //не знал как одним запросом добавить в базу несколько hwid, поэтому такой кривозадый способ.
        public function AddHwidStrInBase($hwidsTableName, $login, $hwidstr, $banned)
        {
            $hwids = $this->ExplodeHwid($hwidstr);
            
            foreach ($hwids as $item)
                $this->AddOneHwidInBase($hwidsTableName, $login, $item, (strpos($item, 'COM') === 0) ? 0 : $banned); //COM-hwid не баним автоматически
        }
        
        public function SetHwidStrBanStatus($hwidsTableName, $hwidstr, $isBanned) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $hwids = $this->ExplodeHwid($hwidstr);
            
            $hwid_conditions = '';
            $arguments = array (
                'banned' => $isBanned
            );
            
            $hwids_afterif = array();
            foreach ($hwids as $item)
                if (strpos($item, 'COM') !== 0) // COM-hwid не баним автоматически
                    $hwids_afterif[] = $item;
            
            $hwids_count = count($hwids_afterif);
            $i = 0;
            foreach ($hwids_afterif as $item)
            {
                $i++;
                if ($i == $hwids_count)
                    $hwid_conditions .= '`hwid`=:hwid'.$i;
                else
                    $hwid_conditions .= '`hwid`=:hwid'.$i.' OR ';
                
                $arguments += array (
                'hwid'.$i => $item
                );
            }
            
            $insertRequest = "UPDATE `{$hwidsTableName}` SET `banned`=:banned WHERE $hwid_conditions";
            
            $preparedRequest = null;
            $setupBannedStatus = $this->_dbConnector->ExecutePreparedRequest($insertRequest, $arguments, $preparedRequest);
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $setupBannedStatus;
        }
        
        
        public function SetPlayerHwidsBanStatus($hwidsTableName, $login, $isBanned) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $insertRequest = "UPDATE `{$hwidsTableName}` SET `banned`=:banned WHERE `login`=:login";
            $arguments = array (
                'banned' => $isBanned,
                'login'  => $login
            );
            
            $preparedRequest = null;
            $setupBannedStatus = $this->_dbConnector->ExecutePreparedRequest($insertRequest, $arguments, $preparedRequest);
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $setupBannedStatus;
        }
        
    }
    
?>
