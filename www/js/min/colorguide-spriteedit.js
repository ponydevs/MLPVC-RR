"use strict";DocReady.push(function(){var t=window.SpriteColorMap,e=window.AppearanceColors,n=window.HEX_COLOR_REGEX,i=$("#form-cont").empty(),o=$.mk("select").append('<option value="" style="display:none">(foreign color)</option>'),a=$("#svg-cont").children();$.each(e,function(t,e){$.yiq(e.hex)>=128?"black":"white";o.append('<option value="'+e.hex+'">'+e.label+"</option>")}),o.append('<optgroup label="Universal colors">\n\t\t\t<option value="#FFFFFF">Eye | Shines</option>\n\t\t\t<option value="#000000">Eye | Pupil</option>\n\t\t</optgroup>\n\t\t<optgroup label="Uniform mannequin">\n\t\t\t<option value="#D8D8D8">Mannequin | Outline</option>\n\t\t\t<option value="#E6E6E6">Mannequin | Fill</option>\n\t\t\t<option value="#BFBFBF">Mannequin | Shadow Outline</option>\n\t\t\t<option value="#CCCCCC">Mannequin | Shdow Fill</option>\n\t\t</optgroup>'),$.each(t,function(t,e){var l=o.clone();l.find('option[value="'+e+'"]').first().attr("selected",!0),l.on("change",function(){var t=$(this),e=t.find("option:selected").val();e.length&&t.siblings("input").val(e).triggerHandler("change",[!0])}),i.append($.mk("div").attr("data-ph",t).append('<span class="color-preview" style="background-color:'+e+'"></span>',l,$.mk("input").attr({type:"text",required:!0,value:e,spellcheck:"false",autocomplete:"off",title:"Hexadecimal color"}).patternAttr(n).on("keyup change input",function(e,i,o){var l=$(this),r=l.siblings().first(),p=l.siblings("select"),s=("string"==typeof o?o:this.value).trim(),c=n.test(s);if(c){r.removeClass("invalid").css("background-color",s.replace(n,"#$1")),i!==!0&&(p.find('option[value="'+s+'"]').length?p.val(s):p.val(""));a.find("rect").filter(function(){var e=this.getAttribute("data:ph");return"string"==typeof e&&e===t}).attr("fill",s)}else r.addClass("invalid"),p.val("")}).on("paste blur keyup",function(t,e){var i=this,o=function(){var o=$.hexpand(i.value);if(n.test(o)){o=o.replace(n,"#$1").toUpperCase();var a=$(i);switch(t.type){case"paste":case"blur":a.val(o)}a.trigger("change",[e,o]).patternAttr(SHORT_HEX_COLOR_PATTERN.test(i.value)?SHORT_HEX_COLOR_PATTERN:n)}};"paste"===t.type?setTimeout(o,10):o()})).on("mouseenter",function(){var e=a.find("rect").filter(function(){var e=this.getAttribute("data:ph");return"string"==typeof e&&e===t});e.addClass("highlight")}).on("mouseleave",function(){a.find(".highlight").removeClass("highlight")}))}),i.children("div").sort(function(t,e){var n=$(t).children("select").children("option:selected").text()||"",i=$(e).children("select").children("option:selected").text()||"";return n.localeCompare(i)}).prependTo(i),i.append('<button class="green typcn typcn-tick">Save</button>').on("submit",function(t){return t.preventDefault(),$.Dialog.info("Save sprite colors",'This feature is not yet fully implemented, saving is not yet possible. Sorry.<div class="align-center"><span class="sideways-smiley-face">:\\</div>')})});
//# sourceMappingURL=/js/min/colorguide-spriteedit.js.map
