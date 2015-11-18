<?php
        # AuthMe:
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $passRequest = "SELECT `password` FROM `{$playersTableName}` WHERE `login`=:login";
            $passArguments = array ('login' => $login);
            $passPreparedRequest = null;
            
            $preRequestStatus = $this->_dbConnector->ExecutePreparedRequest($passRequest, $passArguments, $passPreparedRequest);
                
            if (!isset($passPreparedRequest) || !$preRequestStatus) {
                return $this::STATUS_DB_ERROR;
            }
                
            $hash = $passPreparedRequest->fetch(PDO::FETCH_ASSOC)['password'];
            if ($hash === null) {
                return $this::STATUS_USER_NOT_EXISTS;
            }
        
            $exp = preg_split('/\\$/', $hash);
            $salt = $exp[2];
			
            $hashedPass = '$SHA$'.$salt.'$'.hash('sha256', hash('sha256', $password).$salt);
            
            $request = "SELECT COUNT(1) FROM `{$playersTableName}` WHERE `login`=:login AND BINARY `password`=:password";
            $arguments = array (
                'login'    => $login,
                'password' => $hashedPass
            );
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
            $authStatus = $preparedRequest->fetchColumn() ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
?>