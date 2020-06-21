<?php

namespace App;

use Monolog\ErrorHandler;

class PennyErrorHandler extends ErrorHandler {
  /**
   * Displays a cute error page if an error occurs
   */
  private function outputErrorPage() {
    if (PHP_SAPI === 'cli')
      return;

    $title = '500 Internal Server Error';
    if (!headers_sent()) {
      http_response_code(500);
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><title>$title</title></head><style>
html, body, #wr {
	width: 100%;
	height: 100%;
	margin: 0;
	padding: 0;
	color: #eef; 
	background-color: #1E3DB3;
}
#wr {
	display: flex;
	align-items: center;
	justify-content: center;
	text-align: center;
}
#c {
	display: flex;
	flex-flow: column nowrap;
}
#c > * { margin: 0 0 10px }
#c > :last-child { margin-bottom: 0 }
#penny {
	display: block;
	font-size: .75rem;
}
#penny > img {
  padding: 0 1rem;
  max-width: 100%;
}
a { color: #def }
body { font-family: sans-serif }
</style><body>
<div id="wr">
	<div id="c">
		<figure id="penny">
		  <img src="/img/penny-gone-mad.png" alt="The club mascot with a crazy facial expression">
		  <figcaption>Artwork by <a href="https://www.deviantart.com/Pirill-Poveniy">Pirill-Poveniy</a> &bull; <a href="https://derpibooru.org/images/1159625" rel="noopener noreferrer">Derpibooru source</a></figcaption>
    </figure>
		<h1>$title</h1>
		<p>The issue has been logged & the developer will be notified.</p>
		<p>You can also <a href="http://fav.me/d9zt1wv">join our Discord server</a> to notify the rest of the staff.</p>
	</div>
</div>
</body></html>
HTML;
  }

  public function handleException($e) {
    $this->outputErrorPage();

    parent::handleException($e);
  }
}
