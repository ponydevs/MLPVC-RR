"use strict";/*! @source http://purl.eligrey.com/github/canvas-toBlob.js/blob/master/canvas-toBlob.js */
!function(t){var o,e=t.Uint8Array,n=t.HTMLCanvasElement,i=n&&n.prototype,s=/\s*;\s*base64\s*(?:;|$)/i,a="toDataURL",l=function(t){for(var n,i,s=t.length,a=new e(s/4*3|0),l=0,b=0,r=[0,0],d=0,f=0;s--;)i=t.charCodeAt(l++),255!==(n=o[i-43])&&void 0!==n&&(r[1]=r[0],r[0]=i,f=f<<6|n,4===++d&&(a[b++]=f>>>16,61!==r[1]&&(a[b++]=f>>>8),61!==r[0]&&(a[b++]=f),d=0));return a};e&&(o=new e([62,-1,-1,-1,63,52,53,54,55,56,57,58,59,60,61,-1,-1,-1,0,-1,-1,-1,0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,-1,-1,-1,-1,-1,-1,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51])),!n||i.toBlob&&i.toBlobHD||(i.toBlob||(i.toBlob=function(t,o){if(o||(o="image/png"),this.mozGetAsFile)t(this.mozGetAsFile("canvas",o));else if(this.msToBlob&&/^\s*image\/png\s*(?:$|;)/i.test(o))t(this.msToBlob());else{var n,i=Array.prototype.slice.call(arguments,1),b=this[a].apply(this,i),r=b.indexOf(","),d=b.substring(r+1),f=s.test(b.substring(0,r));Blob.fake?((n=new Blob).encoding=f?"base64":"URI",n.data=d,n.size=d.length):e&&(n=f?new Blob([l(d)],{type:o}):new Blob([decodeURIComponent(d)],{type:o})),t(n)}}),!i.toBlobHD&&i.toDataURLHD?i.toBlobHD=function(){a="toDataURLHD";var t=this.toBlob();return a="toDataURL",t}:i.toBlobHD=i.toBlob)}("undefined"!=typeof self&&self||"undefined"!=typeof window&&window||(void 0).content||void 0);
//# sourceMappingURL=/js/min/canvas-toBlob.js.map