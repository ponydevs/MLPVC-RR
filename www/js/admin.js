/* global DocReady,HandleNav,Sortable */
DocReady.push(function Admin(){
	'use strict';

	// Manage useful links
	var $uflol = $('.useful-links').find('ol'),
		$sbUflContainer = $('#sidebar').find('.welcome .links'),
		$editForm, PRINTABLE_ASCII_REGEX = $.attributifyRegex(window.PRINTABLE_ASCII_REGEX),
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
						pattern: PRINTABLE_ASCII_REGEX.replace('+','{3,40}'),
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
						pattern: PRINTABLE_ASCII_REGEX.replace('+','{3,255}'),
					})
				),
				$.mk('label').append(
					"<span>Title (optional, 3-70 chars.)</span>",
					$.mk('input').attr({
						type: 'text',
						name: 'title',
						maxlength: 255,
						pattern: PRINTABLE_ASCII_REGEX.replace('+','{3,255}'),
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
					HandleNav.reload(function(){
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
				HandleNav.reload(function(){
					$.Dialog.close();
				});
			}));
		}
	});
});
