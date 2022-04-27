<?php
	require_once 'functions.php';
	
	$config=read_config();
	set_iridiun_chain($config[$_GET['chain']]);

	if (no_displayed_error_result($code, iridiun('getfiltercode', $_GET['txid']))) {
		header('Content-Type: text/plain; charset=UTF-8'); 
		echo $code;
	}
