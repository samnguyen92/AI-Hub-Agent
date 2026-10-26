/**
 * Align Builder Widgets - Embed Video Widgets
 * @package Align
 */

Vvveb.Components.extend("_base", "widgets/embed-video", {
    name: "Embed Video",
    attributes: ["data-component-video"],
    image: "icons/youtube.svg",
    dragHtml: '<img src="' + Vvveb.baseUrl + 'icons/youtube.svg" width="100" height="100">', //use image for drag and swap with iframe on drop for drag performance
    html: '<div data-component-video style="width:640px;height:480px;" playsinline="true" autoplay="true" mute="true"><iframe frameborder="0" src="https://www.youtube.com/embed/C6fOoy7Se_4?autoplay=1&loop=1&playsinline=1&controls=0&mute=1" width="100%" height="100%"></iframe></div>',
    
    
    //url parameters set with onChange
    t:'y',//video type
    video_id:'',//video id
    url: '', //html5 video src
    autoplay: false,
    controls: false,
    loop: false,
    playsinline: true,
    mute: false,
    resizable:true,//show select box resize handlers
    resizeMode:"css",//div unlike img/iframe etc does not have width,height attributes need to use css
	youtubeRegex:/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]+)/i,
	vimeoRegex : /(?:vimeo\.com(?:[^\d]+))(\d+)/i,

	init: function (node) {
		iframe = node.querySelector('iframe');
		video = node.querySelector('video');
		
		document.querySelector(".component-properties [data-key=url]").style.display = "none";
		document.querySelector(".component-properties [data-key=poster]").style.display = "none";
		
		//check if html5
		if (video) {
			this.url = video.src;
		} else if (iframe) {//vimeo or youtube
			let src = iframe.getAttribute("src");
			let match;

			if (src && src.indexOf("youtube") && (match = src.match(this.youtubeRegex))) {//youtube
				this.video_id = match[1];
				this.t = "y";
			} else if (src && src.indexOf("vimeo") && (match = src.match(this.vimeoRegex))) { //vimeo
				this.video_id = match[1];
				this.t = "v";
			} else {
				this.t = "h";
			}
		}
		
		document.querySelector(".component-properties input[name=video_id]").value = this.video_id;
		document.querySelector(".component-properties input[name=url]").value = this.url;
		document.querySelector(".component-properties select[name=t]").value = this.t;
	},
	
	onChange: function (node, property, value) {
		this[property.key] = value;
		//if (property.key == "t")
		{
			switch (this.t) {
				case 'y':
					document.querySelector(".component-properties [data-key=video_id]").style.display = "";
					document.querySelector(".component-properties [data-key=url]").style.display = "none";
					document.querySelector(".component-properties [data-key=poster]").style.display = "none";
				
					newnode = generateElements(`<iframe width="100%" height="100%" allowfullscreen="true" frameborder="0" allow="autoplay" 
										src="https://www.youtube.com/embed/${this.video_id}?autoplay=${this.autoplay}&controls=${this.controls}&loop=${this.loop}&playsinline=${this.playsinline}&mute=${this.mute}">
								</iframe>`)[0];
				break;
				case 'v':
					document.querySelector(".component-properties [data-key=video_id]").style.display = "";
					document.querySelector(".component-properties [data-key=url]").style.display = "none";
					document.querySelector(".component-properties [data-key=poster]").style.display = "none";
					newnode = generateElements(`<iframe width="100%" height="100%" allowfullscreen="true" frameborder="0" allow="autoplay" 
										src="https://player.vimeo.com/video/${this.video_id}?autoplay=${this.autoplay}&controls=${this.controls}&loop=${this.loop}&playsinline=${this.playsinline}&mute=${this.mute}">
								</iframe>`)[0];
				break;
				case 'h':
					document.querySelector(".component-properties [data-key=video_id]").style.display = "none";
					document.querySelector(".component-properties [data-key=url]").style.display = "";
					document.querySelector(".component-properties [data-key=poster]").style.display = "";
					newnode = generateElements('<video poster="' + this.poster + '" src="' + this.url + '" ' + (this.autoplay?' autoplay ':'') + (this.controls?' controls ':'') + (this.loop?' loop ':'') + (this.playsinline?' playsinline ':'') + (this.mute?' mute ':'') + ' style="height: 100%; width: 100%;"></video>')[0];
				break;
			}
			
			node.querySelector(":scope > iframe,:scope  > video").replaceWith(newnode);
			return node;
		}
		
		return node;
	},	
	
    properties: [{
        name: "Provider",
        key: "t",
        inputtype: SelectInput,
        data:{
			options: [{
                text: "Youtube",
                value: "y"
            },{
                text: "Vimeo",
                value: "v"
            },{
                text: "HTML5",
                value: "h"
            }]
       },
	 },{
        name: "Video",
        key: "video_id",
        inputtype: TextInput,
   		onChange: function(node, value, input, component) {
			
			let youtube = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]+)/i;
			let vimeo = /(?:vimeo\.com(?:[^\d]+))(\d+)/i;
			let id = false;
			let t = false;

			if (((id = value.match(youtube)) && (t = "y")) || ((id = value.match(vimeo)) && (t = "v"))) {
				document.querySelector(".component-properties select[name=t]").value = t;
				document.querySelector(".component-properties input[name=video_id]").value = id[1];

				component.t = t;
				component.video_id = id[1];

				return id[1];
			}
			
			return node;
		}
    },{
        name: "Poster",
        key: "poster",
        htmlAttr: "poster",
        inputtype: ImageInput
    },{
        name: "Url",
        key: "url",
        inputtype: TextInput
    },{
		name: "Width",
        key: "width",
        htmlAttr: "style",
        inline:false,
        col:6,
        inputtype: CssUnitInput
    },{
        name: "Height",
        key: "height",
        htmlAttr: "style",
        inline:false,
        col:6,
        inputtype: CssUnitInput
    },{
		key: "video_options",
        inputtype: SectionInput,
        name:false,
        data: {header:"Options"},
    },{
        name: "Auto play",
        key: "autoplay",
        htmlAttr: "autoplay",
        inline:true,
        col:4,
        inputtype: CheckboxInput
    },{
        name: "Plays inline",
        key: "playsinline",
        htmlAttr: "playsinline",
        inline:true,
        col:4,
        inputtype: CheckboxInput
    },{
        name: "Controls",
        key: "controls",
        htmlAttr: "controls",
        inline:true,
        col:4,
        inputtype: CheckboxInput
    },{
        name: "Loop",
        key: "loop",
        htmlAttr: "loop",
        inline:true,
        col:4,
        inputtype: CheckboxInput
    },{
        name: "Mute",
        key: "mute",
        htmlAttr: "mute",
        inline:true,
        col:4,
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
