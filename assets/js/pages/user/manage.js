(function() {
  'use strict';

  if (typeof window.ROLES === 'undefined') return;

  let $briefing = $content.children('.briefing'),
    $currRole = $briefing.find('.role-label'),
    currRole = $currRole.text().trim(),
    $RoleModFormTemplate = $.mk('form').attr('id', 'rolemod').html('<select name="value" required><optgroup label="Possible roles"></optgroup></select>'),
    $OptGrp = $RoleModFormTemplate.find('optgroup'),
    $changeRole = $('#change-role'),
    $changeRoleMask = $('#change-dev-role-mask'),
    $contributions = $('.contributions');

  $.each(window.ROLES, (name, label) => {
    $OptGrp.append(`<option value=${name}>${label}</option>`);
  });

  $changeRole.on('click', function() {
    const userId = $changeRole.attr('data-for');
    $.Dialog.request('Change group', $RoleModFormTemplate.clone(), 'Change', function($form) {
      let $currRoleOpt = $form.find('option').filter(function() {
        return this.innerHTML === currRole;
      }).attr('selected', true);
      $form.on('submit', function(e) {
        e.preventDefault();

        if ($form.children('select').val() === $currRoleOpt.attr('value'))
          return $.Dialog.close();

        let data = $form.mkData();
        $.Dialog.wait(false, 'Moving user to the new group');

        $.API.put(`/user/${userId}/role`, data, function() {
          if (this.already_in === true)
            return $.Dialog.close();

          if (!this.status) return $.Dialog.fail(false, this.message);

          $.Navigation.reload(true);
        });
      });
    });
  });

  $changeRoleMask.on('click', function() {
    $.Dialog.request($changeRoleMask.attr('title'), $RoleModFormTemplate.clone(), 'Change', function($form) {
      let $currRoleOpt = $form.find('option').filter(function() {
        return this.innerHTML === currRole;
      }).attr('selected', true);
      $form.on('submit', function(e) {
        e.preventDefault();

        if ($form.children('select').val() === $currRoleOpt.attr('value'))
          return $.Dialog.close();

        let data = $form.mkData();
        $.Dialog.wait(false, 'Changing role mask');

        $.API.put(`/setting/dev_role_label`, data, function() {
          if (!this.status) return $.Dialog.fail(false, this.message);

          $.Navigation.reload(true);
        });
      });
    });
  });

  $contributions.on('click', '#purge-contrib-cache', e => {
    e.preventDefault();
    const $btn = $('#purge-contrib-cache');
    const userId = $btn.attr('data-for');

    $.Dialog.wait('Purge cached contribution data');

    $.API.delete(`/user/${userId}/contrib-cache`, function() {
      if (!this.status) return $.Dialog.fail(false, this.message);

      $.Dialog.success(false, this.message, true);
      $contributions.html(this.html);
    });
  });
})();
