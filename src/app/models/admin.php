<?php

namespace Models;

class Admin {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
        $this->admin = new \DB\Jig\Mapper($db, 'admins.json');
	}

	function getAdmin($username, $password) {

		$response = new \Response($this->tr);
		$this->admin->load(array('@username=? AND @password=?', $username, $password));
		if ($this->admin->dry()) {
			$response->message = 'Admin not found';
			return $response;
		}

		$response->result = $this->admin->cast();
		$response->success = true;
		return $response;

	}

}

?>
