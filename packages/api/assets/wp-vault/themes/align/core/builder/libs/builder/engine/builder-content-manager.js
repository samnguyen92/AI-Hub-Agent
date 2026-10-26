/**
 * Align Builder Engine - Content Manager (HTML & Text)
 * @package Align
 */

Vvveb.ContentManager = {
	getAttr: function(element, attrName) {
		return element.getAttribute(attrName);
	},
	
	setAttr: function(element, attrName, value) {
		return element.setAttribute(attrName, value);
	},
	
	setHtml: function(element, html, outer = false) {
		if (outer) {
			const template = document.createElement('template');
			template.innerHTML = html.trim();
			let newelement = template.content.children[0];
			element.parentNode.replaceChild(newelement,element);
			element.remove();
			return newelement;
		} else {
			element.innerHTML = html;
		}
	
		return element;
	},
	
	getHtml: function(element, outer = false) {
		if (outer) {
			return element.outerHTML;
		} else {
			return element.innerHTML;
		}
	},

	setText: function(element, text) {
		return element.textContent = text;
	},
	
	getText: function(element) {
		return element.textContent;
	},
};

function getNodeTree (node, parent, allowedComponents, idToNode = {}) {
	
	function getNodeTreeTraverse (node, parent, id = '') {
		
		if (node.hasChildNodes()) {
			for (let j = 0; j < node.childNodes.length; j++) {
				
				const child = node.childNodes[j];

				//skip text and comments nodes
				if (child.nodeType == 3 || child.nodeType == 8) {
					continue;
				}

				let tagNameUpper = (child.tagName || '').toUpperCase();

				//skip non-visual/script/style/link/meta/template elements
				if (["SCRIPT", "STYLE", "LINK", "META", "NOSCRIPT", "TEMPLATE"].includes(tagNameUpper)) {
					continue;
				}

				//skip internal editor helper elements (#vvvebjs-new-section, data-vvveb-helpers, vvvebjs-*)
				if (child.id === 'vvvebjs-new-section' || 
					(child.id && child.id.startsWith('vvvebjs-')) ||
					(tagNameUpper === 'NEWSECTION') ||
					(child.hasAttribute && child.hasAttribute('data-vvveb-helpers')) ||
					(child.closest && child.closest('#vvvebjs-new-section'))) {
					continue;
				}
				
				let matchChild = (child && child["attributes"] != undefined) ? Vvveb.Components.matchNode(child) : null;
				if (!matchChild && child && child.tagName) {
					let tag = child.tagName.toLowerCase();
					let friendlyTitle = child.id ? ('#' + child.id) : (child.className && typeof child.className === 'string' ? ('.' + child.className.trim().split(/\s+/)[0]) : '');
					
					const tagIconMap = {
						img: 'icons/image.svg', picture: 'icons/image.svg', source: 'icons/image.svg', figure: 'icons/image.svg', canvas: 'icons/image.svg',
						video: 'icons/video.svg', audio: 'icons/video.svg',
						svg: 'icons/symbol.svg',
						a: 'icons/button.svg', button: 'icons/button.svg',
						p: 'icons/paragraph.svg', span: 'icons/paragraph.svg', h1: 'icons/paragraph.svg', h2: 'icons/paragraph.svg', h3: 'icons/paragraph.svg',
						h4: 'icons/paragraph.svg', h5: 'icons/paragraph.svg', h6: 'icons/paragraph.svg', b: 'icons/paragraph.svg', strong: 'icons/paragraph.svg',
						i: 'icons/paragraph.svg', em: 'icons/paragraph.svg', figcaption: 'icons/paragraph.svg',
						input: 'icons/form.svg', textarea: 'icons/form.svg', select: 'icons/form.svg', form: 'icons/form.svg',
						ul: 'icons/list.svg', ol: 'icons/list.svg', li: 'icons/list.svg',
						table: 'icons/table.svg', tr: 'icons/table.svg', td: 'icons/table.svg', th: 'icons/table.svg',
						div: 'icons/container.svg', section: 'icons/container.svg', article: 'icons/container.svg', header: 'icons/container.svg',
						footer: 'icons/container.svg', nav: 'icons/container.svg', aside: 'icons/container.svg', main: 'icons/container.svg'
					};

					matchChild = {
						name: tag.toUpperCase() + (friendlyTitle ? (' ' + friendlyTitle) : ''),
						type: 'html/' + tag,
						image: tagIconMap[tag] || 'icons/container.svg'
					};
				}

				if (matchChild) {
					if (Array.isArray(allowedComponents)
						&& allowedComponents.indexOf(matchChild.type) == -1) {
						
						element = getNodeTreeTraverse(child, parent);	
						continue;
					}
				
					let title;
					for (const attr of ["id", "title", "aria-label"]) {
						title = child.getAttribute(attr);
						if (title) {
							break;
						}
					}

					element = {
						name: matchChild.name,
						image: matchChild.image,
						type: matchChild.type,
						title,
						node: child,
						id: id + '-' + j,
						astId: (child.getAttribute && child.getAttribute('data-ast-id')) ? child.getAttribute('data-ast-id') : null,
						children: []
					};

					parent.push(element);
					idToNode[id + '-' + j] = child;
					
					// If this is an atomic / blackbox component (e.g. WP Shortcode, Gutenberg Block, WP Post Content, or allowChildren === false), do NOT traverse children
					// Also treat media leaf elements (img, picture, source) as blackboxes — their child nodes (source tags, nested img) are internal and clutter the tree.
					let isBlackbox = (
						(child.hasAttribute && (child.hasAttribute('data-component-shortcode') || child.hasAttribute('data-component-wp-block') || child.hasAttribute('data-component-post-content'))) ||
						(matchChild.type && (matchChild.type === 'wordpress/shortcode' || matchChild.type === 'wordpress/block' || matchChild.type === 'wordpress/post-content')) ||
						(matchChild.allowChildren === false) ||
						(matchChild.isLeaf === true) ||
						(['img', 'picture', 'source'].includes(tagNameUpper.toLowerCase()))
					);

					if (!isBlackbox) {
						element = getNodeTreeTraverse(child, element.children, id + '-' + j);
					}
				} else {
					element = getNodeTreeTraverse(child, parent, id + '-' + j);	
				}
			}
		}

		return false;
	}
	
	getNodeTreeTraverse(node, parent, '1');
}

