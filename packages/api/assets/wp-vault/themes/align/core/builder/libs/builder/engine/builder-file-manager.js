/**
 * Align Builder Engine - File Manager (Pages Tree & Routing)
 * @package Align
 */

Vvveb.FileManager = {
	tree:false,
	pages:{},
	currentPage: false,
	allowedComponents: {},
	
	init: function(allowedComponents = {}) {
		
		this.allowedComponents = allowedComponents;
		this.tree = document.querySelector("#filemanager .tree > ol");
		if (!this.tree) return;
		this.tree.replaceChildren();
		
		this.tree.addEventListener("change", function (e) {
			let checkbox = e.target.closest("li.folder > input[type=checkbox]");
			if (checkbox) {
				let folderLi = checkbox.closest("li.folder");
				let folderName = folderLi?.dataset?.folder || folderLi?.getAttribute('data-folder');
				if (checkbox.checked) {
					// Single-open accordion mode: collapse all other folders
					const allFolders = Vvveb.FileManager.tree ? Vvveb.FileManager.tree.querySelectorAll("li.folder[data-folder]") : [];
					allFolders.forEach(otherLi => {
						if (otherLi !== folderLi) {
							const otherCb = otherLi.querySelector(":scope > input[type=checkbox]");
							if (otherCb) otherCb.checked = false;
						}
					});
					Vvveb.FileManager.saveActiveFolder(folderName);
				} else {
					Vvveb.FileManager.saveActiveFolder('');
				}
			}
		});

		this.tree.addEventListener("click", function (e) {
			let element = e.target.closest("a");
			if (element) {
				e.stopImmediatePropagation();
				if (element.classList.contains('view')) return;
				e.preventDefault();
				return false;
			}
		});	
		
		this.tree.addEventListener("click", function (e) {
			let element = e.target.closest(".delete");
			if (element) {
				Vvveb.FileManager.deletePage(element.closest("li"), e);
				e.stopImmediatePropagation();
				e.preventDefault();
				return false;
			}
		});

		this.tree.addEventListener("click", function (e) {
			let element = e.target.closest(".rename");
			if (element) {
				Vvveb.FileManager.renamePage(element.closest("li"), e, false);
				e.stopImmediatePropagation();
				e.preventDefault();
				return false;
			}
		});

		this.tree.addEventListener("click", function (e) {
			let element = e.target.closest(".duplicate");
			if (element) {
				Vvveb.FileManager.renamePage(element.closest("li"), e, true);
				e.stopImmediatePropagation();
				e.preventDefault();
				return false;
			}
		});
		
		this.tree.addEventListener("click", function (e) {
			let element = e.target.closest("li[data-page] label");
			if (element) {
				let page = element.parentNode.dataset.page;
				if (page) Vvveb.FileManager.loadPage(page, allowedComponents);
				e.preventDefault();
				return false;			
			}
		});
		
		this.tree.addEventListener("click", function (e) {
			let element = e.target.closest("li[data-component] label");
			if (element) {
				const node = element.parentNode._node;
				if (node) {
					if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);
					node.click();
				}
			}
		});
		
		this.tree.addEventListener("mouseenter", function (e) {
			let element = e.target.closest("li[data-component] label");
			if (element) {
				const node = element.parentNode._node;
				if (node) {
					if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);
					node.dispatchEvent(new MouseEvent("mousemove", {
						bubbles: true,
						cancelable: true,
					}));
				}
				//node.trigger("mousemove");
			}			
		});
	},
	
	clear: function() {
		this.pages = {};
		this.currentPage = false;
		this.tree.replaceChildren();
	},
	
	deletePage: function(element, e) {
		let page = element.dataset;
		if (confirm(`Are you sure you want to delete "${page.file}"template?`)) {

			let detail = Object.assign({ page: page.page }, this.pages[page.page] || {});
			detail['element'] = element;
			//allow event to change page or cancel by setting page to false
			window.dispatchEvent(new CustomEvent("Vvveb.FileManager.deletePage", {
				detail
			}));

			if (typeof deleteUrl != "undefined" && detail.page !== false) {
				
				fetch(deleteUrl, {method: "POST",  body: new URLSearchParams({file:page.file})})
				.then((response) => {
					if (!response.ok) {  return Promise.reject(response);  }
					return response.text();
				})
				.then((rawText) => {
					let data;
					try {
						data = JSON.parse(rawText);
					} catch (err) {
						data = { success: true, message: rawText };
					}
					let bg = "success";
					if (data && data.success === false) {		
						bg = "danger";
					}

					displayToast(bg, "Delete", data?.message ?? data);
				})
				.catch(error => {
					console.error(error);
					let message = error?.message || error?.statusText || "Error deleting page!";
					displayToast("danger", "Error", message);

					if (error && typeof error.text === "function") {
						error.text().then( errorMessage => {
							let msg = errorMessage.substr(0, 200);
							displayToast("danger", "Error", msg);
						}).catch(() => {});
					}
				});
			}
			
			if (element) {
				element.remove();
			}
		}
	},	
	
	renamePage: function(element, e, duplicate = false) {
		let page = element.dataset;
		let newfile = prompt(`Enter new file name for "${page.file}"`, page.file);
		let _self = this;
		if (newfile) {

			let detail = {page, newfile, element};
			//allow event to change page or newfile or cancel by setting page to false
			window.dispatchEvent(new CustomEvent("Vvveb.FileManager.renamePage", {
				detail
			}));

			if (typeof renameUrl != "undefined" && detail.page) {

				fetch(renameUrl, {method: "POST",  body: new URLSearchParams({file:page.file, newfile:newfile, duplicate})})
				.then((response) => {
					if (!response.ok) {  return Promise.reject(response);  }
					return response.text();
				})
				.then((rawText) => {
					let data;
					try {
						data = JSON.parse(rawText);
					} catch (err) {
						data = { success: true, message: rawText };
					}
					let bg = "success";
					if (data && data.success === false) {		
						bg = "danger";
					}

					newfile = data?.newfile ?? newfile;	
					displayToast(bg, duplicate ? "Duplicate" : "Rename", data?.message ?? data);
					let baseName = newfile.replace('.html', '');
					let newName = friendlyName(newfile.replace(/.*[\/\\]+/, '')).replace('.html', '');
					
					if (duplicate) {
						let data = Object.assign({}, _self.pages[page.page]);
						data["file"] = newfile;
						data["title"] = newName;
						Vvveb.FileManager.addPage(baseName, data);
					} else {
						if (_self.pages[page.page]) {
							_self.pages[page.page]["file"] = newfile;
							_self.pages[page.page]["title"] = newName;
						}
						page.url = data?.url ?? page.url.replace(page.file, newfile);
						page.file = newfile;
						const labelSpan = element.querySelector(":scope > label span");
						if (labelSpan) labelSpan.innerHTML = newName;
						element.querySelector(":scope > label a.view")?.setAttribute("href", page.url);
						if (_self.pages[page.page]) {
							_self.pages[page.page]["url"] = page.url;
							_self.pages[page.page]["file"] = page.file;
						}
					}
				})
				.catch(error => {
					console.error(error);
					let message = error?.message || error?.statusText || "Error renaming page!";
					displayToast("danger", "Error", message);

					if (error && typeof error.text === "function") {
						error.text().then( errorMessage => {
							let msg = errorMessage.substr(0, 200);
							displayToast("danger", "Error", msg);
						}).catch(() => {});
					}
				});				
			} else {
				if (detail.page) {
					
				}
			}
		}
	},
	
	addPage: function(name, data, afterPage = false, append = true) {
		let detail = {name, data};
		//allow event to change name or cancel by setting name to false
		window.dispatchEvent(new CustomEvent("Vvveb.FileManager.addPage", {
			detail
		}));

		if (!name) {
			return false;
		}

		this.pages[name] = data;
		data['name'] = name;

		let folder = this.tree;
		if (data.folder) {
			if ((data.folder && data.folder != "/") && !(folder = folder.querySelector('li[data-folder="' + data.folder + '"]')))  {
				data.folderTitle = friendlyName(data.folder);
				let folderElements = generateElements(tmpl("vvveb-filemanager-folder", data));
				folder = Array.from(folderElements).find(el => el.nodeType === 1) || folderElements[0];
				
				// Set initial state for this folder
				const activeFolder = this.getActiveFolder();
				const cb = folder.querySelector(":scope > input[type=checkbox]");
				if (cb) {
					cb.checked = !!(activeFolder && activeFolder === data.folder);
				}

				this.tree.append(folder);
			}

			if (folder && typeof folder.querySelector === "function") {
				folder = folder.querySelector("ol") || folder;
			} else {
				folder = this.tree;
			}
		} 
		
		let page = generateElements(tmpl("vvveb-filemanager-page", data))[0];
		if (afterPage && (afterPage = folder.querySelector('[data-page="' + afterPage + '"]'))) {
			afterPage.after(page);
		} else {
			if (append) {
				folder.append(page);
			} else {
				folder.prepend(page);
			}
		}
		
		return page;
	},
	
	addPages: function(pages) {
		for (const page in pages) {
			this.addPage(pages[page]['name'], pages[page]);
		}
	},

	getActiveFolder: function() {
		try {
			return localStorage.getItem('align_builder_active_folder') || '';
		} catch (e) {
			return '';
		}
	},

	saveActiveFolder: function(folderName) {
		try {
			if (folderName) {
				localStorage.setItem('align_builder_active_folder', folderName);
			} else {
				localStorage.removeItem('align_builder_active_folder');
			}
			console.log('[Align FileTree] Active open folder: ' + (folderName || 'none'));
		} catch (e) {
			console.warn('[Align FileTree] Error saving active folder:', e);
		}
	},

	restoreFolderStates: function(activeFolderName = null) {
		const targetFolder = activeFolderName || this.getActiveFolder() || "Pages";
		const folderLis = this.tree ? this.tree.querySelectorAll("li.folder[data-folder]") : [];
		folderLis.forEach(li => {
			const folderName = li.getAttribute('data-folder') || li.dataset.folder;
			const checkbox = li.querySelector(":scope > input[type=checkbox]");
			if (!checkbox || !folderName) return;

			// Single-open accordion mode: only the target folder is opened
			if (folderName === targetFolder) {
				checkbox.checked = true;
			} else {
				checkbox.checked = false;
			}
		});
		if (targetFolder) {
			this.saveActiveFolder(targetFolder);
		}
	},
	
	addComponent: function(name, url, title, page) {
		document.querySelector("[data-page='" + page + "'] > ol", this.tree).append(
			tmpl("vvveb-filemanager-component", {name:name, url:url, title:title}));
	},
	
	loadComponents: function(allowedComponents = {}) {

		let tree = [];
		getNodeTree(window.FrameDocument.body, tree, allowedComponents);
		
		let html = drawComponentsTree(tree);
		document.querySelector("[data-page='" + this.currentPage + "'] > ol", this.tree).replaceWith(html);
	},
	
	getCurrentUrl: function() {
		if (this.currentPage) {
			return this.pages[this.currentPage]['url'];
		}
	},	
    
	getCurrentPage: function() {
		return this.currentPage;
	},	
    
	getPageData: function(key) {
		if (this.currentPage) {
			if (key) {
				return this.pages[this.currentPage][key];
			} else {
				return this.pages[this.currentPage];
			}
		}
	},	
    
    
	getCurrentFileName: function() {
	    if (this.currentPage) {
		    let folder = this.pages[this.currentPage]['folder'];
		    folder = folder ? folder + '/': ''; 
		    return folder + this.pages[this.currentPage]['file'];
            }
	},
	
	reloadCurrentPage: function() {
		if (this.currentPage)
		return this.loadPage(this.currentPage);
	},
	
	loadPage: function(name, allowedComponents = false, disableCache = true, loadComponents = false) {
		if (!this.pages || !this.pages[name]) {
			console.warn('[Align FileManager] Page not found in tree:', name);
			return false;
		}
		let url = this.pages[name]['url'] ?? "";
		let file = this.pages[name]['file'] ?? url;
		
		let page = this.tree.querySelector("[data-page='" + name + "']");
		//remove active from current active page
		this.tree.querySelector("[data-page].active")?.classList.remove("active");
		//set loaded page as active
		if (page) {
			page.classList.add("active");
			
			// Single-open accordion: open only parent folder for active page and close others
			const parentFolder = page.closest("li.folder[data-folder]");
			if (parentFolder) {
				const folderName = parentFolder.getAttribute('data-folder') || parentFolder.dataset.folder;
				if (folderName) {
					this.restoreFolderStates(folderName);
				}
			}
			if (this.scrollToPage) {
				this.scrollToPage(page);
			}
		}
		
		this.currentPage = name;
		let previewBtn = document.querySelector(".btn-preview-url");
		if (previewBtn) {
			previewBtn.setAttribute("href", url);
		}

		//allow event to change page or url or cancel by setting url to false
		let self = this;

		window.dispatchEvent(new CustomEvent("Vvveb.FileManager.loadPage", {
			detail: self.pages[name],
		}));

		if (file) {
			Vvveb.Builder.loadUrl(file + (disableCache ? (file.indexOf('?') > -1 ? '&r=':'?r=') + Math.random():''), 
				function () { 
					if (loadComponents) { Vvveb.FileManager.loadComponents(allowedComponents); }
					Vvveb.SectionList.loadSections(allowedComponents); 
					Vvveb.TreeList.loadComponents(); 
					Vvveb.StyleManager.init(); 
				});
		}
	},

	scrollToPage: function(page) {
		if (page) {
			let container = document.querySelector("#filemanager .tree") || page.parentElement;
			if (container) {
				let cTop = container.scrollTop, cHeight = container.clientHeight;
				let eTop = page.offsetTop, eHeight = page.offsetHeight;
				if (eTop < cTop) {
					container.scrollTop = eTop;
				} else if (eTop + eHeight > cTop + cHeight) {
					container.scrollTop = eTop + eHeight - cHeight;
				}
			}
		}
	},
}
