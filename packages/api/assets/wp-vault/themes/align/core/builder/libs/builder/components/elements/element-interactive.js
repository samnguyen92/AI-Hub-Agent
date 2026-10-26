/**
 * Align Builder Elements Components - Flip Box, Counter, Testimonial & Social Icons
 * @package Align
 */

Vvveb.Components.add("elements/flip-box", {
    classes: ["flip-box"],
    name: "Flip box",
    image: "icons/flipbox.svg",
    html: `<div class="flip-box enabled">
		  <div class="flip-box-inner">
			<div class="flip-box-front">
				  <div class="card">
				  <img src="https://picsum.photos/200/300" class="card-img-top" alt="Post">
				  <div class="card-body">
					<h5 class="card-title">Card title</h5>
					<p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
					<a href="#" class="btn btn-primary">Go somewhere</a>
				  </div>
				</div>	
			</div>
			
			<div class="flip-box-back">
				<div class="d-flex align-items-center flex-column">
				  <div class="flex-shrink-0">
					<img src="https://picsum.photos/200/300" alt="Post">
				  </div>
				  <div class="flex-grow-1 ms-3">
					<p>
						This is some content from a media component. You can replace this with any content and adjust it as needed.
					</p>
					
					<a href="#" class="btn btn-primary">Go somewhere</a>
				  </div>
				</div>
			</div>
		  </div>
		</div>`,
		properties: [{
			name: "Width",
			key: "width",
			htmlAttr: "style",
			inputtype: CssUnitInput
		},{
			name: "Height",
			key: "height",
			htmlAttr: "style",
			inputtype: CssUnitInput
		},{
			name: "Enabled",
			key: "enabled",
			htmlAttr: "class",
			validValues: ["enabled"],
			inputtype: ToggleInput,
			data: {
				on: "enabled",
				off: ""
			}
		},{
			name: "Show back",
			key: "back",
			htmlAttr: "class",
			validValues: ["back"],
			inputtype: ToggleInput,
			data: {
				on: "back",
				off: ""
			}
		},
		{
			name: "Vertical",
			key: "vertical",
			htmlAttr: "class",
			validValues: ["vertical"],
			inputtype: ToggleInput,
			data: {
				on: "vertical",
				off: ""
			}
		},
	]
});   

