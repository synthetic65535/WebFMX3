<?php
	/*
	��� ���������� ������ � ������ WorldGuard ���������� ����� ������� JSON ���������� ����������:
		["synthetic","mokko","cookiezi"]
	� ���� �������:
		[{"id":"7686015f586e3bebaa3646f30d0682d9","name":"synthetic"},{"id":"2427a68bfa5d374da2cc724a9ce45f37","name":"Mokko"},{"id":"3444faa8743e3fabb2152cfc6f520e72","name":"Cookiezi"}]
	��������� ��������� �� 1 �� 100. �� ��������� �� ����� 600 ������� �� 10 ����� ( https://www.spigotmc.org/threads/using-the-mojang-api.95092/#post-1042758 )
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
	
	// ������ ������ ���������� � �����:
	$dbWorker = new DatabaseWorker();
	if ($dbWorker === null) {
		SendErrorMessage('dbWorker error!', 'Unable to create dbWorker!');
	}
	
	// ������������ � ����:
	if (!$dbWorker->SetupDatabase($dbHost, $dbName, $dbUser, $dbPassword)) {
		SendErrorMessage('dbWorker error!', 'Unable to connect to database!');
	}
	
	$response = '';
	foreach ($json_data as $username) {
		// �������� ��� � ������ ��������:
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
