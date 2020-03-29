<?php

namespace Ajax;

class MainAjax {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

	}

	function beforeroute($f3, $args) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		$this->amIAuthenticated();
	}


	function afterroute($f3) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		header('Content-Type: application/json');
		echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);

	}

	function amIAuthenticated() {
		$f3=\Base::instance();

		if ( empty($f3->get('SESSION.userId')) ) {
			$f3->error(401, "Authentication error");
		}

	}

}
?>
