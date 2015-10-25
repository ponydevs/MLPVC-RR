<div id="content">
	<h1><?=$heading?></h1>
	<p>A searchable list of chracater <?=$color?>s from the show</p>
<? if (PERM('inspector')){ ?>
	<div class="notice warn tagediting">
		<label>Some features are unavailable</label>
		<p>Because you seem to be using a mobile device, editing tags & colors may not work, as it requires you to right-click. If you want to do either of these, please do so from a computer.</p>
	</div>
<? } ?>
	<div id='universal'>
		<div>
			<strong>Universal colors</strong>
			<div class="notes">These colors apply to most characters in the show. Use these unless a different color is specified.</div>
			<ul class="colors static">
				<li>
					<span class='cat'>Normal: </span><span style='background-color:#FFFFFF' title='Teeth Fill'>#FFFFFF</span><span style='background-color:#B0D8E7' title='Teeth Outline'>#B0D8E7</span><span style='background-color:#BD1C77' title='Mouth Fill'>#BD1C77</span><span style='background-color:#A41967' title='Darker Mouth Fill'>#A41967</span><span style='background-color:#841555' title='Darkest Mouth Fill'>#841555</span><span style='background-color:#F17031' title='Tongue'>#F17031</span><span style='background-color:#BE4406' title='Tongue Dark'>#BE4406</span><span style='background-color:#000000' title='Emotional Turmoil (up to 15% opacity)'>#000000</span>
				</li>
<? /*
				<li>
					<span class='cat'>Discorded (Partial):</span>
					<span style='background-color:#CECECE' title='Teeth Outline'>#CECECE</span>
					<span style='background-color:#92376D' title='Mouth Fill'>#92376D</span>
					<span style='background-color:#702050' title='Mouth Dark Fill'>#702050</span>
					<span style='background-color:#BEA1BB' title='Tongue'>#BEA1BB</span>
					<span style='background-color:#966D92' title='Tongue Dark'>#966D92</span>
				</li>
				<li>
					<span class='cat'>Discorded (Total):</span>
					<span style='background-color:#CCCDD3' title='Teeth Outline'>#CCCDD3</span>
					<span style='background-color:#6D6765' title='Mouth Fill'>#6D6765</span>
					<span style='background-color:#444140' title='Mouth Dark Fill'>#444140</span>
					<span style='background-color:#ABAAA8' title='Tongue'>#ABAAA8</span>
					<span style='background-color:#828180' title='Tongue Dark'>#828180</span>
				</li>
 */ ?>
			</ul>
		</div>
	</div>
	<p class='align-center links'>
<? if (PERM('inspector')){ ?>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new <?=$EQG?'Character':'Pony'?></button>
<? } ?>
		<a class='btn blue typcn typcn-world' href="/<?=$color?>guide<?=($EQG?'':'/eqg')?>/1"><?=$EQG?'List of Ponies':'List of Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-tags' href="/<?=$color?>guide/tags">List of tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/<?=$color?>guide/changes">List of major changes</a>
	</p>

<?  if (PERM('user')){ ?>
	<form id="search-form"><input name="q" <?=!empty($_GET['q'])?" value='".apos_encode($_GET['q'])."'":''?> title='Search'> <button class='blue typcn typcn-zoom'></button><button type='reset' class='orange typcn typcn-times' title='Clear'<?=empty($_GET['q'])?'disabled':''?>></button><p>Enter tags separated by commas. You can search for up to 6 tags at a time.</p></form>
<?  }
	else echo Notice('info',"<span class='typcn typcn-info-large'></span> Please sign in with the button in the sidebar to use the search feature.</p>",true); ?>
	<?=$Pagination?>
	<?=render_ponies_html($Ponies)?>
	<?=$Pagination?>
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>', EQG = <?=$EQG?'true':'false'?>;</script>
<?php if (PERM('inspector')){ ?>
<script>var TAG_TYPES_ASSOC = <?=json_encode($TAG_TYPES_ASSOC)?>, MAX_SIZE = '<?=get_max_upload_size()?>', PRINTABLE_ASCII_REGEX = '<?=PRINTABLE_ASCII_REGEX?>', HEX_COLOR_PATTERN = <?=rtrim(HEX_COLOR_PATTERN,'u')?>;</script>
<?php } ?>
</div>
