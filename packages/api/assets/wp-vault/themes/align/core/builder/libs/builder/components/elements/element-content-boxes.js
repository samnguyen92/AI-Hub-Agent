/**
 * Align Builder Elements Components - Content Boxes, Pricing, Code, Image Compare & Rating
 * @package Align
 */

Vvveb.Components.add("elements/icon-list", {
    nodes: [".counter"],
    name: "Icon list",
    image: "icons/icon-list.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/divider", {
    nodes: [".counter"],
    name: "Divider",
    image: "icons/stopwatch.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/separator", {
    nodes: [".counter"],
    name: "Separator",
    image: "icons/separator.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/Image box", {
    nodes: [".counter"],
    name: "Image Box",
    image: "icons/stopwatch.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/Icon box", {
    nodes: [".counter"],
    name: "Image Box",
    image: "icons/stopwatch.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/animated-headline", {
    nodes: [".counter"],
    name: "Animated headline",
    image: "icons/dots_three.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/price-table", {
    nodes: [".counter"],
    name: "Price table",
    image: "icons/price-table.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/price-list", {
    nodes: [".counter"],
    name: "Price list",
    image: "icons/stopwatch.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/reviews", {
    nodes: [".counter"],
    name: "Reviews",
    image: "icons/reviews.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/code", {
    nodes: ["code"],
    name: "Code",
    image: "icons/code.svg",
    html: `<code>print "Hello world!"</code>`,
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
  
Vvveb.Components.add("elements/image-compare", {
    nodes: [".counter"],
    name: "Image Compare",
    image: "icons/image-compare.svg",
    html: `<div class="c-compare" style="--value:50%;">
	  <img class="c-compare__left" src="https://picsum.photos/200/300" alt="" />
	  <img class="c-compare__right" src="https://picsum.photos/200/300" alt="" />
	</div>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/rating", {
    nodes: [".rating"],
    name: "Rating stars",
    image: "icons/rating.svg",
    html: `<div class="rating">
                <i class="la la-star text-warning"></i>
                <i class="la la-star text-warning"></i>
                <i class="la la-star text-warning"></i>
                <i class="la la-star text-warning"></i>
                <i class="la la-star text-secondary"></i>
            </div>`,
    properties: [
	]
});
