/**
 * Align Builder Widgets - Google Maps & OpenStreetMap Widgets
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

Vvveb.ComponentsGroup['Widgets'] = ["widgets/googlemaps", "widgets/embed-video", "widgets/chartjs", "widgets/lottie",/* "widgets/facebookpage", */"widgets/paypal", /*"widgets/instagram",*/ "widgets/twitter", "widgets/openstreetmap"/*, "widgets/facebookcomments"*/];

Vvveb.Components.extend("_base", "widgets/googlemaps", {
    name: "Google Maps",
    attributes: ["data-component-maps"],
    image: "icons/map.svg",
    dragHtml: '<img src="' + Vvveb.baseUrl + 'icons/maps.png">',
    html: '<div data-component-maps><iframe frameborder="0" src="https://maps.google.com/maps?q=Bucharest&z=15&t=q&key=&output=embed" width="100%" height="100%" style="width:100%;height:100%;left:0px"></iframe></div>',
    resizable:true,//show select box resize handlers
    resizeMode:"css",
    
    
    //url parameters
    z:3, //zoom
    q:'Paris',//location
    t: 'q', //map type q = roadmap, w = satellite
    key: '',
    
	init: function (node) {
		let iframe = node.querySelector('iframe');
		let url = new URL(iframe.getAttribute("src"));
		let params = new URLSearchParams(url.search);
		
		this.z = params.get("z");
		this.q = params.get("q");
		this.t = params.get("t");
		this.key = params.get("key");

		document.querySelector(".component-properties input[name=z]").value = this.z;
		document.querySelector(".component-properties input[name=q]").value = this.q;
		document.querySelector(".component-properties select[name=t]").value = this.t;
		document.querySelector(".component-properties input[name=key]").value = this.key;
	},
	    
    onChange: function (node, property, value) {
		map_iframe = node.querySelector('iframe');
		
		this[property.key] = value;
		
		mapurl = 'https://maps.google.com/maps?q=' + this.q + '&z=' + this.z + '&t=' + this.t + '&output=embed';
		
		map_iframe.setAttribute("src",mapurl);
		
		return node;
	},

    properties: [{
        name: "Address",
        key: "q",
        inputtype: TextInput
    },{
        name: "Map type",
        key: "t",
        inputtype: SelectInput,
        data:{
			options: [{
                value: "q",
                text: "Roadmap"
            },{
                value: "w",
                text: "Satellite"
            }]
       },
    },{
        name: "Zoom",
        key: "z",
        inputtype: RangeInput,
        data:{
			max: 20, //max zoom level
			min:1,
			step:1
		}
    },{
        name: "Key",
        key: "key",
        inputtype: TextInput
	}]
});

Vvveb.Components.extend("_base", "widgets/openstreetmap", {
    name: "Open Street Map",
    attributes: ["data-component-openstreetmap"],
    image: "icons/map.svg",
    dragHtml: '<img src="' + Vvveb.baseUrl + 'icons/maps.png">',
    html: `<div data-component-openstreetmap><iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=-62.04673002474011%2C16.95487694424327%2C-61.60521696321666%2C17.196751341562923&layer=mapnik"></iframe></div>`,
    resizable:true,//show select box resize handlers
    resizeMode:"css",
    
    
    //url parameters
    bbox:'',//location
    layer: 'mapnik', //map type
    
	init: function (node) {
		let iframe = node.querySelector('iframe');
		let url = new URL(iframe.getAttribute("src"));
		let params = new URLSearchParams(url.search);
		
		this.bbox = params.get("bbox");
		this.layer = params.get("layer");

		document.querySelector(".component-properties input[name=bbox]").value = this.bbox;
		document.querySelector(".component-properties input[name=layer]").value = this.layer;
	},
	    
    onChange: function (node, property, value) {
		map_iframe = node.querySelector('iframe');
		
		this[property.key] = value;
		
		mapurl = 'https://www.openstreetmap.org/export/embed.html?bbox=' + this.bbox + '&layer=' + this.layer;
		
		map_iframe.setAttribute("src",mapurl);
		
		return node;
	},

    properties: [{
        name: "Map",
        key: "bbox",
        inputtype: TextInput
/*    },{
        name: "Layer",
        key: "layer",
        inputtype: SelectInput,
        data:{
			options: [{
                value: "",
                text: "Default"
            },{
                value: "Y",
                text: "CyclOSM"
            },{
                value: "C",
                text: "Cycle Map"
            },{
                value: "T",
                text: "Transport Map"
            }]
       }*/
	}]
});
