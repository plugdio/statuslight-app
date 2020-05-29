<?php

namespace Models;

class Admin {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
		$db = new \DB\SQL(
		    'mysql:host=' . trim(getenv('MYSQL_HOST')) . ';port=3306;dbname=statuslight',
		    trim(getenv('MYSQL_USER')),
		    trim(getenv('MYSQL_PASSWORD'))
		);
        $this->admin = new \DB\SQL\Mapper($db, 'mqttadmins');
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
