/**
 * Align Builder HTML Components - Form Container & Actions
 * @package Align
 */

Vvveb.Components.extend("_base", "html/form", {
    nodes: ["form"],
    image: "icons/form.svg",
    name: "Form",
    html: `<form action="" method="POST">
	  <div class="mb-3">
		<label for="exampleInputEmail1" class="form-label">Email address</label>
		<input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
		<div id="emailHelp" class="form-text">We'll never share your email with anyone else.</div>
	  </div>
	  <div class="mb-3">
		<label for="exampleInputPassword1" class="form-label">Password</label>
		<input type="password" class="form-control" id="exampleInputPassword1">
	  </div>
	  <div class="mb-3 form-check">
		<input type="checkbox" class="form-check-input" id="exampleCheck1">
		<label class="form-check-label" for="exampleCheck1">Check me out</label>
	  </div>
	  <button type="submit" class="btn btn-primary">Submit</button>
	</form>`,
    properties: [/*{
        name: "Style",
        key: "style",
        htmlAttr: "class",
        validValues: ["", "form-search", "form-inline", "form-horizontal"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: "Default"
            }, {
                value: "form-search",
                text: "Search"
            }, {
                value: "form-inline",
                text: "Inline"
            }, {
                value: "form-horizontal",
                text: "Horizontal"
            }]
        }
    }, */{
        name: "Action",
        key: "action",
        htmlAttr: "action",
        inputtype: TextInput
    }, {
        name: "Method",
        key: "method",
        htmlAttr: "method",
        inputtype: SelectInput,
        data: {
            options: [{
                value: "post",
                text: "Post"
            }, {
                value: "get",
                text: "Get"
            }]
        }
    }, {
        name: "Encoding type",
        key: "enctype",
        htmlAttr: "enctype",
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: ""
            }, {
                value: "application/x-www-form-urlencoded",
                text: "Url encoded (default)"
            }, {
                value: "multipart/form-data",
                text: "Multipart (for file upload)"
            }, {
                value: "text/plain",
                text: "Text plain"
            }]
        }
    }]
});
