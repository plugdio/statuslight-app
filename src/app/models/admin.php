<?php

namespace Models;

class Admin {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $this->admin = new \DB\SQL\Mapper($f3->get('db'), 'mqttadmins');
	}

	function getAdmin($username, $password) {

		$response = new \Response($this->tr);
		$this->admin->load(array('username=? AND password=?', $username, md5($password)));
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
