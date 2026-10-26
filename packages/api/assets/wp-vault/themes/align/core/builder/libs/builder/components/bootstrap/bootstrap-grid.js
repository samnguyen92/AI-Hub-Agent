/**
 * Align Builder Bootstrap - Grid Container Layout
 * @package Align
 */

Vvveb.Components.extend("_base", "html/container", {
    classes: ["container", "container-fluid"],
    image: "icons/container.svg",
    html: '<div class="container" style="min-height:150px;"><div class="m-5">Container</div></div>',
    name: "Container",
    properties: [
     {
        name: "Type",
        key: "type",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["container", "container-fluid"],
        data: {
            options: [{
                value: "container",
                text: "Default"
            }, {
                value: "container-fluid",
                text: "Fluid"
            }]
        }
    },
	{
        name: "Background",
        key: "background",
		htmlAttr: "class",
        validValues: bgcolorClasses,
        inputtype: SelectInput,
        data: {
            options: bgcolorSelectOptions
        }
    },
	{
        name: "Background Color",
        key: "background-color",
		htmlAttr: "style",
        inputtype: ColorInput,
    },
	{
        name: "Text Color",
        key: "color",
		htmlAttr: "style",
        inputtype: ColorInput,
    }],
});

