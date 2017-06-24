<div id="content">
    <h1><?=$heading?></h1>
    <p>Displaying <?=$itemsPerPage?> items/page</p>

    <?=$Pagination?>
    <div class="responsive-table">
    <?=\App\Users::getContributionListHTML($params['type'], $data)?>
    </div>
    <?=$Pagination?>
</div>
