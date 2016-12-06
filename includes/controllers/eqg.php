<?php

use App\HTTP;

if (is_numeric($data))
	HTTP::Redirect("/movie/$data");
else HTTP::Redirect("/movie/equestria-girls-$data");
