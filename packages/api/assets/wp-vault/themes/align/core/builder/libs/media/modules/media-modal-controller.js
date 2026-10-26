/**
 * Align Builder Media - Modal Controller Class
 * @package Align
 */

function ucFirst(str) {
  if (!str) return str;

  return str[0].toUpperCase() + str.slice(1);
}

if (typeof mediaScanUrl === "undefined") {
	var mediaPath = "/media/";
	var mediaScanUrl = "scan.php";
	var uploadUrl = "upload.php";
}

class MediaModal {
	constructor (modal = true)
	{
		this.isInit = false;
		this.isModal = modal;
		
		this.modalHtml = 
		`
		<div class="modal fade modal-full" id="MediaModal" tabindex="-1" role="dialog" aria-labelledby="MediaModalLabel" aria-hidden="true">
		  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
			<div class="modal-content">
			  <div class="modal-header">
				<h5 class="modal-title fw-normal" id="MediaModalLabel">Media</h5>
                
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
				  <!-- <span aria-hidden="true"><i class="la la-times la-lg"></i></span> -->
				</button>
			  </div>
			  <div class="modal-body">
	
                      <div class="filemanager">

						<div class="top-right d-flex justify-content-between">
                             
							<div class="">          
								<div class="breadcrumbs"></div>
							</div>
                                       
                                   
							<div class="">                   
								<div class="search">
									<input type="search" id="media-search-input" placeholder="Find a file.." />
								</div>
								
								<button class="btn btn-outline-secondary btn-sm btn-icon me-5 float-end" 
								   data-bs-toggle="collapse" 
								   data-bs-target=".upload-collapse" 
								   aria-expanded="false" 
								   >
								   <i class="la la-upload la-lg"></i>
									Upload file
								</button>
							</div>
							
						</div>

						<div class="top-panel">

							<div class="upload-collapse collapse">

								<button id="upload-close" type="button" class="btn btn-sm btn-light" aria-label="Close" data-bs-toggle="collapse" data-bs-target=".upload-collapse" aria-expanded="true">
								   <span aria-hidden="true"><i class="la la-times la-lg"></i></span>
								</button>
								
							   <h3>Drop or choose files to upload</h3>
							   
							   <input type="file" multiple class=""> 
								
								<div class="status"></div>
							</div>


						</div>
						
						<div class="display-panel">
							
							<ul class="data" id="media-files"></ul>
						
							<div class="nothingfound">
								<div class="nofiles">
									<i class="la la-folder-open"></i>
								</div>
								<div>No files here.</div>
								<div class="mt-4">
									<button class="btn btn-outline-secondary btn-sm btn-icon" data-bs-toggle="collapse" data-bs-target=".upload-collapse" aria-expanded="false">
									<i class="la la-upload la-lg"></i>
									Upload file 
									</button>
								</div>
							</div>
						</div>
					</div>

			  </div>
			  <div class="modal-footer justify-content-between">
			  
				<div class="align-left">
			
				</div>
			  
				<div class="align-right">
					<button type="button" class="btn btn-secondary btn-icon me-1" data-bs-dismiss="modal">
						<i class="la la-times"></i>
						<span>Cancel</span>
					</button>
					<button type="button" class="btn btn-primary btn-icon save-btn">
						<i class="la la-check"></i>
						<span>Add selected</span>
					</button>
				</div>
			  </div>
			</div>
		  </div>
		</div>`;
		
		this.response = [],
		this.currentPath = '';
		this.breadcrumbsUrls = [];
		this.filemanager = null;
		this.breadcrumbs = null;
		this.fileList = null;
		this.mediaPath = mediaPath;
		this.type = "single";
		this.container = document.getElementById("MediaModal");
	}
	
	getResponse(response) {
		return this.response;
	}

	setResponse(response) {
		this.response = response;
		this.currentPath = '',
		this.breadcrumbsUrls = [];
	}
	
	addModalHtml() {
		if (this.isModal) document.body.append(generateElements(this.modalHtml)[0]);
		this.container = document.getElementById("MediaModal");
		this.container.querySelector(".save-btn").addEventListener("click", () => this.save());
	}
	
	showUploadLoading() {
		this.container.querySelector(".upload-collapse .status").innerHTML = `
		<div class="spinner-border" style="width: 5rem; height: 5rem;margin: 5rem auto; display:block" role="status">
		  <span class="visually-hidden">Loading...</span>
		</div>`;
	}

	hideUploadLoading() {
		this.container.querySelector(".upload-collapse .status").innerHTML = '';
	}
	
	save() {
		
		let file = this.container.querySelector(".files input:checked").value ?? false;
		let src = file;
		
		if (!file) return;

		if (file.indexOf("//") == -1) {
			src = this.mediaPath + file;
		}

		if (this.targetThumb) {
			document.querySelector(this.targetThumb).setAttribute("src", src);
		}
		
		if (this.callback) {
			this.callback(src);
		}

		if (this.targetInput) {
			let input = document.querySelector(this.targetInput);
			input.value = file;
			const e = new Event("change",{bubbles: true});
			input.dispatchEvent(e);
			//$(this.targetInput).val(file).trigger("change");
		}

		let modal = bootstrap.Modal.getOrCreateInstance(this.container);
		if (this.isModal) modal.hide();
	}
	
