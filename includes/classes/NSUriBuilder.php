<?php

namespace App;

use Peertopark\UriBuilder;

class NSUriBuilder extends UriBuilder {
	public function __toString() {
		return $this->build_http_string();
	}
}
