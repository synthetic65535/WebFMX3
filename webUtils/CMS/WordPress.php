<?php
        # WordPress:
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
 
            $request = "SELECT `user_pass` FROM `{$playersTableName}` WHERE `user_login`=:login";
            $arguments = array ('login' => $login);
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
    
            $wpHasher = new PasswordHash(8, TRUE);
            $hashedPassword = $preparedRequest->fetchColumn();
            $authStatus = $wpHasher->CheckPassword($password, $hashedPassword) ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
?>