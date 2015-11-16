<?php

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
                $this->_lastPDOError = $pdoException.getMessage();
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
        const STATUS_USER_EXISTS     = 1;
        const STATUS_USER_NOT_EXISTS = 0;
        
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
        
        // Результат GetUsernameByUUID:
        const STATUS_QUERY_SUCCESS        = 1;
        const STATUS_QUERY_USER_NOT_FOUND = 0;
        
        // Результат IsHwidBanned:
        const STATUS_USER_BANNED     = 1;
        const STATUS_USER_NOT_BANNED = 0;
        
        // Ошибки подключения к БД:
        const STATUS_DB_OBJECT_NOT_PRESENT = -1;
        const STATUS_DB_ERROR              = -2;
        
		// Варианты CMS:
        const CMS_CUSTOM    = 0;
		const CMS_DLE       = 1;
		const CMS_WEBMCR    = 2;
		const CMS_WORDPRESS = 3;
		const CMS_PUNBB     = 4;
		const CMS_AUTHME    = 5;
		const CMS_VBULLETIN = 6;
		const CMS_IPB       = 7;
		const CMS_XENFORO   = 8;
		
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
        
        public function IsPlayerInBase($playersTableName, $login, $password) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $request = "SELECT COUNT(1) FROM `{$playersTableName}` WHERE `login`=:login AND BINARY `password`=:password";
            $arguments = array (
                'login'    => $login,
                'password' => $password
            );
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
            $authStatus = $preparedRequest->fetchColumn() ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $authStatus;
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
        
        
        
        public function IsHwidBanned($hwidsTableName, $hwid) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $request = "SELECT COUNT(1) FROM `{$hwidsTableName}` WHERE `hwid`=:hwid AND `banned`=true";
            $arguments = array (
                'hwid' => $hwid
            );
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
            $bannedStatus = $preparedRequest->fetchColumn() ? $this::STATUS_USER_BANNED : $this::STATUS_USER_NOT_BANNED;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            return $bannedStatus;
        }
        
        public function AddHwidInBase($hwidsTableName, $login, $hwid) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $insertRequest = "INSERT INTO `{$hwidsTableName}` (`login`, `hwid`, `banned`) VALUES (:login, :hwid, 0)";
            $arguments = array (
                'login' => $login,
                'hwid'  => $hwid
            );            
            
            $preparedRequest = null;
            $insertionStatus = $this->_dbConnector->ExecutePreparedRequest($insertRequest, $arguments, $preparedRequest);
            $this->_dbConnector->ClosePreparedRequest($preparedRequest); 
            return $insertionStatus;
        }
        
        public function SetHwidBanStatus($hwidsTableName, $hwid, $isBanned) {
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $insertRequest = "UPDATE `{$hwidsTableName}` SET `banned`=:banned WHERE `hwid`=:hwid";
            $arguments = array (
                'banned' => $isBanned,
                'hwid'   => $hwid
            );            
            
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