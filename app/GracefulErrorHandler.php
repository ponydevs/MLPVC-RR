<?php

namespace App;

use Monolog\ErrorHandler;

class GracefulErrorHandler extends ErrorHandler {
	private function _outputErrorPage(){
		$title = '500 Internal Server Error';
		header($_SERVER['SERVER_PROTOCOL']." $title");
		echo <<<HTML
<!DOCTYPE html>
<html><head><title>$title</title></head><style>
html, body, #wr {
	width: 100%;
	height: 100%;
	margin: 0;
	padding: 0;
}
#wr {
	display: flex;
	align-items: center;
	justify-content: center;
	text-align: center;
	background: black;
}
#c {
	display: flex;
	flex-flow: column nowrap;
	color: #fff; 
}
#c > * { margin: 0 0 10px }
#c > :last-child { margin-bottom: 0 }
#emoji {
	display: block;
	font-size: 10vh;
    font-family: "Apple Color Emoji","Segoe UI Emoji","NotoColorEmoji","Segoe UI Symbol","Android Emoji","EmojiSymbols";
}
a { color: #def }
p, h1 { font-family: sans-serif }
</style><body>
<div id="wr">
	<div id="c">
		<span id="emoji">&#x1F5A5;&#x1F525;&#x1F692;</span>
		<h1>$title</h1>
		<p>The issue has been logged & the developer will be notified.</p>
		<p>You can also <a href="https://discord.gg/hrffb8k">join our Discord server</a> to notify the rest of the staff.</p>
	</div>
</div>
</body></html>
HTML;
	}

	public function handleException($e){
		$this->_outputErrorPage();
		parent::handleException($e);
	}
}
