<?php

if (array_key_exists('data',$_POST)) {
	$ref=$_POST['data']['lis_outcome_service_url'];

	require_once dirname(__DIR__, 2) . '/loadEnv.php';

    $key = getenv('LTI_KEY') ?: "LOCAL_DEFAULT_KEY";
	$secret = getenv('LTI_SECRET') ?: "LOCAL_DEFAULT_SECRET";

	if (!array_key_exists('lis_result_sourcedid', $_POST['data'])) {
		print 'In lti\test\index.php : No ID<br>';
		print_r($_POST);
	} else {
		$postJson= json_encode($_POST);
		$ses = array('fname' => $_POST['data']['lis_person_name_given'], 'lname' => $_POST['data']['lis_person_name_family'], 'id' => $_POST['data']['lis_result_sourcedid'], 'url' => $_POST['data']['lis_outcome_service_url']);

		include 'php/message.php';
		include 'php/OAuthBody.php';
		$id    = $ses['id'];
		$url   = $ses['url'];
		$grade = $_POST["data"]["grade"];
		
		if (is_null($grade)) {
			$grade = 0;
		}
		
		$result = sendOAuthBodyPOST("POST", $url, $key, $secret, "application/xml", message($id, $grade));
		$result = preg_replace("/\r|\n/", "", $result);
		
		if(stristr($result, 'success') === FALSE) {
			$status= "failure";
			print $result;
		} else {
			$status= "success";
			$status.= "\n$result";
		}
		
		print $status;
		$time=date('Y-m-d H:i:s');
	}
}
