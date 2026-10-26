/**
 * Align Builder Media Components - Embed Video & HTML5 Video Registry
 *
 * @package AlignBuilder
 */

if (!window.Vvveb) window.Vvveb = {};
if (!window.Vvveb.Components) window.Vvveb.Components = {};

Vvveb.Components.extend("_base", "widgets/embed-video", {
    name: "Embed Video",
    attributes: ["data-component-video"],
    image: "icons/youtube.svg",
    dragHtml: '<img src="' + Vvveb.baseUrl + 'icons/youtube.svg" width="100" height="100">',
    html: '<div data-component-video style="width:640px;height:480px;" playsinline="true" autoplay="true" mute="true"><iframe frameborder="0" src="https://www.youtube.com/embed/C6fOoy7Se_4?autoplay=1&loop=1&playsinline=1&controls=0&mute=1" width="100%" height="100%"></iframe></div>',
    
    t: 'y',
    video_id: '',
    url: '',
    autoplay: false,
    controls: false,
    loop: false,
    playsinline: true,
    mute: false,
    resizable: true,
    resizeMode: "css",
    youtubeRegex: /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]+)/i,
    vimeoRegex: /(?:vimeo\.com(?:[^\d]+))(\d+)/i,

    init: function (node) {
        let iframe = node.querySelector('iframe');
        let video = node.querySelector('video');
        
        let urlProp = document.querySelector(".component-properties [data-key=url]");
        if (urlProp) urlProp.style.display = "none";
        let posterProp = document.querySelector(".component-properties [data-key=poster]");
        if (posterProp) posterProp.style.display = "none";
        
        if (video) {
            this.url = video.src;
        } else if (iframe) {
            let src = iframe.getAttribute("src");
            let match;

            if (src && src.indexOf("youtube") && (match = src.match(this.youtubeRegex))) {
                this.video_id = match[1];
                this.t = "y";
            } else if (src && src.indexOf("vimeo") && (match = src.match(this.vimeoRegex))) {
                this.video_id = match[1];
                this.t = "v";
            } else {
                this.t = "h";
            }
        }
        
        let vidInput = document.querySelector(".component-properties input[name=video_id]");
        if (vidInput) vidInput.value = this.video_id;
        let urlInput = document.querySelector(".component-properties input[name=url]");
        if (urlInput) urlInput.value = this.url;
        let tSelect = document.querySelector(".component-properties select[name=t]");
        if (tSelect) tSelect.value = this.t;
    },
    
    onChange: function (node, property, value) {
        this[property.key] = value;
        let newnode = null;

        switch (this.t) {
            case 'y':
                let vidProp = document.querySelector(".component-properties [data-key=video_id]");
                if (vidProp) vidProp.style.display = "";
                let urlPropY = document.querySelector(".component-properties [data-key=url]");
                if (urlPropY) urlPropY.style.display = "none";
                let posterPropY = document.querySelector(".component-properties [data-key=poster]");
                if (posterPropY) posterPropY.style.display = "none";
            
                newnode = generateElements(`<iframe width="100%" height="100%" allowfullscreen="true" frameborder="0" allow="autoplay" 
                                    src="https://www.youtube.com/embed/${this.video_id}?autoplay=${this.autoplay}&controls=${this.controls}&loop=${this.loop}&playsinline=${this.playsinline}&mute=${this.mute}">
                            </iframe>`)[0];
            break;
            case 'v':
                let vidPropV = document.querySelector(".component-properties [data-key=video_id]");
                if (vidPropV) vidPropV.style.display = "";
                let urlPropV = document.querySelector(".component-properties [data-key=url]");
                if (urlPropV) urlPropV.style.display = "none";
                let posterPropV = document.querySelector(".component-properties [data-key=poster]");
                if (posterPropV) posterPropV.style.display = "none";
                newnode = generateElements(`<iframe width="100%" height="100%" allowfullscreen="true" frameborder="0" allow="autoplay" 
                                    src="https://player.vimeo.com/video/${this.video_id}?autoplay=${this.autoplay}&controls=${this.controls}&loop=${this.loop}&playsinline=${this.playsinline}&mute=${this.mute}">
                            </iframe>`)[0];
            break;
            case 'h':
                let vidPropH = document.querySelector(".component-properties [data-key=video_id]");
                if (vidPropH) vidPropH.style.display = "none";
                let urlPropH = document.querySelector(".component-properties [data-key=url]");
                if (urlPropH) urlPropH.style.display = "";
                let posterPropH = document.querySelector(".component-properties [data-key=poster]");
                if (posterPropH) posterPropH.style.display = "";
                newnode = generateElements('<video poster="' + (this.poster || '') + '" src="' + (this.url || '') + '" ' + (this.autoplay?' autoplay ':'') + (this.controls?' controls ':'') + (this.loop?' loop ':'') + (this.playsinline?' playsinline ':'') + (this.mute?' mute ':'') + ' style="height: 100%; width: 100%;"></video>')[0];
            break;
        }
        
        let targetEl = node.querySelector(":scope > iframe,:scope > video");
        if (targetEl && newnode) {
            targetEl.replaceWith(newnode);
        }
        return node;
    },    
    
    properties: [{
        name: "Provider",
        key: "t",
        inputtype: SelectInput,
        data: {
            options: [{
                value: "y",
                text: "Youtube"
            }, {
                value: "v",
                text: "Vimeo"
            }, {
                value: "h",
                text: "HTML5"
            }]
        },
        onChange: function(node, value) {
            return this.onChange(node, { key: "t" }, value);
        }
    }, {
        name: "Video ID",
        key: "video_id",
        inputtype: TextInput,
        data: {
            prepand: "youtube.com/watch?v="
        },
        onChange: function(node, value) {
            return this.onChange(node, { key: "video_id" }, value);
        }
    }, {
        name: "URL",
        key: "url",
        inputtype: TextInput,
        onChange: function(node, value) {
            return this.onChange(node, { key: "url" }, value);
        }
    }, {
        name: "Poster",
        key: "poster",
        inputtype: ImageInput,
        onChange: function(node, value) {
            return this.onChange(node, { key: "poster" }, value);
        }
    }, {
        name: "Autoplay",
        key: "autoplay",
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        onChange: function(node, value) {
            return this.onChange(node, { key: "autoplay" }, value);
        }
    }, {
        name: "Controls",
        key: "controls",
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        onChange: function(node, value) {
            return this.onChange(node, { key: "controls" }, value);
        }
    }, {
        name: "Loop",
        key: "loop",
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        onChange: function(node, value) {
            return this.onChange(node, { key: "loop" }, value);
        }
    }, {
        name: "Mute",
        key: "mute",
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        onChange: function(node, value) {
            return this.onChange(node, { key: "mute" }, value);
        }
    }]
});
