/**
 * Align Builder Widgets - Facebook, Instagram & Twitter Widgets
 * @package Align
 */

Vvveb.Components.extend("_base", "widgets/facebookcomments", {
    name: "Facebook Comments",
    attributes: ["data-component-facebookcomments"],
    image: "icons/facebook.svg",
    dragHtml: '<img src="' + Vvveb.baseUrl + 'icons/facebook.svg">',
    html: '<div  data-component-facebookcomments><script>(function(d, s, id) {\
			  let js, fjs = d.getElementsByTagName(s)[0];\
			  if (d.getElementById(id)) return;\
			  js = d.createElement(s); js.id = id;\
			  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.6&appId=";\
			  fjs.parentNode.insertBefore(js, fjs);\
			}(document, \'script\', \'facebook-jssdk\'));</script>\
			<div class="fb-comments" \
			data-href="' + window.location.href + '" \
			data-numposts="5" \
			data-colorscheme="light" \
			data-mobile="" \
			data-order-by="social" \
			data-width="100%" \
			></div></div>',
    properties: [{
        name: "Href",
        key: "business",
        htmlAttr: "data-href",
        child:".fb-comments",
        inputtype: TextInput
    },{		
        name: "Item name",
        key: "item_name",
        htmlAttr: "data-numposts",
        child:".fb-comments",
        inputtype: TextInput
    },{		
        name: "Color scheme",
        key: "colorscheme",
        htmlAttr: "data-colorscheme",
        child:".fb-comments",
        inputtype: TextInput
    },{		
        name: "Order by",
        key: "order-by",
        htmlAttr: "data-order-by",
        child:".fb-comments",
        inputtype: TextInput
    },{		
        name: "Currency code",
        key: "width",
        htmlAttr: "data-width",
        child:".fb-comments",
        inputtype: TextInput
	}]
});
/*
Vvveb.Components.extend("_base", "widgets/instagram", {
    name: "Instagram",
    attributes: ["data-component-instagram"],
    image: "icons/instagram.svg",
    drophtml: '<img src="' + Vvveb.baseUrl + 'icons/instagram.png">',
    html: '<div align=center data-component-instagram>\
			<blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="https://www.instagram.com/p/tsxp1hhQTG/" data-instgrm-version="8" style=" background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:658px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);"><div style="padding:8px;"> <div style=" background:#F8F8F8; line-height:0; margin-top:40px; padding:50% 0; text-align:center; width:100%;"> <div style=" background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACwAAAAsCAMAAAApWqozAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAAAMUExURczMzPf399fX1+bm5mzY9AMAAADiSURBVDjLvZXbEsMgCES5/P8/t9FuRVCRmU73JWlzosgSIIZURCjo/ad+EQJJB4Hv8BFt+IDpQoCx1wjOSBFhh2XssxEIYn3ulI/6MNReE07UIWJEv8UEOWDS88LY97kqyTliJKKtuYBbruAyVh5wOHiXmpi5we58Ek028czwyuQdLKPG1Bkb4NnM+VeAnfHqn1k4+GPT6uGQcvu2h2OVuIf/gWUFyy8OWEpdyZSa3aVCqpVoVvzZZ2VTnn2wU8qzVjDDetO90GSy9mVLqtgYSy231MxrY6I2gGqjrTY0L8fxCxfCBbhWrsYYAAAAAElFTkSuQmCC); display:block; height:44px; margin:0 auto -44px; position:relative; top:-22px; width:44px;"></div></div> <p style=" margin:8px 0 0 0; padding:0 4px;"> <a href="https://www.instagram.com/p/tsxp1hhQTG/" style=" color:#000; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none; word-wrap:break-word;" target="_blank">Text</a></p> <p style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; line-height:17px; margin-bottom:0; margin-top:8px; overflow:hidden; padding:8px 0 7px; text-align:center; text-overflow:ellipsis; white-space:nowrap;">A post shared by <a href="https://www.instagram.com/instagram/" style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px;" target="_blank"> Instagram</a> (@instagram) on <time style=" font-family:Arial,sans-serif; font-size:14px; line-height:17px;" datetime="-">-</time></p></div></blockquote>\
			<script async defer src="//www.instagram.com/embed.js"></script>\
		</div>',
    properties: [{
        name: "Widget id",
        key: "instgrm-permalink",
        htmlAttr: "data-instgrm-permalink",
        child: ".instagram-media",
        inputtype: TextInput
    }],
});
*/
Vvveb.Components.extend("_base", "widgets/twitter", {
    name: "Twitter",
    attributes: ["data-component-twitter"],
    image: "icons/twitter.svg",
    dragHtml: '<img src="' + Vvveb.baseUrl + 'icons/twitter.svg">',
    html: '<div data-component-twitter><iframe width="100%" height="100%"src="https://platform.twitter.com/embed/Tweet.html?embedId=twitter-widget-0&frame=false&hideCard=false&hideThread=false&id=943901463998169088"></iframe></div>',
    resizable:true,//show select box resize handlers
    resizeMode:"css",
    twitterRegex : /(?:twitter\.com(?:[^\d]+))(\d+)/i,

    tweet:'',//location
	init: function (node) {
		let iframe = node.querySelector('iframe');
		let src = iframe.getAttribute("src");
		let url = new URL(src);
		let params = new URLSearchParams(url.search);
		
		this.tweet = params.get("id");
		
		if (!this.tweet) {
			if (match = src.match(this.twitterRegex)) {
				this.tweet = match[1];
			}
			
		}

		document.querySelector(".component-properties input[name=tweet]").value = this.tweet;
	},
	    
    onChange: function (node, property, value) {
		tweet_iframe = node.querySelector('iframe');

		if (property.key == "tweet") {
			this[property.key] = value;
			
			tweeturl = 'https://platform.twitter.com/embed/Tweet.html?embedId=twitter-widget-0&frame=false&hideCard=false&hideThread=false&id=' + this.tweet;
			
			tweet_iframe.setAttribute("src",tweeturl);
		}
		
		return node;
	},

    properties: [{
        name: "Tweet",
        key: "tweet",
        inputtype: TextInput,
   		onChange: function(node, value, input, component) {
			
			let twitterRegex = /(?:twitter\.com(?:[^\d]+))(\d+)/i;
			let id = false;

			if (id = value.match(twitterRegex)) {
				document.querySelector(".component-properties input[name=tweet]").value = id[1];

				component.tweet = id[1];
				return id[1];
			}
			
			return node;
		}
	}]
});
