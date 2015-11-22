<?php
        # XenForo:
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $saltRequest = "SELECT `pass_salt` FROM `{$playersTableName}` WHERE `login`=:login";
            $saltArguments = array('login' => $login);
            $saltPreparedRequest = null;
            
            $getSaltStatus = $this->_dbConnector->ExecutePreparedRequest($saltRequest, $saltArguments, $saltPreparedRequest);
            if (!isset($saltPreparedRequest) || !$getSaltStatus) {
                return $this::STATUS_DB_ERROR;
            }
                
            $salt = $saltPreparedRequest->fetch(PDO::FETCH_ASSOC)['salt'];
            if ($salt === null) {
                return $this::STATUS_USER_NOT_EXISTS;
            }
            
            $rpass = hash('sha256', hash('sha256', $password).$salt);
            
            $request = "SELECT COUNT(1) FROM `{$playersTableName}` WHERE `login`=:login AND BINARY `password`=:password";
    
            $arguments = array (
                'login'    => $login,
                'password' => $rpass
            );
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
            $authStatus = $preparedRequest->fetchColumn() ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
?>