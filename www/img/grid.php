<?php
	$c = '#FFF';
	$o = 1;

	if (!empty($_GET['c']) && preg_match('/^([wg]|(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}))$/',$_GET['c'])){
		if ($_GET['c'] !== 'w'){
			$c = $_GET['c'];
			if ($c === 'g') $c = '#C6C6C6';
			else $c = '#'.strtoupper($c);
		}
	}

	if (!empty($_GET['o']) && preg_match('/^0?\.[1-9]$/', $_GET['o']))
		$o = $_GET['o'];

	header('Content-Type: image/svg+xml');
	echo <<<XML
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" x="0px" y="0px" viewBox="0 0 16 16" enable-background="new 0 0 16 16" xml:space="preserve"><polyline fill="none" stroke="$c" opacity="$o" stroke-miterlimit="10" points="15.5,0 15.5,15.5 0,15.5"/></svg>
XML;