Vvveb.Components.add("elements/counter", {
    nodes: [".counter"],
    name: "Counter",
    image: "icons/stopwatch.svg",
    html: `<i class="font-icon la la-star"></i>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/testimonial", {
    nodes: [".counter"],
    name: "Testimonial",
    image: "icons/testimonial.svg",
    html: `<blockquote cite="https://en.wikipedia.org/wiki/Marcus_Aurelius">
				<p>Today I shall be meeting with interference, ingratitude, insolence, disloyalty, ill-will, and selfishness all of them due to the offenders' ignorance of what is good or evil.</p>
				<cite class="small">
					<a href="https://en.wikipedia.org/wiki/Marcus_Aurelius" class="text-decoration-none" target="blank">Marcus Aurelius</a>
				</cite>	
			</blockquote>`,
    properties: [
	]
});   

Vvveb.Components.add("elements/social-icons", {
    classes: ["social-icons"],
    name: "Social icons",
    image: "icons/social-icons.svg",
    html: `<ul class="social-icons list-unstyled">
				<li>
					<a href="https://facebook.com">
						<i class="lab la-facebook-f la-2x"></i> <span>Facebook</span>
					</a>
				</li>
				<li>
					<a href="https://linkedin.com">
						<i class="lab la-linkedin-in la-2x"></i> <span>Linkedin</span>
					</a>
				</li>		
				<li>
					<a href="https://twitter.com">
						<i class="lab la-twitter la-2x"></i> <span>Twitter</span>
					</a>
				</li>					
				<li>
					<a href="https://instagram.com">
						<i class="lab la-instagram la-2x"></i> <span>Instagram</span>
					</a>
				</li>				
				<li>
					<a href="https://github.com">
						<i class="lab la-github la-2x"></i> <span>Github</span>
					</a>
				</li>
			</ul>`,
		properties: [{
			//name: "List",
			key: "list",
			//component: "elements/tab",
			inline:false,
			inputtype: ListInput,
			data: {
				selector:"li",
				container:"",
				prefix:"Item ",
				name: "text",
				nameElement: "span",
				removeElement: true,
				//"newElement": ``
			},
			onChange: function(node, value, input, component, event) {
				let element = node;

				if (event.action) {
					if (event.action == "add") {
						node.append(generateElements(`<li>
							<a href="https://twitter.com">
								<i class="lab la-twitter la-2x"></i> <span>Twitter</span>
							</a>
						</li>`)[0]);
						
						//temporary solution to better update list
						Vvveb.Components.render("elements/social-icons");
					}
					if (event.action == "remove") {
						node.querySelector(":scope > li:nth-child(" + event.index + ")").remove();
					} else if (event.action == "select") {
						let el = node.querySelector(":scope > li:nth-child(" + event.index + ")");
						//el[0].click();
						//Vvveb.Builder.iframe.contentWindow.bootstrap.Collapse.getOrCreateInstance(el[0]).toggle();
					}
				}
				
				return node;
			},
		},{
			name: "Inline",
			key: "list-inline",
			htmlAttr: "class",
			validValues: ["list-inline"],
			inputtype: ToggleInput,
			data: {
				on: "list-inline",
				off: ""
			}
		},{
			name: "Unstyled",
			key: "list-unstyled",
			htmlAttr: "class",
			validValues: ["list-unstyled"],
			inputtype: ToggleInput,
			data: {
				on: "list-unstyled",
				off: ""
			}
		}]
}); 

function carouselAfterDrop(node) {
	//check if swiper js is included and if not add it when drag starts to allow the script to load
	body = Vvveb.Builder.frameBody;

	if (!body.querySelector("#swiper-js")) {
		let link = document.createElement('link');
		let lib = document.createElement('script');
		let code = document.createElement('script');
		link.href = '../../libs/swiper/swiper-bundle.min.css';
		link.id = 'swiper-css';
		link.rel = 'stylesheet';
		lib.id = 'swiper-js';
		lib.type = 'text/javascript';
		lib.src = '../../libs/swiper/swiper-bundle.min.js';
		code.type = 'text/javascript';
		code.text = `
		let swiper = [];
		function initSwiper(onlyNew = false) {
			if (typeof Swiper == "undefined") return;
			let list = document.querySelectorAll('.swiper' + (onlyNew ? ":not(.swiper-initialized)" : "") );
			list.forEach(el => {
				let params = {      
					navigation: {
						nextEl: ".swiper-button-next",
						prevEl: ".swiper-button-prev",
					},      
					pagination: {
						el: ".swiper-pagination",
				  },
				};
				for (i in el.dataset) {
					let param = el.dataset[i];
					if (param == 'true') param = true;
					if (param == 'false') param = false;
					
					if (param[0] == '{') {
						param = JSON.parse(param);
					}
					params[i] = param;
				}
				swiper.push(new Swiper(el, params))
				//swiper.push(new Swiper(el, { ...{autoplay:{delay: 500}}, ...el.dataset}))		
			});
		}

		if (document.readyState !== 'loading') {
			initSwiper();
		  } else {
			document.addEventListener('DOMContentLoaded', e => initSwiper());
		  }`;			
		
		body.appendChild(link);
		body.appendChild(lib);
		body.appendChild(code);
		
		lib.addEventListener('load', function() {		
			Vvveb.Builder.iframe.contentWindow.initSwiper();
		});
	} else {
		Vvveb.Builder.iframe.contentWindow.initSwiper(true);
	}
	
	return node;
};
