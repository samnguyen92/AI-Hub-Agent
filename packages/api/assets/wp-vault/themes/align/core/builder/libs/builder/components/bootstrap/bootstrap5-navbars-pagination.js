/**
 * Align Builder Bootstrap 5 - Breadcrumbs, Pagination, Progress & Navbar
 * @package Align
 */

Vvveb.Components.extend("_base", "html/breadcrumbs", {
    classes: ["breadcrumb"],
    name: "Breadcrumbs",
    image: "icons/breadcrumbs.svg",
    html: `<ol class="breadcrumb">
			<li class="breadcrumb-item"><a href="#">Home</a></li>
			<li class="breadcrumb-item"><a href="#">Library</a></li>
			s<li class="breadcrumb-item active" aria-current="page"><a href="#">Book</a></li>
		  </ol>`,
	properties: [{
		name: "Divider",
		key: "--bs-breadcrumb-divider",
		htmlAttr: "style",
		inputtype: TextInput
	}]        
});

Vvveb.Components.extend("_base", "html/breadcrumbitem", {
	classes: ["breadcrumb-item"],
    name: "Breadcrumb Item",
    html: '<li class="breadcrumb-item"><a href="#">Library</a></li>',
    properties: [{
        name: "Active",
        key: "active",
        htmlAttr: "class",
        validValues: ["", "active"],
        inputtype: ToggleInput,
        data: {
            on: "active",
            off: ""
        }
    }]
});
Vvveb.Components.extend("_base", "html/pagination", {
    classes: ["pagination"],
    name: "Pagination",
    image: "icons/pagination.svg",
    html: '<nav aria-label="Page navigation example">\
	  <ul class="pagination">\
		<li class="page-item"><a class="page-link" href="#">Previous</a></li>\
		<li class="page-item"><a class="page-link" href="#">1</a></li>\
		<li class="page-item"><a class="page-link" href="#">2</a></li>\
		<li class="page-item"><a class="page-link" href="#">3</a></li>\
		<li class="page-item"><a class="page-link" href="#">Next</a></li>\
	  </ul>\
	</nav>',

    properties: [{
        name: "Size",
        key: "size",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["pagination-lg", "pagination-sm"],
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "pagination-lg",
                text: "Large"
            },{
                value: "pagination-sm",
                text: "Small"
            }]
        }
    },{
        name: "Alignment",
        key: "alignment",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["justify-content-center", "justify-content-end"],
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "justify-content-center",
                text: "Center"
            },{
                value: "justify-content-end",
                text: "Right"
            }]
        }
    }]	
});
Vvveb.Components.extend("_base", "html/pageitem", {
	classes: ["page-item"],
    html: '<li class="page-item"><a class="page-link" href="#">1</a></li>',
    name: "Pagination Item",
    properties: [{
        name: "Link To",
        key: "href",
        htmlAttr: "href",
        child:".page-link",
        inputtype: LinkInput
    },{
        name: "Active",
        key: "active",
        htmlAttr: "class",
        validValues: ["active"],
        inputtype: ToggleInput,
        data: {
            on: "active",
            off: ""
        }
    },{
        name: "Disabled",
        key: "disabled",
        htmlAttr: "class",
        validValues: ["disabled"],
        inputtype: ToggleInput,
        data: {
            on: "disabled",
            off: ""
        }
   }]
});
Vvveb.Components.extend("_base", "html/progress", {
    classes: ["progress"],
    name: "Progress Bar",
    image: "icons/progressbar.svg",
    html: '<div class="progress"><div class="progress-bar w-25"></div></div>',
    properties: [{
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
        name: "Progress",
        key: "background",
        child:".progress-bar",
		htmlAttr: "class",
        validValues: ["", "w-25", "w-50", "w-75", "w-100"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: "None"
            },{
                value: "w-25",
                text: "25%"
            },{
                value: "w-50",
                text: "50%"
            },{
                value: "w-75",
                text: "75%"
            },{
                value: "w-100",
                text: "100%"
            }]
        }
    }, 
    {
        name: "Progress background",
        key: "background",
        child:".progress-bar",
		htmlAttr: "class",
        validValues: bgcolorClasses,
        inputtype: SelectInput,
        data: {
            options: bgcolorSelectOptions
        }
    },{
        name: "Striped",
        key: "striped",
        child:".progress-bar",
        htmlAttr: "class",
        validValues: ["", "progress-bar-striped"],
        inputtype: ToggleInput,
        data: {
            on: "progress-bar-striped",
            off: "",
        }
    },{
        name: "Animated",
        key: "animated",
        child:".progress-bar",
        htmlAttr: "class",
        validValues: ["", "progress-bar-animated"],
        inputtype: ToggleInput,
        data: {
            on: "progress-bar-animated",
            off: "",
        }
    }]
});
Vvveb.Components.extend("_base", "html/navbar", {
    classes: ["navbar"],
    image: "icons/navbar.svg",
    name: "Nav Bar",
    html: `<nav class="navbar navbar-expand-lg bg-body-secondary bg-body-tertiary">
			  <div class="container-fluid">
				<a class="navbar-brand" href="#">Navbar</a>
				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
				  <span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="navbarTogglerDemo02">
				  <ul class="navbar-nav me-auto mb-2 mb-lg-0">
					<li class="nav-item">
					  <a class="nav-link active" aria-current="page" href="#">Home</a>
					</li>
					<li class="nav-item">
					  <a class="nav-link" href="#">Link</a>
					</li>
					<li class="nav-item">
					  <a class="nav-link disabled">Disabled</a>
					</li>
				  </ul>
				  <form class="d-flex" role="search">
					<input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
					<button class="btn btn-outline-success" type="submit">Search</button>
				  </form>
				</div>
			  </div>
			</nav>`,
    
    properties: [{
        name: "Color theme",
        key: "color",
        htmlAttr: "class",
        validValues: ["navbar-light", "navbar-dark"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "navbar-light",
                text: "Light"
            },{
                value: "navbar-dark",
                text: "Dark"
            }]
        }
    },{
        name: "Background color",
        key: "background",
        htmlAttr: "class",
        validValues: bgcolorClasses,
        inputtype: SelectInput,
        data: {
            options: bgcolorSelectOptions
        }
    },{
        name: "Placement",
        key: "placement",
        htmlAttr: "class",
        validValues: ["fixed-top", "fixed-bottom", "sticky-top"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "fixed-top",
                text: "Fixed Top"
            },{
                value: "fixed-bottom",
                text: "Fixed Bottom"
            },{
                value: "sticky-top",
                text: "Sticky top"
            }]
        }
    }]
});
