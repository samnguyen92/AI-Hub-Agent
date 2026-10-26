/**
 * Align Builder Media - Upload Dropzone & File Ops
 * @package Align
 */

MediaModal.prototype.onUpload = function(event) {
			let file;
			if (this.files && this.files[0]) {
				Vvveb.MediaModal.showUploadLoading();
				let reader = new FileReader();
				reader.onload = imageIsLoaded;
				reader.readAsDataURL(this.files[0]);
				//reader.readAsBinaryString(this.files[0]);
				file = this.files[0];
			}

			function imageIsLoaded(e) {
					
					let image = e.target.result;
					
					let formData = new FormData();
					formData.append("file", file);
					formData.append("mediaPath", Vvveb.MediaModal.mediaPath + Vvveb.MediaModal.currentPath);
					formData.append("onlyFilename", true);
		
					fetch(uploadUrl, {method: "POST",  body: formData})
					.then((response) => {
						if (!response.ok) {
							return Promise.resolve(response.text()).then((responseInText) => {
								return Promise.reject([response, responseInText]);
							});
						}
  						//if (!response.ok) { throw new Error(response) }
						return response.text()
					})
					.then((data) => {
						let fileElement = Vvveb.MediaModal.addFile({
							name:data,
							type:"file",
							path: Vvveb.MediaModal.currentPath + "/" + data,
							size:1
						},true);
						
						fileElement.scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
						
						Vvveb.MediaModal.hideUploadLoading();				
					})
					.catch((error) => {
						let [response, responseInText] = error;
						let message = responseInText ?? error?.message ?? error?.statusText ?? "Error uploading!";
						Vvveb.MediaModal.hideUploadLoading();						
						displayToast("bg-danger", "Error", message.substr(0, 200));
						if (response.text && !response.bodyUsed) {
							response.text().then( errorMessage => {
								let message = errorMessage.substr(0, 200);
								console.log(message);
								displayToast("bg-danger", "Error", message);
							});			
						}			
					});		
			}
		}	
	
MediaModal.prototype.deleteFile = function(el) {
			let parent = el.closest("li");
			let file = parent.querySelector('input[type ="hidden"]').value;
			if (confirm(`Are you sure you want to delete "${file}"template?`)) {
				
			fetch(deleteUrl, {method: "POST",  body: new URLSearchParams({file})})
				.then((response) => {
					if (!response.ok) {  return Promise.reject(response);  }
					return response.text();
				})
				.then((data) => {
					let bg = "bg-success";
					if (data.success) {		
					} else {
						//bg = "bg-danger";
					}

					displayToast(bg, "Delete", data.message ?? data);
				
					parent.remove();	
				})
				.catch(error => {
					console.log(error);
					let message = error?.message ?? error?.statusText ?? "Error deleting file!";
					displayToast("bg-danger", "Error", message);
					error.text().then( errorMessage => {
						let message = errorMessage.substr(0, 200);
						displayToast("bg-danger", "Error", message);
					})	
				});	
			}
		}

MediaModal.prototype.renameFile = function(el) {
			let parent = el.closest("li");
			let file = parent.querySelector('input[type ="hidden"]').value;
			let newfile = prompt(`Enter new file name for "${file}"`, file);

			if (newfile) {
				fetch(renameUrl, {method: "POST",  body: {file, newfile}})
				.then((response) => {
					console.log(response);
					if (!response.ok) { throw new Error(response) }
					return response.text()
				})
				.then((data) => {
					let bg = "bg-success";
					if (data.success) {		
					} else {
						//bg = "bg-danger";
					}
					
					displayToast(bg, "Save", data.message ?? data);
				})
				.catch(error => {
					console.log(error);
					displayToast("bg-danger", "Error", "Error renaming file!");
				});	
			}
		}
		
