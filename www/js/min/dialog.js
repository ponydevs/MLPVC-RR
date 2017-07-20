"use strict";function _classCallCheck(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}var _typeof="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},_createClass=function(){function t(t,e){for(var o=0;o<e.length;o++){var i=e[o];i.enumerable=i.enumerable||!1,i.configurable=!0,"value"in i&&(i.writable=!0),Object.defineProperty(t,i.key,i)}}return function(e,o,i){return o&&t(e.prototype,o),i&&t(e,i),e}}();!function(t,e){var o={fail:"red",success:"green",wait:"blue",request:"",confirm:"orange",info:"darkblue",segway:"lavander"},i={fail:"fail",success:"success",wait:"info",request:"warn",confirm:"caution",info:"info",segway:"reload"},n={fail:"Error",success:"Success",wait:"Sending request",request:"Input required",confirm:"Confirmation",info:"Info",segway:"Pending navigation"},l={fail:"There was an issue while processing the request.",success:"Whatever you just did, it was completed successfully.",wait:"Sending request",request:"The request did not require any additional info.",confirm:"Are you sure?",info:"No message provided.",segway:"A previous action requires reloading the current page. Press reload once you're ready."},a=function(){t.Navigation.reload(!0)},s=function(){t.Dialog.close()},r=function(){function e(o,i){var n=this;_classCallCheck(this,e),this.label=o,t.each(i,function(t,e){return n[t]=e})}return _createClass(e,[{key:"setLabel",value:function(t){return this.label=t,this}},{key:"setFormId",value:function(t){return this.formid=t,this}}]),e}(),c=function(){function c(){_classCallCheck(this,c),this.$dialogOverlay=t("#dialogOverlay"),this.$dialogContent=t("#dialogContent"),this.$dialogHeader=t("#dialogHeader"),this.$dialogBox=t("#dialogBox"),this.$dialogWrap=t("#dialogWrap"),this.$dialogScroll=t("#dialogScroll"),this.$dialogButtons=t("#dialogButtons"),this._open=this.$dialogContent.length?{}:e,this._CloseButton=new r("Close",{action:s}),this._$focusedElement=e}return _createClass(c,[{key:"isOpen",value:function(){return"object"===_typeof(this._open)}},{key:"_display",value:function(n){var a=this;if("string"!=typeof n.type||void 0===o[n.type])throw new TypeError("Invalid dialog type: "+_typeof(n.type));n.content||(n.content=l[n.type]);var s=t.extend({content:l[n.type]},n);s.color=o[n.type];var r=Boolean(this._open),c=t.mk("div").append(s.content),d=r&&"request"===this._open.type&&["fail","wait"].includes(s.type)&&!s.forceNew,u=void 0;if(s.color.length&&c.addClass(s.color),r)if(this.$dialogOverlay=t("#dialogOverlay"),this.$dialogBox=t("#dialogBox"),this.$dialogHeader=t("#dialogHeader"),"string"==typeof s.title&&this.$dialogHeader.text(s.title),this.$dialogContent=t("#dialogContent"),d){var h=(u=this.$dialogContent.children(":not(#dialogButtons)").last()).children(".notice:last-child");h.length?h.show():(h=t.mk("div").append(t.mk("p")),u.append(h)),h.attr("class","notice "+i[s.type]).children("p").html(s.content).show(),this._controlInputs("wait"===s.type)}else this._open=s,this.$dialogButtons=t("#dialogButtons").empty(),this._controlInputs(!0),this.$dialogContent.append(c),s.buttons&&(0===this.$dialogButtons.length&&(this.$dialogButtons=t.mk("div","dialogButtons")),this.$dialogButtons.appendTo(this.$dialogContent));else this._storeFocus(),this._open=s,this.$dialogOverlay=t.mk("div","dialogOverlay"),this.$dialogHeader=t.mk("div","dialogHeader"),"string"==typeof s.title&&this.$dialogHeader.text(s.title),this.$dialogContent=t.mk("div","dialogContent"),this.$dialogBox=t.mk("div","dialogBox").attr({role:"dialog","aria-labelledby":"dialogHeader"}),this.$dialogScroll=t.mk("div","dialogScroll"),this.$dialogWrap=t.mk("div","dialogWrap"),this.$dialogContent.append(c),this.$dialogButtons=t.mk("div","dialogButtons").appendTo(this.$dialogContent),this.$dialogBox.append(this.$dialogHeader).append(this.$dialogContent),this.$dialogOverlay.append(this.$dialogScroll.append(this.$dialogWrap.append(this.$dialogBox))).appendTo($body),$body.addClass("dialog-open"),this.$dialogOverlay.siblings().prop("inert",!0);if(d||(this.$dialogHeader.attr("class",s.color?s.color+"-bg":""),this.$dialogContent.attr("class",s.color?s.color+"-border":"")),!d&&s.buttons&&t.each(s.buttons,function(o,i){var n=t.mk("input").attr({type:"button",class:s.color?s.color+"-bg":e});i.form&&1===(u=t("#"+i.form)).length&&(n.on("click",function(){u.find("input[type=submit]").first().trigger("click")}),u.prepend(t.mk("input").attr("type","submit").hide().on("focus",function(t){t.preventDefault(),a.$dialogButtons.children().first().focus()}))),n.val(i.label).on("click",function(e){e.preventDefault(),t.callCallback(i.action,[e])}),a.$dialogButtons.append(n)}),window.withinMobileBreakpoint()||this._setFocus(),$w.trigger("dialog-opened"),Time.Update(),t.callCallback(s.callback,[u]),r){var g=this.$dialogContent.children(":not(#dialogButtons)").last();d&&(g=g.children(".notice").last()),this.$dialogOverlay.stop().animate({scrollTop:"+="+(g.position().top+parseFloat(g.css("margin-top"),10)+parseFloat(g.css("border-top-width"),10))},"fast")}}},{key:"fail",value:function(){var t=arguments.length>0&&arguments[0]!==e?arguments[0]:n.fail,o=arguments.length>1&&arguments[1]!==e?arguments[1]:l.fail,i=arguments.length>2&&arguments[2]!==e&&arguments[2];this._display({type:"fail",title:t,content:o,buttons:[this._CloseButton],forceNew:i})}},{key:"success",value:function(){var t=arguments.length>0&&arguments[0]!==e?arguments[0]:n.success,o=arguments.length>1&&arguments[1]!==e?arguments[1]:l.success,i=arguments.length>2&&arguments[2]!==e&&arguments[2],a=arguments.length>3&&arguments[3]!==e?arguments[3]:e;this._display({type:"success",title:t,content:o,buttons:i?[this._CloseButton]:e,callback:a})}},{key:"wait",value:function(){var o=arguments.length>0&&arguments[0]!==e?arguments[0]:n.wait,i=arguments.length>1&&arguments[1]!==e?arguments[1]:l.wait,a=arguments.length>2&&arguments[2]!==e&&arguments[2],s=arguments.length>3&&arguments[3]!==e?arguments[3]:e;this._display({type:"wait",title:o,content:t.capitalize(i)+"&hellip;",forceNew:a,callback:s})}},{key:"request",value:function(){var t=arguments.length>0&&arguments[0]!==e?arguments[0]:n.request,o=arguments.length>1&&arguments[1]!==e?arguments[1]:l.request,i=arguments.length>2&&arguments[2]!==e?arguments[2]:"Submit",a=arguments.length>3&&arguments[3]!==e?arguments[3]:e;"function"==typeof i&&void 0===a&&(a=i,i=e);var c=[],d=void 0;if(o instanceof jQuery)d=o.attr("id");else if("string"==typeof o){var u=o.match(/<form\sid=["']([^"']+)["']/);u&&(d=u[1])}!1!==i?(d&&c.push(new r(i,{submit:!0,form:d})),c.push(new r("Cancel",{action:s}))):c.push(new r("Close",{action:s})),this._display({type:"request",title:t,content:o,buttons:c,callback:a})}},{key:"confirm",value:function(){var o=arguments.length>0&&arguments[0]!==e?arguments[0]:n.confirm,i=arguments.length>1&&arguments[1]!==e?arguments[1]:l.confirm,a=this,c=arguments.length>2&&arguments[2]!==e?arguments[2]:["Eeyup","Nope"],d=arguments.length>3&&arguments[3]!==e?arguments[3]:e;void 0===d&&(d="function"==typeof c?c:s),t.isArray(c)||(c=["Eeyup","Nope"]);var u=[new r(c[0],{action:function(){d(!0)}}),new r(c[1],{action:function(){d(!1),a._CloseButton.action()}})];this._display({type:"confirm",title:o,content:i,buttons:u})}},{key:"info",value:function(){var t=arguments.length>0&&arguments[0]!==e?arguments[0]:n.info,o=arguments.length>1&&arguments[1]!==e?arguments[1]:l.info,i=arguments.length>2&&arguments[2]!==e?arguments[2]:e;this._display({type:"info",title:t,content:o,buttons:[this._CloseButton],callback:i})}},{key:"segway",value:function(){var o=arguments.length>0&&arguments[0]!==e?arguments[0]:n.reload,i=arguments.length>1&&arguments[1]!==e?arguments[1]:l.reload,s=arguments.length>2&&arguments[2]!==e?arguments[2]:"Reload",c=arguments.length>3&&arguments[3]!==e?arguments[3]:e;"undefined"==typeof callback&&"function"==typeof s&&(c=s,s="Reload"),this._display({type:"segway",title:o,content:i,buttons:[new r(s,{action:function(){t.callCallback(c),a()}})]})}},{key:"setFocusedElement",value:function(t){t instanceof jQuery&&(this._$focusedElement=t)}},{key:"_storeFocus",value:function(){if(!(void 0!==this._$focusedElement&&this._$focusedElement instanceof jQuery)){var o=t(":focus");this._$focusedElement=o.length>0?o.last():e}}},{key:"_restoreFocus",value:function(){void 0!==this._$focusedElement&&this._$focusedElement instanceof jQuery&&(this._$focusedElement.focus(),this._$focusedElement=e)}},{key:"_setFocus",value:function(){var t=this.$dialogContent.find("input,select,textarea").filter(":visible"),e=this.$dialogButtons.children();t.length>0?t.first().focus():e.length>0&&e.first().focus()}},{key:"_controlInputs",value:function(t){var e=this.$dialogContent.children(":not(#dialogButtons)").last().add(this.$dialogButtons).find("input, button, select, textarea");t?e.filter(":not(:disabled)").addClass("temp-disable").disable():e.filter(".temp-disable").removeClass("temp-disable").enable()}},{key:"close",value:function(o){if(!this.isOpen())return t.callCallback(o,!1);this.$dialogOverlay.siblings().prop("inert",!1),this.$dialogOverlay.remove(),this._open=e,this._restoreFocus(),t.callCallback(o),$body.removeClass("dialog-open")}},{key:"clearNotice",value:function(t){var e=this.$dialogContent.children(":not(#dialogButtons)").children(".notice:last-child");return!!e.length&&(!(void 0!==t&&!t.test(e.html()))&&(e.hide(),e.hasClass("info")&&this._controlInputs(!1),!0))}}]),c}();t.Dialog=new c;var d=function(){t.Dialog.isOpen()&&window.withinMobileBreakpoint()&&t.Dialog.$dialogContent.css("margin-top",t.Dialog.$dialogHeader.outerHeight())};$w.on("resize",t.throttle(200,d)).on("dialog-opened",d)}(jQuery);
//# sourceMappingURL=/js/min/dialog.js.map
