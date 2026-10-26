/**
 * Align Builder HTML Components - Audio, PDF, Custom HTML & Embeds
 * @package Align
 */

Vvveb.Components.extend("_base", "html/audio", {
    nodes: ["audio"],
    attributes: ["data-component-audio"],
    name: "Audio",
    image: "icons/audio.svg",
    html: `<figure data-component-audio><audio controls src="#"></audio></figure>`,
    properties: [{
        name: "Src",
        key: "src",
        child:"audio",
        htmlAttr: "src",
        inputtype: LinkInput
	}, {
       	key: "audio_options",
        inputtype: SectionInput,
        name:false,
        data: {header:"Options"},
    }, {
		name: "Autoplay",
        key: "autoplay",
        htmlAttr: "autoplay",
        child:"audio",
        inputtype: CheckboxInput,
        inline:true,
        col:4,
/*    }, {
        name: "Controls",
        key: "controls",
        htmlAttr: "controls",
        inputtype: CheckboxInput,
        child:"audio",
        inline:true,
        col:4,
*/    }, {
        name: "Loop",
        key: "loop",
        htmlAttr: "loop",
        inputtype: CheckboxInput,
        child:"audio",
        inline:true,
        col:4
    }]
});


Vvveb.Components.extend("_base", "html/pdf", {
    attributes: ["data-component-pdf"],
    image: "icons/pdf.svg",
    name: "Pdf embed",
    html: `<object data="" type="application/pdf" data-component-pdf></object>`,
    properties: [{
        name: "Data",
        key: "data",
        htmlAttr: "data",
        inputtype: TextInput
    }]
});

Vvveb.Components.extend("_base", "html/embed", {
    attributes: ["data-component-embed"],
    image: "icons/embed.svg",
    name: "Embed",
    html: `<object data="" type="application/pdf" data-component-pdf></object>`,
    properties: [{
        name: "Data",
        key: "data",
        htmlAttr: "data",
        inputtype: TextInput
    }]
});

Vvveb.Components.extend("_base", "html/html", {
    nodes: ["html"],
    name: "Html Page",
    image: "icons/posts.svg",
    html: `<html><body></body></html>`,
    properties: [{
        name: "Title",
        key: "title",
        htmlAttr: "innerHTML",
        inputtype: TextInput,
        child:"title",
    }, {
        name: "Meta description",
        key: "description",
        htmlAttr: "content",
        inputtype: TextInput,
        child:'meta[name=description]',
    }, {
        name: "Meta keywords",
        key: "keywords",
        htmlAttr: "content",
        inputtype: TextInput,
        child:'meta[name=keywords]',
    }]
});

/*
Vvveb.ComponentsGroup['Base'] =
["html/heading", "html/image", "html/hr",  "html/form", "html/textinput", "html/textareainput", "html/selectinput", "html/fileinput", "html/checkbox", "html/radiobutton", "html/link", "html/video", "html/button", "html/paragraph", "html/blockquote", "html/list", "html/table", "html/preformatted"];

*/

Vvveb.Components.extend("_base", "html/iframe", {
	attributes: ["data-component-iframe"],
    name: "Iframe",
    image: "icons/file.svg",
    html: '<div data-component-iframe><iframe src="https://www.vvveb.com" width="320" height="240"></iframe></div>',
	properties: [{
        name: "Src",
        key: "src",
        htmlAttr: "src",
        child:"iframe",
        inputtype: TextInput
    }, {
        name: "Width",
        key: "width",
        htmlAttr: "width",
        child:"iframe",
        inputtype: CssUnitInput
    }, {
        name: "Height",
        key: "height",
        htmlAttr: "height",
        child:"iframe",
        inputtype: CssUnitInput
	}]	
});
