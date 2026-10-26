/**
 * Align Builder Media Components - Media Inputs & Inspector Bridges
 * Manages ImageInput, VideoInput, SvgInput, and FileUploadInput controls.
 *
 * @package AlignBuilder
 */

let ImageInput = { ...(typeof Input !== "undefined" ? Input : {}), ...{
    tag: "img",

    events: [
        ["focusout", "onChange", "input[type=text]"],
        ["change", "onUpload", "input[type=file]"],
        ["change", "onImageChange", "input[type=text]"],
        ["click", "onClick", ".btn-set-media, button:not(.btn-media-disconnect)"],
        ["click", "onClick", "img"],
        ["click", "onDisconnect", ".btn-media-disconnect"],
    ],

    getContainer: function(node, input, targetEl) {
        if (node && node.querySelector && (node.classList?.contains("vvveb-media-input-wrapper") || node.querySelector(".vvveb-media-input-wrapper"))) {
            return node.classList?.contains("vvveb-media-input-wrapper") ? node : node.querySelector(".vvveb-media-input-wrapper");
        }
        if (targetEl && targetEl.closest) {
            let found = targetEl.closest(".vvveb-media-input-wrapper");
            if (found) return found;
        }
        if (input && input.element) {
            let el = input.element[0] || input.element;
            if (el && el.querySelector) {
                return el.classList?.contains("vvveb-media-input-wrapper") ? el : (el.querySelector(".vvveb-media-input-wrapper") || el);
            }
        }
        if (this && this.element) {
            let el = this.element[0] || this.element;
            if (el && el.querySelector) {
                return el.classList?.contains("vvveb-media-input-wrapper") ? el : (el.querySelector(".vvveb-media-input-wrapper") || el);
            }
        }
        return null;
    },

    extractMediaInfo: function(selectedEl, tag) {
        if (!selectedEl) return { mediaId: null, mediaTitle: null };

        tag = tag || this.tag || "img";
        let target = (selectedEl.tagName && selectedEl.tagName.toLowerCase() === tag.toLowerCase())
            ? selectedEl
            : (selectedEl.querySelector ? selectedEl.querySelector(tag) : null);

        let pic = (selectedEl.tagName === "PICTURE")
            ? selectedEl
            : (selectedEl.closest ? selectedEl.closest("picture") : (target?.parentElement?.tagName === "PICTURE" ? target.parentElement : null));

        let mediaId = null;
        let mediaTitle = null;

        const checkEl = (el) => {
            if (!el || !el.getAttribute) return;
            if (!mediaId) {
                mediaId = el.getAttribute("data-id") || el.getAttribute("data-media-id") || el.getAttribute("data-attachment-id");
                if (!mediaId && el.className && typeof el.className === "string") {
                    let match = el.className.match(/\bwp-(?:image|video|media)-(\d+)\b/);
                    if (match) mediaId = match[1];
                }
            }
            if (!mediaTitle) {
                mediaTitle = el.getAttribute("data-media-title") || el.getAttribute("data-title") || el.getAttribute("alt");
            }
        };

        if (pic) checkEl(pic);
        if (target) checkEl(target);
        if (!mediaId && selectedEl) checkEl(selectedEl);

        if (mediaId && !mediaTitle) {
            mediaTitle = "Media #" + mediaId;
        }

        return { mediaId, mediaTitle };
    },

    setLockedView: function(mediaId, title, container) {
        container = container || this.getContainer(null, this);
        if (!container) return;

        let unlockedDiv = container.querySelector(".media-unlocked-container");
        let lockedDiv = container.querySelector(".media-locked-container");
        let titleEl = container.querySelector(".locked-media-title");
        let idValueEl = container.querySelector(".locked-id-value");
        let idBadgeEl = container.querySelector(".media-locked-id-badge");

        let displayTitle = (title && String(title).trim()) ? String(title).trim() : ("Media #" + mediaId);
        if (titleEl) {
            titleEl.textContent = displayTitle;
            titleEl.setAttribute("title", displayTitle);
        }
        if (idValueEl) {
            idValueEl.textContent = mediaId ? mediaId : "";
        } else if (idBadgeEl) {
            idBadgeEl.textContent = "#" + (mediaId || "");
        }
        if (idBadgeEl) idBadgeEl.setAttribute("title", "Media ID: " + mediaId);
        if (unlockedDiv) unlockedDiv.style.display = "none";
        if (lockedDiv) lockedDiv.style.display = "block";
        const isBg = container?.closest?.('[data-group="background"], .section-background_image_header, [data-property="Image"]') || container?.querySelector?.('#input-Image');
        if (!isBg && (this.tag === "img" || !this.tag) && window.AlignBuilderModules?.MediaSizeTags?.render) {
            window.AlignBuilderModules.MediaSizeTags.render(lockedDiv, mediaId, window.Vvveb?.Builder?.selectedEl);
        }
    },

    setUnlockedView: function(url, container) {
        container = container || this.getContainer(null, this);
        if (!container) return;

        let unlockedDiv = container.querySelector(".media-unlocked-container");
        let lockedDiv = container.querySelector(".media-locked-container");
        let inputEl = container.querySelector(".media-url-input") || container.querySelector('input[type="text"]');

        if (inputEl && typeof url === "string") {
            inputEl.value = (url && url.indexOf("data:image") === -1 && url !== "none") ? url : "";
        }
        if (unlockedDiv) unlockedDiv.style.display = "block";
        if (lockedDiv) lockedDiv.style.display = "none";
        let tagsEl = container.querySelector(".align-media-sizes-tags-wrapper");
        if (tagsEl) tagsEl.style.display = "none";
    },

    onDisconnect: function(event, node, input) {
        let selectedEl = window.Vvveb?.Builder?.selectedEl;
        let tag = (input && input.tag) || (this && this.tag) || (selectedEl?.tagName?.toLowerCase() === "video" ? "video" : "img");
        let controller = (tag === "video" ? VideoInput : ImageInput);
        let container = controller.getContainer(node, input, (event ? event.target : this));

        if (selectedEl) {
            let target = (selectedEl.tagName && selectedEl.tagName.toLowerCase() === tag.toLowerCase())
                ? selectedEl
                : (selectedEl.querySelector ? selectedEl.querySelector(tag) : null);

            let pic = (selectedEl.tagName === "PICTURE")
                ? selectedEl
                : (selectedEl.closest ? selectedEl.closest("picture") : (target?.parentElement?.tagName === "PICTURE" ? target.parentElement : null));

            if (pic && target) {
                pic.querySelectorAll("source").forEach(s => s.remove());
                pic.replaceWith(target);
                selectedEl = target;
                if (window.Vvveb?.Builder) {
                    window.Vvveb.Builder.selectedEl = target;
                }
            }

            const cleanElement = function(el) {
                ["src","data-src","srcset","data-srcset","sizes","data-id","data-media-id","data-attachment-id","data-media-title","data-align-sizes","data-selected-sizes"].forEach(a => el.removeAttribute(a));
                if (el.tagName === "IMG" || el.tagName === "VIDEO") el.setAttribute("src", "");
                if (el.style && el.style.backgroundImage) el.style.backgroundImage = "";
                if (el.hasAttribute && el.hasAttribute("data-bg-src")) el.removeAttribute("data-bg-src");
                if (el.className && typeof el.className === "string") {
                    el.className = el.className.replace(/\bwp-(?:image|video|media)-\d+\b/g, "").replace(/\s+/g, " ").trim();
                    if (!el.className) el.removeAttribute("class");
                }
            };

            cleanElement(selectedEl);
            if (target && target !== selectedEl) {
                cleanElement(target);
            }
        }

        controller.setUnlockedView("", container);

        if (container) {
            let defaultIcon = (window.Vvveb ? Vvveb.baseUrl : 'libs/builder/') + (tag === "video" ? 'icons/video.svg' : 'icons/image.svg');
            let previewImg = container.querySelector(tag) || container.querySelector("img") || container.querySelector("video");
            if (previewImg) {
                previewImg.src = defaultIcon;
                previewImg.setAttribute("src", defaultIcon);
            }
            let inputEl = container.querySelector(".media-url-input") || container.querySelector('input[type="text"]');
            if (inputEl) {
                inputEl.value = "";
                inputEl.focus();
            }
            const customEvt = new CustomEvent("propertyChange", {
                detail: { value: "", input: input || controller, origEvent: event }
            });
            container.dispatchEvent(customEvt);
        }

        if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
            window.AlignBuilderModules.WpSaveHandler.setButtonState("dirty");
        }
        document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => btn.removeAttribute("disabled"));

        if (selectedEl && window.Vvveb?.Builder?.selectNode) {
            window.Vvveb.Builder.selectNode(selectedEl);
        }
        console.log(`[Align MediaInputs] Disconnected media ID and cleared image for tag <${tag}>`);
    },

    resolveImgUrl: function(val, selectedNode) {
        if (!val || val === "none" || val.startsWith("data:image")) {
            return (window.Vvveb ? Vvveb.baseUrl : 'libs/builder/') + 'icons/image.svg';
        }
        if (val.startsWith("http://") || val.startsWith("https://") || val.startsWith("//") || val.startsWith("data:")) {
            return val;
        }
        if (val.startsWith("/")) {
            return window.location.origin + val;
        }
        if (val.startsWith("wp-content/") || val.startsWith("wp-includes/")) {
            return window.location.origin + "/" + val;
        }
        if (selectedNode) {
            let target = (selectedNode.tagName === "IMG") ? selectedNode : (selectedNode.querySelector ? selectedNode.querySelector("img") : null);
            if (target && target.src && typeof target.src === "string" && target.src.startsWith("http")) {
                return target.src;
            }
        }
        if (window.VvvebWpConfig && window.VvvebWpConfig.pluginUrl) {
            return window.VvvebWpConfig.pluginUrl + val.replace(/^(\.\/)/, '');
        }
        return (window.Vvveb ? Vvveb.baseUrl : 'libs/builder/') + val.replace(/^(\.\/)/, '');
    },

    setValue: function(value) {
        let container = this.getContainer(null, this);
        let inputEl = container ? (container.querySelector(".media-url-input") || container.querySelector('input[type="text"]')) : null;
        if (!inputEl && this.element) {
            let el = this.element[0] || this.element;
            if (el && el.querySelector) {
                inputEl = el.querySelector(".media-url-input") || el.querySelector('input[type="text"]');
            }
        }

        let isBgInput = (this.data && this.data.key === "Image") || (inputEl && (inputEl.name === "Image" || inputEl.id === "input-Image"));

        if (isBgInput) {
            if ((!value || value.indexOf("data:image") === 0 || value === "none") && window.Vvveb?.Builder?.selectedEl) {
                let el = window.Vvveb.Builder.selectedEl;
                let bgData = (el.style && el.style.backgroundImage) ? el.style.backgroundImage : null;
                if (!bgData && el.getAttribute) {
                    bgData = el.getAttribute("data-bg-src");
                }
                if (!bgData && el.ownerDocument?.defaultView?.getComputedStyle) {
                    try {
                        bgData = el.ownerDocument.defaultView.getComputedStyle(el).backgroundImage;
                    } catch(e) {}
                }
                if (bgData) {
                    let clean = '';
                    let quotedMatch = bgData.match(/url\(\s*(['"])(.*?)\1\s*\)/i);
                    if (quotedMatch && quotedMatch[2]) {
                        clean = quotedMatch[2];
                    } else {
                        let unquotedMatch = bgData.match(/url\(\s*([^\s'")]+)\s*\)/i);
                        clean = (unquotedMatch && unquotedMatch[1]) ? unquotedMatch[1] : bgData;
                    }
                    clean = clean.replace(/^['"]+|['"]+$/g, '').trim();
                    if (clean && clean !== "none" && !clean.startsWith("data:image") && !clean.includes("icons/image.svg")) {
                        value = clean;
                    }
                }
            }
        } else {
            if ((!value || value.indexOf("data:image") === 0 || value === "none") && window.Vvveb?.Builder?.selectedEl) {
                let el = window.Vvveb.Builder.selectedEl;
                let target = el ? (el.tagName === "IMG" ? el : (el.querySelector ? el.querySelector("img") : el)) : null;
                let dataSrc = target ? (target.getAttribute("data-src") || target.getAttribute("data-poster") || target.getAttribute("src")) : null;
                if (dataSrc && dataSrc.indexOf("data:image") === -1 && dataSrc !== "none") {
                    value = dataSrc;
                }
            }
        }

        // Dual-state view switching based on media ID presence
        let mediaInfo = this.extractMediaInfo(isBgInput ? null : (window.Vvveb?.Builder?.selectedEl || null), this.tag);
        if (mediaInfo.mediaId) {
            this.setLockedView(mediaInfo.mediaId, mediaInfo.mediaTitle, container);
        } else {
            this.setUnlockedView(value, container);
        }

        let previewSrc = this.resolveImgUrl(value, isBgInput ? null : (window.Vvveb?.Builder?.selectedEl || null));
        if (inputEl) {
            inputEl.value = (value && value.indexOf("data:image") === -1 && value !== "none") ? value : "";
        }

        let previewImg = container ? container.querySelector(this.tag) : (this.element ? (this.element.querySelector ? this.element.querySelector(this.tag) : (this.element[0] ? this.element[0].querySelector(this.tag) : null)) : null);
        if (previewImg) {
            if (previewImg.getAttribute('src') !== previewSrc) {
                previewImg.src = previewSrc;
                previewImg.setAttribute('src', previewSrc);
            }
            if (!previewImg.onerror) {
                previewImg.onerror = function() {
                    this.onerror = null;
                    this.src = (window.Vvveb ? Vvveb.baseUrl : 'libs/builder/') + 'icons/image.svg';
                };
            }
        }
    },

    onUpload: function(event, node) {
        let self = this;
        if (this.files && this.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                let image = e.target.result;
                const customEvt = new CustomEvent('propertyChange', { detail: { value: image, input: self } });
                if (node) node.dispatchEvent(customEvt);
            };
            reader.readAsDataURL(this.files[0]);
        }
    },

    onImageChange: function(event, node, input) {
        let self = this;
        let src = (event && event.target && typeof event.target.value === "string") ? event.target.value : (self.value || "");
        let tag = (input && input.tag) ? input.tag : (self.tag || "img");

        let img = node ? (node.tagName && node.tagName.toLowerCase() === tag.toLowerCase() ? node : (node.querySelector ? node.querySelector(tag) : null)) : null;
        if (img) {
            if (src) {
                let resolvedSrc = (self.resolveImgUrl ? self.resolveImgUrl(src, window.Vvveb?.Builder?.selectedEl || null) : src);
                img.src = resolvedSrc;
                img.setAttribute("src", resolvedSrc);
                if (img.hasAttribute("data-src")) {
                    img.setAttribute("data-src", resolvedSrc);
                }
                img.onerror = function() {
                    this.onerror = null;
                    this.src = (window.Vvveb ? Vvveb.baseUrl : 'libs/builder/') + 'icons/image.svg';
                };
            } else {
                img.src = (window.Vvveb ? Vvveb.baseUrl : 'libs/builder/') + 'icons/image.svg';
                img.removeAttribute("data-src");
            }
        }
        
        if (input && input.onChange) {
            input.value = src;
            try {
                input.onChange.call(self, event, node, input);
            } catch (e) {
                console.warn('[Align MediaInputs] Error in input.onChange:', e);
            }
        }
        
        if (window.Vvveb?.Builder?.selectedEl) {
            let selectedEl = window.Vvveb.Builder.selectedEl;
            if (selectedEl.tagName && selectedEl.tagName.toLowerCase() === tag.toLowerCase()) {
                selectedEl.setAttribute("src", src);
                if (selectedEl.parentElement?.tagName === 'PICTURE') {
                    selectedEl.parentElement.querySelectorAll('source').forEach(s => s.remove());
                }
                if (src) {
                    if (selectedEl.hasAttribute("data-src")) selectedEl.setAttribute("data-src", src);
                    selectedEl.addEventListener("load", () => window.Vvveb?.Builder?.selectNode?.(selectedEl), { once: true });
                } else {
                    selectedEl.removeAttribute("data-src");
                    window.Vvveb?.Builder?.selectNode?.(selectedEl);
                }
            }
        }
        if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) window.AlignBuilderModules.WpSaveHandler.setButtonState('dirty');
        document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => btn.removeAttribute("disabled"));
    },

    onClick: function(e, element) {
        if (typeof MediaModal !== "undefined") {
            if (!window.Vvveb) window.Vvveb = {};
            if (!window.Vvveb.MediaModal) {
                window.Vvveb.MediaModal = new MediaModal(true);
                window.Vvveb.MediaModal.mediaPath = window.mediaPath;
            }
            window.Vvveb.MediaModal.open(this.closest("[data-target-input]"));
        }
    },
    
    init: function(data) {
        this.data = data || {};
        let selectedEl = window.Vvveb?.Builder?.selectedEl;
        let mediaInfo = this.extractMediaInfo(selectedEl, this.tag);
        if (mediaInfo.mediaId) {
            this.data.is_locked = true;
            this.data.mediaId = mediaInfo.mediaId;
            this.data.mediaTitle = mediaInfo.mediaTitle;
        }
        return this.render("imageinput-gallery", this.data);
    }
}};

let VideoInput = { ...ImageInput, ...{
    tag: "video",

    events: [
        ["focusout", "onChange", "input[type=text]"],
        ["change", "onImageChange", "input[type=text]"],
        ["click", "onClick", ".btn-set-media, button:not(.btn-media-disconnect)"],
        ["click", "onClick", "video"],
        ["click", "onDisconnect", ".btn-media-disconnect"],
    ],

    init: function(data) {
        this.data = data || {};
        let selectedEl = window.Vvveb?.Builder?.selectedEl;
        let mediaInfo = this.extractMediaInfo(selectedEl, "video");
        if (mediaInfo.mediaId) {
            this.data.is_locked = true;
            this.data.mediaId = mediaInfo.mediaId;
            this.data.mediaTitle = mediaInfo.mediaTitle;
        }
        return this.render("videoinput-gallery", this.data);
    }
}};

let SvgInput = { ...ImageInput, ...{
    tag: "svg",

    events: [
        ["change", "onChange", "input[type=text]"],
        ["click", "onClick", "button"],
        ["click", "onClick", "svg"],
    ],

    init: function(data) {
        return this.render("svginput-gallery", data);
    },
    
    setValue: function(value) {
        if (this.element && value) {
            let el = this.element[0] || this.element;
            let input = el.querySelector ? el.querySelector('svg') : null;
            if (input) { 
                input.innerHTML = value;
            }
        }
    },
    
    onChange: function(event, node, input) {
        if (event && event.target) {
            let self = this;
            let value = this.value;
            let src = (window.mediaPath || '') + value;

            fetch(src)
            .then(res => res.text())
            .then(svgCode => {
                let element = window.Vvveb?.Builder?.selectedEl;
                if (!element) return;
                let newElement = (typeof generateElements === "function") ? generateElements(svgCode)[0] : null;
                if (!newElement) return;

                let attributes = element.attributes;
                for (let i = 0; i < attributes.length; i++) {
                    let attr = attributes[i];
                    if (attr.name && attr.name !== "viewBox") {
                        newElement.setAttribute(attr.name, attr.value);
                    }
                }
            
                element.replaceWith(newElement);
                window.Vvveb.Builder.selectedEl = newElement;

                if (window.Vvveb.Builder.selectedEl) {
                    window.Vvveb.Builder.selectNode(window.Vvveb.Builder.selectedEl);
                    let svg = node.querySelector('svg');
                    if (svg) {
                        svg.innerHTML = newElement.innerHTML;
                        svg.setAttribute('viewBox', newElement.getAttribute('viewBox'));
                    }
                }
            }).catch(err => console.warn('[Align MediaInputs] Svg fetch error:', err));
        }
    },
    
    onImageChange: function(event, node, input) {}
}};

let FileUploadInput = { ...(typeof TextInput !== "undefined" ? TextInput : {}), ...{
    events: [["focusout", "onChange", "input"]],
    init: function(data) {
        return this.render("textinput", data);
    }
}};
