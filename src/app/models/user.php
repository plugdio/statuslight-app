<?php

namespace Models;

class User {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
		$this->user = new \DB\SQL\Mapper($f3->get('db'), 'users');

	}

	function saveUser($userId, $provider, $name, $email) {

		$response = new \Response($this->tr);
		$this->user->load(array('userId=?', $userId));
		if ($this->user->dry()) {
			$this->user->userId = $userId;
			$this->user->provider = $provider;
			$this->user->name = $name;
			$this->user->email = $email;
			$this->user->save();
		}
		$response->result = $this->user->cast();
		$response->success = true;
		return $response;

	}

	function getUser($id) {

		$response = new \Response($this->tr);
		$this->user->load(array('id=?', $id));
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
