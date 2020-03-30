<?php

namespace Presenters;

class MainPresenter {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

	}

	function beforeroute($f3, $args) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
/*
		if ($args[0] != '/auth') {
			$this->amIAuthenticated($args[0]);

		}
*/
	}


	function afterroute($f3) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
	}

	function blank($f3, $args) {

		$f3->set('register_link', 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=' . $f3->get('client_id') . '&response_type=code&redirect_uri=' . urlencode($f3->get('redirectUriTeams')) . '&response_mode=query&scope=' . urlencode($f3->get('scope')) . '&state=' . $this->tr);

		$f3->set('bg_class', 'bg-white');
		$f3->set('current_page', 'REGISTER');
		echo \Template::instance()->render('index.html');

	}



}
?>
