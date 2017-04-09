<?php
        # vBulletin:
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $saltRequest = "SELECT `salt` FROM `{$playersTableName}` WHERE `username`=:login";
            $saltArguments = array('login' => $login);
            $saltPreparedRequest = null;
            
            $getSaltStatus = $this->_dbConnector->ExecutePreparedRequest($saltRequest, $saltArguments, $saltPreparedRequest);
            
            if (!isset($saltPreparedRequest) || !$getSaltStatus) {
                return $this::STATUS_DB_ERROR;
            }
            
            $salt = $saltPreparedRequest->fetch(PDO::FETCH_ASSOC)['members_pass_salt'];
            if ($salt === null) {
                return $this::STATUS_USER_NOT_EXISTS;
            }
            
            $request = "SELECT COUNT(1) FROM `{$playersTableName}` WHERE `username`=:login AND BINARY `password`=:password";
            
            $arguments = array (
                'login'    => $login,
                'password' => md5(md5($password).$salt)
            );
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
            $authStatus = $preparedRequest->fetchColumn() ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
?>
