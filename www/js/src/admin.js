/* global DocReady,HandleNav,Sortable,DOMStringList */
DocReady.push(function(){
	'use strict';

	// Manage useful links
	let $uflol = $('.useful-links').find('ol'),
		$sbUflContainer = $('#sidebar').find('.welcome .links'),
		$LinkEditFormTemplate, PRINTABLE_ASCII_PATTERN = $.attributifyRegex(window.PRINTABLE_ASCII_PATTERN),
		ROLES_ASSOC = window.ROLES_ASSOC;

	$uflol.on('click','.edit-link',function(){
		let linkid = $(this).closest('[id^=ufl-]').attr('id').substring(4);

		$.Dialog.wait(`Editing link #${linkid}`, 'Retrieving link information from server');
		
		$.post(`/admin/usefullinks?action=get&linkid=${linkid}`,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			let data = this;
			$.Dialog.request(false, getLinkEditForm(linkid), 'Save changes', function($form){
				$form.find('input[name=label]').val(data.label);
				$form.find('input[name=url]').val(data.url);
				$form.find('input[name=title]').val(data.title);
				$form.find('select[name=minrole]').val(data.minrole);
			});
		}));
	});
	$uflol.on('click','.delete-link',function(){
		let $li = $(this).closest('[id^=ufl-]'),
			linkid = $li.attr('id').substring(4);

		$.Dialog.confirm(`Delete link #${linkid}`, 'Are you sure you want to delete this link?', function(sure){
			if (!sure) return;

			$.Dialog.wait(false, 'Removing link');

			$.post(`/admin/usefullinks?action=del&linkid=${linkid}`,$.mkAjaxHandler(function(){
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
		$.Dialog.request('Add a link', getLinkEditForm(), 'Add');
	});
	function getLinkEditForm(linkid){
		if (typeof $LinkEditFormTemplate === 'undefined'){
			let roleSelect =
				`<select name='minrole' required>
					<option value='' selected style='display:none'>Select one</option>
					<optgroup label="Available roles">`;
			$.each(ROLES_ASSOC, (name, label) => {
				if (name === 'guest' || name === 'ban')
					return;
				roleSelect += `<option value="${name}">${label}</option>`;
			});
			roleSelect += "</optgroup></select>";

			$LinkEditFormTemplate = $.mk('form','link-editor').html(
				`<label>
					<span>Label (3-35 chars.)</span>
					<input type="text" name="label" maxlength="35" pattern="${PRINTABLE_ASCII_PATTERN.replace('+','{3,35}')}" required>
				</label>
				<label>
					<span>URL (3-255 chars.)</span>
					<input type="text" name="url" maxlength="255" pattern="${PRINTABLE_ASCII_PATTERN.replace('+','{3,255}')}" required>
				</label>
				<label>
					<span>Title (optional, 3-70 chars.)</span>
					<input type="text" name="title" maxlength="70" pattern="${PRINTABLE_ASCII_PATTERN.replace('+','{3,70}')}">
				</label>
				<label>
					<span>Role required to view</span>
					${roleSelect}
				</label>`
			).on('submit', function(e){
				e.preventDefault();

				let data = $(this).serialize();
				$.Dialog.wait(false);

				$.post(`/admin/usefullinks?action=${linkid?`set&linkid=${linkid}`:'make'}`,data, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.wait(false, 'Reloading page', true);
					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}));
			});
		}

		return $LinkEditFormTemplate.clone(true,true);
	}

	let $ReorderBtn = $('#reorder-links');
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

			let list = [];
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
		// Paste Handling Code based on http://stackoverflow.com/a/6804718/1344955
		$('.mass-approve').children('.textarea').on('paste', function(e){
			let types, pastedData, savedContent, editableDiv = this;
			if (e.originalEvent.clipboardData && e.originalEvent.clipboardData.types && e.originalEvent.clipboardData.getData){
				types = e.originalEvent.clipboardData.types;
				if (((types instanceof DOMStringList) && types.contains("text/html")) || (types.indexOf && types.indexOf('text/html') !== -1)){
					pastedData = e.originalEvent.clipboardData.getData('text/html');
					processPaste(pastedData);
					e.stopPropagation();
					e.preventDefault();
					return false;
				}
			}
			savedContent = document.createDocumentFragment();
			while (editableDiv.childNodes.length > 0){
				savedContent.appendChild(editableDiv.childNodes[0]);
			}
			(function waitForPastedData(elem, savedContent){
				if (elem.childNodes && elem.childNodes.length > 0){
					let pastedData = elem.innerHTML;
					elem.innerHTML = "";
					elem.appendChild(savedContent);
					processPaste(pastedData);
				}
				else setTimeout(function(){ waitForPastedData(elem, savedContent) }, 20);
			})(editableDiv, savedContent);
			return true;
		});

		let deviationRegex = /(?:[A-Za-z\-\d]+\.)?deviantart\.com\/art\/(?:[A-Za-z\-\d]+-)?(\d+)/g,
			deviationRegexLocal = /\/(?:[A-Za-z\-\d]+-)?(\d+)$/;
		function processPaste(pastedData){
			pastedData = pastedData.replace(/<img[^>]+>/g,'').match(deviationRegex);
			let deviationIDs = {};

			$.each(pastedData, (_, el) => {
				let match = el.match(deviationRegexLocal);
				if (match && typeof deviationIDs[match[1]] === 'undefined')
					deviationIDs[match[1]] = true;
			});

			let deviationIDArray = Object.keys(deviationIDs);
			if (!deviationIDArray)
				return $.Dialog.fail('No deviations found on the pasted page.');

			$.Dialog.wait('Bulk approve posts', `Attempting to approve ${deviationIDArray.length} post${deviationIDArray.length!==1?'s':''}`);

			$.post('/admin/mass-approve',{ids:deviationIDArray.join(',')},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				let message = this.message,
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
