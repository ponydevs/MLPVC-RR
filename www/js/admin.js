/* global DocReady,HandleNav,Sortable,DOMStringList */
DocReady.push(function Admin(){
	'use strict';

	// Manage useful links
	var $uflol = $('.useful-links').find('ol'),
		$sbUflContainer = $('#sidebar').find('.welcome .links'),
		$editForm, PRINTABLE_ASCII_PATTERN = $.attributifyRegex(window.PRINTABLE_ASCII_PATTERN),
		ROLES_ASSOC = window.ROLES_ASSOC;

	$uflol.on('click','.edit-link',function(){
		var linkid = $(this).closest('[id^=ufl-]').attr('id').substring(4);

		$.Dialog.wait('Editing link #'+linkid, 'Retrieving link information from server');

		$.post('/admin/usefullinks/get/'+linkid,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			var data = this;
			$.Dialog.request(false, getEditForm(linkid), 'link-editor', 'Save changes',function($form){
				$form.find('input[name=label]').val(data.label);
				$form.find('input[name=url]').val(data.url);
				$form.find('input[name=title]').val(data.title);
				$form.find('select[name=minrole]').val(data.minrole);
			});
		}));
	});
	$uflol.on('click','.delete-link',function(){
		var $li = $(this).closest('[id^=ufl-]'),
			linkid = $li.attr('id').substring(4);

		$.Dialog.confirm('Delete link #'+linkid, 'Are you sure you want to delete this link?', function(sure){
			if (!sure) return;

			$.Dialog.wait(false, 'Removing link');

			$.post('/admin/usefullinks/del/'+linkid,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$li.remove();
				$('#s-ufl-'+linkid).remove();
				if ($sbUflContainer.is(':empty'))
					$sbUflContainer.hide();
				$.Dialog.close();
			}));
		});
	});
	$('#add-link').on('click',function(){
		$.Dialog.request('Add a link', getEditForm(), 'link-editor', 'Add');
	});
	function getEditForm(linkid){
		if (typeof $editForm === 'undefined'){
			var $rsOptgroup = $.mk('optgroup').attr('label', 'Available roles'),
				$roleSelect = $.mk('select').attr({
					name: 'minrole',
					required: true,
				}).append("<option value='' selected default>Select one</option>");
			delete ROLES_ASSOC.guest;
			delete ROLES_ASSOC.ban;
			$.each(ROLES_ASSOC, function(name, label){
				$rsOptgroup.append($.mk('option').attr('value', name).text(label));
			});
			$roleSelect.append($rsOptgroup);

			$editForm = $.mk('form').attr('id','link-editor').append(
				$.mk('label').append(
					"<span>Label (3-35 chars.)</span>",
					$.mk('input').attr({
						type: 'text',
						name: 'label',
						maxlength: 30,
						pattern: PRINTABLE_ASCII_PATTERN.replace('+','{3,40}'),
						required: true,
					})
				),
				$.mk('label').append(
					"<span>URL (3-255 chars.)</span>",
					$.mk('input').attr({
						type: 'text',
						name: 'url',
						maxlength: 255,
						required: true,
						pattern: PRINTABLE_ASCII_PATTERN.replace('+','{3,255}'),
					})
				),
				$.mk('label').append(
					"<span>Title (optional, 3-70 chars.)</span>",
					$.mk('input').attr({
						type: 'text',
						name: 'title',
						maxlength: 255,
						pattern: PRINTABLE_ASCII_PATTERN.replace('+','{3,255}'),
					})
				),
				$.mk('label').append(
					"<span>Role required to view</span>",
					$roleSelect
				)
			).on('submit', function(e){
				e.preventDefault();

				var data = $(this).serialize();
				$.Dialog.wait(false);

				$.post('/admin/usefullinks/'+(linkid?'set/'+linkid:'make'),data, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.wait(false, 'Reloading page', true);
					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}));
			});
		}

		return $editForm.clone(true,true);
	}

	var $ReorderBtn = $('#reorder-links');
	$ReorderBtn.on('click',function(){
		if (!$ReorderBtn.hasClass('typcn-tick')){
			$ReorderBtn.removeClass('typcn-arrow-unsorted darkblue').addClass('typcn-tick green').html('Save');
			$uflol.addClass('sorting').children().find('.buttons').append('<span class="btn darkblue typcn typcn-arrow-move"></span>');
			new Sortable($uflol.get(0), {
			    ghostClass: "moving",
			    scroll: true,
			    animation: 150,
			    handle: '.typcn-arrow-move',
			});
		}
		else {
			$.Dialog.wait('Re-ordering links');

			var list = [];
			$uflol.children().each(function(){
				list.push($(this).find('.typcn-arrow-move').remove().end().attr('id').split('-').pop());
			});

			$.post('/admin/usefullinks/reorder', {list:list.join(',')}, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Dialog.wait(false, 'Reloading page', true);
				$.Navigation.reload(function(){
					$.Dialog.close();
				});
			}));
		}
	});

	// Mass-aprove posts
	(function(){

		function waitForPastedData(elem, savedContent){

			// If data has been processes by browser, process it
			if (elem.childNodes && elem.childNodes.length > 0){

				// Retrieve pasted content via innerHTML
				// (Alternatively loop through elem.childNodes or elem.getElementsByTagName here)
				var pastedData = elem.innerHTML;

				// Restore saved content
				elem.innerHTML = "";
				elem.appendChild(savedContent);

				// Call callback
				processPaste(pastedData);
			}

			// Else wait 20ms and try again
			else setTimeout(function(){ waitForPastedData(elem, savedContent) }, 20);
		}

		var $textarea = $('.mass-approve').children('.textarea').on('paste', function(e){
			var types, pastedData, savedContent, editableDiv = this;

			// Browsers that support the 'text/html' type in the Clipboard API (Chrome, Firefox 22+)
			if (e.originalEvent.clipboardData && e.originalEvent.clipboardData.types && e.originalEvent.clipboardData.getData){

				// Check for 'text/html' in types list. See abligh's answer below for deatils on
				// why the DOMStringList bit is needed. We cannot fall back to 'text/plain' as
				// Safari/Edge don't advertise HTML data even if it is available
				types = e.originalEvent.clipboardData.types;
				if (((types instanceof DOMStringList) && types.contains("text/html")) || (types.indexOf && types.indexOf('text/html') !== -1)){

					// Extract data and pass it to callback
					pastedData = e.originalEvent.clipboardData.getData('text/html');
					processPaste(pastedData);

					// Stop the data from actually being pasted
					e.stopPropagation();
					e.preventDefault();
					return false;
				}
			}

			// Everything else: Move existing element contents to a DocumentFragment for safekeeping
			savedContent = document.createDocumentFragment();
			while (editableDiv.childNodes.length > 0){
				savedContent.appendChild(editableDiv.childNodes[0]);
			}

			// Then wait for browser to paste content into it and cleanup
			waitForPastedData(editableDiv, savedContent);
			return true;
		});

		var deviationRegex = /(?:[A-Za-z\-\d]+\.)?deviantart\.com\/art\/(?:[A-Za-z\-\d]+-)?(\d+)/g,
			deviationRegexLocal = /\/(?:[A-Za-z\-\d]+-)?(\d+)$/;
		function processPaste(pastedData){
			$textarea.addClass('reading');
			
			pastedData = pastedData.replace(/<img[^>]+>/g,'').match(deviationRegex);
			var deviationIDs = {};

			$.each(pastedData,function(_, el){
				var match = el.match(deviationRegexLocal);
				if (match && typeof deviationIDs[match[1]] === 'undefined')
					deviationIDs[match[1]] = true;
			});

			var deviationIDArray = Object.keys(deviationIDs);
			if (!deviationIDArray)
				return $.Dialog.fail('No deviations found on the pasted page.');

			$.Dialog.wait('Bulk approve posts', "Attempting to approve "+(deviationIDArray.length)+' post'+(deviationIDArray.length!==1?'s':''));

			$.post('/post/mass-approve',{ids:deviationIDArray.join(',')},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				var message = this.message,
					f = function(){
						if (message)
							$.Dialog.success(false, message, true);
						else $.Dialog.close();
					};
				if (!this.reload)
					f();
				else {
					$.Dialog.wait(false, "Reloading page");
					$.Navigation.reload(f);
				}
			}));
		}
	})();
});
