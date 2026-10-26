/**
 * Align Builder HTML Components - Buttons, Paragraphs, Quotes, Lists & Preformatted
 * @package Align
 */

Vvveb.Components.extend("_base", "html/button", {
    nodes: ["button"],
    name: "Html Button",
    image: "icons/button.svg",
    html: '<button>Button</button>',
    properties: [{
        name: "Text",
        key: "text",
        htmlAttr: "innerHTML",
        inputtype: TextInput,
        childlessOnly: true,
        filter: function(element) {
            return !element || !element.children || element.children.length === 0;
        }
    }, {
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput
    }, {
        name: "Type",
        key: "type",
		htmlAttr: "type",
        inputtype: SelectInput,
        data: {
			options: [{
				value: "button",
				text: "button"
			}, {	
				value: "reset",
				text: "reset"
			}, {
				value: "submit",
				text: "submit"
			}],
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
        htmlAttr: "disabled",
        inputtype: ToggleInput,
        data: {
            on: "disabled",
            off: ""
        },
        init: function(element) {
            return element.hasAttribute("disabled");
        },
        inline: true,
        col: 6,
	}]
});

Vvveb.Components.extend("_base", "html/paragraph", {
    nodes: ["p"],
    name: "Paragraph",
    image: "icons/paragraph.svg",
    html: '<p>Lorem ipsum</p>',
    properties: [{
		name: "Text",
		key: "innerHTML",
		sort:1,
		inline:false,
		htmlAttr: "innerHTML",
		inputtype: TextareaInput,
		childlessOnly: true,
		filter: function(element) {
			return !element || !element.children || element.children.length === 0;
		}
	}, {
        name: "Text align",
        key: "p-text-align",
        htmlAttr: "class",
        sort:2,
        inline:false,
        validValues: ["", "text-start", "text-center", "text-end"],
        inputtype: RadioButtonInput,
        data: {
            extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "",
                icon:"la la-times",
                //text: "None",
                title: "None",
                checked:true,
            }, {
                value: "text-start",
                //text: "Left",
                title: "text-start",
                icon:"la la-align-left",
                checked:false,
            }, {
                value: "text-center",
                //text: "Center",
                title: "Center",
                icon:"la la-align-center",
                checked:false,
            }, {
                value: "text-end",
                //text: "Right",
                title: "Right",
                icon:"la la-align-right",
                checked:false,
            }],
        },
	}]
});

Vvveb.Components.extend("_base", "html/blockquote", {
    nodes: ["blockquote"],
    name: "Blockquote",
	image: "icons/blockquote.svg",
	html: `<blockquote cite="https://en.wikipedia.org/wiki/Marcus_Aurelius">
				<p>Today I shall be meeting with interference, ingratitude, insolence, disloyalty, ill-will, and selfishness all of them due to the offenders' ignorance of what is good or evil.</p>
				<cite class="small">
					<a href="https://en.wikipedia.org/wiki/Marcus_Aurelius" class="text-decoration-none" target="blank">Marcus Aurelius</a>
				</cite>	
			</blockquote>`,
    properties: [{
        name: "Align",
        key: "align",
        htmlAttr: "class",
        inline:false,
        validValues: ["", "align-left", "align-center", "align-right"],
        inputtype: RadioButtonInput,
        data: {
            extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "",
                icon:"la la-times",
                //text: "None",
                title: "None",
                checked:true,
            }, {
                value: "align-left",
                //text: "Left",
                title: "text-start",
                icon:"la la-align-left",
                checked:false,
            }, {
                value: "align-center",
                //text: "Center",
                title: "Center",
                icon:"la la-align-center",
                checked:false,
            }, {
                value: "align-right",
                //text: "Right",
                title: "Right",
                icon:"la la-align-right",
                checked:false,
            }],
        },
	}, {
        name: "Cite",
        key: "cite",
        inline:false,
        htmlAttr: "cite",
        inputtype: TextInput,
    }]
});

Vvveb.Components.extend("_base", "html/list-item", {
    nodes: ["li"],
    name: "List item",
	image: "icons/list.svg",
	html: `<li>List item</li>`,
    properties: [{
        name: "Text",
        key: "innerHTML",
        sort: 1,
        inline: false,
        htmlAttr: "innerHTML",
        inputtype: TextareaInput,
        childlessOnly: true,
        filter: function(element) {
            return !element || !element.children || element.children.length === 0;
        }
    }]
});

Vvveb.Components.extend("_base", "html/list", {
    nodes: ["ul", "ol"],
    name: "List",
	image: "icons/list.svg",
	html: `<ul>
				<li>Begin with the possible; begin with one step.</li>
				<li>Never think of results, just do!</li>
				<li>Patience is the mother of will.</li>
				<li>Man must use what he has, not hope for what is not.</li>
				<li>Only super-efforts count.</li>
			</ul>`,
	properties: [{
        name: "Type",
        key: "type",
        inputtype: SelectInput,
        
        onChange: function(node, value) {
			return changeNodeName(node, value);
		},	
			
        init: function(node) {
            return node.nodeName.toLowerCase()
        },
        
        data:{
            options: [{
                value: "ul",
                text: "Unordered"
            }, {
                value: "ol",
                text: "Ordered"
            }]
       },
    },{
		name: "Items",
        key: "items",
        inputtype: ListInput,
		htmlAttr:"data-slides-per-view",
		inline:true,
		data: {
			selector:"li",
			container:"",
			prefix:"Item ",
			removeElement: true,
			"newElement": `<li>Do everything quickly and well.</li>`
		},
		onChange: function(node, value, input, component, event) {
			let element = node;

			if (event.action) {
				if (event.action == "add") {
					//node.append(generateElements(`<li>List item</li>`)[0]);
					
					//temporary solution to better update list
					Vvveb.Components.render("html/list");
				}
				if (event.action == "remove") {
				} else if (event.action == "select") {
				}
			}
			
			return node;
		},
	}]			
});

Vvveb.Components.extend("_base", "html/preformatted", {
    nodes: ["pre"],
    name: "Preformatted",
	image: "icons/paragraph.svg",
	html: `<pre>Today I shall be meeting with interference, 
ingratitude, insolence, disloyalty, ill-will, and
selfishness all of them due to the offenders'
ignorance of what is good or evil..</pre>`,
    properties: [{
        name: "Text",
        key: "text",
        inline:false,
        htmlAttr: "innerHTML",
        inputtype: TextareaInput,
        data:{
			rows:20,
		}
    }]
});