	init() {
		if (!this.isInit) {
			if (this.isModal) this.addModalHtml();
			let self = this;

			this.initGallery();
			this.isInit = true;

			this.container.querySelector(".filemanager input[type=file]").addEventListener("change", this.onUpload);
			this.container.querySelector(".filemanager").addEventListener("click", function (e) {
				let element = e.target.closest(".btn-delete");
				if (element) {
					 self.deleteFile(element);
				} else {
					element = e.target.closest(".btn-rename");
					if (element) {
						 self.renameFile(element);
					}
				}
			});

			const event = new CustomEvent( "mediaModal:init", {detail: { type:this.type, targetInput:this.targetInput, targetThumb:this.targetThumb, callback:this.callback} });
			window.dispatchEvent(event);			
		}
	}
	
	open(element, callback) {
		if (element instanceof Element) {
			this.targetInput = element.dataset.targetInput;
			this.targetThumb = element.dataset.targetThumb;
			if (element.dataset.type) {
				this.type = element.dataset.type;
			}
		} else if (element) {
			this.targetInput = element.targetInput;
			this.targetThumb = element.targetThumb;
			if (element.type) {
				this.type = element.type;
			}
		}
		
		this.callback = callback;
		this.init();

		let modal = bootstrap.Modal.getOrCreateInstance(this.container);
		if (this.isModal) modal.show();
	}


	initGallery() {
		this.filemanager = this.container.querySelector('.filemanager'),
		this.breadcrumbs = this.container.querySelector('.breadcrumbs'),
		this.fileList = this.filemanager.querySelector('.data');
		let _this = this;

		// Start by fetching the file data from scan.php with an AJAX request
		if (!this.response.length) {//if response set by a plugin ignore fetch
			fetch(mediaScanUrl)
			.then((response) => {
				if (!response.ok) { throw new Error(response) }
				return response.json();
			})
			.then((data) => {
				 _this.response = [data],
				 _this.currentPath = '',
				 _this.breadcrumbsUrls = [];

				let folders = [],
					files = [];
					
				window.dispatchEvent(new HashChangeEvent("hashchange"));
			})
			.catch(error => {
				console.error("Media Error:", error?.message || error?.statusText || error);
				displayToast("bg-danger", "Error", "Error loading media!");
			});
		} else {
			this.goto("");
		}
		// This event listener monitors changes on the URL. We use it to
		// capture back/forward navigation in the browser.

		window.addEventListener('hashchange', function(){

			_this.goto(window.location.hash);

			// We are triggering the event. This will execute 
			// this function on page load, so that we show the correct folder:

		});


		// Hiding and showing the search box
		let search = this.filemanager.querySelector('input[type=search]');

		this.filemanager.querySelector('.search').addEventListener("click", function(){
			let _search = this;

			_search.querySelectorAll('span').forEach(function (el,i) { el.style.display = "none";});
			search.style.display = "block";
			search.focus();

		});


		// Listening for keyboard input on the search field.
		// We are using the "input" event which detects cut and paste
		// in addition to keyboard input.

		search.addEventListener('input', function(e) {

			let folders = [];
			let files = [];

			let value = this.value.trim();

			if(value.length) {

				_this.filemanager.classList.add('searching');

				// Update the hash on every key stroke
				window.location.hash = 'search=' + value.trim();

			}

			else {

				_this.filemanager.classList.remove('searching');
				window.location.hash = encodeURIComponent(_this.currentPath);

			}

		});
		
		search.addEventListener('keyup', function(e) {

			// Clicking 'ESC' button triggers focusout and cancels the search

			let search = this;

			if(e.keyCode == 27) {

				search.trigger('focusout');

			}

		});
		
		search.addEventListener("focusout", function(e) {

			// Cancel the search

			let search = this;

			if(!search.value.trim().length) {

				window.location.hash = encodeURIComponent(_this.currentPath);
				search.style.display = 'none';
				search.parentNode.querySelectorAll('span').style.display = '';

			}

		});

		// Clicking on folders

		this.fileList.addEventListener('click', function(e) {
			let el = event.target.closest('li.folders');
			if (el) {
				e.preventDefault();

				let nextDir = el.querySelector('a').getAttribute('href');

				if(_this.filemanager.classList.contains('searching')) {

					// Building the this.breadcrumbs

					_this.breadcrumbsUrls = _this.generateBreadcrumbs(nextDir);

					_this.filemanager.classList.remove('searching');
					let search = _this.filemanager.querySelector('input[type=search]');
					search.val('')
					search.style.display = 'none';
					_this.filemanager.querySelectorAll('span').forEach(e => e.style.display = '');
				}
				else {
					_this.breadcrumbsUrls.push(nextDir);
				}

				window.location.hash = encodeURIComponent(nextDir);
				_this.currentPath = nextDir;
			}
		});


		// Clicking on this.breadcrumbs

		this.breadcrumbs.addEventListener('click', function(e){
			let el = event.target.closest('a');
			if (el) {
				e.preventDefault();

				let index = [...el.parentNode.children].indexOf(el),
					nextDir = _this.breadcrumbsUrls[index];
					nextDir = el.getAttribute("href");

				_this.breadcrumbsUrls.length = Number(index);

				window.location.hash = encodeURIComponent(nextDir);
			}
		});
	}


		// Navigates to the given hash (path)

}
