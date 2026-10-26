/**
 * Align Builder Media Components - Responsive Image, Picture & Figure Registry
 * Handles responsive picture art-direction, dummy unwrapping, and inspector bindings.
 *
 * @package AlignBuilder
 */

if (!window.Vvveb) window.Vvveb = {};
if (!window.Vvveb.Builder) window.Vvveb.Builder = {};

if (!window.Vvveb.Builder.unwrapPictureTag) {
    window.Vvveb.Builder.unwrapPictureTag = function(node) {
        if (!node) return null;
        let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : null);
        return img || node;
    };
}

Vvveb.Components.extend("_base", "html/image", {
    nodes: ["img", "picture"],
    name: "Image",
    html: '<img decoding="async" src="https://picsum.photos/seed/align/600/400" alt="Image" loading="lazy">',
    image: "icons/image.svg",
    resizable: true,
    
    properties: [{
        name: "Image",
        key: "src",
        htmlAttr: "src",
        inputtype: ImageInput,
        init: function(node) {
            if (!node) return '';
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (!img || !img.getAttribute) return '';
            let dataSrc = img.getAttribute('data-src');
            let src = img.getAttribute('src');
            if (dataSrc && (!src || src.startsWith('data:image') || src === 'none')) {
                return dataSrc;
            }
            return src || dataSrc || '';
        },
        onChange: function(node, value, input) {
            if (!node) {
                node = window.Vvveb?.Builder?.selectedEl;
            }
            if (!node) return null;
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (img) {
                img.setAttribute('src', value);
                if (img.hasAttribute('data-src') || img.classList.contains('lazy')) {
                    img.setAttribute('data-src', value);
                }

                // If setting a dummy/placeholder image, strip srcset and sizes and unwrap picture
                const isDummy = !value || value.includes('picsum.photos') || value.includes('placeholder') || value.startsWith('data:image') || value === 'none';
                if (isDummy) {
                    img.removeAttribute('srcset');
                    img.removeAttribute('data-srcset');
                    img.removeAttribute('sizes');
                    img.removeAttribute('data-id');
                    img.className = (img.className || '').replace(/\bwp-image-\d+\b/g, '').replace(/\s+/g, ' ').trim();
                    if (img.parentElement && img.parentElement.tagName === 'PICTURE') {
                        const pic = img.parentElement;
                        pic.querySelectorAll('source').forEach(s => s.remove());
                        pic.replaceWith(img);
                    }
                }
            }
            return img;
        }
    }, {
        name: "Width",
        key: "width",
        htmlAttr: "width",
        inputtype: NumberInput,
        init: function(node) {
            if (!node) return '';
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            return (img && img.getAttribute) ? (img.getAttribute('width') || '') : '';
        },
        onChange: function(node, value, input) {
            if (!node) node = window.Vvveb?.Builder?.selectedEl;
            if (!node) return node;
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (img && img.setAttribute) {
                if (value) img.setAttribute('width', value);
                else img.removeAttribute('width');
            }
            return node;
        }
    }, {
        name: "Height",
        key: "height",
        htmlAttr: "height",
        inputtype: NumberInput,
        init: function(node) {
            if (!node) return '';
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            return (img && img.getAttribute) ? (img.getAttribute('height') || '') : '';
        },
        onChange: function(node, value, input) {
            if (!node) node = window.Vvveb?.Builder?.selectedEl;
            if (!node) return node;
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (img && img.setAttribute) {
                if (value) img.setAttribute('height', value);
                else img.removeAttribute('height');
            }
            return node;
        }
    }, {
        name: "Alt Text",
        key: "alt",
        htmlAttr: "alt",
        inputtype: TextInput,
        init: function(node) {
            if (!node) return '';
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            return (img && img.getAttribute) ? (img.getAttribute('alt') || '') : '';
        },
        onChange: function(node, value, input) {
            if (!node) node = window.Vvveb?.Builder?.selectedEl;
            if (!node) return node;
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (img && img.setAttribute) {
                if (value) img.setAttribute('alt', value);
                else img.removeAttribute('alt');
            }
            return node;
        }
    }, {
        name: "Use Current Featured Image",
        key: "use_current_featured_image",
        defaultValue: false,
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        init: function(node) {
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (!img) return false;
            let src = (img.getAttribute("src") || "").trim();
            let dataShortcode = img.getAttribute("data-featured-shortcode") || "";
            let hasClass = img.classList.contains("blogFeaturedImage") || img.classList.contains("wp-block-post-featured-image");
            let hasAttr = img.getAttribute("data-align-featured") === "1" || src.includes("[align_post_hero_image]") || dataShortcode.includes("[align_post_hero_image]");
            return hasClass || hasAttr;
        },
        onChange: function(node, value, input) {
            if (!node) {
                node = window.Vvveb?.Builder?.selectedEl;
            }
            if (!node) return node;
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (!img) return node;

            const featuredUrl = '/wp-content/uploads/2026/08/home-3.webp';

            if (value) {
                img.setAttribute("data-align-featured", "1");
                img.setAttribute("data-featured-shortcode", "[align_post_hero_image]");
                img.setAttribute("src", featuredUrl);
                img.setAttribute("alt", "[align_post_title]");
                img.classList.add("blogFeaturedImage", "wp-block-post-featured-image");

                // Update Image src and alt inputs in the inspector panel if open
                let srcInput = document.querySelector('.component-properties input[name="src"], .image-input input');
                if (srcInput) {
                    srcInput.value = featuredUrl;
                }
                let altInput = document.querySelector('.component-properties input[name="alt"]');
                if (altInput) {
                    altInput.value = "[align_post_title]";
                }
                let previewImg = document.querySelector('.component-properties .image-preview img, .component-properties .preview-image');
                if (previewImg) {
                    previewImg.src = featuredUrl;
                }
            } else {
                img.removeAttribute("data-align-featured");
                img.removeAttribute("data-featured-shortcode");
                img.classList.remove("blogFeaturedImage", "wp-block-post-featured-image");
                img.setAttribute("src", "https://picsum.photos/seed/align/600/400");
                let srcInput = document.querySelector('.component-properties input[name="src"], .image-input input');
                if (srcInput) {
                    srcInput.value = "https://picsum.photos/seed/align/600/400";
                }
                let previewImg = document.querySelector('.component-properties .image-preview img, .component-properties .preview-image');
                if (previewImg) {
                    previewImg.src = "https://picsum.photos/seed/align/600/400";
                }
            }

            if (window.Vvveb && window.Vvveb.Undo) {
                window.Vvveb.Undo.addMutation({
                    type: 'attributes',
                    target: img,
                    attributeName: 'src',
                    oldValue: value ? '' : featuredUrl,
                    newValue: value ? featuredUrl : ''
                });
            }
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpSaveHandler) {
                window.AlignBuilderModules.WpSaveHandler.setButtonState("dirty");
            }
            document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => btn.removeAttribute("disabled"));

            return img;
        }
    }, {
        name: "Featured",
        key: "featured_image",
        defaultValue: false,
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        init: function(node) {
            if (!node) return false;
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (!img || !img.getAttribute) return false;
            let loading = (img.getAttribute("loading") || "").toLowerCase();
            let isNoLazy = img.classList.contains("no-lazy") || img.classList.contains("ignorelazyload");
            let isEager = loading === "eager" || img.getAttribute("fetchpriority") === "high" || isNoLazy;
            return isEager;
        },
        onChange: function(node, value, input) {
            if (!node) {
                node = window.Vvveb?.Builder?.selectedEl;
            }
            if (!node) return node;
            let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : node);
            if (!img) return node;
            if (value) {
                img.setAttribute("loading", "eager");
                img.setAttribute("fetchpriority", "high");
                img.setAttribute("data-align-preload", "1");
                img.classList.add("no-lazy");
                img.classList.remove("lazy");
                let dataSrc = img.getAttribute("data-src");
                if (dataSrc && !dataSrc.startsWith("data:image")) {
                    img.setAttribute("src", dataSrc);
                    img.removeAttribute("data-src");
                }
                img.removeAttribute("srcset");
                img.removeAttribute("data-srcset");
            } else {
                img.removeAttribute("fetchpriority");
                img.removeAttribute("data-align-preload");
                img.setAttribute("loading", "lazy");
                img.classList.remove("no-lazy");
                img.classList.add("lazy");
                let curSrc = img.getAttribute("src");
                if (curSrc && !curSrc.startsWith("data:image")) {
                    img.setAttribute("data-src", curSrc);
                }
                img.removeAttribute("srcset");
                img.removeAttribute("data-srcset");
            }

            if (window.Vvveb && window.Vvveb.Undo) {
                window.Vvveb.Undo.addMutation({
                    type: 'attributes',
                    target: img,
                    attributeName: 'loading',
                    oldValue: value ? 'lazy' : 'eager',
                    newValue: value ? 'eager' : 'lazy'
                });
            }
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpSaveHandler) {
                window.AlignBuilderModules.WpSaveHandler.setButtonState("dirty");
            }
            document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => btn.removeAttribute("disabled"));

            return img;
        }
    }, {
        name: "Align",
        key: "align",
        htmlAttr: "class",
        inline: false,
        validValues: ["", "align-left", "align-center", "align-right"],
        inputtype: RadioButtonInput,
        data: {
            extraclass: "btn-group-sm btn-group-fullwidth",
            options: [{
                value: "",
                icon: "la la-times",
                title: "None",
                checked: true,
            }, {
                value: "align-left",
                title: "text-start",
                icon: "la la-align-left",
                checked: false,
            }, {
                value: "align-center",
                title: "Center",
                icon: "la la-align-center",
                checked: false,
            }, {
                value: "align-right",
                title: "Right",
                icon: "la la-align-right",
                checked: false,
            }],
        },
    }, {
        key: "link_options",
        inputtype: SectionInput,
        data: { header: "Link" },
    }, {
        name: "Enable link",
        key: "enable_link",
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        setGroup: value => {
            let group = document.querySelectorAll('.mb-2[data-group="link"]');
            if (group.length) {
                group.forEach(el => {
                    el.style.display = value ? "" : "none";
                });
            }
        },
        onChange: function(node, value, input) {
            this.setGroup(value);
            if (!node) return node;
            if (value) {
                const wrappingElement = document.createElement('a');
                node.replaceWith(wrappingElement);
                wrappingElement.appendChild(node);
            } else {
                if (node.parentNode && node.parentNode.tagName?.toLowerCase() == "a"){
                    node.parentNode.replaceWith(node);
                }
            }
            return node;
        },
        init: function (node) {
            if (!node || !node.parentNode) return false;
            let value = node.parentNode.tagName?.toLowerCase() == "a";
            this.setGroup(value);
            return value;
        }
    }].concat(
        (typeof linkComponentProperties !== "undefined" && Array.isArray(linkComponentProperties))
            ? linkComponentProperties.map(
                (el) => { let a = Object.assign({}, el); a["parent"] = "a"; a["group"] = "link"; return a; }
            )
            : []
    ),

    init(node) {
        if (node && Vvveb.Builder.unwrapPictureTag) {
            node = Vvveb.Builder.unwrapPictureTag(node);
        }
        let group = document.querySelectorAll('.mb-2[data-group="link"]');
        let aid = node?.getAttribute?.('data-id') || node?.parentElement?.getAttribute?.('data-id');
        if (aid && window.AlignBuilderModules?.MediaSizeTags?.render) {
            let container = document.querySelector('.vvveb-media-input-wrapper .media-locked-container');
            if (container) {
                window.AlignBuilderModules.MediaSizeTags.render(container, aid, node);
            }
        }
        return node;
    }
});
