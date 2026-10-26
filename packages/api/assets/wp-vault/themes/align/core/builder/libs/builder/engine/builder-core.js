/**
 * Align Builder Engine - Core Helpers & Template Functions
 * @package Align
 */

/*
Copyright 2017 Ziadin Givan

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

https://github.com/givanz/VvvebJs
*/


// Simple JavaScript Templating and buildParams
// John Resig - https://johnresig.com/ - MIT Licensed
(function(){
  let cache = {};
  let startTag = "{%";
  let endTag = "%}";
  let re1 = new RegExp(`((^|${endTag})[^\t]*)'`,"g");
  let re2 = new RegExp(`\t=(.*?)${endTag}`,"g");
  
  this.tmpl = function tmpl(str, data){
    // Figure out if we're getting a template, or if we need to
    // load the template - and be sure to cache the result.
	let fn = /^[-a-zA-Z0-9]+$/.test(str) ?
      cache[str] = cache[str] ||
        tmpl(document.getElementById(str).innerHTML) :
              
      // Generate a reusable function that will serve as a template
      // generator (and which will be cached).
      new Function("obj",
        "let p=[],print=function(){p.push.apply(p,arguments);};" +
         
        // Introduce the data as local variables using with(){}
        "with(obj){p.push('" +
         
        // Convert the template into pure JavaScript
        str
          .replace(/[\r\t\n]/g, " ")
          .split(startTag).join("\t")
          .replace(re1, "$1\r")
          .replace(re2, "',$1,'")
          .split("\t").join("');")
          .split(endTag).join("p.push('")
          .split("\r").join("\\'")
      + "');}return p.join('');");
    // Provide some basic currying to the user
    return data ? fn( data ) : fn;
  };
})();


function buildParams( prefix, obj,  add ) {
	let rbracket = /\[\]$/;

	if ( Array.isArray( obj ) ) {

		// Serialize array item.
		for(const key in obj) {
			let v = obj[key];
			if ( rbracket.test( prefix ) ) {

				// Treat each array item as a scalar.
				add( prefix, v );

			} else {

				// Item is non-scalar (array or object), encode its numeric index.
				buildParams(
					prefix + "[" + ( typeof v === "object" && v != null ? key : "" ) + "]",
					v,
					add
				);
			}
		}

	} else if ( typeof obj === "object" ) {

		// Serialize object item.
		for (const name in obj ) {
			buildParams( prefix + "[" + name + "]", obj[ name ],  add );
		}

	} else {

		// Serialize scalar item.
		add( prefix, obj );
	}
}

// Serialize an array of form elements or a set of
// key/values into a query string
function nestedFormData( a ) {
	let prefix,
		s = [],
		add = function( key, valueOrFunction ) {

			// If value is a function, invoke it and use its return value
			let value = typeof valueOrFunction === "function" ?
				valueOrFunction() :
				valueOrFunction;

			s[ s.length ] = encodeURIComponent( key ) + "=" +
				encodeURIComponent( value == null ? "" : value );
		};

	if ( a == null ) {
		return "";
	}

	// If an array was passed in, assume that it is an array of form elements.
	if ( Array.isArray( a ) || ( typeof a === "object" && a !== null ) ) {

		// Serialize the form elements
		for(const key in a) {
			let v = a[key];
			add( key, v );
		}

	} else {

		// If traditional, encode the "old" way (the way 1.3.2 or older
		// did it), otherwise encode params recursively.
		for (const prefix in a ) {
			buildParams( prefix, a[ prefix ], add );
		}
	}

	// Return the resulting serialization
	return s.join( "&" );
};


let delay = (function(){
  let timer = 0;
  return function(callback, ms){
    clearTimeout (timer);
    timer = setTimeout(callback, ms);
  };
})();

function isElement(obj){
   return (typeof obj==="object") &&
      (obj.nodeType===1) && (typeof obj.style === "object") &&
      (typeof obj.ownerDocument ==="object")/* && obj.tagName != "BODY"*/;
}

function generateElements(html) {
  const template = document.createElement('template');
  template.innerHTML = html.trim();
  return template.content.children;
}

function offset(el) {
  if (!el || !el.getBoundingClientRect) return { top: 0, left: 0 };
  let box = el.getBoundingClientRect();
  let win = el.ownerDocument ? (el.ownerDocument.defaultView || window) : window;
  let docElem = el.ownerDocument ? (el.ownerDocument.documentElement || el.ownerDocument.body || document.documentElement) : document.documentElement;
  let scrollTop = (win.pageYOffset !== undefined) ? win.pageYOffset : ((win.scrollY !== undefined) ? win.scrollY : (docElem.scrollTop || 0));
  let scrollLeft = (win.pageXOffset !== undefined) ? win.pageXOffset : ((win.scrollX !== undefined) ? win.scrollX : (docElem.scrollLeft || 0));
  return {
    top: box.top + scrollTop - (docElem.clientTop || 0),
    left: box.left + scrollLeft - (docElem.clientLeft || 0)
  };
}

function scrollToNode(node, behavior = "smooth") {
  if (!node || !node.getBoundingClientRect) return;
  let win = node.ownerDocument ? (node.ownerDocument.defaultView || window) : window;
  let doc = node.ownerDocument || document;
  let docEl = doc.documentElement || doc.body;
  if (!win || !docEl) return;
  
  let rect = node.getBoundingClientRect();
  let currentScroll = win.pageYOffset || docEl.scrollTop || 0;
  let targetScroll = currentScroll + rect.top - (win.innerHeight / 2) + (rect.height / 2);
  
  try {
    win.scrollTo({
      top: Math.max(0, targetScroll),
      behavior: behavior
    });
  } catch (e) {
    docEl.scrollTop = Math.max(0, targetScroll);
  }
}

if (Vvveb === undefined) var Vvveb = {};
Vvveb.scrollToNode = scrollToNode;

Vvveb.defaultComponent = "_base";
Vvveb.preservePropertySections = true;
//icon = use component icon when dragging | html = use component html to create draggable element
Vvveb.dragIcon = 'icon';
//if empty the html of the component is used to view dropping in real time but for large elements it can jump around for this you can set a html placeholder with this option
Vvveb.dragElementStyle = "background:limegreen;width:100%;height:3px;border:1px solid limegreen;box-shadow:0px 0px 2px 1px rgba(0,0,0,0.14);overflow:hidden;";
Vvveb.dragHtml = '<div style="' + Vvveb.dragElementStyle + '"></div>';

let scriptSrc = document.currentScript ? document.currentScript.src : '';
if (scriptSrc.includes('/engine/')) {
    Vvveb.baseUrl = scriptSrc.replace(/\/engine\/[^\/]*?\.js.*$/, '/');
} else if (scriptSrc) {
    Vvveb.baseUrl = scriptSrc.replace(/[^\/]*?\.js.*$/, '');
} else {
    Vvveb.baseUrl = 'libs/builder/';
}
Vvveb.imgBaseUrl = Vvveb.baseUrl + 'icons/';

Vvveb.ComponentsGroup = {};
Vvveb.SectionsGroup = {};
Vvveb.BlocksGroup = {};
Vvveb.StylesGroup = {};
