<?php
use App\CoreUtils;
use App\Permission;
/** @var $title string */ ?>
<div id="content" class="section-container">
	<h1><?=$title?></h1>
	<p>Various tools related to managing the site</p>
	<div class='align-center button-block'>
		<a class='btn link typcn typcn-document-text' href="/admin/logs">Logs</a>
<?php   if (Permission::sufficient('developer')){ ?>
		<a class='btn link typcn typcn-code' href="/admin/wsdiag">WS</a>
<?php   } ?>
		<a class='btn link typcn typcn-link' href="/admin/usefullinks">Useful Links</a>
		<a class='btn link typcn typcn-user' href="/admin/pcg-appearances">PCG Appearances</a>
	</div>

<?=CoreUtils::getOverdueSubmissionList()?>

	<section class="mass-approve">
		<h2><span class="typcn typcn-tick"></span>Bulk approve posts <button id="bulk-how" class="darkblue typcn typcn-info-large">How it works</button></h2>
		<div class="textarea" contenteditable="true"></div>
	</section>

<?  if (Permission::sufficient('developer')){ ?>
	<section class="elastic-status">
		<h2><span class="typcn typcn-zoom"></span>Elastic status</h2>
<?php   try {
			$client = CoreUtils::elasticClient();
			$client->ping();
			$indices = $client->cat()->indices(['v' => true]);
			$nodes = $client->cat()->nodes(['v' => true]); ?>
		<pre><code><strong>Indices</strong><br><?php
			$usedIndexes = ['appearances'];
			foreach ($indices as $no => $index){
				if (!in_array($index['index'], $usedIndexes, true))
					continue;

				echo "#$no ";
				foreach ($index as $key => $value){
					if (empty($value))
						continue;
					echo "$key:$value ";
				}
				echo "\n";
			}
		?></code></pre>
		<pre><code><strong>Nodes</strong><br><?php
			foreach ($nodes as $no => $node){
				echo "#$no ";
				foreach ($node as $key => $value){
					if (empty($value))
						continue;
					echo "$key:$value ";
				}
			}
		?></code></pre>
<?php   }
		catch (\Elasticsearch\Common\Exceptions\NoNodesAvailableException $e){
			echo '<strong>Server is down.</strong></code></pre>';
		} ?>
	</section>
<?  } ?>

	<section class="recent-posts">
		<h2><span class="typcn typcn-bell"></span>Most recent posts</h2>
		<div><?=\App\Posts::getMostRecentList()?></div>
	</section>
</div>
