/* global ace */
ace.define("ace/mode/colorguide_highlight_rules",["require","exports","ace/lib/oop","ace/mode/text_highlight_rules"], function(require, exports) {
	"use strict";

	var ColorGuideHighlightRules = function() {
		this.$rules = {
			"start": [
				{
					token: "comment.line",
					regex: /^\/\/.+$/,
				},
				{
					token: "invalid",
					regex: /^(?:[^\/]+\/{2}.*|\/(?:[^\/]?.*)?)$/,
				},
				{
					token: "invalid",
					regex: /^\s*[^#].*$/,
				},
				{
					token: "color",
					regex: /^\s*#(?:[a-f\d]{6}|[a-f\d]{3})\s+/,
				},
				{
					token: "invalid",
					regex: /^\s*#\S*[^a-f\d\s]\S*?(\s|$)/,
				},
				{
					token: "invalid",
					regex: /^\s*#(?:[a-f\d]{1,5}|[a-f\d]{4,5}|[a-f\d]{7,})?\S?/,
				},
				{
					token: "colorname",
					regex: /\s*[a-z\d][ -~]{2,29}\s*$/,
				},
				{
					token: "invalid",
					regex: /\s*.*[^ -~].*\s*$/,
				},
				{
					token: "invalid",
					regex: /\s*(?:.{1,2}|.{30,})\s*$/,
				},
				{ caseInsensitive: true },
			],
			"color": [
				{
					token: "constant.other",
					regex: /^\s*#(?:[a-f\d]{6}|[a-f\d]{3})/,
				},
			],
			"colorname": [
				{
					token: "string.unquoted",
					regex: /[^\s#][ -~]{2,29}\s*$/,
				},
			],
		};
	};
	require("../lib/oop").inherits(ColorGuideHighlightRules, require("./text_highlight_rules").TextHighlightRules);

	exports.ColorGuideHighlightRules = ColorGuideHighlightRules;
});

ace.define("ace/mode/colorguide",["require","exports","ace/mode/colorguide_highlight_rules","ace/mode/folding/coffee","ace/range","ace/mode/text","ace/lib/oop"], function(require, exports) {
	"use strict";

	function Mode(){ this.HighlightRules = require("./colorguide_highlight_rules").ColorGuideHighlightRules }
	require("../lib/oop").inherits(Mode, require("./text").Mode);

	Mode.prototype.getNextLineIndent = function(state, line) {
		return this.$getIndent(line);
	};
	Mode.prototype.$id = "ace/mode/colorguide";

	exports.Mode = Mode;
});
