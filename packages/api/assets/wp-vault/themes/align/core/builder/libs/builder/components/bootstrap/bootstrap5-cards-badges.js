/**
 * Align Builder Bootstrap 5 - Cards, Badges & Alerts
 * @package Align
 */

Vvveb.Components.extend("_base","html/alert", {
    classes: ["alert"],
    name: "Alert",
    image: "icons/alert.svg",
    html: '<div class="alert alert-warning alert-dismissible fade show" role="alert">\
		  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">\
			<!--span aria-hidden="true">&times;</span-->\
		  </button>\
		  <strong>Holy guacamole!</strong> You should check in on some of those fields below.\
		</div>',
    properties: [{
        name: "Type",
        key: "type",
        htmlAttr: "class",
        validValues: ["alert-primary", "alert-secondary", "alert-success", "alert-danger", "alert-warning", "alert-info", "alert-light", "alert-dark"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "alert-primary",
                text: "Default"
            },{
                value: "alert-secondary",
                text: "Secondary"
            },{
                value: "alert-success",
                text: "Success"
            },{
                value: "alert-danger",
                text: "Danger"
            },{
                value: "alert-warning",
                text: "Warning"
            },{
                value: "alert-info",
                text: "Info"
            },{
                value: "alert-light",
                text: "Light"
            },{
                value: "alert-dark",
                text: "Dark"
            }]
        }
    }]
});
Vvveb.Components.extend("_base", "html/badge", {
    classes: ["badge"],
    image: "icons/badge.svg",
    name: "Badge",
    html: '<span class="badge bg-primary">Primary badge</span>',
    properties: [{
        name: "Color",
        key: "color",
        htmlAttr: "class",
        validValues:["bg-primary", "bg-secondary", "bg-success", "bg-danger", "bg-warning", "bg-info", "bg-body-secondary", "bg-dark"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "bg-primary",
                text: "Primary"
            },{
                value: "bg-secondary",
                text: "Secondary"
            },{
                value: "bg-success",
                text: "Success"
            },{
                value: "bg-warning",
                text: "Warning"
            },{
                value: "bg-danger",
                text: "Danger"
            },{
                value: "bg-info",
                text: "Info"
            },{
                value: "bg-body-secondary",
                text: "Light"
            },{
                value: "bg-dark",
                text: "Dark"
            }]
        }
     }]
});
Vvveb.Components.extend("_base", "html/card", {
    classes: ["card"],
    image: "icons/panel.svg",
    name: "Card",
    html: '<div class="card">\
		  <img class="card-img-top bg-body-secondary" src="https://picsum.photos/200/300" alt="Card image cap">\
		  <div class="card-body">\
			<h4 class="card-title">Card title</h4>\
			<p class="card-text">Some quick example text to build on the card title and make up the bulk of the card\'s content.</p>\
			<a href="#" class="btn btn-primary">Go somewhere</a>\
		  </div>\
		</div>'
});

Vvveb.Components.extend("_base", "html/listgroup", {
    name: "List Group",
    image: "icons/list_group.svg",
    classes: ["list-group"],
    html: '<ul class="list-group">\n  <li class="list-group-item">\n    <span class="badge bg-success">14</span>\n    Cras justo odio\n  </li>\n  <li class="list-group-item">\n    <span class="badge bg-primary">2</span>\n    Dapibus ac facilisis in\n  </li>\n  <li class="list-group-item">\n    <span class="badge bg-danger">1</span>\n    Morbi leo risus\n  </li>\n</ul>',
	properties: [{
        name: "Flush",
        key: "flush",
        htmlAttr: "class",
        validValues: ["", "list-group-flush"],
        inputtype: ToggleInput,
        data: {
            on: "list-group-flush",
            off: ""
        }
    },{
        name: "Numbered",
        key: "numbered",
        htmlAttr: "class",
        validValues: ["", "list-group-numbered"],
        inputtype: ToggleInput,
        data: {
            on: "list-group-numbered",
            off: ""
        }
    },{
        name: "Horizontal",
        key: "horizontal",
        htmlAttr: "class",
        validValues: ["", "list-group-horizontal"],
        inputtype: ToggleInput,
        data: {
            on: "list-group-horizontal",
            off: ""
        }
    }]    
});

Vvveb.Components.extend("_base", "html/listitem", {
    name: "List Item",
    classes: ["list-group-item"],
    html: '<li class="list-group-item"><span class="badge bg-primary">14</span> Cras justo odio</li>',
	properties: [{
        name: "Background",
        key: "background",
        htmlAttr: "class",
        validValues:["list-group-item-primary", "list-group-item-secondary", "list-group-item-success", "list-group-item-danger", "list-group-item-warning", "list-group-item-info", "list-group-item-light", "list-group-item-dark"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: "Default"
            },{
                value: "list-group-item-primary",
                text: "Primary"
            },{
                value: "list-group-item-secondary",
                text: "Secondary"
            },{
                value: "list-group-item-success",
                text: "Success"
            },{
                value: "list-group-item-warning",
                text: "Warning"
            },{
                value: "list-group-item-danger",
                text: "Danger"
            },{
                value: "list-group-item-info",
                text: "Info"
            },{
                value: "list-group-item-light",
                text: "Light"
            },{
                value: "list-group-item-dark",
                text: "Dark"
            }]
        }
     },	{
        name: "Active",
        key: "active",
        htmlAttr: "class",
        validValues: ["", "active"],
        inputtype: ToggleInput,
        inline:true,
        col:6,
        data: {
            on: "active",
            off: ""
        }
    },{
        name: "Disabled",
        key: "disabled",
        htmlAttr: "class",
        validValues: ["", "disabled"],
        inputtype: ToggleInput,
        inline:true,
        col:6,
        data: {
            on: "disabled",
            off: ""
        }
    }]    
});
