<div id="content">
	<h1><?=$title?></h1>
	<p>Originally made by <?=profile_link(get_user('Dasprid','name','name, avatar_url'), FORMAT_FULL)?></p>
	<?=Notice('info',"<p><span class='typcn typcn-info-large'></span> This is a tool which helps you to find an original $color, given two different background {$color}s and the resulting blended $color, when the original $color is blended over it.</p><p><strong>Shift+Click</strong> an input to open a dialog where you can enter RGB values</p><a class='btn typcn typcn-arrow-back' href='/{$color}guide'>Back to $Color Guide</a>", true)?>

	<div id="blend-wrap">
		<form autocomplete="off">
			<table>
				<thead>
					<tr>
						<th>Background</th>
						<th>Blended <?=$color?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<div class="clr">
								<span class='clrp'></span><input type="text" class="clri" id="screw_lastpass--search1" name="bg1" pattern="<?=$HexPattern?>" required spellcheck="false" value="#ffffff">
							</div>
						</td>
						<td>
							<div class="clr">
								<span class='clrp'></span><input type="text" class="clri" id="screw_lastpass--search2" name="blend1" pattern="<?=$HexPattern?>" required spellcheck="false" value="#daf6f7">
							</div>
						</td>
					</tr>
					<tr>
						<td>
							<div class="clr">
								<span class='clrp'></span><input type="text" class="clri" id="screw_lastpass--search3" name="bg2" pattern="<?=$HexPattern?>" required spellcheck="false" value="#000000">
							</div>
						</td>
						<td>
							<div class="clr">
								<span class='clrp'></span><input type="text" class="clri" id="screw_lastpass--search4" name="blend2" pattern="<?=$HexPattern?>" required spellcheck="false" value="#9bb5b6">
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			<input type="submit" class="hidden">
		</form>
		<span></span>
		<div class="result">
			<div class="preview"></div>
			<span class="hex" data-color="<?=$color?>"></span>
			<span class="hexa" data-color="<?=$color?>"></span>
			<span class="rgba" data-color="<?=$color?>"></span>
			<span class="opacity" data-color="value"></span>
		</div>
	</div>
	<div class="hidden delta-warn">
		<?=Notice('warn',"<span class='typcn typcn-warning'></span> The result may not be accurate as the difference between the optimal $color and the closest match is too large", true)?>
	</div>
</div>
<script>var HEX_COLOR_PATTERN = <?=rtrim(HEX_COLOR_PATTERN,'u')?>;</script>
