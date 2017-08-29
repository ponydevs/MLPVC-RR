<div id="content">
	<h1><?=$title?></h1>
	<p>Reverse various filters applied to an image</p>

	<div id="controls">
		<div class="section">
			<div id="filter-type">
				<h2>Filter type</h2>
				<select>
					<option value="normal" selected>Normal</option>
					<option value="multiply">Multiply</option>
				</select>
			</div>
			<div id="known-colors">
				<h2>Known color pairs</h2>
				<table>
					<thead>
						<tr>
							<th>Original</th>
							<th>Filtered</th>
							<!--<td><button id="add-known-color" class="green typcn typcn-plus" title="Add known color"></button></td>-->
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<div id="filter-candidates">
				<h2>Potential filters</h2>
				<ul></ul>
			</div>
		</div>
		<div class="section">
			<div id="image-select">
				<h2>Image</h2>
				<button class="blue typcn typcn-upload">Browse&hellip;</button>
				<input type="file" class="hidden" accept=".png,.jpg,.jpeg,.bmp,image/png,image/jpeg,image/bmp">
			</div>
			<div id="sensitivity">
				<h2>Sensitivity<span class="display"></span></h2>
				<div></div>
			</div>
			<div id="overlay">
				<h2>Overlay</h2>
				<label><input type="checkbox" checked><span>Show overlay</span></label>
				<label class="select-color"><span>Overlay color:</span> <span><input type="text"></span></label>
			</div>
			<div id="result">
				<button class="green typcn typcn-download" disabled>Save filter-free image</button>
			</div>
		</div>
	</div>
	<div id="preview-wrap">
		<div id="preview">
			<canvas id="preview-overlay" width="1920" height="1080"></canvas>
			<canvas id="preview-image" width="1920" height="1080"></canvas>
		</div>
	</div>
</div>
<?  global $HEX_COLOR_REGEX;
	echo \App\CoreUtils::exportVars(['HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX]);
