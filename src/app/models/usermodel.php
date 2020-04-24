<?php

namespace Models;

class UserModel {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
        $this->user = new \DB\Jig\Mapper($db, 'users.json');
	}

	function saveUser($teamsProfile, $accessToken, $refreshToken) {

		$response = new \Response($this->tr);
		$this->user->load(array('@teamsId=?', $teamsProfile->id));
		if ($this->user->dry()) {
			$this->user->id = guid();
			$this->user->teamsId = $teamsProfile->id;
			$this->user->teamsProfile = $teamsProfile;
			$this->user->save();
		}
		$teamsTokens = new \stdClass();
		$teamsTokens->accessToken = $accessToken;
		$teamsTokens->refreshToken = $refreshToken;
		$this->user->teamsTokens = $teamsTokens;
		$this->user->save();

		$response->result = $this->user->cast();
		$response->success = true;
		return $response;

	}

	function getUser($userId) {

		$response = new \Response($this->tr);
		$this->user->load(array('@id=?', $userId));
		if ($this->user->dry()) {
			$response->message = 'User not found';
			return $response;
		}

		$response->result = $this->user->cast();
		$response->success = true;
		return $response;

	}

}

?>
