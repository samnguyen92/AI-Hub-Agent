/**
 * Align Builder Bootstrap 5 - Grid Container & Buttons
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

https://github.com/givanz/Vvvebjs
*/

Vvveb.ComponentsGroup['Bootstrap 5'] =
["html/container", "html/gridrow", "html/btn", "html/btn-link", "html/buttongroup", "html/buttontoolbar", "html/alert", "html/card", "html/listgroup", "html/badge", "html/progress", "html/navbar", "html/breadcrumbs", "html/pagination"];


Vvveb.Components.extend("_base", "html/container", {
    classes: ["container", "container-fluid"],
    image: "icons/container.svg",
    html: '<div class="container" style="min-height:150px;"><div class="m-5">Container</div></div>',
    name: "Container",
    properties: [
     {
        name: "Type",
        key: "type",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["container", "container-fluid"],
        data: {
            options: [{
                value: "container",
                text: "Default"
            },{
                value: "container-fluid",
                text: "Fluid"
            }]
        }
    },{
        name: "Background",
        key: "background",
		htmlAttr: "class",
        validValues: bgcolorClasses,
        inputtype: SelectInput,
        data: {
            options: bgcolorSelectOptions
        }
    },{
        name: "Background Color",
        key: "background-color",
		htmlAttr: "style",
        inputtype: ColorInput,
    },{
        name: "Text Color",
        key: "color",
		htmlAttr: "style",
        inputtype: ColorInput,
    }],
});

Vvveb.Components.extend("html/link", "html/btn", {
    classes: ["btn"],
    nodes: null,
    name: "Button",
    image: "icons/button.svg",
    html: '<a class="btn btn-primary">Primary</a>',
    properties: [{
        name: "Background",
        key: "background",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["btn-default", "btn-primary", "btn-info", "btn-success", "btn-warning", "btn-info", "btn-light", "btn-dark", "btn-outline-primary", "btn-outline-info", "btn-outline-success", "btn-outline-warning", "btn-outline-info", "btn-outline-light", "btn-outline-dark", "btn-link"],
        data: {
            options: [{
                value: "btn-default",
                text: "Default"
            },{
                value: "btn-primary",
                text: "Primary"
            },{
                value: "btn btn-info",
                text: "Info"
            },{
                value: "btn-success",
                text: "Success"
            },{
                value: "btn-warning",
                text: "Warning"
            },{
                value: "btn-info",
                text: "Info"
            },{
                value: "btn-light",
                text: "Light"
            },{
                value: "btn-dark",
                text: "Dark"
            },{
                value: "btn-outline-primary",
                text: "Primary outline"
            },{
                value: "btn btn-outline-info",
                text: "Info outline"
            },{
                value: "btn-outline-success",
                text: "Success outline"
            },{
                value: "btn-outline-warning",
                text: "Warning outline"
            },{
                value: "btn-outline-info",
                text: "Info outline"
            },{
                value: "btn-outline-light",
                text: "Light outline"
            },{
                value: "btn-outline-dark",
                text: "Dark outline"
            },{
                value: "btn-link",
                text: "Link"
            }]
        }
    },{
        name: "Size",
        key: "size",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["btn-lg", "btn-sm"],
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "btn-lg",
                text: "Large"
            },{
                value: "btn-sm",
                text: "Small"
            }]
        }
   	},{
        name: "Autofocus",
        key: "autofocus",
        htmlAttr: "autofocus",
        inputtype: ToggleInput,
        data: {
            on: "autofocus",
            off: ""
        },
        init: function(element) {
            return element.hasAttribute("autofocus");
        },
        inline: true,
        col: 6,
   	},{
        name: "Disabled",
        key: "disabled",
        htmlAttr: "class",
        inline: true,
        col: 6,
        inputtype: ToggleInput,
        validValues: ["disabled"],
        data: {
            on: "disabled",
            off: ""
        }
    },{
	key: "link_options",
        inputtype: SectionInput,
        name:false,
        data: {header:"Link"},
    }]
});
	
Vvveb.Components.extend("_base", "html/buttongroup", {
    classes: ["btn-group"],
    name: "Button Group",
    image: "icons/button_group.svg",
    html: '<div class="btn-group" role="group" aria-label="Basic example"><button type="button" class="btn btn-secondary">Left</button><button type="button" class="btn btn-secondary">Middle</button> <button type="button" class="btn btn-secondary">Right</button></div>',
	properties: [{
	    name: "Size",
        key: "size",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["btn-group-lg", "btn-group-sm"],
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "btn-group-lg",
                text: "Large"
            },{
                value: "btn-group-sm",
                text: "Small"
            }]
        }
    },{
	    name: "Alignment",
        key: "alignment",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["btn-group", "btn-group-vertical"],
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "btn-group",
                text: "Horizontal"
            },{
                value: "btn-group-vertical",
                text: "Vertical"
            }]
        }
    }]
});
Vvveb.Components.extend("_base", "html/buttontoolbar", {
    classes: ["btn-toolbar"],
    name: "Button Toolbar",
    image: "icons/button_toolbar.svg",
    html: '<div class="btn-toolbar" role="toolbar" aria-label="Toolbar with button groups">\
		  <div class="btn-group me-2" role="group" aria-label="First group">\
			<button type="button" class="btn btn-secondary">1</button>\
			<button type="button" class="btn btn-secondary">2</button>\
			<button type="button" class="btn btn-secondary">3</button>\
			<button type="button" class="btn btn-secondary">4</button>\
		  </div>\
		  <div class="btn-group me-2" role="group" aria-label="Second group">\
			<button type="button" class="btn btn-secondary">5</button>\
			<button type="button" class="btn btn-secondary">6</button>\
			<button type="button" class="btn btn-secondary">7</button>\
		  </div>\
		  <div class="btn-group" role="group" aria-label="Third group">\
			<button type="button" class="btn btn-secondary">8</button>\
		  </div>\
		</div>'
});
