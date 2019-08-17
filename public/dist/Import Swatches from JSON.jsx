// Import swatches from JSON
/* global alert,app,activeDocument,RGBColor,File,DocumentColorSpace,Folder,Window */
(function(nil) {
  'use strict';

  var JSON = {};
// <JSON PARSER>
  (function(){"use strict";function f(t){return 10>t?"0"+t:t}function this_value(){return this.valueOf()}function quote(t){return rx_escapable.lastIndex=0,rx_escapable.test(t)?'"'+t.replace(rx_escapable,function(t){var e=meta[t];return"string"==typeof e?e:"\\u"+("0000"+t.charCodeAt(0).toString(16)).slice(-4)})+'"':'"'+t+'"'}function str(t,e){var r,n,o,u,f,a=gap,i=e[t];switch(i&&"object"==typeof i&&"function"==typeof i.toJSON&&(i=i.toJSON(t)),"function"==typeof rep&&(i=rep.call(e,t,i)),typeof i){case"string":return quote(i);case"number":return isFinite(i)?String(i):"null";case"boolean":case"null":return String(i);case"object":if(!i)return"null";if(gap+=indent,f=[],"[object Array]"===Object.prototype.toString.apply(i)){for(u=i.length,r=0;u>r;r+=1)f[r]=str(r,i)||"null";return o=0===f.length?"[]":gap?"[\n"+gap+f.join(",\n"+gap)+"\n"+a+"]":"["+f.join(",")+"]",gap=a,o}if(rep&&"object"==typeof rep)for(u=rep.length,r=0;u>r;r+=1)"string"==typeof rep[r]&&(n=rep[r],o=str(n,i),o&&f.push(quote(n)+(gap?": ":":")+o));else for(n in i)Object.prototype.hasOwnProperty.call(i,n)&&(o=str(n,i),o&&f.push(quote(n)+(gap?": ":":")+o));return o=0===f.length?"{}":gap?"{\n"+gap+f.join(",\n"+gap)+"\n"+a+"}":"{"+f.join(",")+"}",gap=a,o}}var rx_one=/^[\],:{}\s]*$/,rx_two=/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,rx_three=/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,rx_four=/(?:^|:|,)(?:\s*\[)+/g,rx_escapable=/[\\"\x00-\x1f\x7f-\x9f\xad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,rx_dangerous=/[\x00\xad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;"function"!=typeof Date.prototype.toJSON&&(Date.prototype.toJSON=function(){return isFinite(this.valueOf())?this.getUTCFullYear()+"-"+f(this.getUTCMonth()+1)+"-"+f(this.getUTCDate())+"T"+f(this.getUTCHours())+":"+f(this.getUTCMinutes())+":"+f(this.getUTCSeconds())+"Z":null},Boolean.prototype.toJSON=this_value,Number.prototype.toJSON=this_value,String.prototype.toJSON=this_value);var gap,indent,meta,rep;"function"!=typeof JSON.stringify&&(meta={"\b":"\\b","	":"\\t","\n":"\\n","\f":"\\f","\r":"\\r",'"':'\\"',"\\":"\\\\"},JSON.stringify=function(t,e,r){var n;if(gap="",indent="","number"==typeof r)for(n=0;r>n;n+=1)indent+=" ";else"string"==typeof r&&(indent=r);if(rep=e,e&&"function"!=typeof e&&("object"!=typeof e||"number"!=typeof e.length))throw new Error("JSON.stringify");return str("",{"":t})}),"function"!=typeof JSON.parse&&(JSON.parse=function(text,reviver){function walk(t,e){var r,n,o=t[e];if(o&&"object"==typeof o)for(r in o)Object.prototype.hasOwnProperty.call(o,r)&&(n=walk(o,r),void 0!==n?o[r]=n:delete o[r]);return reviver.call(t,e,o)}var j;if(text=String(text),rx_dangerous.lastIndex=0,rx_dangerous.test(text)&&(text=text.replace(rx_dangerous,function(t){return"\\u"+("0000"+t.charCodeAt(0).toString(16)).slice(-4)})),rx_one.test(text.replace(rx_two,"@").replace(rx_three,"]").replace(rx_four,"")))return j=eval("("+text+")"),"function"==typeof reviver?walk({"":j},""):j;throw new SyntaxError("JSON.parse")})})();
  var swimg = '\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR\x00\x00\x00\x88\x00\x00\x006\b\x06\x00\x00\x00\x1Cz\xD1W\x00\x00\x00\x01sRGB\x00\xAE\xCE\x1C\xE9\x00\x00\x00\x04gAMA\x00\x00\xB1\x8F\x0B\xFCa\x05\x00\x00\x00\tpHYs\x00\x00\x0E\xC3\x00\x00\x0E\xC3\x01\xC7o\xA8d\x00\x00\x07jIDATx^\xED\x9AYk\x14K\x14\xC7\xE73\x88\x82\x1A\xD4\xBC\xB8\x04w\xD4\xA8\xD1\x04\xF7}\x1BqCT4\x8A+j\x10D\x10\x05}sA\x9C\xAB\xE0\xAE(.\xB8D\x8D\xFB\xBE\xE2\x16\x15Q\x82\xF8\xE2\x87\x90<\xDC\xFBx\xEE\xFCO\xA6z\xAA\xAB\xAB;\x95\xCEt\x9C\x81\xF3\xF0\xA3\xAB\xAA\xEB\xD4\xA9\xD4\xF9\xD7\xA9\xEA\xD1\xD4\xB0a\xC3H\xD1\xBD{wA\xF0\x11\x10\xC8\xBCy\xF3h\xFE\xFC\xF9\x89\x80\xB1\xE1C\xF7)\x147\x01\x81\xA4\xD3\xE9D\x11\x81\x94\x16\xD6\f\x92$"\x90\xD2\xC2Y \xE5\xE5\xE5\x1E\xB6\xF7\xAE\xB4G =z\xF4\xA0n\xDD\xBA\xF1\xD3\xF6\xDE\x05\xD8\x9B\xD8\xFA%\x89m\x0E6l\xB6q\xE8\xDF\xBF\xBF\x136\xDB\x80@\xE6\xCE\x9D\xEB\xA1\x04\x81\xF2\x93\'O\xE8\xC7\x8F\x1F\xFC\x9C5k\x16\xA3\xF7u\xC5U C\x87\x0E\xA5\x81\x03\x07REE\x05?\xD1\xB6k\xD7.\xFA\xF6\xED\x1B?\xCD\xFE\xAE\xBCy\xF3&@\x92\xC1\xB1\xF1\xFC\xF9s\'l\xB6qX\xB2d\x89\x136\xDB\x80@\xE6\xCC\x99\xE3\xF1\xF8\xF1cF\x95\xD5s\xC8\x90!4~\xFCx__W\\\x04\x02Q Heee\x9C-\xBAv\xEDJ]\xBAt\xA1\x07\x0F\x1EPSS\x13\xFD\xFC\xF9\xD3j\xE7\xC2\xCB\x97/\x03u\x1Bx\x07a\x0E\x1A4\xC8\xD7\xBF\x10 \xF8\xFB\xF7\xEFgPV\x97x\x94\xF7\xEE\xDD\xCB\x94\xB4@\xDE\xBD{G\x07\x0F\x1E\xA4\x993g\xFA\xFA\xBB\xE0"\x90\x9E={\xD2\xAAU\xAB\xE8\xEE\xDD\xBB\xD4\xD0\xD0@uuu\xB4x\xF1b\xFA\xFE\xFD;\xFD\xFE\xFD\x9BN\x9D:e\xB5s\xE1\xE9\xD3\xA7\xD6v\x9D#G\x8E\xB08\xF1\xF7\x8D\x1B7\x8EE\x1A\x96\x82\xE3\xF07\x04bk\xD7q\x16\xC8\xEC\xD9\xB3=\xEE\xDF\xBF\xCF\xA8r\xAF^\xBD\xBC\xB6g\xCF\x9E\xD1\xD4\xA9S}\xFD]p\x11\xC8\x9A5k\xE8\xF4\xE9\xD3\x9C\xA5\xE0s\xE9\xD2\xA5|\xBCm\xDF\xBE\x9D\xFA\xF4\xE9c\xB5q\xE5\xD1\xA3G\xD6v\b\x00\xC7)2\xD7\xC8\x91#\xF9\xE8\xC1\xDF\x88@\xE1X\xC5\x02\x16J$\x18\xCF\x05\x9Bm\x1CT\xF0\x97/_\xEE=u\xF4>&\x01\x81\xA8\xFB\x05\xC0\xEE\x05z\x19\x1C:t\x88\x17p\xE3\xC6\x8D\xBE\xFE.\xB8\b\xA4\xBE\xBE\x9EF\x8F\x1EM\x9B6m\xA2\x87\x0F\x1F\xD2\x8B\x17/\xF8\x0F@&\x19<x\xB0\xD5\xC6\x15\x88\xDBl\xEB\xD7\xAF\x1F-Z\xB4\x88.\\\xB8@\x1F>|\xA0\xB7o\xDF\xD2\xEA\xD5\xABy\x0E#F\x8C\xA0\xAD[\xB7r[\xD8"\xB6\x95{\xF7\xEEyY\x03edc\x80r\xA7N\x9D\x18\x94m\xB6q@\xF6\xB5\xB5\xEB\x84\xF5\x89\x14\xC8\x8D\x1B7\x18\xBD\xAC\xEA\xD8](\xD7\xD4\xD4\xF8lZ\xC3E \x10\x1F\xEE!w\xEE\xDC\xE1\xFB\x00\x82\n\xBB\xF6\x1C-\n\b\xDClCfB@0\xBF\xB1c\xC7\xD2\xB4i\xD38\xFD\xDF\xBE}\x9B\xAE]\xBB\xC6ul\x06\x9Bm\x1Cn\xDD\xBA\xE5\t\x04e%\x10\x94\x95@P\xB6\xD9\xC6a\xE1\xC2\x85\xFC\x8C\xCA \xAA\x8FI@ 8w\x15W\xAE\\a\xF4\xB2\xAA\x1F>|\x98/\x8D+W\xAE\xF4\xD9\xB4\x86\x8B@ \x8A\xEA\xEAjZ\xBF~=\x07\t\xC7\x02v\xF8\xDA\xB5k\xF9\x88\xC1\x17\x8E\xCD\xCE\x85\x9B7o\x06\xDA \xC0\xE9\xD3\xA7\xD3\x8A\x15+\xE8\xDC\xB9s\xB4s\xE7N\xCE\x1Eg\xCF\x9E\xA5W\xAF^\xD1\xC5\x8B\x17\xB9\xFE\xFA\xF5\xEB\x80m\x1C :\x17l\xB6q\b\x0B\xBE\x8E\xB3@f\xCC\x98\xE1\x81\x94\x0B\xF4\xB2\xAAc@\b\x04\x17\xBA\t\x13&p[\xEF\xDE\xBD\x03\xA8\xB1\x14.\x02\xD9\xB6m\x1B\x0B\x03\xBF\x9B\xE0^\x00_\xEF\xDF\xBF\xA7)S\xA6P\xE7\xCE\x9D\xDB\xF5;\x88m\xE1q\x84\xE1\xDEq\xE9\xD2%\xBEH\xEF\xDB\xB7\x8F\xF6\xEC\xD9C\'O\x9E\xE4L\t\xD1\xE0\xFD\xC7\x8F\x1F\x03\xB6q\x80\x1F\x95)\x94O\x80\xB2\xBA\xA4\xA2\x8C\xBE\xFA\xA7\xB7\xC2\x1C\xAF5\x16,X\xC0\xCF\xA8\f\xA2\xFA\x98\x04\x04\x82\x9D\xA48s\xE6\f\xA3\x97U\x1D\x1C?~\x9C\xAE_\xBF\xCE\x97H\xD4q_0Q}\x15.\x02\x01\x10\t\x02\x87]\x8B\xBB\xC7\xE6\xCD\x9B\xF9H\x83(\xB7l\xD9b\xB5q\xE1\xF2\xE5\xCB\x81\xB6/_\xBE\xF0\xD7\n\xFC C"C!\xFD\xE3\xA2\f\xF0\xAE\xB6\xB6\x96\x8F>\xD36\x0E\x10\x9C\x12\b\xCAJ (+\x81\xA0\x8C\xBE\xC8x&\xE6x\xAD\x81\xBF\xC5\xD6\xAE\x13\xD6\' \x10\x9C\xB7\n\xEC\x1E\xA0\x97U\x1D,[\xB6\x8C/o;v\xEC\xF0\xDApoP\xA86\x1DW\x81\xE8\xE0b\xDA\xB7o_>z\xE0_\xFDN\x11\x07\xCCW\x07m\'N\x9C`\xE1\xE3X\xC3\x1D\xE4\xE8\xD1\xA3\x9C\xBD*++i\xD4\xA8Q|\xB4\xE1\xD3~\xC3\x86\r\x81\xF1\xE2\x00\xE1\xE3"\x0EP\x0EC\xF5\xBFz\xF5\xAA\x87>\x8E+f\xC6\b\xC3f\x1B\x10\b>]\xDB\x02\xFE\x01\xCEl;\x7F\xFE|\xA0M\x11G \n|=}\xFE\xFC\x99\x9F\xB6\xF7q\x81\b2\x99\f\x7FZ\xE2r\b\x1A\x1B\x1B=\xB0kq\xD7\xB2\xD9v\x14\xC7\x8E\x1D\xB3\xB6\'M@ 8\xE7\x93\xA4=\x02\x11:\x9E\x80@&O\x9E\x9C("\x90\xD2" \x90I\x93&%\x8A\b\xA4\xB4\b\b\x04\x9F\xAC\x13\'NL\x04\x8C-\x02)-R\xF8]A\x10\xC2H\x8D\x193\x86\x14_\xBF~\x15\x04\x1F\xA9\xE1\xC3\x87\x93\x02\r\xCD\xCD\xCD\x82\xE0!\x02\x11"\t\x15H\xE6\xD3\xBF\x1D\xCA?\x8D\xFF\tE\x88\bD\x88D\x04"D"\x02\x11"\xF1\xFD\x0E"\x02\x11LR\xFA\x7F\x07\x14\x81\b&\xC5-\x90L-\xA5R\xA9\x1C\xB5\xB4\xCE\xD6\xA7Pt\xA4/\x1F\rT\x95\xAA\xA4t\xBD\xED\xDD\xDF\xA7x\x05R\x7F\x80\xCA\xF5@e\xEB\xE9\x8C\xF6\xBE\x90$\xE2\xCB5\xF0"\x10\'\x02\x93\xC3\x8E\x1Ep\x80v\x9B\xEDI\x90\x88/\x11HA\tN\x0E\x0B\x97\xA2\xF2\xBA\xA6\xC0\xBBuiu\x14dI7d\xDB\x9A(= EU\xFA\xAE\xD7\x83\xCE\x19"\xEA\xF8\xB0\xFB\x82\x9F\xFC\x98F 1&\xFB\xB6\xCD\xA7e<\x7F\x1B\xECZ\xE6\xA9\xDA[\xC6\xCE\x8D\x9B\xC9\xCF\xD17\x0F\xEB\xDCm\xE3$C\x91_R\xF3\x0Ba_\x04-h\x10\x84\x17\b=\xB8F`\xB3\xFDl\xA2\xB3\xFA\xD2\xC7\x84\xDD\x80J\xCFvw]\xBE\x9CG\xF7e\xF8\xCD\x8Do\xB7\xC9\xFAUb\x86OO\b!s\xD7\xE7\x950E.\x90\x1C\xB9]\xE4\x0B\x1C\x16\x95\xD1\x03\xA2-\xAC5{\xE4\x88Z\\\xDD\x17\xCA\xB9q\xD6\xA5\xB3~\xB0\xCB\xB9\x8E`\xFB\x03g\x9F\x8F\xD6\x87\xC7\r\xCB^Z?\xBD\x1E6\xF7\\\xBB]\xE8\x85\xA54\x04\x92\x05;6\xBF8z\xAA\xCD/\xAE\xDA\xD5\xBE\xDD\x1D\x1A\x98p<_\xDE\xF8Jp\xA8g\xC7\xC2\x98>\x01\xDA\xE6S(\x81\x84\xCF\x9D\xE7\x99\x15\x8A=\xBB\x16\x86"\xBE\xA4f\xBF$\xBCE\xC3\xC2\xE7v\fv\xAB/8F\x10\xB2\x01\xACB\x10\xD58\xBC\xE0\xAD\xEC\xB60_\xD9:\x8B\xCD8Z\xAA\xD2\xDA1\x15:\x1F3\xF0\xFEq\xF3D\b\xC4a\xEE\xF9\xCD`\xDA\x85\xCD\xA3m\x14\xFD%\xD5\x97Z\xB9\xBDe\xA1\xB9\x8D\xC5\xA0\xFF\xF1\xB9w\xE6\x11\xC2\x81\xCB\xD9\xF8\xC6R\x84\xF9R\xB6\x9A\x0F\xB3\x1E1\x1F\xEF\xF2\xEA\x8D\xE7\xF7c\xBD#\x99u\xDB\xDC}G\x9A\xDA\f\xBA]X\xB9\xED\x94\xCC\x11#\xFC\x1DD B$"\x10!\x12\x11\x88\x10\x89\bD\x88D\x04"D"\x02\x11"\t\x15\xC8\x9F?\x7F:\x14\xE5W(.D B$"\x10!\x92\xA2\x11\xC8\xAF_\xBF\x84"D2\x88\x10\x89\bD\x88D\x04"D"\x02\x11"h\xA6\xFF\x01 \x8C\xE2\xE9n\xE9\xC4`\x00\x00\x00\x00IEND\xAEB`\x82';
// </JSON_PARSER>

// <SETUP>
// jshint -W055
  var _Version = function(str) {
    var _split = str.split('.');
    this.major = _split[0];
    this.minor = _split[1];
  };
  _Version.prototype.toString = function() {
    return this.major + '.' + this.minor;
  };
  _Version.prototype.base = function() {
    return this.major + '.0';
  };
  _Version.prototype.compatibleWith = function(ver) {
    if (!(ver instanceof _Version))
      throw new Error('ver must be an instance of Version');
    return this.major === ver.major;
  };
  var madeNewDoc = app.documents.length === 0,
    _Document = !madeNewDoc ? app.activeDocument : app.documents.add(DocumentColorSpace.RGB),
    SCRIPT_VERSION = new _Version('1.5'),
    title = 'Import swatches from JSON (by MLP-VectorClub, version ' + SCRIPT_VERSION + ')';

  function _RGBColor(r, g, b) {
    var newRGBColor = new RGBColor();
    newRGBColor.red = r;
    newRGBColor.green = g;
    newRGBColor.blue = b;
    return newRGBColor;
  }

  function _ColorGroup(name) {
    var swatchGroup = _Document.swatchGroups.add();
    swatchGroup.name = name;
    return swatchGroup;
  }

  function _Swatch(name, r, g, b) {
    var newSwatch = activeDocument.swatches.add();
    newSwatch.name = name;
    newSwatch.color = _RGBColor(r, g, b);
    return newSwatch;
  }

// </SETUP>

  var alert = function(text, beforegroup) {
      var win = new Window('dialog', title, nil, { closeButton: false }),
        ml = text.indexOf('\n') !== -1;
      win.add('statictext', ml ? [0, 0, 340, 70] : nil, text, { multiline: ml });
      if (typeof beforegroup === 'function')
        beforegroup(win);
      var btngrp = win.add('group');
      btngrp.add('button', nil, 'OK');
      if (beforegroup !== true)
        win.show();
      return {
        close: function() {
          win.close();
        },
      };
    },
    fin = function(text) {
      alert(text, function(win) {
        var grp = win.add('group');
        grp.add('image', nil, swimg);
      });
    },
    wait = function(text) {
      var win = new Window('palette', title, nil, { closeButton: false }),
        ml = text.indexOf('\n') !== -1;
      win.add('statictext', ml ? [0, 0, 340, 70] : nil, text, { multiline: ml });
      win.show();
      return {
        close: function() {
          win.close();
        },
      };
    };

  var importers = {
    '1.0': function(imported) {
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

            var color = cg[colorname],
              swatchName = cgname + ' | ' + colorname,
              capitalMatch = /(^|\s)([A-Z])[^.\s]+/;

            if (swatchName.length > 31)
              swatchName = swatchName.replace(/\bHighlight\b/g, 'HL');

            while (swatchName.length > 31 && capitalMatch.test(swatchName)){
              swatchName = swatchName.replace(capitalMatch, '$1$2.');
            }

            CG.addSwatch(
              new _Swatch(
                swatchName,
                parseInt(color.substring(1, 3), 16),
                parseInt(color.substring(3, 5), 16),
                parseInt(color.substring(5, 7), 16),
              ),
            );
          }
        }
      }
    },
  };

  var fileDialog = new Window('dialog', title);
  fileDialog.add('statictext', [0, 0, 520, 20], 'Please click the button below and select the JSON file you got from our Color Guide.');
  var importSingleCG = fileDialog.add('checkbox', nil, '\xA0Import as a single swatch group');
  importSingleCG.value = true;
  fileDialog.add('statictext', [0, 0, 520, 35], 'By default, all colors will be imported to a single swatch group.\nIf you\'d like to have each color group in a separate swatch group, untick the checkbox above.', { multiline: true });
  var btngrp = fileDialog.add('group'),
    browsebtn = btngrp.add('button', nil, 'Browse...'),
    closebtn = btngrp.add('button', nil, 'Cancel');
  browsebtn.onClick = function() {
    var jsonFile = File.openDialog('Find the JSON file downloaded from our Color Guide',
      (
        $.os.match(/Macintosh/i)
          ? function(f) {
            return f.name.match(/\.json$/i);
          }
          : 'JSON files:*.json'
      ),
    );

    if (jsonFile){
      var importingAlert = wait('Importing...');
      jsonFile.open('r');
      var imported = jsonFile.read();
      jsonFile.close();
      try {
        imported = JSON.parse(imported);
      } catch (e){
        fileDialog.close();
        return alert('Error while parsing JSON (in ' + e.fileName + ':' + e.line + '):' + e + '\n' + e.stack);
      }

      if (typeof imported.Version !== 'string')
        imported.Version = '1.4';

      var jsonVersion = new _Version(imported.Version),
        baseVersion = jsonVersion.base();

      if (!jsonVersion.compatibleWith(SCRIPT_VERSION) || typeof importers[baseVersion] !== 'function'){
        return alert('The selected JSON file (v' + jsonVersion + ') is not compatible with your current script file (v' + SCRIPT_VERSION + ').\nPlease grab an updated script file from the guide to import this appearance.');
      }

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

      importers[baseVersion](imported);

      importingAlert.close();
      fileDialog.close();
      fin('All color groups have been imported successfully.\nWe suggest that you remove any built-in swatches you don\'t need, then save the rest for future use.\n(Icon with books on Swatches panel > Save Swatches...)');
    }
  };
  closebtn.onClick = function() {
    fileDialog.close();
  };
  fileDialog.show();

})();
