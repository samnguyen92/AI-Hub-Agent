/**
 * Align Builder Media - File Manager Navigation
 * @package Align
 */

MediaModal.prototype.goto = function(hash) {

			hash = decodeURIComponent(hash).slice(1).split('=');
			let _this = this;

			if (hash.length) {
				let rendered = '';

				// if hash has search in it

				if (hash[0] === 'search') {

					this.filemanager.classList.add('searching');
					rendered = _this.searchData(_this.response, hash[1].toLowerCase());

					if (rendered.length) {
						this.currentPath = hash[0];
						this.render(rendered);
					}
					else {
						this.render(rendered);
					}

				}

				// if hash is some path

				else if (hash[0].trim().length) {

					rendered = this.searchByPath(hash[0]);

					if (rendered.length) {

						this.currentPath = hash[0];
						this.breadcrumbsUrls = this.generateBreadcrumbs(hash[0]);
						this.render(rendered);

					}
					else {
						this.currentPath = hash[0];
						this.breadcrumbsUrls = this.generateBreadcrumbs(hash[0]);
						this.render(rendered);
					}

				}

				// if there is no hash

				else {
					this.currentPath = this.response[0]?.path ?? "";
					this.breadcrumbsUrls.push(this.currentPath);
					this.render(this.searchByPath(this.currentPath));
				}
			}
		}

		// Splits a file path and turns it into clickable breadcrumbs

		MediaModal.prototype.generateBreadcrumbs = function(nextDir){
			let path = nextDir.split('/').slice(0);
			for(let i=1;i<path.length;i++){
				path[i] = path[i-1]+ '/' +path[i];
			}
			return path;
		}


		// Locates a file by path

MediaModal.prototype.searchByPath = function(dir) {
			let path = dir.split('/'),
				demo = this.response,
				flag = 0;

			for(let i=0;i<path.length;i++){
				for(let j=0;j<demo.length;j++){
					if(demo[j].name === path[i]){
						flag = 1;
						demo = demo[j].items;
						break;
					}
				}
			}

			//demo = flag ? demo : [];
			return demo;
		}


		// Recursively search through the file tree

MediaModal.prototype.searchData = function(data, searchTerms) {

			let _this = this;
			let folders = [];
			let files = [];

			let _searchData = function (data, searchTerms) { 
			data.forEach(function(d){
				if(d.type === 'folder') {

						_searchData(d.items,searchTerms);

						if(d.name.toLowerCase().indexOf(searchTerms) >= 0) {
						folders.push(d);
					}
				}
				else if(d.type === 'file') {
						if(d.name.toLowerCase().indexOf(searchTerms) >= 0) {
						files.push(d);
					}
				}
			});
			};
			
			_searchData(data, searchTerms);
			
			return {folders: folders, files: files};
		}


