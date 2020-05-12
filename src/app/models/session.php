<?php

namespace Models;

class Session {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
        $this->session = new \DB\Jig\Mapper($db, 'sessions.json');
	}

	function saveSession($sessionType, $userId, $token) {

		$this->session->load(array('@userId=? AND @type=? AND @state=?', $userId, $sessionType, SESSION_STATE_ACTIVE));
		while (!$this->session->dry()) {
			$this->session->state = SESSION_STATE_INACTIVE;
			$this->session->closedReason = 'New session started';
			$this->session->save();
			$this->session->next();
		}
		
		$this->session->reset();	
    	$this->session->type = $sessionType;
    	$this->session->userId = $userId;
    	$this->session->token = serialize($token);
    	$this->session->startTime = time();
    	$this->session->lastUpdatedTime = time();
    	$this->session->state = SESSION_STATE_ACTIVE;
    	$this->session->save();
	}

	function getActiveSessionForUser($userId) {
		$response = new \Response($this->tr);
		$this->session->load(array('@userId=? AND @state=?', $userId, SESSION_STATE_ACTIVE));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}

		$response->result = $this->session->cast();
		$response->success = true;
		return $response;
	}

	function getActiveSessions() {
		$response = new \Response($this->tr);
		$this->session->load(array('@state=?', SESSION_STATE_ACTIVE));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}
		$response->result = array();

		while (!$this->session->dry()) {
			if ($this->session->lastUpdatedTime < time() - 24 * 60 * 60) {
				$this->session->state = SESSION_STATE_INACTIVE;
				$this->session->closedReason = 'lastUpdatedTime over timeout';
				$this->session->save();
			} else {
				$response->result[] = $this->session->cast();
			}
			$this->session->next();
		}

		$response->success = true;
		return $response;
	}

	function updateSession($sessionId, $token, $newState, $closedReason = null) {
		$response = new \Response($this->tr);
		
		$this->session->load(array('@_id=?', $sessionId));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}
		
    	$this->session->token = serialize($token);
    	$this->session->lastUpdatedTime = time();
    	$this->session->state = $newState;
    	$this->session->closedReason = $closedReason;
    	$this->session->save();
	}

}

?>
