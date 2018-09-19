<?php
        # PunBB:
            if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
            
            // 1. ������� ����� ��������� ������� �� ������������
            // ----------------------
            // ��������� �� ������� �� ������������ �� ������.
            // ���� ������������� ����� � ������� �� �����, ������� ���� ����
            
            global $forum_bans;
            $bans_cache = '../cache/cache_bans.php';
            
            // ���� ���� �� ������, ������� ������
            if (!file_exists($bans_cache))
            {
                $authStatus = $this::STATUS_DB_ERROR;
                return $authStatus;
            }
            
            if (!defined('FORUM_BANS_LOADED'))
                require $bans_cache;
            
            // ���� ��� ���������� �� �������, ������� ������
            if (!defined('FORUM_BANS_LOADED'))
            {
                $authStatus = $this::STATUS_DB_ERROR;
                return $authStatus;
            }
            
            $username_lowercase = strtolower($login);
            
            foreach ($forum_bans as $cur_ban)
            {
                if (($cur_ban['username'] != '') && ($username_lowercase == strtolower($cur_ban['username'])))
                {
                    $authStatus = $this::STATUS_USER_BANNED;
                    return $authStatus;
                }
            }
            
            // ----------------------
            
            // ����������� ���� ��� ����������� �������� �� �����-������
            
            $saltRequest = "SELECT `salt` FROM `{$playersTableName}` WHERE `username`=:login";
            $saltArguments = array ('login' => $login);
            $saltPreparedRequest = null;
            $saltStatus = $this->_dbConnector->ExecutePreparedRequest($saltRequest, $saltArguments, $saltPreparedRequest);
            if (!isset($saltPreparedRequest) || !$saltStatus) {
                $authStatus = $this::STATUS_DB_ERROR;
                return $authStatus;
            }
            $salt = $saltPreparedRequest->fetch(PDO::FETCH_ASSOC)['salt'];
            if ($salt === null) {
                $authStatus = $this::STATUS_USER_NOT_EXISTS;
                return $authStatus;
            }
            
            // ----------------------
            
            // 2. ����� ��������� �������� �� �����-������
            
            $request = "SELECT COUNT(1) FROM `{$playersTableName}` WHERE `username`=:login AND BINARY `password`=:password";
            
            $arguments = array (
                'login'    => $login,
                'password' => sha1($salt.sha1($password))
            );
            
            $preparedRequest = null;
            $status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
            if (!isset($preparedRequest) || !$status) {
                $authStatus =  $this::STATUS_DB_ERROR;
                return $authStatus;
            }
            $authStatus = $preparedRequest->fetchColumn() ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
            $this->_dbConnector->ClosePreparedRequest($preparedRequest);
?>
