<?php

/*	CSRF code based on the OWASP guide: https://www.owasp.org/index.php/PHP_CSRF_Guard
	by Abbas Naderi Afooshteh from OWASP under Creative Commons 3.0 License.
	Contributions from Krzysztof Kotowicz <krzysztof.kotowicz at securing.pl>,
	Jakub Kałużny <jakub.artur.kaluzny at g>
*/ 

class PlainmailCSRFGuard{

	function generateToken($prefix, $name){

		if (function_exists("hash_algos") and in_array("sha512",hash_algos())){	
			$token = hash("sha512",mt_rand(0,mt_getrandmax()));
		}
		else{		
			$token=' ';		
			for ($i=0;$i<128;++$i){		
				$r=mt_rand(0,35);	
				if ($r<26){
					$c=chr(ord('a')+$r);
				}
				else{ 
					$c=chr(ord('0')+$r-26);
				} 		
				$token.=$c;		
			}		
		}

		if(strlen($token) === 128){	
			$_SESSION[$prefix.$name] = $token;
			return $token;
		}
		
		$this->destroyToken($name);
		return false;
	}

	function destroyToken($prefix, $name){
		unset($_SESSION[$prefix.$name]);
	}

	function validateToken($prefix, $name, $token){

		$token_ok = false;

		if(!empty($token) && isset($_SESSION[$prefix.$name]) && ($_SESSION[$prefix.$name] === $token)){
			$token_ok = true;
		}

		$this->destroyToken($prefix, $name);
		return $token_ok;
	}

	function makeFields($prefix){
		$name = uniqid("csrf_", 1); 

		if($token  = $this->generateToken($prefix, $name)){	
			return array($name, $token);
		}
		
		return false;
	}

	
}