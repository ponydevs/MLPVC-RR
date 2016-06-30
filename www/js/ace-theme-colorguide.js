/* global ace */
ace.define("ace/theme/colorguide",['require','exports'], (require, exports) => {
	'use strict';

	exports.isDark = false;
	exports.cssClass = "ace-colorguide";
	exports.cssText = require("/css/ace-theme-colorguide.min.css");

	require("../lib/dom").importCssString(exports.cssText, exports.cssClass);
});
