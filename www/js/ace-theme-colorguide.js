/* global ace */
ace.define("ace/theme/colorguide",['require','exports'],function(require, exports){
	'use strict';

	exports.isDark = false;
	exports.cssClass = "ace-colorguide";
	exports.cssText = require("/css/ace-theme-colorguide.min.css");

	var dom = require("../lib/dom");
	dom.importCssString(exports.cssText, exports.cssClass);
});
