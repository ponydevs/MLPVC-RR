/* global mk */
window.$TemplateGenFormTemplate = (function($){
	'use strict';

	const IMAGES_VERSION = '1.1';

	return $.mk('form','template-gen-form').html(
		`<div class="label">
			<span>Species</span>
			<div class="radio-group">
				<label><input type="radio" name="features" value="" required checked><span>Earth pony</span></label>
				<label><input type="radio" name="features" value="horn" required><span>Unicorn</span></label>
				<label><input type="radio" name="features" value="wing" required><span>Pegasus</span></label>
				<label><input type="radio" name="features" value="horn,wing" required><span>Alicorn</span></label>
			</div>
		</div>
		<div class="label">
			<span>Body type</span>
			<div class="radio-group">
				<label><input type="radio" name="body" value="female" required checked><span>Female</span></label>
				<label><input type="radio" name="body" value="male" required><span>Male</span></label>
			</div>
		</div>
		<div class="label">
			<span>Eye shape</span>
			<div class="radio-group">
				<label><input type="radio" name="eyes" value="1" required checked><span>#1</span></label>
				<label class="male-hide"><input type="radio" name="eyes" value="2" required><span>#2</span></label>
				<label><input type="radio" name="eyes" value="3" required><span>#3</span></label>
			</div>
		</div>
		<div class="label">
			<span>Eye gradient</span>
			<div class="radio-group">
				<label><input type="radio" name="eye_grad" value="2" required checked><span>2 colors</span></label>
				<label><input type="radio" name="eye_grad" value="3" required><span>3 colors</span></label>
			</div>
		</div>`
	).on('submit', function(e){
		e.preventDefault();

		// We don't want the form to close out randomly when someone presses enter for example
	}).on('added',function(){
		let colors = {}, ready = false;
		const
			$form = $(this),
			previewCanvas = mk('canvas'),
			$downloadButton = $.mk('a').attr({'class':'btn typcn typcn-download',download:'sprite.png',disabled:true}).text('Download'),
			$acceptCheckbox = $.mk('input').attr({
				type: 'checkbox',
				name: 'accept_terms',
			}).on('change mouseup',function(){
				$downloadButton.attr('disabled',!this.checked);
			}),
			$applyColors = $.mk('input').attr({
				type: 'checkbox',
				name: 'apply_colors',
				checked: true,
			}),
			templateImageNames = [
				"cm_square",
				"eyes_male12",
				"eyes_male3",
				"eyes_male12_grad2",
				"eyes_male12_grad3",
				"eyes_male3_grad2",
				"eyes_male3_grad3",
				"eyes_female1",
				"eyes_female2",
				"eyes_female3",
				"eyes_female12_grad2",
				"eyes_female12_grad3",
				"eyes_female3_grad2",
				"eyes_female3_grad3",
				"horn_female",
				"horn_male",
				"wing_female",
				"wing_male",
				"body_female",
				"body_male",
				"eye_grad2",
				"eye_grad3",
			],
			templateImages = {},
			drawImage = (ctx, img) => {
				if (typeof templateImages[img] === 'undefined')
					throw new Error('Missing template image: '+img);
				ctx.drawImage(templateImages[img],0,0,300,300,0,0,300,300);
			},
			generate = () => {
				if (!ready)
					return;

				const
					data = $form.mkData(),
					ctx = previewCanvas.getContext('2d'),
					maleBody = data.body === 'male';
				delete data.accept_terms;

				ctx.clearRect(0,0,ctx.canvas.width,ctx.canvas.height);

				drawImage(ctx, 'cm_square');

				// Body shape
				drawImage(ctx, `body_${data.body}`);

				const $maleHide = $form.find('.male-hide > input');
				if (maleBody){
					if ($maleHide.is(':checked'))
						$maleHide.parent().prev().children('input').prop('checked', true);
				}
				$maleHide.attr('disabled',maleBody);

				// Horn / Wings
				if (data.features){
					$.each(data.features.split(','),(_, feature) => {
						drawImage(ctx, `${feature}_${data.body}`);
					});
				}
				delete data.features;

				// Eyes
				drawImage(ctx, `eyes_${data.body}${maleBody&&data.eyes<3?'12':data.eyes}`);
				drawImage(ctx, `eyes_${data.body}${data.eyes<3?'12':'3'}_grad${data.eye_grad}`);
				delete data.eyes;

				const applyColors = Boolean(data.apply_colors);
				delete data.apply_colors;

				// Other stuff
				delete data.body;
				$.each(data,(k,v) => {
					drawImage(ctx, k+v);
				});

				if (!applyColors)
					return;

				const imageData = ctx.getImageData(0, 0, ctx.canvas.width, ctx.canvas.height);
				for (let i = 0; i < imageData.data.length; i += 4){
					const alpha = imageData.data[i + 3];
					if (alpha === 0)
						continue;

					const mapping = colors[imageData.data.slice(i,i+3).join(',')];
					if (mapping){
						imageData.data[i] = mapping.r;
						imageData.data[i + 1] = mapping.g;
						imageData.data[i + 2] = mapping.b;
					}
				}
				ctx.putImageData(imageData, 0, 0);

				$downloadButton.attr('href',ctx.canvas.toDataURL('image/png'));
			};
		previewCanvas.width = 300;
		previewCanvas.height = 300;
		$(previewCanvas).on('mousedown dragstart contextmenu keydown',() => false).on('focus',function(){
			this.blur();
		});
		$form.append(
			$.mk('label').append(
				$applyColors,
				` <span>Replace placeholder colors with the colors from the appearance</span>`
			),
			$.mk('div').html(previewCanvas),
			$.mk('label').append(
				$acceptCheckbox,
				` <span>I accept that generated images are licensed under <a href='https://creativecommons.org/licenses/by-nc-sa/4.0/' target="_blank" rel="noopener">CC-BY-NC-SA 4.0</a></span>`
			)
		).on('got-colors',function(_, incolors){
			colors = {};
			$.each(incolors,(o,n)=>{
				const orgb = $.hex2rgb(o);
				colors[`${orgb.r},${orgb.g},${orgb.b}`] = $.hex2rgb(n);
			});
			if (typeof incolors['#606060'] !== 'undefined')
				$form.find('input[name="eye_grad"][value="3"]').prop('checked', true);
			if (typeof incolors['#B7B7B7'] !== 'undefined')
				$form.find('input[name="features"][value="horn"]').prop('checked', true);
		}).on('change click mousedown','input',$.throttle(100,generate));
		$('#dialogButtons').prepend($downloadButton);

		$.Dialog.wait(false,'Preloading images');
		let loaded = 0;
		$.each(templateImageNames,function(_,name){
			const img = new Image();
			img.src = `/img/sprite_template/${name}.png?v=`+IMAGES_VERSION;
			$(img).on('load',function(){
				loaded++;
				templateImages[name] = img;

				if (loaded === templateImageNames.length){
					$.Dialog.clearNotice(/Preloading/);
					ready = true;
					generate();
				}
			}).on('error',function(){
				console.log('Loaded %d out of %d before erroring',loaded,templateImages.length);
				$.Dialog.fail(false, 'Some images failed to load, please try <a class="sprite-template-gen">re-opening this form</a>, and if this issue persists, please <a class="send-feedback">let us know</a>.');
			});
		});
	});
})(jQuery);
