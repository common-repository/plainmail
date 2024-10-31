<?php 

class PlainmailErrorHandler{

	private $Errors = array();

	function showErrors($template = "<div class=\"%s\">%s</div>", $class = "error"){
		
		$r = '';
		
		while($e = array_pop($this->Errors[$class])){
			$r.= sprintf($template, $class, htmlspecialchars($e));
		}

		return $r;
	}

	function Stack($value, $class='error'){
		if(array_key_exists($class, $this->Errors)){ 
			$this->Errors[$class][] = $value;
		}
		else{
			$this->Errors[$class] = array($value);
		}
	}

	function Flush(){
		$this->Errors = array();
	}

	function HasErrors($class="error"){
		return (bool)(count($this->Errors[$class]));
	}
	
}