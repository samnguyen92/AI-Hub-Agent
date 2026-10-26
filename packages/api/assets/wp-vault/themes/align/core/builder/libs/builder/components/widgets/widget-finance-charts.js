/**
 * Align Builder Widgets - PayPal, Chart.js & Lottie Widgets
 * @package Align
 */

Vvveb.Components.extend("_base", "widgets/paypal", {
    name: "Paypal",
    attributes: ["data-component-paypal"],
    image: "icons/paypal.svg",
    html: '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" data-component-paypal>\
\
				<!-- Identify your business so that you can collect the payments. -->\
				<input type="hidden" name="business"\
					value="givanz@yahoo.com">\
\
				<!-- Specify a Donate button. -->\
				<input type="hidden" name="cmd" value="_donations">\
\
				<!-- Specify details about the contribution -->\
				<input type="hidden" name="item_name" value="VvvebJs">\
				<input type="hidden" name="item_number" value="Support">\
				<input type="hidden" name="currency_code" value="USD">\
\
				<!-- Display the payment button. -->\
				<input type="image" name="submit"\
				src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif"\
				alt="Donate">\
				<img alt="" width="1" height="1"\
				src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" >\
\
			</form>',
    properties: [{
        name: "Email",
        key: "business",
        htmlAttr: "value",
        child:"input[name='business']",
        inputtype: TextInput
    },{		
        name: "Item name",
        key: "item_name",
        htmlAttr: "value",
        child:"input[name='item_name']",
        inputtype: TextInput
    },{		
        name: "Item number",
        key: "item_number",
        htmlAttr: "value",
        child:"input[name='item_number']",
        inputtype: TextInput
    },{		
        name: "Currency code",
        key: "currency_code",
        htmlAttr: "value",
        child:"input[name='currency_code']",
        inputtype: TextInput
	}],
});
    
Vvveb.Components.extend("_base", "widgets/facebookpage", {
    name: "Facebook Page Plugin",
    attributes: ["data-component-facebookpage"],
    image: "icons/facebook.svg",
    dropHtml: '<img src="' + Vvveb.baseUrl + 'icons/facebook.png">',
	html: `<div data-component-facebookpage><div class="fb-page" 
			 data-href="https://www.facebook.com/facebook" 
			 data-tabs="timeline"
			 data-width="" 
			 data-height="" 
			 data-small-header="true" 
			 data-adapt-container-width="true" 
			 data-hide-cover="false" 
			 data-show-facepile="true">
			 
				<blockquote cite="https://www.facebook.com/facebook" class="fb-xfbml-parse-ignore">
					<a href="https://www.facebook.com/facebook">Facebook</a>
				</blockquote>

			</div>

			<div id="fb-root"></div>
			<script async defer crossorigin="anonymous" src="https://connect.facebook.net/ro_RO/sdk.js#xfbml=1&version=v15.0" nonce="o7Y7zPjy"></script>
		</div>`,

    properties: [{
        name: "Small header",
        key: "small-header",
        htmlAttr: "data-small-header",
        child:".fb-page",
        inputtype: TextInput
    },{		
        name: "Adapt container width",
        key: "adapt-container-width",
        htmlAttr: "data-adapt-container-width",
        child:".fb-page",
        inputtype: TextInput
    },{		
        name: "Hide cover",
        key: "hide-cover",
        htmlAttr: "data-hide-cover",
        child:".fb-page",
        inputtype: TextInput
    },{		
        name: "Show facepile",
        key: "show-facepile",
        htmlAttr: "data-show-facepile",
        child:".fb-page",
        inputtype: TextInput
    },{		
        name: "App Id",
        key: "appid",
        htmlAttr: "data-appId",
        child:".fb-page",
        inputtype: TextInput
	}],
   onChange: function(node, input, value, component) {
	   
	   let newElement = generateElements(this.html)[0];
	   newElement.find(".fb-page").setAttribute(input.htmlAttr, value);

	   frameHead.querySelector("[data-fbcssmodules]").remove();
	   frameBody.querySelector("[data-fbcssmodules]").remove();
	   frameHead.querySelector("script[src^='https://connect.facebook.net']").remove();


	   node.parent().html(newElement.html());
	   return newElement;
	}	
});
    
