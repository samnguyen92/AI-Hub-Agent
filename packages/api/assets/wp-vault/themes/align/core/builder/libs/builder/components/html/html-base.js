/**
 * Align Builder HTML Components - Base Containers, Headings & Spans
 * @package Align
 */

Vvveb.ComponentsGroup['Base'] =
["html/div", "html/heading", "html/paragraph", "html/span", "html/link", "html/image", "html/button", "html/hr", "html/form", "html/textinput", "html/textareainput", "html/selectinput"/*, "html/fileinput"*/, "html/checkbox", "html/radiobutton", "html/blockquote", "html/list", "html/table", "html/preformatted", "html/audio", "html/video","html/iframe"];

Vvveb.Components.extend("_base", "html/div", {
    nodes: ["div"],
    name: "Div",
    image: "icons/container.svg",
    html: '<div></div>',
    properties: []
});

Vvveb.Components.extend("_base", "html/span", {
    nodes: ["span"],
    name: "Span",
    image: "icons/paragraph.svg",
    html: '<span>Text</span>',
    properties: [{
        name: "Text",
        key: "innerHTML",
        inline: false,
        htmlAttr: "innerHTML",
        inputtype: TextareaInput,
        childlessOnly: true,
        filter: function(element) {
            return !element || !element.children || element.children.length === 0;
        }
    }]
});

Vvveb.Components.extend("_base", "html/heading", {
    image: "icons/heading.svg",
    name: "Heading",
    nodes: ["h1", "h2","h3", "h4","h5","h6"],
    html: "<h1>Heading</h1>",
    
	properties: [{
		name: "Text",
		key: "innerHTML",
		inline:false,
		sort:1,
		htmlAttr: "innerHTML",
		inputtype: TextareaInput,
		childlessOnly: true,
		filter: function(element) {
			return !element || !element.children || element.children.length === 0;
		}
	}, {
        name: "Size",
        key: "size",
        sort:2,
        inputtype: SelectInput,
        
        onChange: function(node, value) {
			return changeNodeName(node, "h" + value);
		},	
			
        init: function(node) {
            let regex;
            regex = /H(\d)/.exec(node.nodeName);
            if (regex && regex[1]) {
                return regex[1];
            }
            return 1;
        },
        
        data:{
			options: [{
                value: "1",
                text: "Heading 1"
            }, {
                value: "2",
                text: "Heading 2"
            }, {
                value: "3",
                text: "Heading 3"
            }, {
                value: "4",
                text: "Heading 4"
            }, {
                value: "5",
                text: "Heading 5"
            }, {
                value: "6",
                text: "Heading 6"
            }]
		}
   	}]
});    


let linkComponentProperties = [
/*	{
		name: "Text",
		key: "text",
		sort:1,
		htmlAttr: "innerText",
		inputtype: TextInput
	},*/
	{
		name: "Url",
		key: "href",
		sort:2,
		htmlAttr: "href",
		inputtype: LinkInput
	},{
		name: "Rel",
		key: "rel",
		sort:3,
		htmlAttr: "rel",
		inputtype: TextInput
   	},{
		name: "Text",
		key: "innerHTML",
		sort: 3,
		htmlAttr: "innerHTML",
		inputtype: TextareaInput,
		childlessOnly: true,
		filter: function(element) {
			return !element || !element.children || element.children.length === 0;
		}
	}, {
		name: "Target",
		key: "target",
		sort:4,
		htmlAttr: "target",
		inputtype: SelectInput,
		data:{
			options: [{
				value: "",
				text: ""
			}, {
				value: "_blank",
				text: "Blank"
			}, {
				value: "_parent",
				text: "Parent"
			}, {
				value: "_self",
				text: "Self"
			}, {
				value: "_top",
				text: "Top"
			}]
	   },
	}, {
		name: "Download",
		sort:5,
		key: "download",
		htmlAttr: "download",
		inputtype: CheckboxInput,
}];

Vvveb.Components.extend("_base", "html/link", {
    nodes: ["a"],
    name: "Link",
    html: '<a href="#" rel="noopener">Link Text</a>',
	image: "icons/link.svg",
    properties: linkComponentProperties
});