MediaModal.prototype.addFile = function(f, selected) {
				let _this= this;
				let isImage = false;
				let actions = '';
				
				let fileSize = _this.bytesToSize(f.size),
						name = _this.escapeHTML(f.name),
						fileType = name.split('.'),
						icon = '<span class="icon file"></span>';

					fileType = fileType[fileType.length-1];
					
					if (fileType == "jpg" || fileType == "jpeg" || fileType == "png" || fileType == "gif" || fileType == "svg" || fileType == "webp") {
						
						//icon = '<div class="image" style="background-image: url(' + _this.mediaPath + f.path + ');"></div>';
						icon = '<img class="image" loading="lazy" src="' + _this.mediaPath + f.path + '">';
						isImage = true;
					} else {
						icon = '<span class="icon file f-'+fileType+'">.'+fileType+'</span>';
					}
					//icon = '<span class="icon file f-'+fileType+'">.'+fileType+'</span>';

				
				
				actions += '<a href="javascript:void(0);" title="Rename" class="btn btn-outline-primary btn-sm border-0 btn-rename"><i class="la la-edit"></i></a> <a href="javascript:void(0);" title="Delete" class="btn btn-outline-danger btn-sm border-0 btn-delete"><i class="la la-trash"></i></a>';

				const event = new CustomEvent("mediaModal:fileActions", {detail: { file: _this.mediaPath + f.path, name, fileType, fileSize, isImage, fileType, actions} });
				window.dispatchEvent(event);			

				if (isImage) actions += '<a href="javascript:void(0);" class="preview-link p-2"><i class="la la-search-plus"></i></a>';
				
				let file = generateElements('<li class="files">\
						<label class="form-check">\
						<input type="hidden" value="' +  _this.mediaPath + f.path + '" name="filename[]">\
						  <input type="' + ((_this.type == "single") ? "radio" : "checkbox") + '" class="form-check-input" value="' + f.path + '" name="file[]" ' + ((selected == "single") ? "checked" : "") + '><span class="form-check-label"></span>\
						  <div href="#\" class="files">'+icon+'<div class="info"><div class="name">'+ name +'</div><span class="details">'+fileSize+'</span>\
							' + actions + '\
							 <div class="preview">\
								<img src="' + _this.mediaPath + f.path + '">\
								<div>\
									<span class="name">'+ name +'</span><span class="details">'+fileSize+'</span>\
								</div>\
							</div>\
						  </div>\
						</label>\
					</li>')[0];
				
				_this.fileList.append(file);

				if (selected) {
					file.querySelector("input[type ='radio'], input[type='checkbox']").checked = true;
				}
				
				return file;
		}

		
MediaModal.prototype.render = function(data) {

			let scannedFolders = [],
				scannedFiles = [];

			if(Array.isArray(data)) {

				data.forEach(function (d) {

					if (d.type === 'folder') {
						scannedFolders.push(d);
					}
					else if (d.type === 'file') {
						scannedFiles.push(d);
					}

				});

			}
			else if(typeof data === 'object') {

				scannedFolders = data.folders;
				scannedFiles = data.files;

			}


			// Empty the old result and make the new one

			this.fileList.replaceChildren();//.style.display = 'none';
			if(!scannedFolders.length && !scannedFiles.length) {
				this.filemanager.querySelector('.nothingfound').style.display = '';
			}
			else {
				this.filemanager.querySelector('.nothingfound').style.display = 'none';
			}

			let _this = this;
			
			if(scannedFolders.length) {

				scannedFolders.forEach(function(f) {

					let itemsLength = f.items.length,
						name = _this.escapeHTML(f.name),
						icon = '<span class="icon folder"></span>';

					if(itemsLength) {
						icon = '<span class="icon folder full"></span>';
					}

					if(itemsLength == 1) {
						itemsLength += ' item';
					}
					else if(itemsLength > 1) {
						itemsLength += ' items';
					}
					else {
						itemsLength = 'Empty';
					}

					let folder = generateElements('<li class="folders"><a href="'+ f.path +'" title="'+ f.path +'" class="folders">'+icon+'<div class="info"><span class="name">' + name + '</span> <span class="details">' + itemsLength + '</span></div></a></li>')[0];
					_this.fileList.append(folder);
				});

			}

			if(scannedFiles.length) {

				scannedFiles.forEach(function(f) {

					_this.addFile(f);
					
				});

			}


			// Generate the breadcrumbs

			let url = '';

			if(this.filemanager.classList.contains('searching')){

				url = '<span>Search results: </span>';
				this.fileList.classList.remove('animated');

			}
			else {

				this.fileList.classList.add('animated');

				this.breadcrumbsUrls.forEach(function (u, i) {

					let name = u.split('/');

					if (i !== _this.breadcrumbsUrls.length - 1) {
						url += '<a href="'+u+'"><span class="folderName">' + name[name.length-1] + '</span></a> <span class="arrow">→</span> ';
					}
					else {
						url += '<span class="folderName">' + name[name.length-1] + '</span>';
					}

				});

			}

			this.breadcrumbs.replaceChildren();
			this.breadcrumbs.appendChild(generateElements('<a href="/"><i class="la la-home"></i><span class="folderName">&ensp;home</span></a>')[0]);
			this.breadcrumbs.appendChild(generateElements('<span>' + url + '</span>')[0]);
			
			// Show the generated elements

			this.fileList.animate({'display':'inline-block'});

		}


		// This function escapes special html characters in names

MediaModal.prototype.escapeHTML = function(text) {
			return text.replace(/\&/g,'&amp;').replace(/\</g,'&lt;').replace(/\>/g,'&gt;');
		}


		// Convert file sizes from bytes to human readable units

		MediaModal.prototype.bytesToSize = function(bytes) {
			let sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
			if (bytes == 0) return '0 Bytes';
			let i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
			return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
		};

window.Vvveb = window.Vvveb || {};
Vvveb.MediaModal = new MediaModal();