Vvveb.Components.extend("_base", "widgets/chartjs", {
    name: "Chart.js",
    attributes: ["data-component-chartjs"],
    image: "icons/chart.svg",
	dragHtml: '<img src="' + Vvveb.baseUrl + 'icons/chart.svg">',
    html: '<div data-component-chartjs class="chartjs" data-chart=\'{\
			"type": "line",\
			"data": {\
				"labels": ["Red", "Blue", "Yellow", "Green", "Purple", "Orange"],\
				"datasets": [{\
					"data": [12, 19, 3, 5, 2, 3],\
					"fill": false,\
					"borderColor":"rgba(255, 99, 132, 0.2)"\
				},{\
					"fill": false,\
					"data": [3, 15, 7, 4, 19, 12],\
					"borderColor": "rgba(54, 162, 235, 0.2)"\
				}]\
			}}\' style="min-height:240px;min-width:240px;width:100%;height:100%;position:relative">\
			  <canvas></canvas>\
			</div>',
	chartjs: null,
	ctx: null,
	node: null,

	config: {/*
			type: 'line',
			data: {
				labels: ["Red", "Blue", "Yellow", "Green", "Purple", "Orange"],
				datasets: [{
					data: [12, 19, 3, 5, 2, 3],
					fill: false,
					borderColor:'rgba(255, 99, 132, 0.2)',
				},{
					fill: false,
					data: [3, 15, 7, 4, 19, 12],
					borderColor: 'rgba(54, 162, 235, 0.2)',
				}]
			},*/
	},		

	dragStart: function (node) {
		//check if chartjs is included and if not add it when drag starts to allow the script to load
		body = Vvveb.Builder.frameBody;
		
		if (!body.querySelector("#chartjs-script")) {
			body.append(generateElements('<script id="chartjs-script" src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.min.js"></script>')[0]);
			body.append(generateElements('<script>\
				document.addEventListener("DOMContentLoaded", function() {\
					document.querySelectorAll(".chartjs").forEach(function(el) {\
						let canvas = el.querySelector("canvas");\
						if (!canvas) return;\
						let ctx = canvas.getContext("2d");\
						let config = JSON.parse(el.dataset.chart || "{}");\
						new Chart(ctx, config);\
					});\
				});\
			  </script>')[0]);
		}
		
		return node;
	},
	

	drawChart: function () {
		if (this.chartjs != null) this.chartjs.destroy();
		this.node.dataset.chart = JSON.stringify(this.config);
		
		config = Object.assign({}, this.config);//avoid passing by reference to avoid chartjs to fill the object
		this.chartjs = new Chart(this.ctx, config);
	},
	
	init: function (node) {
		this.node = node;
		this.ctx = node.querySelector("canvas").getContext("2d");
		this.config = JSON.parse(node.dataset.chart);
		this.drawChart();

		return node;
	},
  
  
	beforeInit: function (node) {
		return node;
	},
    
    properties: [{
        name: "Type",
        key: "type",
        inputtype: SelectInput,
        data:{
			options: [{
                text: "Line",
                value: "line"
            },{
                text: "Bar",
                value: "bar"
            },{
                text: "Pie",
                value: "pie"
            },{
                text: "Doughnut",
                value: "doughnut"
            },{
                text: "Polar Area",
                value: "polarArea"
            },{
                text: "Bubble",
                value: "bubble"
            },{
                text: "Scatter",
                value: "scatter"
            },{
                text: "Radar",
                value: "radar"
            }]
       },
		init: function(node) {
			return JSON.parse(node.dataset.chart).type;
		},
       onChange: function(node, value, input, component) {
		   component.config.type = value;
		   component.drawChart();
		   
		   return node;
		}
	 }]
});

function lottieAfterDrop(node) {
	//check if lottie js is included and if not add it when drag starts to allow the script to load
	body = Vvveb.Builder.frameBody;

	if (!body.querySelector("#lottie-js")) {
		let lib = document.createElement('script');
		let code = document.createElement('script');
		lib.id = 'lottie-js';
		lib.type = 'text/javascript';
		lib.src = 'https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js';
		code.type = 'text/javascript';
		code.text = `
		let lottie = [];
		function initLottie(onlyNew = false) {
			if (typeof bodymovin == "undefined") return;
			
			
			let list = document.querySelectorAll('.lottie' + (onlyNew ? ":not(.lottie-initialized)" : "") );
			list.forEach(el => {
				el.replaceChildren();
				let animItem = bodymovin.loadAnimation({
				  wrapper: el,
				  animType: 'svg',
				  loop: (el.dataset.loop  == "true" ? true : false),
				  autoplay: (el.dataset.autoplay == "true" ? true : false),
				  path: el.dataset.path
				});
				
			});
		}

		if (document.readyState !== 'loading') {
			initLottie();
		  } else {
			document.addEventListener('DOMContentLoaded', initLottie);
		  }`;			
		
		body.appendChild(lib);
		body.appendChild(code);
		
		lib.addEventListener('load', function() {		
			Vvveb.Builder.iframe.contentWindow.initLottie();
		});
	} else {
		Vvveb.Builder.iframe.contentWindow.initLottie(true);
	}
	
	return node;
};

Vvveb.Components.add("widgets/lottie", {
    name: "Lottie",
    image: "icons/lottie.svg",
    attributes: ["data-component-lottie"],
    html: `
	  <div class="lottie" data-component-lottie data-path="https://labs.nearpod.com/bodymovin/demo/markus/isometric/markus2.json" data-loop="true" data-autoplay="true">
	  </div>	
	`,
	afterDrop: lottieAfterDrop,
	
    onChange: function (node, property, value) {
		Vvveb.Builder.iframe.contentWindow.initLottie();
		Vvveb.Builder.selectNode(node);
		return node;
	},	
	
    properties: [{
		name: "Path",
        key: "path",
        //inputtype: ImageInput,
        inputtype: TextInput,
		htmlAttr:"data-path",
	},{
		name: "Autoplay",
        key: "autoplay",
		htmlAttr:"data-autoplay",
		inputtype: CheckboxInput,
		inline:true,
        col:4
	},{	name: "Loop",
        key: "loop",
		htmlAttr:"data-loop",
		inputtype: CheckboxInput,
		inline:true,
        col:4
	}]
});
