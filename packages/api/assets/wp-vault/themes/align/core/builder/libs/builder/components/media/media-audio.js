/**
 * Align Builder Media Components - Audio Registry
 *
 * @package AlignBuilder
 */

if (!window.Vvveb) window.Vvveb = {};
if (!window.Vvveb.Components) window.Vvveb.Components = {};

Vvveb.Components.extend("_base", "html/audio", {
    nodes: ["audio"],
    attributes: ["data-component-audio"],
    name: "Audio",
    image: "icons/audio.svg",
    html: `<figure data-component-audio><audio controls src="#"></audio></figure>`,
    properties: [{
        name: "Src",
        key: "src",
        child: "audio",
        htmlAttr: "src",
        inputtype: LinkInput
    }, {
        key: "audio_options",
        inputtype: SectionInput,
        name: false,
        data: { header: "Options" },
    }, {
        name: "Autoplay",
        key: "autoplay",
        htmlAttr: "autoplay",
        child: "audio",
        inputtype: CheckboxInput,
        inline: true,
        col: 6,
    }, {
        name: "Loop",
        key: "loop",
        htmlAttr: "loop",
        inputtype: CheckboxInput,
        child: "audio",
        inline: true,
        col: 6
    }]
});
