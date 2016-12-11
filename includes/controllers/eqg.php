<?php

use App\HTTP;

/** @var $data string */

if (is_numeric($data))
	HTTP::redirect("/movie/$data");
else HTTP::redirect("/movie/equestria-girls-$data");
