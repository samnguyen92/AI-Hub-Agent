/**
 * Align Builder HTML Components - Radio, Checkbox, File & Video HTML Elements
 * @package Align
 */

Vvveb.Components.extend("_base", "html/radiobutton", {
    name: "Radio Button",
	attributes: {"type":"radio"},
    image: "icons/radio.svg",
    html: `<div class="form-check">
			  <label class="form-check-label">
				<input class="form-check-input" type="radio" name="radiobutton"> Option 1
			  </label>
			</div>
			<div class="form-check">
			  <label class="form-check-label">
				<input class="form-check-input" type="radio" name="radiobutton" checked> Option 2
			  </label>
			</div>
			<div class="form-check">
			  <label class="form-check-label">
				<input class="form-check-input" type="radio" name="radiobutton"> Option 3
			  </label>
			</div>`,
    properties: [{
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput,
        //inline:true,
        //col:6
    },{
        name: "Value",
        key: "value",
        htmlAttr: "value",
        inputtype: TextInput,
        //inline:true,
        //col:6
    },{
		name: "Checked",
        key: "checked",
        htmlAttr: "Checked",
        inputtype: CheckboxInput,
        //inline:true,
        //col:6
	},{
        name: "Disabled",
        key: "disabled",
        htmlAttr: "disabled",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
	},{
        name: "Required",
        key: "required",
        htmlAttr: "required",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
     }]
});

Vvveb.Components.extend("_base", "html/checkbox", {
    name: "Checkbox",
    attributes: {"type":"checkbox"},
    image: "icons/checkbox.svg",
    html: `<div class="form-check">
			  <label class="form-check-label">
				<input class="form-check-input" type="checkbox" value=""> Default checkbox
			  </label>
			</div>`,
    properties: [{
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput,
        //inline:true,
        //col:6
    },{
        name: "Value",
        key: "value",
        htmlAttr: "value",
        inputtype: TextInput,
        //inline:true,
        //col:6
    },{
		name: "Checked",
        key: "checked",
        htmlAttr: "Checked",
        inputtype: CheckboxInput,
        //inline:true,
        //col:6
	},{
        name: "Disabled",
        key: "disabled",
        htmlAttr: "disabled",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
	},{
        name: "Required",
        key: "required",
        htmlAttr: "required",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
     }]
});

/*
Vvveb.Components.extend("_base", "html/fileinput", {
    name: "Input group",
	attributes: {"type":"file"},
    image: "icons/text_input.svg",
    html: '<input type="file" class="form-control">'
});
*/

Vvveb.Components.extend("_base", "html/video", {
    nodes: ["video"],
    name: "Video",
    html: '<video width="320" height="240" playsinline loop autoplay muted src="../../media/demo/sample.webm" poster="../../media/sample.webp"><video>',
    dragHtml: '<img  width="320" height="240" src="' + Vvveb.baseUrl + 'icons/video.svg">',
	image: "icons/video.svg",
    resizable:true,//show select box resize handlers
    properties: [{
        name: "Video",
        //child: "source",
        key: "src",
        htmlAttr: "src",
        inputtype: VideoInput
    },{       
		name: "Poster",
        key: "poster",
        htmlAttr: "poster",
        inputtype: ImageInput
    },{
        name: "Width",
        key: "width",
        htmlAttr: "width",
        inputtype: TextInput
    }, {
        name: "Height",
        key: "height",
        htmlAttr: "height",
        inputtype: TextInput
    },{
        name: "Muted",
        key: "muted",
        htmlAttr: "muted",
        inputtype: CheckboxInput
    },{
        name: "Loop",
        key: "loop",
        htmlAttr: "loop",
        inputtype: CheckboxInput
    },{
        name: "Autoplay",
        key: "autoplay",
        htmlAttr: "autoplay",
        inputtype: CheckboxInput
    },{
        name: "Plays inline",
        key: "playsinline",
        htmlAttr: "playsinline",
        inputtype: CheckboxInput
    },{
        name: "Controls",
        key: "controls",
        htmlAttr: "controls",
        inputtype: CheckboxInput
    },{
	name:"",
	key: "autoplay_warning",
        inline:false,
        col:12,
        inputtype: NoticeInput,
        data: {
			type:'warning',
			title:'Autoplay',
			text:'Most browsers allow auto play only if video is muted and plays inline'
		}
	}]
});
