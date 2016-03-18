<div id="content">
	<div class="browser-<?=browser_name_to_class_name($browser['browser_name'])?>"></div>
	<h1><?=$browser['browser_name'].' '.$browser['browser_ver']?></h1>
	<p>on <?=$browser['platform']?></p>

    <?=!empty($Session)?Notice('warn',"You're debugging session #{$Session['id']} (belongs to ".profile_link(get_user($Session['user'])).")"):''?>
	<?=Notice('info','Browser recognition testing page',"The following page is used to make sure that the site's browser detection script works as it should. If you're seeing a browser and/or operating system that's different from what you're currently using, please <a href='#feedback' class='send-feedback'>let us know.</a>")?>

	<section>
		<h2>Your User Agent string</h2>
		<p><code><?=$browser['user_agent']?></code></p>
	</section>
</div>
