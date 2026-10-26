/**
 * Align Builder HTML Components - HTML5 Semantic Layout Nodes
 * @package Align
 */

Vvveb.Components.extend("_base", "html/nav", {
    nodes: ["nav"],
    name: "Nav",
    image: "icons/navbar.svg",
    html: '<nav></nav>',
    properties: []
});

Vvveb.Components.extend("_base", "html/main", {
    nodes: ["main"],
    name: "Main",
    image: "icons/section.svg",
    html: '<main></main>',
    properties: []
});

Vvveb.Components.extend("_base", "html/article", {
    nodes: ["article"],
    name: "Article",
    image: "icons/post.svg",
    html: '<article></article>',
    properties: []
});

Vvveb.Components.extend("_base", "html/aside", {
    nodes: ["aside"],
    name: "Aside",
    image: "icons/left-column-layout.svg",
    html: '<aside></aside>',
    properties: []
});

Vvveb.Components.extend("_base", "html/details", {
    nodes: ["details"],
    name: "Details",
    image: "icons/accordion.svg",
    html: '<details><summary>Summary</summary><p>Details content</p></details>',
    properties: []
});

Vvveb.Components.extend("_base", "html/summary", {
    nodes: ["summary"],
    name: "Summary",
    image: "icons/paragraph.svg",
    html: '<summary>Summary</summary>',
    properties: [{
        name: "Text",
        key: "innerHTML",
        inline: false,
        htmlAttr: "innerHTML",
        inputtype: TextInput
    }]
});

Vvveb.Components.extend("_base", "html/figcaption", {
    nodes: ["figcaption"],
    name: "Figcaption",
    image: "icons/paragraph.svg",
    html: '<figcaption>Caption</figcaption>',
    properties: [{
        name: "Text",
        key: "innerHTML",
        inline: false,
        htmlAttr: "innerHTML",
        inputtype: TextInput
    }]
});

Vvveb.Components.extend("_base", "html/inline-text", {
    nodes: ["strong", "b", "em", "i", "u", "s", "small", "mark", "sub", "sup"],
    name: "Inline Text",
    image: "icons/paragraph.svg",
    html: '<span>Text</span>',
    properties: [{
        name: "Text",
        key: "innerHTML",
        inline: false,
        htmlAttr: "innerHTML",
        inputtype: TextareaInput
    }]
});
