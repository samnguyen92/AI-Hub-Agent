/**
 * Align Builder Elements Components - Carousel & Swiper Slider Elements
 * @package Align
 */

Vvveb.Components.add("elements/carousel", {
    name: "Carousel",
    image: "icons/carousel.svg",
    classes: ["swiper"],
    html: `
	  <div class="swiper" data-slides-per-view="3" data-draggable="true">
		<div class="swiper-wrapper">
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slide 1</p></div>
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slide 2</p></div>
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slide 3</p></div>
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slide 4</p></div>
		</div>
		<div class="swiper-pagination"></div>

		<!--
		<div class="swiper-button-prev"></div>
		<div class="swiper-button-next"></div>
		-->
		
		<!-- <div class="swiper-scrollbar"></div> -->
	  </div>	
	`,
	
	beforeInit(node) {
		if (node.dataset.breakpoints) {
			breakpoints = JSON.parse(node.dataset.breakpoints);
			
			for (let prop of this.properties) {
				if (prop.breakpoint && breakpoints[prop.breakpoint] && breakpoints[prop.breakpoint][prop.breakname]) {
					prop.defaultValue = breakpoints[prop.breakpoint][prop.breakname];
				}
			}
		} 

		return node;
	},
	
	afterDrop: carouselAfterDrop,
	
    onChange: function (node, property, value) {
		let element = node;
		if (property.key == "autoplay" && value == true) {
			value = {"waitForTransition":true,"enabled":value,"delay":element.dataset.delay};
		}

		if (value == 'true') value= true;
		if (value == 'false') value = false;

		/*
		element.swiper.params[property.key] = value;
		element.swiper.originalParams[property.key] = value;

		if (property.key == 'effect') {
			//Vvveb.Builder.iframe.contentWindow.cleanSwiper(element);
			element.swiper.destroy();
			Vvveb.Builder.iframe.contentWindow.initSwiper(true);
		} else {
			element.swiper.update();
		}
		*/
		
		if (property.group == "breakpoints") {
				let breakpoints;
				if (node.dataset.breakpoints) {
					breakpoints = JSON.parse(node.dataset.breakpoints);
				} else {
					breakpoints = {};
				}
				
				property.breakpoint = parseInt(property.breakpoint);
				
				if (!breakpoints[property.breakpoint]) {
					breakpoints[property.breakpoint] = {};
				}
				
				breakpoints[property.breakpoint][property.breakname] = parseInt(value);
				
				node.dataset.breakpoints = JSON.stringify(breakpoints);
			
			
		}
		
		element.swiper.destroy();
		Vvveb.Builder.iframe.contentWindow.initSwiper(true);

		return node;
	},

    properties: [{
		name: "Slides",
        key: "slidesPerView",
        inputtype: ListInput,
		htmlAttr:"data-slides-per-view",
		inline:true,
		data: {
			selector:".swiper-slide",
			container:".swiper-wrapper",
			prefix:"Slide ",
			removeElement: false,//handle manually with removeSlide
			//"newElement": `<div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slide 1</p></div>`
		},
        onChange: function(node, value, input, component, event) {
			let element = node;
			let dataset = {};
			for (i in element.dataset) {
				dataset[i] = element.dataset[i];
			}; 

			if (event.action) {
				if (event.action == "add") {
					let random = Math.floor(Math.random() * 6) + 1;
					let index = element.swiper.slides.length + 1;
					element.swiper.appendSlide(generateElements(`<div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slide ${index}</p></div>`)[0]);
					element.swiper.slideTo(index);
					//temporary solution to better update list
					Vvveb.Components.render("elements/carousel");
				}
				if (event.action == "remove") {
					element.swiper.removeSlide(event.index);
				} else if (event.action == "select") {
					element.swiper.slideTo(event.index, 300, true);
				}
			}
			
			setTimeout(function () {
				for (i in dataset) {
					element.swiper.params[i] = dataset[i];
					element.dataset[i] = dataset[i];
				}; 
				element.swiper.update(); 
			}, 1000);
			
			return node;
		},
	},{
		name: "Slides per view",
        key: "slidesPerView",
        inputtype: NumberInput,
		htmlAttr:"data-slides-per-view",
	},{
		name: "Space between",
        key: "spaceBetween",
        inputtype: NumberInput,
		htmlAttr:"data-space-between",
	},{
		name: "Speed",
        key: "speed",
        inputtype: NumberInput,
		htmlAttr:"data-speed",
		data: {step:100},
	},{
		name: "Delay",
        key: "delay",
        inputtype: NumberInput,
		htmlAttr:"data-delay",
		data: {step:100},
    },{
        name: "Effect",
        key: "effect",
        inputtype: SelectInput,
        htmlAttr:"data-effect",
      
        data:{
            options: [{
                value: "",
                text: "None"
            }, {
                value: "fade",
                text: "Fade"
            }, {
                value: "flip",
                text: "Flip"
            }, {
                value: "cube",
                text: "Cube"
            }, {
                value: "cards",
                text: "Cards"
            }, {
                value: "creative",
                text: "Creative"
            }]
	}
    },{
	key: "carousel_options",
        inputtype: SectionInput,
        name:false,
        data: {header:"Options"},
    },{	
	name: "Simulate touch",
        key: "simulateTouch",
	htmlAttr:"data-simulate-touch",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{	
	name: "Autoplay",
        key: "autoplay",
	htmlAttr:"data-autoplay",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{
	name: "Auto height",
        key: "autoHeight",
	htmlAttr:"data-auto-height",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{
	name: "Centered slides",
        key: "centeredSlides",
	htmlAttr:"data-centered-slides",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{	name: "Center insufficient",
        key: "centerInsufficientSlides",
	htmlAttr:"data-center-insufficient-slides",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{	name: "Loop",
        key: "loop",
	htmlAttr:"data-loop",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{	name: "Mouse wheel",
        key: "mousewheel",
	htmlAttr:"data-mousewheel",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{	
        name: "Pagination",
        key: "pagination",
	htmlAttr:"data-pagination",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{	name: "Rewind",
        key: "rewind",
	htmlAttr:"data-rewind",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{	name: "Scrollbar",
        key: "scrollbar",
	htmlAttr:"data-scrollbar",
	inputtype: CheckboxInput,
	inline:true,
        col:6
    },{
        name: "direction",
        key: "direction",
	htmlAttr:"data-direction",
	section: style_section,
        col:6,
        inline:false,
        inputtype: RadioButtonInput,
        data: {
            extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "horizontal",
                icon:"la la-arrow-down",
                title: "Horizontal",
                checked:true,
            },{
                value: "vertical",
                title: "Vertical",
                icon:"la la-arrow-right",
                checked:false,
            }],
	}
    },{
        key: "breakpoint_options",
        inputtype: SectionInput,
        name:false,
        data: {header:"Breakpoints"},
    },{
        name: "Slides mobile",
        group: "breakpoints",
        key: "sm.view",
        breakpoint: "320",
        breakname: "slidesPerView",
        inputtype: NumberInput,
    },{
        name: "Space mobile",
        group: "breakpoints",
        key: "sm.space",
        breakpoint: "320",
        breakname: "spaceBetween",
        inputtype: NumberInput,
    },{
        name: "Slides tablet",
        group: "breakpoints",
        key: "md.view",
        breakpoint: "480",
        breakname: "slidesPerView",
        inputtype: NumberInput,
    },{
        name: "Space tablet",
        group: "breakpoints",
        key: "md.space",
        breakpoint: "480",
        breakname: "spaceBetween",
        inputtype: NumberInput,
    },{
        name: "Slides landscape",
        group: "breakpoints",
        key: "lg.view",
        breakpoint: "768",
        breakname: "slidesPerView",
        inputtype: NumberInput,
    },{
        name: "Space landscape",
        group: "breakpoints",
        key: "lg.space",
        breakpoint: "768",
        breakname: "spaceBetween",
        inputtype: NumberInput,
    },{
        name: "Slides laptop",
        group: "breakpoints",
        key: "xl.view",
        breakpoint: "1024",
        breakname: "slidesPerView",
        inputtype: NumberInput,
    },{
        name: "Space laptop",
        group: "breakpoints",
        key: "xl.space",
        breakpoint: "1024",
        breakname: "spaceBetween",
        inputtype: NumberInput,
    },{
        name: "Slides desktop",
        group: "breakpoints",
        key: "xxl.view",
        breakpoint: "1200",
        breakname: "slidesPerView",
        inputtype: NumberInput,
    },{
        name: "Space desktop",
        group: "breakpoints",
        key: "xxl.space",
        breakpoint: "1200",
        breakname: "spaceBetween",
        inputtype: NumberInput,
    }]
});

//Slider
Vvveb.Components.add("elements/slider", {
	name: "Slider",
    image: "icons/slider.svg",
     html: `
	  <div class="swiper" data-slides-per-view="1" data-draggable="true" data-navigation='{"nextEl": ".swiper-button-next","prevEl": ".swiper-button-prev"}'>
		<div class="swiper-wrapper">
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slider 1</p></div>
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slider 2</p></div>
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slider 3</p></div>
		  <div class="swiper-slide"><img src="https://picsum.photos/200/300" class="img-fluid"><p>Slider 4</p></div>
		</div>
		<div class="swiper-pagination"></div>

		<div class="swiper-button-prev"></div>
		<div class="swiper-button-next"></div>
		
		<!-- <div class="swiper-scrollbar"></div> -->
	  </div>	
	`,
	afterDrop: carouselAfterDrop,
});