function drawComponentsTree(tree) {
	let j = 1;
	let prefix = Math.floor(Math.random() * 100);

	function escapeHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}
	
	function drawComponentsTreeTraverse(tree) {
		let list = document.createElement("ol");
		j++;
		
		for (i in tree) {
			let node = tree[i];
			let id = node.id;
			let li;
			
			if (!id) {
				id = prefix + '-' + j + '-' + i; 
			}
			
			let displayName = node.name || (node.node && node.node.tagName ? node.node.tagName.toUpperCase() : 'Element');
			let rawTitle = (node.title ? friendlyName(node.title) : "");
			let safeDisplayName = escapeHtml(displayName);
			let safeRawTitle = escapeHtml(rawTitle);
			let safeFullTitleAttr = escapeHtml(displayName + (rawTitle ? ' - ' + rawTitle : ''));
			let titleHtml = "";
			if (rawTitle && rawTitle !== displayName && !displayName.endsWith('#' + node.title) && !displayName.endsWith('.' + node.title)) {
				titleHtml = `<span class="tree-node-title text-secondary"> - ${safeRawTitle}</span>`;
			}

			let iconUrl = '';
			if (node.image && node.image !== 'undefined') {
 				if (node.image.startsWith('http://') || node.image.startsWith('https://') || node.image.startsWith('/')) {
					iconUrl = node.image;
				} else if (node.image.startsWith('icons/')) {
					iconUrl = (Vvveb.baseUrl || 'libs/builder/') + node.image;
				} else {
					iconUrl = (Vvveb.imgBaseUrl || ((Vvveb.baseUrl || 'libs/builder/') + 'icons/')) + node.image;
				}
			}
			let bgStyle = iconUrl ? `style="background-image:url('${iconUrl}')"` : '';
			
			let isCanvasNode = node.node && (typeof node.node.nodeType !== 'undefined' || typeof node.node.tagName !== 'undefined');
			let actionsHtml = isCanvasNode ? '<span class="tree-node-actions">\
				<a class="tree-node-action tree-node-clone" title="Duplicate"><i class="icon-copy-outline la la-clone"></i></a>\
				<a class="tree-node-action tree-node-delete" title="Delete"><i class="icon-trash-outline la la-trash-alt"></i></a>\
			</span>' : '';
			
			let isExpanded = Boolean(
				(window.Vvveb?.TreeList?.expandedAstIds && node.astId && window.Vvveb.TreeList.expandedAstIds.has(node.astId))
			);
			let checkedAttr = isExpanded ? ' checked="checked"' : '';
			let isNodeHidden = Boolean(node.node?.getAttribute && node.node.getAttribute("data-align-hide") === "true");
			let hiddenBadgeHtml = isNodeHidden ? '<i class="la la-eye-slash text-warning me-1 tree-node-hidden-icon" title="Hidden from frontend"></i>' : '';

			if (tree[i].children.length > 0) {
				li = generateElements('<li data-component="' + safeDisplayName + '">\
								<label ' + bgStyle + ' title="' + safeFullTitleAttr + '">\
									<span class="tree-node-text">' + hiddenBadgeHtml + '<span class="tree-node-name">' + safeDisplayName + '</span>' + titleHtml + '</span>\
								</label>\
								' + actionsHtml + '\
								<input type="checkbox" id="id' + id + '"' + checkedAttr + '>\
							</li>')[0];		
				li.append(drawComponentsTreeTraverse(node.children));
			}
			else {
				li = generateElements('<li data-component="' + safeDisplayName + '" class="file">\
							<label ' + bgStyle + ' title="' + safeFullTitleAttr + '">\
									<span class="tree-node-text">' + hiddenBadgeHtml + '<span class="tree-node-name">' + safeDisplayName + '</span>' + titleHtml + '</span>\
							</label>\
							' + actionsHtml + '\
							<input type="checkbox" id="id' + id + '">\
						</li>')[0];
			}

			if (isNodeHidden) {
				li.classList.add("is-element-hidden");
			}

			li._treeNode = node.node;
			li._astId = node.astId;
			list.append(li);
		}
		
		return list;
	}
	
	return drawComponentsTreeTraverse(tree);
}
