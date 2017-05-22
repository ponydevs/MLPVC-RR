<?php
use App\CoreUtils;
use App\Permission;
use App\Posts;
/** @var $title string */ ?>
<div id="content">
	<h1><?=$title?></h1>
	<p>Various tools related to managing the site</p>
	<div class='align-center links'>
		<a class='btn darkblue typcn typcn-document-text' href="/admin/logs">Global Logs</a>
<?php   if (Permission::sufficient('developer')){ ?>
		<a class='btn darkblue typcn typcn-code' href="/admin/wsdiag">WS Diagnostics</a>
<?php   } ?>
		<a class='btn darkblue typcn typcn-link' href="/admin/usefullinks">Useful Links</a>
		<a class='btn typcn btn-discord' href="/admin/discord">Discord Server Connections</a>
	</div>

	<section class="overdue-submissions">
		<h2><span class="typcn typcn-time"></span>Overdue submissions</h2>
		<div><?=CoreUtils::getOverdueSubmissionList()?></div>
	</section>

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
			foreach ($indices as $no => $index){
				echo "#$no ";
				foreach ($index as $key => $value){
					if (empty($value))
						continue;
					echo "$key:$value ";
				}
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
			echo "<strong>Server is down.</strong></code></pre>";
		} ?>
	</section>
<?  } ?>

	<section class="recent-posts">
		<h2><span class="typcn typcn-bell"></span>Most recent posts</h2>
		<div></div>
	</section>
</div>
