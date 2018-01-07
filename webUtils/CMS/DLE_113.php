<?php
        # DLE 11.3:
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            $request = "SELECT `password` FROM `{$playersTableName}` WHERE `name`=:login";
            
            $arguments = array ('login' => $login);
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                return $this::STATUS_DB_ERROR;
            }
            $hashedPassword = $preparedRequest->fetchColumn();
            $authStatus = password_verify($password, $hashedPassword) ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
?>
