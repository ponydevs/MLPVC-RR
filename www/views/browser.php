<div id=content>
    <div class="browser-<?=browser_name_to_class_name($browser['browser_name'])?>"></div>
    <h1><?=$browser['browser_name'].' '.$browser['browser_ver']?></h1>
    <p>on <?=$browser['platform']?></p>
    <div class='notice info'>
        <label>Browser recognition testing page</label>
        <p>The following page is used to make sure that the site's browser detection script works as it should. If you're seeing a browser and/or operating system that's different from what you're currently using, please let us know.</p>
    </div>
</div>
