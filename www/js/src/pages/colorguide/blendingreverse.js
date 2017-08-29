/* global DocReady,HEX_COLOR_PATTERN,noUiSlider,saveAs */
$(function(){
	'use strict';

	const RGB = ['red','green','blue'];
	const ADD = {red: 0, green: 1, blue: 2};
	const ix = (i, k) => i + ADD[k];

	const Blender = {
		normal: (A, Top, Bot) => A * Top + (1 - A) * Bot,
		normalReverse: (A, Top, Res) => (Res - (A * Top)) / (1 - A),
		normalFilter: (A, Res, Bot) => (Res - (1-A)*Bot) / A,
		multiply: (A, Top, Bot) => A * ( (Top * Bot) / 255 ) + (1 - A) * Bot,
		multiplyReverse: (A, Top, Res) => (Res - (A * Top)) / (1 - A),
		multiplyFilter: (A, Res, Bot) => {
			Bot /= 255;
			Res /= 255;
			return ((A * Bot - Bot + Res) / (A * Bot)) * 255;
		},
	};

	class MultiplyReverseForm {
		constructor(){
			this.$controls = $('#controls');
			this.$knownColorsTbody = $('#known-colors').find('tbody');
			this.$backupImage = $.mk('img');
			this.backupImage = this.$backupImage.get(0);
			this.overlayColor = new $.RGBAColor(255,0,255,0.75);
			this.haveImage = false;
			this.fileName = null;
			this.selectedFilterColor = null;
			this.$preview = $('#preview');
			this.$previewImageCanvas = $('#preview-image');
			this.previewImageCanvas = this.$previewImageCanvas.get(0);
			this.previewImageCtx = this.previewImageCanvas.getContext('2d');
			this.$previewOverlayCanvas = $('#preview-overlay');
			this.previewOverlayCanvas = this.$previewOverlayCanvas.get(0);
			this.previewOverlayCtx = this.previewOverlayCanvas.getContext('2d');
			this.$addKnownColor = $('#add-known-color').on('click',e => {
				e.preventDefault();

				this.addKnownValueInputRow();
			});
			this.$imageSelect = $('#image-select');
			this.$imageSelectFileInput = this.$imageSelect.children('input').on('change', e => {
				const input = e.target;
				if (input.files && input.files[0]){
					this.fileName = input.files[0].name.split(/[\/]/g).pop();
					const reader = new FileReader();
					reader.onload = e => {
						this.backupImage.src = e.target.result;
						this.$backupImage.one('load',() => {
							this.$backupImage.off('error');
							this.haveImage = true;
							this.updatePreview();
						}).one('error', () => {
							this.$backupImage.off('load');
							$.Dialog.fail('Could not load image. Please make sure it is an actual image file.');
						});
					};
					reader.readAsDataURL(input.files[0]);
				}
			});
			this.$imageSelectFileButton = this.$imageSelect.children('button').on('click', e => {
				e.preventDefault();

				this.$imageSelectFileInput.click();
			});
			this.$filterTypeSelect = $('#filter-type').children('select').on('change',e => {
				this.updateFilterCandidateList();
				this.updatePreview();
			});
			this.$sensitivityControls = $('#sensitivity');
			this.$sensitivitySlider = this.$sensitivityControls.children('div');
			this.$sensitivityDisplay = this.$sensitivityControls.find('.display');
			this.sensitivitySlider = this.$sensitivitySlider.get(0);
			noUiSlider.create(this.sensitivitySlider, {
				start: [10],
				range: {
					'min': 0,
					'max': 255
				},
				step: 1,
				behaviour: 'drag snap',
				format: {
					to: n => parseInt(n,10),
					from: n => parseInt(n,10),
				}
			});
			this.sensitivitySlider.noUiSlider.on('update',(values,handle) => {
				this.$sensitivityDisplay.text(values[handle]);
			});
			this.sensitivitySlider.noUiSlider.on('end',() => {
				this.updatePreview();
			});
			this.$resultSaveButton = $('#result').children('button').on('click', e => {
				e.preventDefault();

				if (!this.haveImage || this.selectedFilterColor === null)
					return;

				this.previewImageCanvas.toBlob(blob => {
					const ins = ' (no '+this.getFilterType()+' filter)';
				    saveAs(blob, this.fileName.replace(/^(.*?)(\.(?:[^.]+))?$/,`$1${ins}$2`) || 'image'+ins+'.png');
				});
			});
			this.$filterCandidates = $('#filter-candidates').children('ul');
			this.$filterCandidates.on('click','li',e => {
				const
					$li = $(e.target).closest('li'),
					hasClass = $li.hasClass('selected');
				this.$filterCandidates.children('.selected').removeClass('selected');
				if (!hasClass){
					$li.addClass('selected');
					this.selectedFilterColor = $.RGBAColor.parse($li.attr('data-rgba'));
				}

				this.updatePreview();
			});
			this.$overlayControls = $('#overlay');
			this.$overlayToggleInput = this.$overlayControls.find('input[type="checkbox"]').on('change input',e => {
				this.$previewOverlayCanvas[e.target.checked ? 'removeClass' : 'addClass']('hidden');
			});
			this.$overlayColorInput = this.$overlayControls.find('input[type="text"]').on('change',e => {
				const newcolor =  $.RGBAColor.parse(e.target.value);
				if (newcolor === null)
					return;

				e.target.value = newcolor;
				this.overlayColor = newcolor;
				this.repaintOverlay();
			}).on('change input blur',e => {
				const
					$el = $(e.target),
					val = $.RGBAColor.parse(e.target.value);
				if (val === null){
					$el.css({
						color: '',
						backgroundColor: '',
					});
					return;
				}

				$el.css({
					color: val.isLight() ? 'black' : 'white',
					backgroundColor: val.toString(),
				});
			});
			this.$overlayColorInput.val(this.overlayColor.toString()).trigger('input');

			this.addKnownValueInputRow();
			this.addKnownValueInputRow();


			/// DEBUG ///
			const vals = ['#FFFF00','#AABD39','#0000FF','#2B3EB8'];
			this.$knownColorsTbody.find('input').each((i, el) => {
				$(el).val(vals[i]).trigger('blur');
			});
		}
		createKnownValueInput(className){
			return $.mk('td').attr('class','color-cell '+className).append(
				$.mk('input').attr({
					type: 'text',
					required: true,
					autocomplete: 'off',
					spellcheck: 'false',
				}).on('input change blur',e => {
					const
						$this = $(e.target),
						value = $this.val(),
						rgb = $.RGBAColor.parse(value);
					if (rgb === null)
						$this.css({color:'',backgroundColor:''});
					else $this.css({
						color: rgb.isLight() ? 'black' : 'white',
						backgroundColor: rgb.toHex(),
					});
				}).on('blur',e => {
					const
						$this = $(e.target),
						parsed = $.RGBAColor.parse($this.val());
					if (parsed !== null)
						$this.removeAttr('pattern').val(parsed);
					else $this.attr('pattern','^[^\\s\\S]$');

					this.updateFilterCandidateList();
				}).on('paste',e => {
					window.requestAnimationFrame(function(){
						$(e.target).trigger('blur');
					});
				})
			);
		}
		addKnownValueInputRow(){
			this.$knownColorsTbody.append(
				$.mk('tr').append(
					this.createKnownValueInput('original'),
					this.createKnownValueInput('filtered')/*,
					$.mk('td').attr('class','actions').append(
						$.mk('button').attr({
							'class':'red typcn typcn-minus',
							title: 'Remove known color pair',
						}).on('click', e => {
							e.preventDefault();

							$(e.target).closest('tr').remove();
						})
					)*/
				)
			);
		}
		redrawPreviewImage(){
			this.previewOverlayCanvas.width =
			this.previewImageCanvas.width = this.backupImage.width;
			this.previewOverlayCanvas.height =
			this.previewImageCanvas.height = this.backupImage.height;
			this.previewImageCtx.drawImage(this.backupImage, 0, 0);
			this.previewOverlayCtx.clearRect(0, 0, this.previewOverlayCanvas.width, this.previewOverlayCanvas.height);
		}
		repaintOverlay(){
			if (!this.haveImage)
				return;

			const overlayData = this.previewOverlayCtx.getImageData(0, 0, this.previewOverlayCanvas.width, this.previewOverlayCanvas.height);
			for (let i=0; i<overlayData.data.length; i+=4){
				if (overlayData.data[i+3] !== 1)
					continue;

				overlayData.data[i] = this.overlayColor.red;
				overlayData.data[i+1] = this.overlayColor.green;
				overlayData.data[i+2] = this.overlayColor.blue;
			}
			this.previewOverlayCtx.putImageData(overlayData,0,0);
		}
		updatePreview(){
			if (!this.haveImage){
				this.$resultSaveButton.disable();
				return;
			}

			this.redrawPreviewImage();

			const noFilterColor = this.selectedFilterColor === null;

			this.$resultSaveButton.attr('disabled', noFilterColor);

			if (noFilterColor)
				return;

			const maxdiff = this.sensitivitySlider.noUiSlider.get();
			const imgData = this.previewImageCtx.getImageData(0,0,this.previewImageCanvas.width,this.previewImageCanvas.height);
			const overlayData = this.previewOverlayCtx.getImageData(0,0,this.previewOverlayCanvas.width,this.previewOverlayCanvas.height);
			const calculator = this.getReverseCalculator();

			for (let i=0; i<imgData.data.length; i+=4){
				$.each(RGB, (_, k) => {
					const j = ix(i, k);
					const newpixel = calculator(this.selectedFilterColor.alpha, this.selectedFilterColor[k], imgData.data[j]);
					const toobig = newpixel-maxdiff > 255;
					const toosmall = newpixel+maxdiff < 0;
					if (toosmall || toobig){
						overlayData.data[i] = this.overlayColor.red;
						overlayData.data[i+1] = this.overlayColor.green;
						overlayData.data[i+2] = this.overlayColor.blue;
						overlayData.data[i+3] = this.overlayColor.alpha;
					}
					imgData.data[j] = $.rangeLimit(newpixel, false, 0, 255);
				});
			}
			this.previewImageCtx.putImageData(imgData,0,0);
			this.previewOverlayCtx.putImageData(overlayData,0,0);
		}
		updateFilterCandidateList(){
			const pairs = [];

			this.$knownColorsTbody.children().each((_, el) => {
				const
					$tr = $(el),
					$inputs = $tr.find('input:valid');
				if ($inputs.length !== 2)
					return;

				const values = {};
				$inputs.each((_, input) => {
					values[input.parentNode.className.split(' ')[1]] = $.RGBAColor.parse(input.value);
				});
				pairs.push(values);
			});

			if (!pairs.length)
				return;

			let allfilters = this.getValidFilterValues(pairs[0].original, pairs[0].filtered);

			this.$filterCandidates.empty();
			if (pairs.length < 2)
				return;

			let bestfilters = this.pickBestFilterValues(allfilters, pairs[1].original, pairs[1].filtered);

			$.each(bestfilters, (_, color) => {
				this.$filterCandidates.append(
					MultiplyReverseForm.getFilterDisplayLi(color)
				);
			});
		}
		static getFilterDisplayLi(color){
			const rgba = color.round().toRGBA();
			return $.mk('li').attr({'data-rgba':rgba,title:'Click to select & apply'}).append(
				$.mk('div').attr('class', 'color-preview').append(
					$.mk('span').css('background-color', rgba)
				),
				$.mk('div').attr('class', 'color-rgba').append(
					`<div><strong>R:</strong> <span class="color-red">${color.red}</span></div>`,
					`<div><strong>G:</strong> <span class="color-green">${color.green}</span></div>`,
					`<div><strong>B:</strong> <span class="color-blue">${color.blue}</span></div>`,
					`<div><strong>A:</strong> <span>${color.alpha*100}%</span></div>`
				)
			);
		}
		getFilterType(){
			return this.$filterTypeSelect.children(':selected').attr('value');
		}
		getFilterCalculator(){
			switch (this.getFilterType()){
				case 'multiply':
					return Blender.multiplyFilter;
				case 'normal':
					return Blender.normalFilter;
			}
		}
		getNormalCalculator(){
			switch (this.getFilterType()){
				case 'multiply':
					return Blender.multiply;
				case 'normal':
					return Blender.normal;
			}
		}
		getReverseCalculator(){
			switch (this.getFilterType()){
				case 'multiply':
					return Blender.multiplyReverse;
				case 'normal':
					return Blender.normalReverse;
			}
		}
		getValidFilterValues(Bot, Top){
			let valid = [];
			for (let i = 0; i<=100; i++){
				const
					values ={},
					alpha = i/100;
				let rip = false;
				$.each(RGB, (_, k) => {
					const run = this.getFilterCalculator()(alpha, Top[k], Bot[k]);
					if (isNaN(run)){
						values[k] = run;
						return;
					}

					if (!isFinite(run) || run < 0 || run > 255){
						rip = true;
						return false;
					}
					values[k] = run;
				});
				if (rip)
					continue;
				values.alpha = alpha;
				valid.push($.RGBAColor.fromRGB(values));
			}
			return valid;
		}
		pickBestFilterValues(valid, Bot, Top){
			let lowestScore, lowestIndex = 0, winners = [];
			const calculator = this.getNormalCalculator();
			$.each(valid, (ix, filterColor) => {
				let score = 0;
				$.each(RGB, (_, k) => {
					if (isNaN(filterColor[k])){
						let subScore, subValue;
						for (let test = 0; test <= 255; test++){
							const run = calculator(filterColor.alpha, test, Bot[k]);
							let diff = Math.abs(Top[k] - run);
							if (subScore === undefined || diff < subScore){
								subScore = diff;
								subValue = test;
							}
						}
						filterColor[k] = subValue;
						score += subScore;
					}
					else {
						const run = calculator(filterColor.alpha, filterColor[k], Bot[k]);
						score += Math.abs(Top[k] - run);
					}
				});
				if (score === 0)
					winners.push(filterColor);
				if (lowestScore === undefined || score < lowestScore){
					lowestScore = score;
					lowestIndex = ix;
				}
			});
			if (winners.length > 0)
				return winners;
			return [valid[lowestIndex]];
		}
	}

	new MultiplyReverseForm();
});
