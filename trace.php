<?php

// Our native trace function for PHP
function trace($msg, $j = false){
	if (! is_string($msg) && $j===false ){
		$msg = "(" . gettype($msg) . ") " . var_export($msg, true);
	}else{
		if ($j===false) {
			$msg = "(" . gettype($msg) . ") " . $msg;
		}
	}
	$h = @fopen('http://127.0.0.1:8189/trace?m='.substr(rawurlencode($msg),0,2000),'r');
	if ($h !== FALSE){
		fclose($h);
	}
	usleep(10000); // !important, yield CPU 1/10 sec to socket
}