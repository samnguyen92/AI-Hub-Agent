/**
 * Align Builder Section - Layout & Container Properties
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

// Replace broken relative media path with placeholder URL
let bgVideoTemplate = '<video playsinline loop muted autoplay src="../../media/sample.webm" poster="../../media/sample.webp"><video>';
let bgYoutubeTemplate = '<div class="iframe-container"><iframe src="https://www.youtube.com/embed/C6fOoy7Se_4?autoplay=1&loop=1&playsinline=1&controls=0&mute=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen=""></iframe></div>';
let bgImageTemplate = '<img src="https://picsum.photos/200/300">';
let defaultSeparatorSvg = '<svg id="Layer_1" data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 41" width="100%" height="300" fill="var(--bs-body-bg)" preserveAspectRatio="none"><defs><style>.cls-1{fill:inherit}</style></defs><title>rough-edges-bottom</title><path class="cls-1" d="M0,185l125-26,33,17,58-12s54,19,55,19,50-11,50-11l56,6,60-8,63,15v15H0Z" transform="translate(0 -159)"/></svg>';

let section_sort = 1;

let SectionContent = [{
        name: "Label",
        key: "label",
        sort: section_sort++,
        htmlAttr: "aria-label",
        inputtype: TextInput
    },{
        name: "Container width",
        key: "container-width",
        sort: section_sort++,
		child:":scope > .container, :scope > .container-fluid",
        htmlAttr: "class",
        validValues: ["container", "container-fluid"],
        inputtype: RadioButtonInput,
        data: {
            extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
				value: "container",
				icon:"la la-box",
				text: "Boxed",
				title: "Boxed"
			},{
				value: "container-fluid",
				icon:"la la-arrows-alt-h",
				title: "Full",
				text: "Full"
            }]
         }
	},{
        name: "Container height",
        key: "container-height",
        sort: section_sort++,
        child:":scope > .container:first-child, :scope > .container-fluid:first-child",
        htmlAttr: "class",
        validValues: ["", "vh-100"],
        inputtype: RadioButtonInput,
        data: {
				extraclass:"btn-group-sm btn-group-fullwidth",
				options: [{
				value: "container",
				icon:"la la-expand",
				text: "Auto",
				title: "Auto",
				checked:true,
            },{
				value: "vh-100",
				icon:"la la-arrows-alt-v",
				title: "Full",
				text: "Full"
            }]
         }
	}
];
