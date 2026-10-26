/**
 * Align Builder Bootstrap - Grid Columns, Rows & Video
 * @package Align
 */

Vvveb.Components.add("html/gridcolumn", {
    name: "Grid Column",
    image: "icons/grid_row.svg",
    classesRegex: ["col-"],
    html: '<div class="col-sm-4"><h3>col-sm-4</h3></div>',
    properties: [{
        name: "Column",
        key: "column",
        inputtype: GridInput,
        data: {hide_remove:true},
		
		beforeInit: function(node) {
			let _class = (node && node.getAttribute) ? (node.getAttribute("class") || "") : "";
			
			var reg = /col-([^-\$ ]*)?-?(\d+)/g; 
			var match;

			while ((match = reg.exec(_class)) != null) {
				this.data["col" + ((match[1] != undefined)?"_" + match[1]:"")] = match[2];
			}
		},
		
		onChange: function(node, value, input) {
			var _class = (node && node.getAttribute) ? (node.getAttribute("class") || "") : "";
			
			//remove previous breakpoint column size
			_class = _class.replace(new RegExp(input.name + '-\\d+?'), '');
			//add new column size
			if (value) _class +=  ' ' + input.name + '-' + value;
			if (node && node.setAttribute) node.setAttribute("class", _class);
			
			return node;
		},				
	}]
});
Vvveb.Components.add("html/gridrow", {
    name: "Grid Row",
    image: "icons/grid_row.svg",
    classes: ["row"],
    html: '<div class="row"><div class="col-sm-4"><h3>col-sm-4</h3></div><div class="col-sm-4 col-5"><h3>col-sm-4</h3></div><div class="col-sm-4"><h3>col-sm-4</h3></div></div>',
    children :[{
		name: "html/gridcolumn",
		classesRegex: ["col-"],
	}],
	beforeInit: function (node)
	{
		properties = [];
		var i = 0;
		var j = 0;
		
		if (node && node.querySelectorAll) {
			node.querySelectorAll('[class*="col-"]').forEach(function(colEl) {
				let _class = colEl.getAttribute("class") || "";
			
				var reg = /col-([^-\$ ]*)?-?(\d+)/g; 
				var match;
				var data = {};

				while ((match = reg.exec(_class)) != null) {
					data["col" + ((match[1] != undefined)?"_" + match[1]:"")] = match[2];
				}
				
				i++;
				properties.push({
					name: "Column " + i,
					key: "column" + i,
					columnNode: colEl,
					col:12,
					inline:true,
					inputtype: GridInput,
					data: data,
					onChange: function(node, value, input) {
						let column = this.columnNode;
						if (!column) return node;
						
						//if remove button is clicked remove column and render row properties
						if (input.nodeName == 'BUTTON')
						{
							column.remove();
							Vvveb.Components.render("html/gridrow");
							return node;
						}

						//if select input then change column class
						let curClass = column.getAttribute("class") || "";
						
						//remove previous breakpoint column size
						curClass = curClass.replace(new RegExp(input.name + '-\\d+?'), '');
						//add new column size
						if (value) curClass +=  ' ' + input.name + '-' + value;
						column.setAttribute("class", curClass);
						
						return node;
					},	
				});
			});
		}
		
		//remove all column properties
		this.properties = this.properties.filter(function(item) {
			return item.key.indexOf("column") === -1;
		});
		
		//add remaining properties to generated column properties
		properties.push(this.properties[0]);
		
		this.properties = properties;
		return node;
	},
    
    properties: [{
        name: "Column",
        key: "column1",
        inputtype: GridInput
	}, {
        name: "Column",
        key: "column1",
        inline:true,
        col:12,
        inputtype: GridInput
	}, {
        name: "",
        key: "addChild",
        inputtype: ButtonInput,
        data: {text:"Add column", icon:"la la-plus"},
        onChange: function(node)
        {
			 if (node && node.append) {
			 	node.append(generateElements('<div class="col-3">Col-3</div>')[0]);
			 }
			 
			 //render component properties again to include the new column inputs
			 Vvveb.Components.render("html/gridrow");
			 
			 return node;
		}
	}]
});
   

Vvveb.Components.extend("_base", "_base", {
	 properties: [
	 {
        name: "Font family",
        key: "font-family",
		htmlAttr: "style",
        sort: base_sort++,
        col:6,
		inline:true,
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "extended"
			}, {
				value: "Ggoogle ",
				text: "google"
			}]
		}
    }]
});
