<?php
	/*
	При добавлении игрока в приват WorldGuard отправляет этому скрипту JSON следующего содержания:
		["synthetic","mokko","cookiezi"]
	А надо вернуть:
		[{"id":"7686015f586e3bebaa3646f30d0682d9","name":"synthetic"},{"id":"2427a68bfa5d374da2cc724a9ce45f37","name":"Mokko"},{"id":"3444faa8743e3fabb2152cfc6f520e72","name":"Cookiezi"}]
	Колчество никнеймов от 1 до 100. Со скоростью не более 600 игроков за 10 минут ( https://www.spigotmc.org/threads/using-the-mojang-api.95092/#post-1042758 )
	*/
	
	header('Content-Type: application/json; charset=utf-8');
	
	include('webUtils/dbUtils.php');
	include('webUtils/auxUtils.php');
	include('settings.php');
	
	function SendErrorMessage($error, $errorMessage) {
		exit('{"error":"'.$error.'","errorMessage":"'.$errorMessage.'"}');
	}
	
	$json_data = json_decode(file_get_contents('php://input'));
	if ($json_data === null) {
		SendErrorMessage('Invalid JSON', 'uuid.php received invalid JSON');
	}
	
	if (count($json_data) > 100) {
		SendErrorMessage('Big Request', 'uuid.php received too much usernames!');
	}
	
	// Создаём объект соединения с базой:
	$dbWorker = new DatabaseWorker();
	if ($dbWorker === null) {
		SendErrorMessage('dbWorker error!', 'Unable to create dbWorker!');
	}
	
	// Подключаемся к базе:
	if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
		SendErrorMessage('dbWorker error!', 'Unable to connect to database!');
	}
	
	$response = '';
	foreach ($json_data as $username) {
		// Получаем ник в верном регистре:
		if ($username != null) {
			$caseValidationStatus = $dbWorker->GetValidCasedLogin($playersTableName, $playersColumnName, $username);
			if ($caseValidationStatus !== $dbWorker::STATUS_QUERY_USER_NOT_FOUND) {
				$response .= '{"id":"'.GenerateUUID($username).'","name":"'.$username.'"},';
			}
		}
	}
	
	$dbWorker->CloseDatabase();
	$response = '['.rtrim($response, ',').']';
	echo $response;
?>
