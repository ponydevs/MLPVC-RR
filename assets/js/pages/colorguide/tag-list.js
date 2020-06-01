(function() {
  'use strict';

  const { TAG_TYPES_ASSOC } = window;
  let $tbody = $('#tags').children('tbody'),
    updateList = function($tr, action) {
      if (!this.status) return $.Dialog.fail(false, this.message);

      if (typeof $tr === 'function')
        return $tr.call(this, action);

      $.Dialog.segway(false, this.message ? $.mk('span').attr('class', 'color-green').html(this.message) : undefined);
    },
    tagUseUpdateHandler = function(successDialog) {
      return function() {
        if (!this.status) return $.Dialog.fail(false, this.message);

        if (this.counts){
          let counts = this.counts;
          $tbody.children().each(function() {
            let $ch = $(this).children(),
              id = parseInt($ch.first().text().trim(), 10);

            if (typeof counts[id] !== 'undefined')
              $ch.last().children('span').text(counts[id]);
          });
        }

        if (successDialog) $.Dialog.success(false, this.message, true);
        else $.Dialog.close();
      };
    };
  window.cgTagEditing = function(tagName, tagID, action, $tr) {
    switch (action){
      case 'delete':
        $.Dialog.confirm(`Deleting the ${tagName} tag`, 'Deleting this tag will also remove it from every appearance where it\'s been used.<br>Are you sure?', ['Delete it', 'Nope'], function(sure) {
          if (!sure) return;

          $.Dialog.wait(false, 'Deleting tag');

          $.API.delete(`/cg/tag/${tagID}`, { sanitycheck: true }, function() {
            updateList.call(this, $tr, action);
          });
        });
        break;
      case 'synon':
        $.Dialog.wait(`Make ${tagName} a synonym`, 'Retrieving tag list from server');

        $.API.get('/cg/tags', { not: tagID, action: action }, function() {
          if (!this.length){
            if (this.undo)
              return window.cgTagEditing.call(this, tagName, tagID, 'unsynon', $tr);

            return $.Dialog.fail(false, this.message);
          }

          let $TagActionForm = $.mk('form', `tag-${action}`),
            $select = $.mk('select').attr('required', true).attr('name', 'target_id'),
            optgroups = {}, ogorder = [];

          $.each(this, function(_, tag) {
            let type = tag.type,
              $option = `<option value="${tag.id}">${tag.name}</option>`;

            if (!type) return $select.append($option);

            if (typeof optgroups[type] === 'undefined'){
              optgroups[type] = $.mk('optgroup').attr('label', TAG_TYPES_ASSOC[type]);
              ogorder.push(type);
            }
            optgroups[type].append($option);
          });

          $.each(ogorder, function(_, key) {
            $select.append(optgroups[key]);
          });

          $TagActionForm.append(
            `<p>Making a tag a synonym will keep it the database, but when searching, it will automatically show results with the target tag.</p>`,
            $.mk('label').append(
              `<span>Select synonym of <strong>${tagName}</strong>:</span>`,
              $select,
            ),
          );

          $.Dialog.request(false, $TagActionForm, 'Make synonym', function($form) {
            $form.on('submit', function(e) {
              e.preventDefault();

              let sent = $form.mkData();
              $.Dialog.wait(false, 'Creating tag synonym');

              $.API.put(`/cg/tag/${tagID}/synonym`, sent, function(data) {
                updateList.call(data, $tr, action);
              });
            });
          });
        });
        break;
      case 'unsynon':{
        let message = this.message;
        $.Dialog.close(function() {
          $.Dialog.confirm(`Remove synonym from ${tagName}`, message, ['Yes, continue…', 'Cancel'], function(sure) {
            if (!sure) return;

            let targetTagName = $.mk('div').html(message).find('strong').prop('outerHTML'),
              $SynonRemoveForm = $.mk('form', 'synon-remove').html(
                `<p>If you leave the option below checked, <strong>${tagName}</strong> will be added to all appearances where ${targetTagName} is used, preserving how the tags worked while the synonym was active.</p>
								<p>If you made these tags synonyms by accident and don't want <strong>${tagName}</strong> to be added to each appearance where ${targetTagName} is used, you should uncheck the box below.</p>
								<label><input type="checkbox" name="keep_tagged" checked><span>Preserve current tag connections</span></label>`,
              );

            $.Dialog.request(false, $SynonRemoveForm, 'Remove synonym', function($form) {
              $form.on('submit', function(e) {
                e.preventDefault();

                let data = $form.mkData();
                $.Dialog.wait(false, 'Removing synonym');

                $.API.delete(`/cg/tag/${tagID}/synonym`, data, function() {
                  updateList.call(this, $tr, action);
                });
              });
            });
          });
        });
      }
        break;
      case 'refresh':
        $.Dialog.wait(`Refresh use count of ${tagName}`, 'Updating use count');

        $.API.post('/cg/tags/recount-uses', { tagids: tagID }, tagUseUpdateHandler());
        break;
    }
  };
  $tbody.on('click', 'button', function(e) {
    e.preventDefault();

    let $btn = $(this),
      $tr = $btn.parents('tr'),
      tagName = $tr.children().eq(1).text().trim(),
      tagID = parseInt($tr.children().first().text().trim(), 10),
      action = this.className.split(' ').pop();

    window.cgTagEditing(tagName, tagID, action, $tr);
  });
  $('.refresh-all').on('click', function() {
    let tagIDs = [],
      title = 'Recalculate tag usage data';
    $tbody.find('button.refresh').each(function() {
      tagIDs.push($(this).closest('tr').children().first().text().trim());
    });

    $.Dialog.wait(title, 'Updating use count' + (tagIDs.length !== 1 ? 's' : ''));

    $.API.post('/cg/tags/recount-uses', { tagids: tagIDs.join(',') }, tagUseUpdateHandler(true));
  });
})();
