/**
 * Align Builder Media Components - OEmbed & External Embeds Registry
 *
 * @package AlignBuilder
 */

if (!window.Vvveb) window.Vvveb = {};
if (!window.Vvveb.Components) window.Vvveb.Components = {};
if (!window.Vvveb.ComponentsGroup) window.Vvveb.ComponentsGroup = {};

Vvveb.ComponentsGroup['Embeds'] = ["embeds/embed"];

Vvveb.Components.extend("_base", "embeds/embed", {
    name: "Embed",
    attributes: ["data-component-oembed"],
    image: "icons/code.svg",
    html: `<div data-component-oembed data-url="">
			<div class="alert alert-light m-5" role="alert">
				<img width="64" src="${Vvveb.baseUrl || ''}icons/code.svg">
				<h6>Enter url to embed</h6>
			</div></div>`,

    properties: [{
        name: "Url",
        key: "url",
        htmlAttr: "data-url",
        inputtype: TextInput,
        onChange: function(node, value) {
            node.innerHTML = `<div class="alert alert-light d-flex justify-content-center">
                  <div class="spinner-border m-5" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>`;
            
            if (typeof getOembed === "function") {
                getOembed(value).then(response => {
                    node.innerHTML = response.html;
                    let containerW = node.offsetWidth;
                    let iframe = node.querySelector("iframe");
                    if (iframe) {
                        let ratio = containerW / iframe.offsetWidth;
                        iframe.setAttribute("width", (containerW));
                        iframe.setAttribute("height", (iframe.offsetHeight * ratio));
                    }

                    node.querySelectorAll('script').forEach(script => {
                        let newScript = (Vvveb.Builder && Vvveb.Builder.frameDoc ? Vvveb.Builder.frameDoc : document).createElement("script");
                        newScript.src = script.src;
                        script.replaceWith(newScript);
                    });                  
                }).catch(error => console.warn('[Align MediaEmbeds] OEmbed fetch error:', error));
            }

            return node;
        },    
    }, {
        name: "Width",
        key: "width",
        child: "iframe",
        htmlAttr: "width",
        inputtype: CssUnitInput
    }, {
        name: "Height",
        key: "height",
        child: "iframe",
        htmlAttr: "height",
        inputtype: CssUnitInput
    }]
});

// Register standard OEmbed providers
const EMBED_PROVIDERS = [
    "youtube", "vimeo", "dailymotion", "flickr", "smugmug", "scribd", "twitter",
    "soundcloud", "slideshare", "spotify", "imgur", "issuu", "mixcloud", "ted",
    "animoto", "tumblr", "kickstarter", "reverbnation", "reddit", "speakerdeck",
    "screencast", "amazon", "someecards", "tiktok", "pinterest", "wolfram", "anghami"
];

for (const provider of EMBED_PROVIDERS) {
    Vvveb.Components.add("embeds/" + provider, {
        name: provider.charAt(0).toUpperCase() + provider.slice(1),
        image: "icons/code.svg",
        html: `<div data-component-oembed data-url="">
                <div class="alert alert-light m-5" role="alert">
                    <img width="64" src="${Vvveb.baseUrl || ''}icons/code.svg">
                    <h6>Enter ${provider} url to embed</h6>
                </div></div>`,
    });
    if (!Vvveb.ComponentsGroup['Embeds'].includes("embeds/" + provider)) {
        Vvveb.ComponentsGroup['Embeds'].push("embeds/" + provider);
    }
}

// PDF Embed Component
Vvveb.Components.extend("_base", "html/pdf", {
    attributes: ["data-component-pdf"],
    image: "icons/pdf.svg",
    name: "Pdf embed",
    html: `<object data="" type="application/pdf" data-component-pdf style="width: 100%; height: 500px;"></object>`,
    properties: [{
        name: "File URL",
        key: "data",
        htmlAttr: "data",
        inputtype: TextInput
    }]
});
