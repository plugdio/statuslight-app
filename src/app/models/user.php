<?php

namespace Models;

class User {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
		$db = new \DB\SQL(
		    'mysql:host=' . trim(getenv('MYSQL_HOST')) . ';port=3306;dbname=statuslight',
		    trim(getenv('MYSQL_USER')),
		    trim(getenv('MYSQL_PASSWORD'))
		);

		$this->user = new \DB\SQL\Mapper($db, 'users');

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

	function deleteUser($userId) {
		$response = new \Response($this->tr);
		$this->user->load(array('id = ?', $userId));
		if ($this->user->dry()) {
			$response->message = 'User not found';
			return $response;
		}
		$this->user->erase();					

		$response->success = true;
		return $response;
	}

}

?>
