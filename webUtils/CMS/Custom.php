<?php
        # Custom:
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
?>
