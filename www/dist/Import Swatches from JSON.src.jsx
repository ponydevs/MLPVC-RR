// Import swatches from JSON
/* global alert,app,activeDocument,RGBColor,File,DocumentColorSpace,Folder,Window */
(function(nil){
"use strict";

var swimg, JSON = {};
// <JSON PARSER>
// jshint ignore:start
(function(){"use strict";function f(t){return 10>t?"0"+t:t}function this_value(){return this.valueOf()}function quote(t){return rx_escapable.lastIndex=0,rx_escapable.test(t)?'"'+t.replace(rx_escapable,function(t){var e=meta[t];return"string"==typeof e?e:"\\u"+("0000"+t.charCodeAt(0).toString(16)).slice(-4)})+'"':'"'+t+'"'}function str(t,e){var r,n,o,u,f,a=gap,i=e[t];switch(i&&"object"==typeof i&&"function"==typeof i.toJSON&&(i=i.toJSON(t)),"function"==typeof rep&&(i=rep.call(e,t,i)),typeof i){case"string":return quote(i);case"number":return isFinite(i)?String(i):"null";case"boolean":case"null":return String(i);case"object":if(!i)return"null";if(gap+=indent,f=[],"[object Array]"===Object.prototype.toString.apply(i)){for(u=i.length,r=0;u>r;r+=1)f[r]=str(r,i)||"null";return o=0===f.length?"[]":gap?"[\n"+gap+f.join(",\n"+gap)+"\n"+a+"]":"["+f.join(",")+"]",gap=a,o}if(rep&&"object"==typeof rep)for(u=rep.length,r=0;u>r;r+=1)"string"==typeof rep[r]&&(n=rep[r],o=str(n,i),o&&f.push(quote(n)+(gap?": ":":")+o));else for(n in i)Object.prototype.hasOwnProperty.call(i,n)&&(o=str(n,i),o&&f.push(quote(n)+(gap?": ":":")+o));return o=0===f.length?"{}":gap?"{\n"+gap+f.join(",\n"+gap)+"\n"+a+"}":"{"+f.join(",")+"}",gap=a,o}}var rx_one=/^[\],:{}\s]*$/,rx_two=/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,rx_three=/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,rx_four=/(?:^|:|,)(?:\s*\[)+/g,rx_escapable=/[\\"\u0000-\u001f\u007f-\u009f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,rx_dangerous=/[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;"function"!=typeof Date.prototype.toJSON&&(Date.prototype.toJSON=function(){return isFinite(this.valueOf())?this.getUTCFullYear()+"-"+f(this.getUTCMonth()+1)+"-"+f(this.getUTCDate())+"T"+f(this.getUTCHours())+":"+f(this.getUTCMinutes())+":"+f(this.getUTCSeconds())+"Z":null},Boolean.prototype.toJSON=this_value,Number.prototype.toJSON=this_value,String.prototype.toJSON=this_value);var gap,indent,meta,rep;"function"!=typeof JSON.stringify&&(meta={"\b":"\\b","	":"\\t","\n":"\\n","\f":"\\f","\r":"\\r",'"':'\\"',"\\":"\\\\"},JSON.stringify=function(t,e,r){var n;if(gap="",indent="","number"==typeof r)for(n=0;r>n;n+=1)indent+=" ";else"string"==typeof r&&(indent=r);if(rep=e,e&&"function"!=typeof e&&("object"!=typeof e||"number"!=typeof e.length))throw new Error("JSON.stringify");return str("",{"":t})}),"function"!=typeof JSON.parse&&(JSON.parse=function(text,reviver){function walk(t,e){var r,n,o=t[e];if(o&&"object"==typeof o)for(r in o)Object.prototype.hasOwnProperty.call(o,r)&&(n=walk(o,r),void 0!==n?o[r]=n:delete o[r]);return reviver.call(t,e,o)}var j;if(text=String(text),rx_dangerous.lastIndex=0,rx_dangerous.test(text)&&(text=text.replace(rx_dangerous,function(t){return"\\u"+("0000"+t.charCodeAt(0).toString(16)).slice(-4)})),rx_one.test(text.replace(rx_two,"@").replace(rx_three,"]").replace(rx_four,"")))return j=eval("("+text+")"),"function"==typeof reviver?walk({"":j},""):j;throw new SyntaxError("JSON.parse")})})();
swimg = "\u0089PNG\r\n\x1A\n\x00\x00\x00\rIHDR\x00\x00\x00\u0088\x00\x00\x006\b\x06\x00\x00\x00\x1Cz\u00D1W\x00\x00\x00\x01sRGB\x00\u00AE\u00CE\x1C\u00E9\x00\x00\x00\x04gAMA\x00\x00\u00B1\u008F\x0B\u00FCa\x05\x00\x00\x00\tpHYs\x00\x00\x0E\u00C3\x00\x00\x0E\u00C3\x01\u00C7o\u00A8d\x00\x00\x07jIDATx^\u00ED\u009AYk\x14K\x14\u00C7\u00E73\u0088\u0082\x1A\u00D4\u00BC\u00B8\x04w\u00D4\u00A8\u00D1\x04\u00F7}\x1BqCT4\u008A+j\x10D\x10\x05}sA\u009C\u00AB\u00E0\u00AE(.\u00B8D\u008D\u00FB\u00BE\u00E2\x16\x15Q\u0082\u00F8\u00E2\u0087\u0090<\u00DC\u00FBx\u00EE\u00FCO\u00A6z\u00AA\u00AB\u00AB;\u0095\u00CEt\u009C\u0081\u00F3\u00F0\u00A3\u00AB\u00AA\u00EB\u00D4\u00A9\u00D4\u00F9\u00D7\u00A9\u00EA\u00D1\u00D4\u00B0a\u00C3H\u00D1\u00BD{wA\u00F0\x11\x10\u00C8\u00BCy\u00F3h\u00FE\u00FC\u00F9\u0089\u0080\u00B1\u00E1C\u00F7)\x147\x01\u0081\u00A4\u00D3\u00E9D\x11\u0081\u0094\x16\u00D6\f\u0092$\"\u0090\u00D2\u00C2Y \u00E5\u00E5\u00E5\x1E\u00B6\u00F7\u00AE\u00B4G =z\u00F4\u00A0n\u00DD\u00BA\u00F1\u00D3\u00F6\u00DE\x05\u00D8\u009B\u00D8\u00FA%\u0089m\x0E6l\u00B6q\u00E8\u00DF\u00BF\u00BF\x136\u00DB\u0080@\u00E6\u00CE\u009D\u00EB\u00A1\x04\u0081\u00F2\u0093'O\u00E8\u00C7\u008F\x1F\u00FC\u009C5k\x16\u00A3\u00F7u\u00C5U C\u0087\x0E\u00A5\u0081\x03\x07REE\x05?\u00D1\u00B6k\u00D7.\u00FA\u00F6\u00ED\x1B?\u00CD\u00FE\u00AE\u00BCy\u00F3&@\u0092\u00C1\u00B1\u00F1\u00FC\u00F9s'l\u00B6qX\u00B2d\u0089\x136\u00DB\u0080@\u00E6\u00CC\u0099\u00E3\u00F1\u00F8\u00F1cF\u0095\u00D5s\u00C8\u0090!4~\u00FCx__W\\\x04\x02Q Heee\u009C-\u00BAv\u00EDJ]\u00BAt\u00A1\x07\x0F\x1EPSS\x13\u00FD\u00FC\u00F9\u00D3j\u00E7\u00C2\u00CB\u0097/\x03u\x1Bx\x07a\x0E\x1A4\u00C8\u00D7\u00BF\x10 \u00F8\u00FB\u00F7\u00EFgPV\u0097x\u0094\u00F7\u00EE\u00DD\u00CB\u0094\u00B4@\u00DE\u00BD{G\x07\x0F\x1E\u00A4\u00993g\u00FA\u00FA\u00BB\u00E0\"\u0090\u009E={\u00D2\u00AAU\u00AB\u00E8\u00EE\u00DD\u00BB\u00D4\u00D0\u00D0@uuu\u00B4x\u00F1b\u00FA\u00FE\u00FD;\u00FD\u00FE\u00FD\u009BN\u009D:e\u00B5s\u00E1\u00E9\u00D3\u00A7\u00D6v\u009D#G\u008E\u00B08\u00F1\u00F7\u008D\x1B7\u008EE\x1A\u0096\u0082\u00E3\u00F07\x04bk\u00D7q\x16\u00C8\u00EC\u00D9\u00B3=\u00EE\u00DF\u00BF\u00CF\u00A8r\u00AF^\u00BD\u00BC\u00B6g\u00CF\u009E\u00D1\u00D4\u00A9S}\u00FD]p\x11\u00C8\u009A5k\u00E8\u00F4\u00E9\u00D3\u009C\u00A5\u00E0s\u00E9\u00D2\u00A5|\u00BCm\u00DF\u00BE\u009D\u00FA\u00F4\u00E9c\u00B5q\u00E5\u00D1\u00A3G\u00D6v\b\x00\u00C7)2\u00D7\u00C8\u0091#\u00F9\u00E8\u00C1\u00DF\u0088@\u00E1X\u00C5\x02\x16J$\x18\u00CF\x05\u009Bm\x1CT\u00F0\u0097/_\u00EE=u\u00F4>&\x01\u0081\u00A8\u00FB\x05\u00C0\u00EE\x05z\x19\x1C:t\u0088\x17p\u00E3\u00C6\u008D\u00BE\u00FE.\u00B8\b\u00A4\u00BE\u00BE\u009EF\u008F\x1EM\u009B6m\u00A2\u0087\x0F\x1F\u00D2\u008B\x17/\u00F8\x0F@&\x19<x\u00B0\u00D5\u00C6\x15\u0088\u00DBl\u00EB\u00D7\u00AF\x1F-Z\u00B4\u0088.\\\u00B8@\x1F>|\u00A0\u00B7o\u00DF\u00D2\u00EA\u00D5\u00ABy\x0E#F\u008C\u00A0\u00AD[\u00B7r[\u00D8\"\u00B6\u0095{\u00F7\u00EEyY\x03edc\u0080r\u00A7N\u009D\x18\u0094m\u00B6q@\u00F6\u00B5\u00B5\u00EB\u0084\u00F5\u0089\x14\u00C8\u008D\x1B7\x18\u00BD\u00AC\u00EA\u00D8](\u00D7\u00D4\u00D4\u00F8lZ\u00C3E \x10\x1F\u00EE!w\u00EE\u00DC\u00E1\u00FB\x00\u0082\n\u00BB\u00F6\x1C-\n\b\u00DClCfB@0\u00BF\u00B1c\u00C7\u00D2\u00B4i\u00D38\u00FD\u00DF\u00BE}\u009B\u00AE]\u00BB\u00C6ul\x06\u009Bm\x1Cn\u00DD\u00BA\u00E5\t\x04e%\x10\u0094\u0095@P\u00B6\u00D9\u00C6a\u00E1\u00C2\u0085\u00FC\u008C\u00CA \u00AA\u008FI@ 8w\x15W\u00AE\\a\u00F4\u00B2\u00AA\x1F>|\u0098/\u008D+W\u00AE\u00F4\u00D9\u00B4\u0086\u008B@ \u008A\u00EA\u00EAjZ\u00BF~=\x07\t\u00C7\x02v\u00F8\u00DA\u00B5k\u00F9\u0088\u00C1\x17\u008E\u00CD\u00CE\u0085\u009B7o\x06\u00DA \u00C0\u00E9\u00D3\u00A7\u00D3\u008A\x15+\u00E8\u00DC\u00B9s\u00B4s\u00E7N\u00CE\x1Eg\u00CF\u009E\u00A5W\u00AF^\u00D1\u00C5\u008B\x17\u00B9\u00FE\u00FA\u00F5\u00EB\u0080m\x1C :\x17l\u00B6q\b\x0B\u00BE\u008E\u00B3@f\u00CC\u0098\u00E1\u0081\u0094\x0B\u00F4\u00B2\u00AAc@\b\x04\x17\u00BA\t\x13&p[\u00EF\u00DE\u00BD\x03\u00A8\u00B1\x14.\x02\u00D9\u00B6m\x1B\x0B\x03\u00BF\u009B\u00E0^\x00_\u00EF\u00DF\u00BF\u00A7)S\u00A6P\u00E7\u00CE\u009D\u00DB\u00F5;\u0088m\u00E1q\u0084\u00E1\u00DEq\u00E9\u00D2%\u00BEH\u00EF\u00DB\u00B7\u008F\u00F6\u00EC\u00D9C'O\u009E\u00E4L\t\u00D1\u00E0\u00FD\u00C7\u008F\x1F\x03\u00B6q\u0080\x1F\u0095)\u0094O\u0080\u00B2\u00BA\u00A4\u00A2\u008C\u00BE\u00FA\u00A7\u00B7\u00C2\x1C\u00AF5\x16,X\u00C0\u00CF\u00A8\f\u00A2\u00FA\u0098\x04\x04\u0082\u009D\u00A48s\u00E6\f\u00A3\u0097U\x1D\x1C?~\u009C\u00AE_\u00BF\u00CE\u0097H\u00D4q_0Q}\x15.\x02\x01\x10\t\x02\u0087]\u008B\u00BB\u00C7\u00E6\u00CD\u009B\u00F9H\u0083(\u00B7l\u00D9b\u00B5q\u00E1\u00F2\u00E5\u00CB\u0081\u00B6/_\u00BE\u00F0\u00D7\n\u00FC C\"C!\u00FD\u00E3\u00A2\f\u00F0\u00AE\u00B6\u00B6\u0096\u008F>\u00D36\x0E\x10\u009C\x12\b\u00CAJ (+\u0081\u00A0\u008C\u00BE\u00C8x&\u00E6x\u00AD\u0081\u00BF\u00C5\u00D6\u00AE\x13\u00D6' \x10\u009C\u00B7\n\u00EC\x1E\u00A0\u0097U\x1D,[\u00B6\u008C/o;v\u00EC\u00F0\u00DApoP\u00A86\x1DW\u0081\u00E8\u00E0b\u00DA\u00B7o_>z\u00E0_\u00FDN\x11\x07\u00CCW\x07m'N\u009C`\u00E1\u00E3X\u00C3\x1D\u00E4\u00E8\u00D1\u00A3\u009C\u00BD*++i\u00D4\u00A8Q|\u00B4\u00E1\u00D3~\u00C3\u0086\r\u0081\u00F1\u00E2\x00\u00E1\u00E3\"\x0EP\x0EC\u00F5\u00BFz\u00F5\u00AA\u0087>\u008E+f\u00C6\b\u00C3f\x1B\x10\b>]\u00DB\x02\u00FE\x01\u00CEl;\x7F\u00FE|\u00A0M\x11G \n|=}\u00FE\u00FC\u0099\u009F\u00B6\u00F7q\u0081\b2\u0099\f\x7FZ\u00E2r\b\x1A\x1B\x1B=\u00B0kq\u00D7\u00B2\u00D9v\x14\u00C7\u008E\x1D\u00B3\u00B6'M@ 8\u00E7\u0093\u00A4=\x02\x11:\u009E\u0080@&O\u009E\u009C(\"\u0090\u00D2\" \u0090I\u0093&%\u008A\b\u00A4\u00B4\b\b\x04\u009F\u00AC\x13'NL\x04\u008C-\x02)-R\u00F8]A\x10\u00C2H\u008D\x193\u0086\x14_\u00BF~\x15\x04\x1F\u00A9\u00E1\u00C3\u0087\u0093\x02\r\u00CD\u00CD\u00CD\u0082\u00E0!\x02\x11\"\t\x15H\u00E6\u00D3\u00BF\x1D\u00CA?\u008D\u00FF\tE\u0088\bD\u0088D\x04\"D\"\x02\x11\"\u00F1\u00FD\x0E\"\x02\x11LR\u00FA\x7F\x07\x14\u0081\b&\u00C5-\u0090L-\u00A5R\u00A9\x1C\u00B5\u00B4\u00CE\u00D6\u00A7Pt\u00A4/\x1F\rT\u0095\u00AA\u00A4t\u00BD\u00ED\u00DD\u00DF\u00A7x\x05R\x7F\u0080\u00CA\u00F5@e\u00EB\u00E9\u008C\u00F6\u00BE\u0090$\u00E2\u00CB5\u00F0\"\x10'\x02\u0093\u00C3\u008E\x1Ep\u0080v\u009B\u00EDI\u0090\u0088/\x11HA\tN\x0E\x0B\u0097\u00A2\u00F2\u00BA\u00A6\u00C0\u00BBuiu\x14dI7d\u00DB\u009A(= EU\u00FA\u00AE\u00D7\u0083\u00CE\x19\"\u00EA\u00F8\u00B0\u00FB\u0082\u009F\u00FC\u0098F 1&\u00FB\u00B6\u00CD\u00A7e<\x7F\x1B\u00ECZ\u00E6\u00A9\u00DA[\u00C6\u00CE\u008D\u009B\u00C9\u00CF\u00D17\x0F\u00EB\u00DCm\u00E3$C\u0091_R\u00F3\x0Ba_\x04-h\x10\u0084\x17\b=\u00B8F`\u00B3\u00FDl\u00A2\u00B3\u00FA\u00D2\u00C7\u0084\u00DD\u0080J\u00CFvw]\u00BE\u009CG\u00F7e\u00F8\u00CD\u008Do\u00B7\u00C9\u00FAUb\u0086OO\b!s\u00D7\u00E7\u00950E.\u0090\x1C\u00B9]\u00E4\x0B\x1C\x16\u0095\u00D1\x03\u00A2-\u00AC5{\u00E4\u0088Z\\\u00DD\x17\u00CA\u00B9q\u00D6\u00A5\u00B3~\u00B0\u00CB\u00B9\u008E`\u00FB\x03g\u009F\u008F\u00D6\u0087\u00C7\r\u00CB^Z?\u00BD\x1E6\u00F7\\\u00BB]\u00E8\u0085\u00A54\x04\u0092\x05;6\u00BF8z\u00AA\u00CD/\u00AE\u00DA\u00D5\u00BE\u00DD\x1D\x1A\u0098p<_\u00DE\u00F8Jp\u00A8g\u00C7\u00C2\u0098>\x01\u00DA\u00E6S(\u0081\u0084\u00CF\u009D\u00E7\u0099\x15\u008A=\u00BB\x16\u0086\"\u00BE\u00A4f\u00BF$\u00BCE\u00C3\u00C2\u00E7v\fv\u00AB/8F\x10\u00B2\x01\u00ACB\x10\u00D58\u00BC\u00E0\u00AD\u00EC\u00B60_\u00D9:\u008B\u00CD8Z\u00AA\u00D2\u00DA1\x15:\x1F3\u00F0\u00FEq\u00F3D\b\u00C4a\u00EE\u00F9\u00CD`\u00DA\u0085\u00CD\u00A3m\x14\u00FD%\u00D5\u0097Z\u00B9\u00BDe\u00A1\u00B9\u008D\u00C5\u00A0\u00FF\u00F1\u00B9w\u00E6\x11\u00C2\u0081\u00CB\u00D9\u00F8\u00C6R\u0084\u00F9R\u00B6\u009A\x0F\u00B3\x1E1\x1F\u00EF\u00F2\u00EA\u008D\u00E7\u00F7c\u00BD#\u0099u\u00DB\u00DC}G\u009A\u00DA\f\u00BA]X\u00B9\u00ED\u0094\u00CC\x11#\u00FC\x1DD B$\"\x10!\x12\x11\u0088\x10\u0089\bD\u0088D\x04\"D\"\x02\x11\"\t\x15\u00C8\u009F?\x7F:\x14\u00E5W(.D B$\"\x10!\u0092\u00A2\x11\u00C8\u00AF_\u00BF\u0084\"D2\u0088\x10\u0089\bD\u0088D\x04\"D\"\x02\x11\"h\u00A6\u00FF\x01 \u008C\u00E2\u00E9n\u00E9\u00C4`\x00\x00\x00\x00IEND\u00AEB`\u0082";
// jshint ignore:end
// </JSON_PARSER>

// <SETUP>
var madeNewDoc = app.documents.length === 0,
	_Document = !madeNewDoc ? app.activeDocument : app.documents.add(DocumentColorSpace.RGB),
	title = 'Import swatches from JSON (by MLP-VectorClub, version 1.1)';
function _RGBColor(r,g,b){
	var newRGBColor = new RGBColor();
	newRGBColor.red = r;
	newRGBColor.green = g;
	newRGBColor.blue = b;
	return newRGBColor;
}
function _ColorGroup(name){
	var swatchGroup = _Document.swatchGroups.add();
	swatchGroup.name = name;
	return swatchGroup;
}
function _Swatch(name,r,g,b){
	var newSwatch = activeDocument.swatches.add();
	newSwatch.name = name;
	newSwatch.color = _RGBColor(r,g,b);
	return newSwatch;
}
// </SETUP>

var alert = function(text, beforegroup){
		var win = new Window('dialog',title,nil,{closeButton:false}),
			ml = text.indexOf('\n')!==-1;
		win.add('statictext',ml?[0,0,340,70]:nil,text,{multiline:ml});
		if (typeof beforegroup === 'function')
			beforegroup(win);
		var btngrp = win.add("group");
		btngrp.add('button',nil,'OK');
		if (beforegroup !== true)
			win.show();
		return {close:function(){win.close()}};
	},
	fin = function(text){
		alert(text, function(win){
			var grp = win.add("group");
			grp.add("image",nil,swimg);
		});
	},
	wait = function(text){
		var win = new Window('palette',title,nil,{closeButton:false}),
			ml = text.indexOf('\n')!==-1;
		win.add('statictext',ml?[0,0,340,70]:nil,text,{multiline:ml});
		win.show();
		return {close:function(){win.close()}};
	};

var fileDialog = new Window('dialog',title);
fileDialog.add('statictext',[0,0,520,20],'Please click the button below and select the JSON file you got from our Color Guide.');
var importSingleCG = fileDialog.add("checkbox",nil,"\u00A0Import as a single swatch group");
importSingleCG.value = true;
fileDialog.add('statictext',[0,0,520,35],'By default, all colors will be imported to a single swatch group.\nIf you\'d like to have each color group in a separate swatch group, untick the checkbox above.',{multiline:true});
var btngrp = fileDialog.add("group"),
	browsebtn = btngrp.add('button',nil,'Browse...'),
	closebtn =  btngrp.add('button',nil,'Cancel');
browsebtn.onClick = function(){
	browsebtn.onClick = nil;
	var jsonFile = File.openDialog('Find the JSON file downloaded from our Color Guide',
			(
				$.os.match(/Macintosh/i)
				? function(f){ return f.name.match(/\.json$/i) }
				: 'JSON files:*.json'
			)
		);

	if (jsonFile){
		var importingAlert = wait('Importing...');
		jsonFile.open('r');
		var imported = jsonFile.read();
		jsonFile.close();
		try {
			imported = JSON.parse(imported);
			if (madeNewDoc){
				var i, safety = 0;
				while (_Document.swatchGroups.length !== 0 && safety++ < 20){
					for (i = 0; i < _Document.swatchGroups.length; i++)
						_Document.swatchGroups[i].remove();
				}
				safety = 0;
				while (_Document.swatches.length !== 0 && safety++ < 20){
					for (i = 0; i < _Document.swatches.length; i++)
						_Document.swatches[i].remove();
				}

				madeNewDoc = false;
			}
			for (var appearancename in imported){
					if (!imported.hasOwnProperty(appearancename))
						continue;
				var appearance = imported[appearancename],
					CG;
				if (typeof appearance !== 'object')
					continue;
				if (importSingleCG.value)
					CG = new _ColorGroup(appearancename);
				for (var cgname in appearance){
					if (!appearance.hasOwnProperty(cgname))
						continue;

					var cg = appearance[cgname];
					if (!importSingleCG.value)
						CG = new _ColorGroup(cgname);

					for (var colorname in cg){
						if (!cg.hasOwnProperty(colorname))
							continue;

						var color = cg[colorname];

						CG.addSwatch(
							new _Swatch(
								cgname+' | '+colorname,
								parseInt(color.substring(1, 3), 16),
								parseInt(color.substring(3, 5), 16),
								parseInt(color.substring(5, 7), 16)
							)
						);
					}
				}
			}

			importingAlert.close();
			fileDialog.close();
			fin('All color groups have been imported successfully.\nWe suggest that you remove any built-in swatches you don\'t need, then save the rest for futue use.\n(Icon with books on Swatches panel > Save Swatches...)');
		}
		catch (e){
			fileDialog.close();
			alert('Error while parsing JSON: '+e+' (in '+e.fileName+':'+e.line+')');
		}
	}
};
closebtn.onClick = function(){
	fileDialog.close();
};
fileDialog.show();

})();
