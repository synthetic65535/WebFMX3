<?php
		# DLE 11.2+:
			if (!isset($this->_dbConnector)) {return $this::STATUS_DB_OBJECT_NOT_PRESENT;}
			
			$request = "SELECT COUNT(1) FROM `dle_users` WHERE `name`=:login";
			
			$arguments = array ('login' => $login);
			
			$preparedRequest = null;
			$status = $this->_dbConnector->ExecutePreparedRequest($request, $arguments, $preparedRequest);
			if (!isset($preparedRequest) || !$status) {
				return $this::STATUS_DB_ERROR;
			}
			$hash = $saltPreparedRequest->fetch(PDO::FETCH_ASSOC)['password'];
			
			// Проверка. Взята из функции password_verify()
			if (!function_exists('crypt'))
				die("Crypt must be loaded!");
			$ret = crypt($password, $hash);
			if (!is_string($ret) || strlen_8bit($ret) != strlen_8bit($hash) || strlen_8bit($ret) <= 13)
				$authStatus = $this::STATUS_USER_NOT_EXISTS;
			$status = 0;
			for ($i = 0; $i < strlen_8bit($ret); $i++)
				$status |= (ord($ret[$i]) ^ ord($hash[$i]));
			// Конец проверки
			
			$authStatus = $status === 0 ? ? $this::STATUS_USER_EXISTS : $this::STATUS_USER_NOT_EXISTS;
			$this->_dbConnector->ClosePreparedRequest($preparedRequest);
?>
