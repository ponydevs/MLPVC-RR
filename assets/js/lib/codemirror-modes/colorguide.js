CodeMirror.defineSimpleMode('colorguide', {
  start: [
    {
      token: 'comment.line.character',
      regex: /\/\/[#@].+$/,
      sol: true,
    },
    {
      token: 'comment.line.double-slash',
      regex: /\/\/.+$/,
      sol: true,
    },
    {
      token: 'hex.identifier',
      regex: /#[a-f\d]{6}\s/i,
      next: 'colorname',
      sol: true,
    },
    {
      token: 'hex.identifier',
      regex: /#[a-f\d]{3}\s/i,
      next: 'colorname',
      sol: true,
    },
    {
      token: 'invalid',
      regex: /#([a-f\d]{4,5}|[a-f\d]{1,2})[^a-f\d]/i,
      next: 'colorname',
      sol: true,
    },
    {
      token: 'meta',
      regex: /\s*/,
      next: 'colorname',
      sol: true,
    },
  ],
  colorname: [
    {
      token: 'colorname',
      regex: /\s*[ -~]{3,30}\s*/,
      next: 'colorid_start',
    },
    {
      token: 'invalid',
      regex: /\s*$/,
      next: 'invalid',
    },
  ],
  colorid_start: [
    {
      token: 'colorid_start',
      regex: /ID:/,
      next: 'colorid',
    },
    {
      token: 'meta',
      regex: /\s*/,
      next: 'start',
    },
  ],
  colorid: [
    {
      token: 'colorid.identifier',
      regex: /\d+$/,
      next: 'start',
    },
  ],
  invalid: [
    {
      token: 'invalid',
      regex: /[\s\S]*/,
    },
  ],
});
