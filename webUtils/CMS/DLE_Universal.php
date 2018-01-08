<?php
        # DLE Universal:
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
            $dle_lib = $_SERVER['DOCUMENT_ROOT'].'/engine/api/api.class.php';
            
            if (!file_exists($dle_lib)) {
               $authStatus = $this::STATUS_DB_ERROR;
               return $authStatus;
            }
            
            require $dle_lib;
            
            $request = $dle_api->external_auth($login, $password);
            if ($request === true) {
                $authStatus = $this::STATUS_USER_EXISTS;
            } else {
                $authStatus = $this::STATUS_USER_NOT_EXISTS;
            }
?>
