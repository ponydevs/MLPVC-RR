'use strict';var _createClass=function(){function a(a,b){for(var c,d=0;d<b.length;d++)c=b[d],c.enumerable=c.enumerable||!1,c.configurable=!0,'value'in c&&(c.writable=!0),Object.defineProperty(a,c.key,c)}return function(b,c,d){return c&&a(b.prototype,c),d&&a(b,d),b}}();function _possibleConstructorReturn(a,b){if(!a)throw new ReferenceError('this hasn\'t been initialised - super() hasn\'t been called');return b&&('object'==typeof b||'function'==typeof b)?b:a}function _inherits(a,b){if('function'!=typeof b&&null!==b)throw new TypeError('Super expression must either be null or a function, not '+typeof b);a.prototype=Object.create(b&&b.prototype,{constructor:{value:a,enumerable:!1,writable:!0,configurable:!0}}),b&&(Object.setPrototypeOf?Object.setPrototypeOf(a,b):a.__proto__=b)}function _classCallCheck(a,b){if(!(a instanceof b))throw new TypeError('Cannot call a class as a function')}(function(a){'use strict';var b=Math.round,c=Math.floor,d={menubar:void 0,statusbar:void 0,tabbar:void 0,picker:void 0},f=window.parent.Key,g={min:4e-3,max:32,step:1.1},e=function(a){a.getContext('2d').clearRect(0,0,a.width,a.height)},h=function(){function a(){_classCallCheck(this,a)}return _createClass(a,[{key:'getAverageOf',value:function getAverageOf(){}}]),a}(),j=function(a){function b(a){_classCallCheck(this,b);var c=_possibleConstructorReturn(this,(b.__proto__||Object.getPrototypeOf(b)).call(this));return c.boundingRect=a,c}return _inherits(b,a),_createClass(b,[{key:'getPixels',value:function getPixels(){}},{key:'getAverageOf',value:function getAverageOf(){}}]),b}(h),k=function(a){function b(a,c){_classCallCheck(this,b);var d=_possibleConstructorReturn(this,(b.__proto__||Object.getPrototypeOf(b)).call(this));return d.boundingRect=a,d.slices=c,d}return _inherits(b,a),_createClass(b,[{key:'getPixels',value:function getPixels(){}},{key:'getAverageOf',value:function getAverageOf(){}}]),b}(h),i=function(){function d(){_classCallCheck(this,d)}return _createClass(d,null,[{key:'calcRectanglePoints',value:function calcRectanglePoints(a,b,d){var e=c(d/2);return{sideLength:d,topLeft:{x:a-e,y:b-e}}}},{key:'distance',value:function distance(a,b){var c=Math.pow,d=2<arguments.length&&void 0!==arguments[2]?arguments[2]:0,e=3<arguments.length&&void 0!==arguments[3]?arguments[3]:0;return Math.sqrt(c(e-b,2)+c(d-a,2))}},{key:'calcCircleSlices',value:function calcCircleSlices(b){var c=b/2,e=Array(b);a.each(e,function(a){e[a]=Array(b)});for(var f=0;f<e.length;f++)for(var g=0;g<e[f].length;g++)e[f][g]=d.distance(f,g,c-0.5,c-0.5)<=c?1:0;return a.each(e,function(a,b){var c=b.join('').replace(/(^|0)1/g,'$1|1').replace(/1(0|$)/g,'1|$1').split('|');e[a]={skip:c[0].length,length:c[1].length}}),e}},{key:'snapPointToPixelGrid',value:function snapPointToPixelGrid(a,c){return b(b(a/c)*c)}}]),d}(),l=function(){function c(d){_classCallCheck(this,c);var e=/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([01]|(?:0?\.\d+)))?\)$/i,f=/^#([a-f0-9]{3}|[a-f0-9]{6})$/i;d=d.trim();var g=d.match(e);if(g&&255>=g[1]&&255>=g[2]&&255>=g[3]&&(!g[4]||1>=g[4]))this.red=parseInt(g[1],10),this.green=parseInt(g[2],10),this.blue=parseInt(g[3],10),this.alpha=g[4]?parseFloat(g[4]):1;else{var h=d.match(f);if(h){var i=h[1];3===i.length&&(i=i[0]+i[0]+i[1]+i[1]+i[2]+i[2]);var j=a.hex2rgb('#'+i);this.red=j.r,this.green=j.g,this.blue=j.b,this.alpha=1}else throw new Error('Unrecognized color format: '+d)}this.opacity=b(100*this.alpha)}return _createClass(c,[{key:'toString',value:function toString(){return 1===this.alpha?a.rgb2hex({r:this.red,g:this.green,b:this.blue}):'rgba('+this.red+','+this.green+','+this.blue+','+this.alpha+')'}}]),c}();window.ColorFormatter=l;var m=function(){function b(){var c=this;_classCallCheck(this,b),this._$menubar=a('#menubar'),this._$menubar.children().children('a.dropdown').on('click',function(b){b.preventDefault(),b.stopPropagation(),c._$menubar.addClass('open'),a(b.target).trigger('mouseenter')}).on('mouseenter',function(b){if(c._$menubar.hasClass('open')){var d=a(b.target);d.hasClass('dropdown')&&(c._$menubar.find('a.active').removeClass('active'),d.addClass('active').next().removeClass('hidden'))}}),this._$filein=a.mk('input','screenshotin').attr({type:'file',accept:'image/png,image/jpeg',tabindex:-1,'class':'fileinput'}).prop('multiple',!0).appendTo($body),this._$openImage=a('#open-image').on('click',function(a){a.preventDefault(),c._$filein.trigger('click')}),this._$closeActiveTab=a('#close-active-tab').on('click',function(a){a.preventDefault();var b=r.getInstance().getActiveTab();b&&b.getElement().find('.close').trigger('click')}),this._$filein.on('change',function(){var b=c._$filein[0].files;if(0!==b.length){var d=1===b.length?'':'s';a.Dialog.wait('Opening file'+d,'Reading opened file'+d+', please wait');var e=0,f=function(){return'undefined'==typeof b[e]?(c._$openImage.removeClass('disabled'),c._$filein.val(''),c.updateCloseActiveTab(),void a.Dialog.close()):void c.handleFileOpen(b[e],function(b){return b?(e++,f()):void(c._$openImage.removeClass('disabled'),a.Dialog.fail('Drag and drop','Failed to read file #'+e+', aborting'))})};f()}});var d=a('#about-dialog-template').children();this._$aboutDialog=a('#about-dialog').on('click',function(){a.Dialog.info('About',d.clone())}),$body.on('click',function(){c._$menubar.removeClass('open'),c._$menubar.find('a.active').removeClass('active'),c._$menubar.children('li').children('ul').addClass('hidden')})}return _createClass(b,[{key:'updateCloseActiveTab',value:function updateCloseActiveTab(){this._$closeActiveTab[r.getInstance().hasTabs()?'removeClass':'addClass']('disabled')}},{key:'handleFileOpen',value:function handleFileOpen(b,c){if(!/^image\/(png|jpeg)$/.test(b.type))return a.Dialog.fail('Invalid file','You may only use PNG or JPG images with this tool'),void c(!1);var d=new FileReader;d.onload=function(){s.getInstance().openImage(d.result,b.name,c)},d.readAsDataURL(b)}}],[{key:'getInstance',value:function getInstance(){return'undefined'==typeof d.menubar&&(d.menubar=new b),d.menubar}}]),b}(),n=function(){function b(){var c=this;_classCallCheck(this,b),this._$el=a('#statusbar'),this._$info=this._$el.children('.info'),this._$pos=this._$el.children('.pos'),this._$colorat=this._$el.children('.colorat'),this._$color=this._$colorat.children('.color'),this._$opacity=this._$colorat.children('.opacity'),this.infoLocked=!1,this.Pos={mouse:'mousepos'},this['_$'+this.Pos.mouse]=this._$pos.children('.mouse'),a.each(this.Pos,function(a){c.setPosition(a)})}return _createClass(b,[{key:'lockInfo',value:function lockInfo(){this.infoLocked=!0}},{key:'unlockInfo',value:function unlockInfo(){this.infoLocked=!1}},{key:'setInfo',value:function setInfo(){var a=0<arguments.length&&void 0!==arguments[0]?arguments[0]:'';this.infoLocked||this._$info.text(a)}},{key:'setPosition',value:function setPosition(b){var c=1<arguments.length&&void 0!==arguments[1]?arguments[1]:{top:NaN,left:NaN},d=2<arguments.length&&void 0!==arguments[2]?arguments[2]:1,e=this.Pos[b];if('string'!=typeof e)throw new Error('[Statusbar.setPosition] Invalid position display key: '+b);1!==d&&(c.left*=d,c.top*=d),this['_$'+e].text(isNaN(c.left)||isNaN(c.top)?'':a.roundTo(c.left,2)+','+a.roundTo(c.top,2))}},{key:'setColorAt',value:function setColorAt(){var b=0<arguments.length&&void 0!==arguments[0]?arguments[0]:'',c=1<arguments.length&&void 0!==arguments[1]?arguments[1]:'';b.length?this._$color.css({backgroundColor:b,color:127<a.yiq(b)?'black':'white'}):this._$color.css({backgroundColor:'',color:''}),this._$color.text(b||''),this._$opacity.text(c||'')}}],[{key:'getInstance',value:function getInstance(){return'undefined'==typeof d.statusbar&&(d.statusbar=new b),d.statusbar}}]),b}(),o=function(b,c){var d=b.find('.color-preview');d.html(a.mk('div').css('background-color','rgba('+c.red+','+c.green+','+c.blue+','+c.opacity/100+')'))},p=a.mk('form','set-area-color').append('<div class="label">\n\t\t\t\t<span>Red, Green, Blue (0-255)</span>\n\t\t\t\t<div class="input-group-3">\n\t\t\t\t\t<input type="number" min="0" max="255" step="1" name="red"   class="change input-red">\n\t\t\t\t\t<input type="number" min="0" max="255" step="1" name="green" class="change input-green">\n\t\t\t\t\t<input type="number" min="0" max="255" step="1" name="blue"  class="change input-blue">\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class="label">\n\t\t\t\t<span>Opacity (%)</span>\n\t\t\t\t<input type="number" min="0" max="100" step="1" name="opacity" class="change">\n\t\t\t</div>\n\t\t\t<div>\n\t\t\t\t<div class="color-preview"></div>\n\t\t\t</div>').on('change keyup input','.change',function areaColorFormInputChange(b){var c=a(b.target).closest('form'),d=c.mkData();o(c,d)}).on('set-color',function(b,c){var d=a(this);a.each(['red','green','blue','opacity'],function(a,b){d.find('input[name="'+b+'"]').val(c[b])}),o(d,c)}),q=function(){function c(d,e){var f=this;_classCallCheck(this,c),this._fileHash=e,this._imgel=new Image,this._imgdata={},this._pickingAreas=[],this.file={extension:void 0,name:void 0},this.setName(d),this._$pickAreaColorDisplay=a.mk('span').attr({'class':'pickcolor','data-info':'Color of the picking areas on this specific tab'}),this._$el=a.mk('li').attr('class','tab').append(this._$pickAreaColorDisplay,a.mk('span').attr({'class':'filename','data-info':this.file.name+'.'+this.file.extension}).text(this.file.name),a.mk('span').attr('class','fileext').text(this.file.extension),a.mk('span').attr({'class':'close','data-info':'Close tab'}).text('\xD7')),this.setPickingAreaColor('rgba(255,0,0,.5)'),this._$el.on('click',function(c){switch(c.preventDefault(),c.target.className){case'close':return a.Dialog.confirm('Close tab','Please confirm that you want to close this tab.',['Close','Cancel'],function(b){b&&(f.close(),a.Dialog.close())});case'pickcolor':return a.Dialog.request('Select a picking area color',p.clone(!0,!0),'Set',function(c){c.triggerHandler('set-color',[f.getPickingAreaColor()]),c.on('submit',function(d){d.preventDefault();var e=c.mkData();a.Dialog.wait(!1,'Setting picking area color');try{f.setPickingAreaColor('rgba('+e.red+','+e.green+','+e.blue+','+b(e.opacity)/100+')')}catch(b){return a.Dialog.fail(!1,d.message)}a.Dialog.close()})});}r.getInstance().activateTab(f)})}return _createClass(c,[{key:'activate',value:function activate(){this._$el.addClass('active')}},{key:'deactivate',value:function deactivate(){this._$el.removeClass('active')}},{key:'isActive',value:function isActive(){return this._$el.hasClass('active')}},{key:'getFileHash',value:function getFileHash(){return this._fileHash}},{key:'setImage',value:function setImage(b,c){var d=this;a(this._imgel).attr('src',b).on('load',function(){d._imgdata.size={width:d._imgel.width,height:d._imgel.height},c(!0)}).on('error',function(){c(!1)})}},{key:'setName',value:function setName(a){var b=a.split(/\./g);this.file.extension=b.pop(),this.file.name=b.join('.')}},{key:'getImageSize',value:function getImageSize(){return this._imgdata.size}},{key:'setImagePosition',value:function setImagePosition(a){this._imgdata.position=a}},{key:'getImagePosition',value:function getImagePosition(){return this._imgdata.position}},{key:'getElement',value:function getElement(){return this._$el}},{key:'placeArea',value:function placeArea(a,b){var c=2<arguments.length&&void 0!==arguments[2]?arguments[2]:!0,d=i.calcRectanglePoints(a.left,a.top,b);if(c)this.addPickingArea(new j(d));else{var e=i.calcCircleSlices(a.left,a.top,b);this.addPickingArea(new k(d,e))}}},{key:'addPickingArea',value:function addPickingArea(a){this._pickingAreas.push(a)}},{key:'getPickingAreas',value:function getPickingAreas(){return this._pickingAreas}},{key:'clearPickingAreas',value:function clearPickingAreas(){this._pickingAreas=[],this.isActive()&&s.getInstance().redrawPickingAreas()}},{key:'getPickingAreaColor',value:function getPickingAreaColor(){return this._pickingAreaColor}},{key:'setPickingAreaColor',value:function setPickingAreaColor(b){this._pickingAreaColor=new l(b),this._$pickAreaColorDisplay.html(a.mk('span').css('background-color',this._pickingAreaColor.toString())),this.isActive()&&s.getInstance().redrawPickingAreas()}},{key:'drawImage',value:function drawImage(){s.getInstance().getImageCanvasCtx().drawImage(this._imgel,0,0,this._imgdata.size.width,this._imgdata.size.height,0,0,this._imgdata.size.width,this._imgdata.size.height)}},{key:'close',value:function close(){r.getInstance().closeTab(this)}}]),c}(),r=function(){function b(){_classCallCheck(this,b),this._$tabbar=a('#tabbar'),this._activeTab=!1,this._tabStorage=[]}return _createClass(b,[{key:'newTab',value:function newTab(){for(var a=arguments.length,b=Array(a),c=0;c<a;c++)b[c]=arguments[c];var d=new(Function.prototype.bind.apply(q,[null].concat(b)));return this._tabStorage.push(d),this.updateTabs(),d}},{key:'activateTab',value:function activateTab(b){var c=this;b instanceof q&&(b=this.indexOf(b)),this._activeTab=!!(this._tabStorage[b]instanceof q)&&b,a.each(this._tabStorage,function(a,b){a===c._activeTab?b.activate():b.deactivate()}),!1!==this._activeTab&&s.getInstance().openTab(this._tabStorage[this._activeTab])}},{key:'indexOf',value:function indexOf(b){var c=parseInt(b.getElement().attr('data-ix'),10);if(isNaN(c)&&a.each(this._tabStorage,function(a,d){if(d===b)return c=a,!1}),isNaN(c))throw console.log(b),new Error('Could not find index of the tab logged above');return c}},{key:'updateTabs',value:function updateTabs(){var b=this;this._$tabbar.children().detach(),a.each(this._tabStorage,function(a,c){b._$tabbar.append(c.getElement().attr('data-ix',a))})}},{key:'getActiveTab',value:function getActiveTab(){return!1===this._activeTab?void 0:this._tabStorage[this._activeTab]}},{key:'getTabs',value:function getTabs(){return this._tabStorage}},{key:'hasTabs',value:function hasTabs(){return 0<this._tabStorage.length}},{key:'closeTab',value:function closeTab(a){var b=this.indexOf(a),c=this._tabStorage.length,d=1<c;d||(s.getInstance().clearImage(),m.getInstance().updateCloseActiveTab()),this._tabStorage.splice(b,1),d&&this.activateTab(Math.min(c-1,b)),this.updateTabs()}}],[{key:'getInstance',value:function getInstance(){return'undefined'==typeof d.tabbar&&(d.tabbar=new b),d.tabbar}}]),b}(),s=function(){function b(){var d=this;_classCallCheck(this,b),this._mousepos={top:NaN,left:NaN},this._zoomlevel=1,this._moveMode=!1,this._$picker=a('#picker'),this.updateWrapSize(),this._$imageOverlay=a.mk('canvas').attr('class','image-overlay'),this._$imageCanvas=a.mk('canvas').attr('class','image-element'),this._$mouseOverlay=a.mk('canvas').attr('class','mouse-overlay'),this._$placeArea=a.mk('button').attr({'class':'place-area typcn typcn-starburst','data-info':'Randomly place a new square picking area on the image (hold Alt to place rounded)'}).on('click',function(a){a.preventDefault();var b=d.getImageCanvasSize();d.placeArea({left:c(Math.random()*b.width),top:c(Math.random()*b.height)},45,!a.altKey)}),this._$clearAreas=a.mk('button').attr({'class':'place-area typcn typcn-delete','data-info':'Clear all picking areas'}).on('click',function(a){a.preventDefault();var b=r.getInstance().getActiveTab();b&&b.clearPickingAreas()}),this._$zoomin=a.mk('button').attr({'class':'zoom-in typcn typcn-zoom-in','data-info':'Zoom in (Alt+Scroll Up)'}).on('click',function(a,b){a.preventDefault(),d.setZoomLevel(d._zoomlevel*g.step,b)}),this._$zoomout=a.mk('button').attr({'class':'zoom-out typcn typcn-zoom-out','data-info':'Zoom out (Alt+Scroll Down)'}).on('click',function(a,b){a.preventDefault(),d.setZoomLevel(d._zoomlevel/g.step,b)}),this._$zoomfit=a.mk('button').attr({'class':'zoom-fit typcn typcn typcn-arrow-minimise','data-info':'Fit in view (Ctrl+0)'}).on('click',function(a){a.preventDefault(),d.setZoomFit()}),this._$zoomorig=a.mk('button').attr({'class':'zoom-orig typcn typcn typcn-zoom','data-info':'Original size (Ctrl+1)'}).on('click',function(a){a.preventDefault(),d.setZoomOriginal()}),this._$zoomperc=a.mk('span').attr({'class':'zoom-perc','data-info':'Current zoom level (Click to enter a custom value)',contenteditable:!0}).text('100%').on('keydown',function(b){if(a.isKey(f.Enter,b)){b.preventDefault();var c=parseFloat(d._$zoomperc.text());isNaN(c)||d.setZoomLevel(c/100),d.updateZoomLevelInputs()}}).on('mousedown',function(){d._$zoomperc.data('mousedown',!0)}).on('mouseup',function(){d._$zoomperc.data('mousedown',!1)}).on('click',function(){!0!==d._$zoomperc.data('focused')&&(d._$zoomperc.data('focused',!0),d._$zoomperc.select())}).on('dblclick',function(a){a.preventDefault(),d._$zoomperc.select()}).on('blur',function(){d._$zoomperc.data('mousedown')||d._$zoomperc.data('focused',!1),0===d._$zoomperc.html().trim().length&&d.updateZoomLevelInputs(),a.clearSelection()}),this._$actionTopLeft=a.mk('div').attr('class','actions actions-tl').append(a.mk('div').attr('class','editing-tools').append(this._$placeArea,this._$clearAreas),a.mk('div').attr('class','zoom-controls').append(this._$zoomin,this._$zoomout,this._$zoomfit,this._$zoomorig,this._$zoomperc)).on('mousedown',function(a){a.stopPropagation(),d._$zoomperc.triggerHandler('blur')}),this._$actionsBottomLeft=a.mk('div').attr('class','actions actions-bl').append('Unused panel').on('mousedown',function(a){a.stopPropagation(),d._$zoomperc.triggerHandler('blur')}),this._$loader=a.mk('div').attr('class','loader'),$w.on('resize',a.throttle(250,function(){d.resizeHandler()})),this._$picker.append(this._$actionTopLeft,this._$actionsBottomLeft,this._$mouseOverlay,this._$imageOverlay,this._$loader);var h,j;$body.on('mousemove',a.throttle(50,function(b){if(r.getInstance().getActiveTab()){var e=d.getWrapPosition(),f=d.getImagePosition(),g=d.getImageCanvasSize();if(d._mousepos.top=b.pageY-e.top,d._mousepos.left=b.pageX-e.left,d._mousepos.top<f.top||d._mousepos.top>f.top+g.height-1||d._mousepos.left<f.left||d._mousepos.left>f.left+g.width-1)d._mousepos.top=NaN,d._mousepos.left=NaN,n.getInstance().setColorAt();else{d._mousepos.top=c((d._mousepos.top-c(f.top))/d._zoomlevel),d._mousepos.left=c((d._mousepos.left-c(f.left))/d._zoomlevel);var k=d.getImageCanvasCtx().getImageData(d._mousepos.left,d._mousepos.top,1,1).data;n.getInstance().setColorAt(a.rgb2hex({r:k[0],g:k[1],b:k[2]}),a.roundTo(100*(k[3]/255),2)+'%')}if(n.getInstance().setPosition('mouse',d._mousepos),h&&j){var l={top:b.pageY,left:b.pageX},m=d.getWrapPosition(),o=i.snapPointToPixelGrid(h.top+(l.top-j.top)-m.top,d._zoomlevel),p=i.snapPointToPixelGrid(h.left+(l.left-j.left)-m.left,d._zoomlevel);d._$imageOverlay.add(d._$imageCanvas).add(d._$mouseOverlay).css({top:o,left:p}),d.updateZoomLevelInputs()}}})),$w.on('mousewheel',function(a){if(a.altKey){a.preventDefault();var b=d.getWrapPosition(),c={top:a.pageY-b.top,left:a.pageX-b.left};0<a.originalEvent.deltaY?d._$zoomout.trigger('click',[c]):d._$zoomin.trigger('click',[c])}}),this._$picker.on('mousewheel',function(a){if(!a.altKey){a.preventDefault();var b=d._wrapheight*(a.shiftKey?0.1:0.025)*Math.sign(a.originalEvent.wheelDelta);a.ctrlKey?d.move({left:'+='+b+'px'}):d.move({top:'+='+b+'px'})}}),$body.on('mousedown',function(b){r.getInstance().getActiveTab()&&a(b.target).is(d._$imageOverlay)&&d._$imageOverlay.hasClass('draggable')&&(b.preventDefault(),d._$imageOverlay.addClass('dragging'),h=d._$imageOverlay.offset(),j={top:b.pageY,left:b.pageX})}),$body.on('mouseup mouseleave blur',function(a){r.getInstance().getActiveTab()&&('mouseup'!==a.type||(h=void 0,j=void 0,d._$imageOverlay.removeClass('dragging')))})}return _createClass(b,[{key:'getTopLeft',value:function getTopLeft(a,b){var c=2<arguments.length&&void 0!==arguments[2]?arguments[2]:this.getWrapCenterPosition(),d=a.left,e=a.top,f=c.left,g=c.top;return{top:g+b*(e-g),left:f+b*(d-f)}}},{key:'getImageCanvasSize',value:function getImageCanvasSize(){return{width:this._$imageCanvas.width(),height:this._$imageCanvas.height()}}},{key:'getImagePosition',value:function getImagePosition(){var a=0<arguments.length&&void 0!==arguments[0]?arguments[0]:this._$imageCanvas.offset(),b=this.getWrapPosition();return{top:a.top-b.top,left:a.left-b.left}}},{key:'getImageCenterPosition',value:function getImageCenterPosition(a,b){var c=this.getWrapPosition();return{top:a.top-c.top+b.height/2,left:a.left-c.left+b.width/2}}},{key:'getWrapCenterPosition',value:function getWrapCenterPosition(){return{top:this._wrapheight/2,left:this._wrapwidth/2}}},{key:'getWrapPosition',value:function getWrapPosition(){var a=this._$picker.offset();return a.top-=(this._wrapheight-this._$picker.outerHeight())/2,a.left-=(this._wrapwidth-this._$picker.outerWidth())/2,a}},{key:'placeArea',value:function placeArea(a,b){var c=2<arguments.length&&void 0!==arguments[2]?arguments[2]:!0,d=r.getInstance().getActiveTab();d&&(d.placeArea(a,b,c),this.redrawPickingAreas())}},{key:'redrawPickingAreas',value:function redrawPickingAreas(){var b=r.getInstance().getActiveTab();if(b){this.clearImageOverlay();var c=this.getImageOverlayCtx();c.fillStyle=b.getPickingAreaColor().toString(),a.each(b.getPickingAreas(),function(b,d){d instanceof j?c.fillRect(d.boundingRect.topLeft.x,d.boundingRect.topLeft.y,d.boundingRect.sideLength,d.boundingRect.sideLength):d instanceof k&&a.each(d.slices,function(a,b){var e=d.boundingRect.topLeft.x+b.skip,f=d.boundingRect.topLeft.y+a;c.fillRect(e,f,b.length,1)})})}}},{key:'clearImageOverlay',value:function clearImageOverlay(){e(this._$imageOverlay[0])}},{key:'updateZoomLevelInputs',value:function updateZoomLevelInputs(){this._$zoomperc.text(a.roundTo(100*this._zoomlevel,2)+'%'),document.activeElement.blur(),this._$zoomout.attr('disabled',this._zoomlevel<=g.min),this._$zoomin.attr('disabled',this._zoomlevel>=g.max)}},{key:'setZoomLevel',value:function setZoomLevel(b,c){var d=r.getInstance().getActiveTab();if(d){var e,f,h=d.getImageSize(),i=a.rangeLimit(b,!1,g.min,g.max);i===this._zoomlevel?(e={width:this._$imageCanvas.width(),height:this._$imageCanvas.height()},f=this._zoomlevel):(e=a.scaleResize(h.width,h.height,{scale:i}),f=this._zoomlevel,this._zoomlevel=e.scale);var j=this.getTopLeft(this.getImagePosition(),i/f,c);this.move({top:j.top,left:j.left,width:e.width,height:e.height}),this.updateZoomLevelInputs()}}},{key:'setZoomFit',value:function setZoomFit(){var b=this;this._fitImageHandler(function(c){var d=b._wrapwidth>b._wrapheight,e=c.width===c.height,f=e?d:c.width>c.height,g=a.scaleResize(c.width,c.height,f?{height:b._wrapheight}:{width:b._wrapwidth});return d&&(g.width>b._wrapwidth?g=a.scaleResize(g.width,g.height,{width:b._wrapwidth}):g.height>b._wrapheight&&(g=a.scaleResize(g.width,g.height,{height:b._wrapheight}))),d||(g.height>b._wrapheight?g=a.scaleResize(g.width,g.height,{height:b._wrapheight}):g.width>b._wrapwidth&&(g=a.scaleResize(g.width,g.height,{width:b._wrapwidth}))),g})}},{key:'setZoomOriginal',value:function setZoomOriginal(){this._fitImageHandler(function(a){return{width:a.width,height:a.height,scale:1}})}},{key:'_fitImageHandler',value:function _fitImageHandler(a){var b=r.getInstance().getActiveTab();if(b){var c=b.getImageSize(),d=a(c),e=(this._wrapheight-d.height)/2,f=(this._wrapwidth-d.width)/2;this.move({top:e,left:f,width:d.width,height:d.height}),this._zoomlevel=d.scale,this.setZoomLevel(this._zoomlevel)}}},{key:'move',value:function move(a){var b=1<arguments.length&&void 0!==arguments[1]&&arguments[1],c=r.getInstance().getActiveTab();c&&(this._$imageOverlay.add(this._$imageCanvas).add(this._$mouseOverlay).css(a),!b&&c.setImagePosition({top:this._$imageOverlay.css('top'),left:this._$imageOverlay.css('left'),width:this._$imageOverlay.css('width'),height:this._$imageOverlay.css('height')}))}},{key:'updateWrapSize',value:function updateWrapSize(){this._wrapwidth=this._$picker.innerWidth(),this._wrapheight=this._$picker.innerHeight()}},{key:'resizeHandler',value:function resizeHandler(){this.updateWrapSize(),'number'==typeof this._zoomlevel&&this.setZoomLevel(this._zoomlevel),n.getInstance().setPosition('pickerCenter',this.getWrapCenterPosition())}},{key:'_setCanvasSize',value:function _setCanvasSize(a,b){this._$imageOverlay[0].width=this._$imageCanvas[0].width=a,this._$imageOverlay[0].height=this._$imageCanvas[0].height=b}},{key:'openImage',value:function openImage(b,c,d){var e=this;if(this._$picker.hasClass('loading'))throw new Error('The picker is already loading another image');this._$picker.addClass('loading'),n.getInstance().setInfo();var f,g=CryptoJS.MD5(b).toString(),h=r.getInstance().getTabs();if(a.each(h,function(a,b){if(b.getFileHash()===g)return f=b,!1}),'undefined'!=typeof f)return this._$picker.removeClass('loading'),r.getInstance().activateTab(f),void d(!0);var i=r.getInstance().newTab(c,g);i.setImage(b,function(b){e._$picker.removeClass('loading'),b?r.getInstance().activateTab(i):a.Dialog.fail('Oh no','The provided image could not be loaded. This is usually caused by attempting to open a file that is, in fact, not an image.'),d(b)})}},{key:'openTab',value:function openTab(a){var b=a.getImageSize();if(!b)throw new Error('Attempt to open a tab without an image');this._$imageCanvas.appendTo(this._$picker),this._setCanvasSize(b.width,b.height),a.drawImage();var c=a.getImagePosition();c?this.move(c,!0):this.setZoomFit(),this.redrawPickingAreas()}},{key:'clearImage',value:function clearImage(){r.getInstance().getActiveTab()&&(this._$imageCanvas.detach(),e(this._$imageCanvas[0]),e(this._$imageOverlay[0]),n.getInstance().setColorAt(),n.getInstance().setPosition('mouse'),this._zoomlevel=1,this.updateZoomLevelInputs(),a.Dialog.close())}},{key:'moveMode',value:function moveMode(a){a&&!this._moveMode?(this._moveMode=!0,this._$imageOverlay.addClass('draggable')):!a&&this._moveMode&&(this._moveMode=!1,this._$imageOverlay.removeClass('draggable dragging'))}},{key:'getImageCanvasCtx',value:function getImageCanvasCtx(){return this._$imageCanvas[0].getContext('2d')}},{key:'getImageOverlayCtx',value:function getImageOverlayCtx(){return this._$imageOverlay[0].getContext('2d')}},{key:'getMouseOverlayCtx',value:function getMouseOverlayCtx(){return this._$mouseOverlay[0].getContext('2d')}}],[{key:'getInstance',value:function getInstance(){return'undefined'==typeof d.picker&&(d.picker=new b),d.picker}}]),b}();m.getInstance(),n.getInstance(),r.getInstance(),s.getInstance(),a(document).on('keydown',function(a){var b=a.target.tagName.toLowerCase();if(('input'!==b||'file'===a.target.type)&&'textarea'!==b&&null===a.target.getAttribute('contenteditable')){switch(a.keyCode){case f[0]:if(!a.ctrlKey||a.altKey)return;s.getInstance().setZoomFit();break;case f[1]:if(!a.ctrlKey||a.altKey)return;s.getInstance().setZoomOriginal();break;case f.Space:if(a.ctrlKey||a.altKey)return;s.getInstance().moveMode(!0);break;default:return;}a.preventDefault()}}),a(document).on('keyup',function(a){var b=a.target.tagName.toLowerCase();if(('input'!==b||'file'===a.target.type)&&'textarea'!==b&&null===a.target.getAttribute('contenteditable')){switch(a.keyCode){case f.Space:if(a.ctrlKey||a.altKey)return;s.getInstance().moveMode(!1);break;case f.Alt:break;default:return;}a.preventDefault()}}),a(document).on('paste','[contenteditable]',function(b){var c='',d=a(this);return b.clipboardData?c=b.clipboardData.getData('text/plain'):window.clipboardData?c=window.clipboardData.getData('Text'):b.originalEvent.clipboardData&&(c=a.mk('div').text(b.originalEvent.clipboardData.getData('text'))),document.queryCommandSupported('insertText')?(document.execCommand('insertHTML',!1,a(c).html()),!1):void(d.find('*').each(function(){a(this).addClass('within')}),setTimeout(function(){d.find('*').each(function(){a(this).not('.within').contents().unwrap()})},1))}),$body.on('mouseenter','[data-info]',function(){n.getInstance().setInfo(a(this).attr('data-info'))}).on('mouseleave','[data-info]',function(){n.getInstance().setInfo()}).on('dragover dragend',function(a){a.stopPropagation(),a.preventDefault()}).on('drop',function(b){b.preventDefault();var c=b.originalEvent.dataTransfer.files;if(0!==c.length){var d=1===c.length?'':'s';a.Dialog.wait('Drag and drop','Reading dropped file'+d+', please wait');var e=0;(function b(){return'undefined'==typeof c[e]?void a.Dialog.close():void m.getInstance().handleFileOpen(c[e],function(c){return c?(e++,b()):void a.Dialog.fail('Drag and drop','Failed to read file #'+e+', aborting')})})()}}),window.Plugin=d})(jQuery);
//# sourceMappingURL=/js/min/colorpicker.js.map
