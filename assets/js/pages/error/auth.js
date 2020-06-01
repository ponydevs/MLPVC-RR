(function() {
  'use strict';

  const { rndkey } = window;
  try {
    if (typeof rndkey === 'string' && typeof window.opener[' ' + rndkey] === 'function')
      window.opener[' ' + rndkey](true, window);
  } catch (e){
  }
})();
