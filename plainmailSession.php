<?php

class PLainmailSession {

	/* 	assuming no other plugin is using sessions, which is unlikely, we'll set 
		a secure session. If another plugin has already set the session, it may
		not be secure. */
		
	function __construct(){

		if(!session_id()){ 
			$httponly = true;
			$session_hash = 'sha512';

			if (in_array($session_hash, hash_algos())) {
	      	  ini_set('session.hash_function', $session_hash);
	   		}

	   		ini_set('session.hash_bits_per_character', 5);
	 		ini_set('session.use_only_cookies', 1);
	   	
			session_start();
		}


		$_SESSION['plainmail.ident'] = $_SERVER['REMOTE_ADDR'];
		$_SESSION['plainmail.start'] = time();
	}

}