<?php

namespace Presenters;

class Popup {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}


	function login($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		echo \Template::instance()->render('popup_login.html');

	}

	function main($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ( empty($f3->get('SESSION.accessToken')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no token - " . print_r($f3->get('SESSION'), true));
			$f3->clear('SESSION');
			$f3->reroute('/popup/login');
		}

		echo \Template::instance()->render('popup_status.html');

	}


}
?>
