<?php

	if (Permission::Insufficient('developer'))
		CoreUtils::NotFound();
		
	header('Content-Type: text/plain; charset=utf-8;');
	readfile(APPATH.'../mlpvc-rr-error.log');