<div id="content">
	<h1>403 Unathorized</h1>
	<p>You <?=\App\Auth::$signed_in ? "don't have permission" : 'must be logged in'?> to view this content</p>
</div>
