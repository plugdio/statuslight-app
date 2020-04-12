<?php

class GraphResponse {

	public $tr;
	public $success;
	public $message;
	public $result;  		// Object, contains the result

	function __construct($tr) {
		if ($tr == NULL) {
			$tr = substr(md5(uniqid(rand(), true)),0,6);
		}
		$this->tr = $tr;
		$this->success = false;
		$this->message = null;
		$this->accessToken = null;
		$this->refreshToken = null;
		$this->result = new stdClass();
	}
	
}

class GraphDummyLogger{
	function debug($t) {}
	function info($t) {}
	function error($t) {}
}

class GraphAPI {

	private $tr;
	private $baseUri;
	private $accessToken;
	private $refreshToken;


	function __construct($scope, $redirectUri, $tr = null, $log = null) {

		$this->tokenUri = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
		$this->baseServiceUri = 'https://graph.microsoft.com/';
		$this->scope = urlencode($scope);
		$this->redirectUri = urlencode($redirectUri);

		if ($tr == NULL) {
			$tr = substr(md5(uniqid(rand(), true)),0,6);
		}
		$this->tr = $tr;

		if ($log == NULL) {
			$log = new \GraphDummyLogger();
		}
		$this->l = $log;
		
	}

	function setAuthParams($clientId, $secret, $authCode) {
		$this->clientId = $clientId;
		$this->secret = urlencode($secret);
		$this->authCode = $authCode;
	}

	function setTokens($accessToken, $refreshToken) {
		$this->accessToken = $accessToken;
		$this->refreshToken = $refreshToken;
	}

	function getSignedInUser() {
		return $this->talk2Api("v1.0/me");
	}

	function getMyPresence() {
		return $this->talk2Api("beta/me/presence");
	}

	private function talk2Api($uri, $method = 'GET', $payload = null) {

		$apiResponse = new \GraphResponse($this->tr);

		$url = $this->baseServiceUri . $uri;

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => array(
				"authorization: Bearer " . $this->accessToken,
				"cache-control: no-cache",
			),
		));

		if ($method == 'GET') {

		} else {
			$this->l->debug($this->tr . " - $method: " . $payload);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		}

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$info = curl_getinfo($curl);
		curl_close($curl);

		if ( ($err) || ($info["http_code"] > 299) ) {
			$this->l->error($this->tr . " - HTTP code: :" . $info["http_code"]);
			$this->l->error($this->tr . " - cURL Error #:" . $err);
			$this->l->error($this->tr . " - cURL info:" . print_r($info, true));

			$apiResponse->success = false;
		}
		
		$this->l->debug($this->tr . " - curl took " . $info['total_time'] . " seconds to send a request to " . $info['url']);

		$responseJson = json_decode($response);

		if (json_last_error() != JSON_ERROR_NONE) {
			$this->l->error($this->tr . " - Not json");
			$this->l->debug($this->tr . " - response: " . $response);
			$apiResponse->success = false;
			$apiResponse->result = $response;
			return $apiResponse;
		}

		$this->l->debug($this->tr . " - response: " . json_encode($responseJson));
		$apiResponse->result = clone($responseJson);

		if (!empty($responseJson->{'odata.error'})) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - odata error :" . print_r($responseJson, true));
			$apiResponse->success = false;
			$apiResponse->message = '';
			return $apiResponse;
		}

		if (!empty($responseJson->error)) {
			if ($responseJson->error->code == 'InvalidAuthenticationToken') {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - InvalidAuthenticationToken");
				$tokenResponse = $this->getToken('refresh_token');
				if (!$tokenResponse->success) {
					return $tokenResponse;
				}
				return $this->talk2Api($uri, $method, $payload);
			}
			$this->l->error($this->tr . " - " . __METHOD__ . " - error :" . print_r($responseJson, true));
			$apiResponse->success = false;
			$apiResponse->message = '';
			return $apiResponse;
		}

		$apiResponse->success = true;
		return $apiResponse;

	}

	function getToken($grantType) {

		$apiResponse = new \GraphResponse($this->tr);

		if ($grantType == 'authorization_code') {
			$payload = 'client_id=' . $this->clientId . '&scope=' . $this->scope . '&code=' . $this->authCode . '&client_secret=' . $this->secret . '&grant_type=authorization_code&redirect_uri=' . $this->redirectUri;
		} elseif ($grantType == 'refresh_token') {
			$payload = 'client_id=' . $this->clientId . '&scope=' . $this->scope . '&refresh_token=' . $this->refreshToken . '&redirect_uri=' . $this->redirectUri . '&grant_type=refresh_token' . '&client_secret=' . $this->secret;
		} else {
			$apiResponse->message = 'Unknown grantType: ' . $grantType;
			return $apiResponse;
		}

		$this->l->debug($this->tr . " - " . __METHOD__ . " - payload :" . $payload);

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->tokenUri,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => $payload,
		  CURLOPT_HTTPHEADER => array(
			"cache-control: no-cache",
			"content-type: application/x-www-form-urlencoded"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		$this->l->debug($this->tr . " - " . __METHOD__ . " - token response: " . $response);

		if ($err) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Error getting token: cURL Error #:" . $err);
			$apiResponse->message = "Authentication error - cURL " . $err;
			return $apiResponse;
		} else {
			$tokenResponse = json_decode($response);
			if (!empty($tokenResponse->error)) {
				$apiResponse->message = "Authentication error - " . "Error getting token: " . $tokenResponse->error . ", " . $tokenResponse->error_description;
				$this->l->error($this->tr . " - " . __METHOD__ . " - " . $apiResponse->message);
				return $apiResponse;
			} elseif (empty($tokenResponse->access_token)) {
				$apiResponse->message = "Error getting token: empty access_token - " . $response;
				$this->l->error($this->tr . " - " . __METHOD__ . " - " . $apiResponse->message);
				return $apiResponse;
			} else {
				$this->l->info($this->tr . " - " . __METHOD__ . " - Got the token"); 
#				$this->l->debug($this->tr . " - " . __METHOD__ . " - Got the token: " . $tokenResponse->access_token);
				$this->accessToken = $tokenResponse->access_token;
				$this->refreshToken = $tokenResponse->refresh_token;
				$this->accessTokenExpiresOn = time() + $tokenResponse->expires_in;
				$apiResponse->accessToken = $this->accessToken;
				$apiResponse->refreshToken = $this->refreshToken;
				$apiResponse->accessTokenExpiresOn = $this->accessTokenExpiresOn;
				$apiResponse->success = true;
				return $apiResponse;
				
			}
		}

	}

}

?>
