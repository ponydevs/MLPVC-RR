/* global ace */
ace.define("ace/mode/colorguide", ["require", "exports", "ace/lib/oop"], (require, exports, oop) => {
	"use strict";

	const TextMode = require("ace/mode/text").Mode;
	const ColorguideHighlightRules = require("ace/mode/colorguide_highlight_rules").ColorguideHighlightRules;
	const Mode = function() {
		this.HighlightRules = ColorguideHighlightRules;
	};
	oop.inherits(Mode, TextMode);
	(function() {
		this.$id = "ace/mode/colorguide";
		this.getNextLineIndent = () => '';
	}).call(Mode.prototype);

	exports.Mode = Mode;
});

ace.define('ace/mode/colorguide_highlight_rules', ["require", "exports"], function(require, exports) {
	"use strict";

	const oop = require("ace/lib/oop");
	const TextHighlightRules = require("ace/mode/text_highlight_rules").TextHighlightRules;

	const ColorguideHighlightRules = function() {
		this.$rules = new TextHighlightRules().getRules();
		this.$rules.start = this.$rules.start.concat([
			{
				token: "comment.line.character",
				regex: /^\/\/[#@].+$/,
			},
			{
				token: "comment.line.double-slash",
				regex: /^\/\/.+$/,
			},
			{
				token: "hex",
				regex: /^#[a-f\d]{6}\s/,
				next: "colorname",
			},
			{
				token: "hex",
				regex: /^#[a-f\d]{3}\s/,
				next: "colorname",
			},
			{
				token: "invalid",
				regex: /^#([a-f\d]{4,5}|[a-f\d]{1,2})[^a-f\d]/,
				next: "colorname",
			},
			{
				token: "colorlink",
				regex: /^@\d+/,
				next: "colorname",
			},
			{
				token: "meta",
				regex: /^\s*/,
				next: "colorname",
			},
		]);
		this.$rules.colorname = [
			{
				token: "colorname",
				regex: /\s*[ -~]{3,30}\s*/,
				next: "colorid_start",
			},
			{
				token: "invalid",
				regex: /\s*$/,
				next: "invalid",
			},
		];
		this.$rules.colorid_start = [
			{
				token: "colorid_start",
				regex: /ID:/,
				next: "colorid",
			},
			{
				token: "meta",
				regex: /\s*/,
				next: "start",
			}
		];
		this.$rules.colorid = [
			{
				token: "colorid",
				regex: /\d+$/,
				next: "start",
			}
		];
		this.$rules.invalid = [
			{
				token: "invalid",
				regex: /[\s\S]*/,
			},
		];
	};

	oop.inherits(ColorguideHighlightRules, TextHighlightRules);

	exports.ColorguideHighlightRules = ColorguideHighlightRules;
});
