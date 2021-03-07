<?php

namespace Models;

require_once "lib/cryptor.php";

class Session {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		$this->encryptionKey = $f3->get('encryptionKey');
		
		$db = new \DB\SQL(
		    'mysql:host=' . trim(getenv('MYSQL_HOST')) . ';port=3306;dbname=statuslight',
		    trim(getenv('MYSQL_USER')),
		    trim(getenv('MYSQL_PASSWORD'))
		);

        $this->session = new \DB\SQL\Mapper($db, 'sessions');
	}

	function saveSession($sessionType, $target, $userId, $token, $refreshToken = null, $sessionState, $closedReason, $status, $statusDetail) {

		$cryptor = new \Chirp\Cryptor($this->encryptionKey);

		$response = new \Response($this->tr);
		$this->session->load(array('userId=? AND type=? AND state=?', $userId, $sessionType, SESSION_STATE_ACTIVE));
		while (!$this->session->dry()) {
/*
			$this->session->state = SESSION_STATE_INACTIVE;
			$this->session->closedReason = 'New session started';
			$this->session->save();
			$this->session->next();
*/
			$this->session->erase();
			$this->session->next();
		}
		
		$this->session->reset();	
    	$this->session->type = $sessionType;
    	$this->session->target = $target;
    	$this->session->userId = $userId;

    	$tokenString = serialize($token);
  		$this->session->token = $cryptor->encrypt($tokenString);

    	if (!empty($refreshToken)) {
    		$this->session->refreshToken = $cryptor->encrypt($refreshToken);
    	}
    	$this->session->startTime = date('Y-m-d H:i:s');
    	$this->session->updatedTime = date('Y-m-d H:i:s');
    	$this->session->state = $sessionState;
    	$this->session->closedReason = $closedReason;
    	$this->session->presenceStatus = $status;
    	$this->session->presenceStatusDetail = $statusDetail;
    	$this->session->save();

    	$response->result = $this->session->cast();
		$response->success = true;
		return $response;
	}

	function getActiveSessionForUser($userId) {
		$response = new \Response($this->tr);
		$this->session->load(array('userId=? AND state=? AND type!=?', $userId, SESSION_STATE_ACTIVE, PROVIDER_DUMMY));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}

		$response->result = $this->session->cast();
		$response->success = true;
		return $response;
	}

	function getActiveDummySessionForUser($userId) {
		$response = new \Response($this->tr);
		$this->session->load(array('userId=? AND state=? AND type=? AND updatedTime < NOW()', $userId, SESSION_STATE_ACTIVE, PROVIDER_DUMMY));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}

		$response->result = $this->session->cast();
		$response->success = true;
		return $response;
	}

	function getActiveSessions() {

		$cryptor = new \Chirp\Cryptor($this->encryptionKey);

		$response = new \Response($this->tr);
		$this->session->load(array('state=?', SESSION_STATE_ACTIVE));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}
		$response->result = array();

		while (!$this->session->dry()) {
			if (strtotime($this->session->updatedTime) < time() - 24 * 60 * 60) {
/*
				$this->session->state = SESSION_STATE_INACTIVE;
				$this->session->closedReason = 'updatedTime over timeout';
				$this->session->save();
*/
				$this->session->erase();
			} else {
				if ($this->session->type == PROVIDER_DUMMY) {
					$this->session->next();
					continue;
				}
				$mySession = $this->session->cast();
				$mySession['token'] = $cryptor->decrypt($mySession['token']);
				if (!empty($mySession['refreshToken'])) {
					$mySession['refreshToken'] = $cryptor->decrypt($mySession['refreshToken']);
				}
				$response->result[] = $mySession;
			}
			$this->session->next();
		}

		$response->success = true;
		return $response;
	}

	function updateSession($sessionId, $token, $newState, $closedReason = null, $status = null, $subStatus = null) {
		
		$cryptor = new \Chirp\Cryptor($this->encryptionKey);

		$response = new \Response($this->tr);
		
		$this->session->load(array('id=?', $sessionId));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}
		
    	$tokenString = serialize($token);
  		$this->session->token = $cryptor->encrypt($tokenString);
    	$this->session->updatedTime = date('Y-m-d H:i:s');
    	$this->session->state = $newState;
    	$this->session->closedReason = $closedReason;
		$this->session->presenceStatus = $status;
		$this->session->presenceStatusDetail = $subStatus;
    	$this->session->save();
	}

	function deleteSessionsForUser($userId) {
		$response = new \Response($this->tr);
		$this->session->load(array('userId = ?', $userId));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}

		while (!$this->session->dry()) {
			$this->session->erase();					
			$this->session->next();
		}

		$response->success = true;
		return $response;
	}

}

?>
