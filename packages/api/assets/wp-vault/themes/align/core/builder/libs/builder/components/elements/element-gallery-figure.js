/**
 * Align Builder Elements Components - Gallery & Figure Elements
 * @package Align
 */

Vvveb.Components.add("elements/gallery", {
    attributes: ["data-component-gallery"],
    name: "Gallery",
    image: "icons/images.svg",
    html: `<div class="gallery masonry has-shadow" data-component-gallery  id="gallery-RANDOM_ID">
				<div class="item">
					<figure>
					  <img class="img-fluid" src="https://picsum.photos/200/300" data-aos="fade-up">
					  <figcaption></figcaption>
						<h3 class="title d-none"></h3>
						<div class="description d-none"></div>
					</figure>
				</div>
				<div class="item">
					<figure>
					  <img class="img-fluid" src="https://picsum.photos/200/300" data-aos="fade-up" data-aos-delay="100">
					  <figcaption></figcaption>
						<h3 class="title d-none"></h3>
						<div class="description d-none"></div>
					</figure>
				</div>
				<div class="item">
					<figure>
					  <img class="img-fluid" src="https://picsum.photos/200/300" data-aos="fade-up" data-aos-delay="200">
					  <figcaption></figcaption>
						<h3 class="title d-none"></h3>
						<div class="description d-none"></div>
					</figure>
				</div>
				<div class="item">
					<figure>
					  <img class="img-fluid" src="https://picsum.photos/200/300" data-aos="fade-up" data-aos-delay="300">
					  <figcaption></figcaption>
						<h3 class="title d-none"></h3>
						<div class="description d-none"></div>
					</figure>
				</div>
				<div class="item">
					<figure>
					  <img class="img-fluid" src="https://picsum.photos/200/300" data-aos="fade-up" data-aos-delay="400">
					  <figcaption></figcaption>
						<h3 class="title d-none"></h3>
						<div class="description d-none"></div>
					</figure>
				</div>
				<div class="item">
					<figure>
					  <img class="img-fluid" src="https://picsum.photos/200/300" data-aos="fade-up" data-aos-delay="500">
					  <figcaption></figcaption>
						<h3 class="title d-none"></h3>
						<div class="description d-none"></div>
					</figure>
				</div>
			</div>
			`,
		properties: [{		
			name: "Masonry layout",
			key: "masonry",
			htmlAttr: "class",
			validValues: ["masonry", "flex"],
			inputtype: ToggleInput,
			data: {
				on: "masonry",
				off: "flex"
			},

			name: false,
			key: "masonry",
			inputtype: RadioButtonInput,
			inline:false,
			col:12,
			htmlAttr:"class",
			validValues: ["masonry", "flex", "grid"],
			data: {
				inline: true,
				extraclass:"btn-group-fullwidth btn-group-sm",
				options: [{
					value: "masonry",
					//icon:"la la-cog",
					text: "Masonry",
					title: "Masonry",
					checked:true,
				},{
					value: "flex",
					text: "Flex",
					title: "Flex",
					//icon:"la la-search",
				},{
					value: "grid",
					text: "Grid",
					title: "Grid",
					//icon:"la la-search",
				}],
			},
			
			setGroup: group => {
				document.querySelectorAll(".mb-2[data-group]").forEach(el => el.style.display = "none");
				document.querySelectorAll('.mb-2[data-group="'+ group + '"]').forEach(el => el.style.display = "");
			}, 		
			onChange : function(node, value, input)  {
				this.setGroup(value);
				return node;
			}, 
			init: function (node) {
				if (node.classList.contains("masonry")) {
					return "masonry";
				} else {
					return "flex";
				}
			},   			
		},{
			name: "Fit images",
			key: "cover",
			htmlAttr: "class",
			validValues: [ "", "cover"],
			inputtype: ToggleInput,
			data: {
				on: "cover",
				off: ""
			},
		},{
			name: "Image shadow",
			key: "shadow",
			htmlAttr: "class",
			validValues: [ "", "has-shadow"],
			inputtype: ToggleInput,
			data: {
				on: "has-shadow",
				off: ""
			},
		},{
			name: "Equal rows",
			key: "grid-auto-rows",
			group:"grid",
			htmlAttr: "style",
			inputtype: ToggleInput,
			data: {
				on: "1fr",
				off: ""
			},
		},{
			name: "Hover effect",
			key: "hover-effect",
			htmlAttr: "class",
			validValues: [ "", "hover-effect"],
			inputtype: ToggleInput,
			data: {
				on: "hover-effect",
				off: ""
			},
		},{
			name: "Horizontal gap",
			key: "column-gap",
			htmlAttr: "style",
			inputtype: CssUnitInput,
			data:{
				max: 100,
				min:0,
				step:1
			}
	   },{
			name: "Vertical gap",
			key: "margin-bottom",
			htmlAttr: "style",
			child: ".item",
			inputtype: CssUnitInput,
			data:{
				max: 100,
				min:0,
				step:1
			}
	   },{
			name: "Images per row masonry",
			key: "column-count",
			group:"masonry",
			htmlAttr: "style",
			inputtype: RangeInput,
			data:{
				max: 12,
				min:1,
				step:1
			}
		},{
			name: "Images per row flex",
			group:"flex",
			key: "flex-basis",
			child: ".item",
			htmlAttr: "style",
			inputtype: RangeInput,
			data:{
				max: 12,
				min:1,
				step:1
			},
			onChange: function(node, value, input, component, inputElement) {
				if (value) {
					value = 100 / value;
					value += "%";
				} 
				
				return value;
			}  			
	   },{		
			name: "Images per row grid",
			group:"grid",
			key: "grid-template-columns",
			//child: ".item",
			htmlAttr: "style",
			inputtype: RangeInput,
			data:{
				max: 12,
				min:1,
				step:1
			},
			onChange: function(node, value, input, component, inputElement) {
				return 'repeat(' + value + ', minmax(0, 1fr))';
			}  			
		},{		
			name: "Images",
			key: "images",
			inputtype: ListInput,
			htmlAttr:"data-images",
			inline:true,
			data: {
				selector:".item",
				container:".gallery",
				prefix:"Image ",
				removeElement: true,//handle manually with removeSlide
				newElement: `<div class="item">
					<figure>
					  <img class="img-fluid" src="https://picsum.photos/200/300" data-aos="fade-up">
					  <figcaption></figcaption>
						<h3 class="title d-none"></h3>
						<div class="description d-none"></div>
					</figure>
				</div>`,
				elementProperties:[{
					name: "Caption",
					key: "caption",
					child:"figcaption",
					htmlAttr: "innerHTML",
					inputtype: TextareaInput
				},{
					name: "Title",
					key: "title",
					child:".title",
					htmlAttr: "innerHTML",
					inputtype: TextInput
				},{
					name: "Description",
					key: "description",
					child:".description",
					htmlAttr: "innerHTML",
					inputtype: TextareaInput
				},{
					name: "Description position",
					key: "position",
					child:"img",
					htmlAttr: "data-desc-position",
					inline:false,
					inputtype: SelectInput,
					data: {
						options: [{
							value: "bottom",
							text: "Bottom"
						},{
							value: "top",
							text: "Top"
						},{
							value: "left",
							text: "Left"
						},{
							value: "right",
							text: "Right"
						}]
					}
			}]
		},
        onChange: function(node, value, input, component, event) {
			if (event.action) {
				if (event.action == "add") {
					//temporary solution to better update list
					Vvveb.Components.render("elements/gallery");
				}
				if (event.action == "remove") {
				} else if (event.action == "select") {
				}
			}
			return node;
		},		
	}],
    init(node)	{

		document.querySelectorAll(".mb-2[data-group]").forEach(el => el.style.display = "none");
		
		let source = "flex";
		if (node.classList.contains("masonry")) {
			source = "masonry";
		} else {
			source = "flex";
		}
		
		document.querySelector('.mb-2[data-group="'+ source + '"]').style.display = "";
	}	
});    

//Tabs
