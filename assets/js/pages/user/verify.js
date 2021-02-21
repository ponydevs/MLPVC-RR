(function() {
  const { verifyHash, verifyAction } = window;
  const $content = $('#content');
  const $verifyStatusWrap = $('#verify-status-wrap');
  const $verifyStatus = $('#verify-status');

  const setStatus = (success, message) => {
    $verifyStatusWrap.html(
      success
        ? '<span class="typcn typcn-tick"/>'
        : '<span class="typcn typcn-warning"/>',
    ).attr('data-status', success ? 'true' : 'false');
    $verifyStatus.remove();
    $content.append(
      $.mk('div').addClass('notice align-center ' + (success ? 'success' : 'fail')).text(message)
    );
  };

  $.API.post('/user/verify', { hash: verifyHash, action: verifyAction }, function() {
    setStatus(this.status, this.message);
  });
})();
