(function() {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, Button, BaseControl, ToggleControl, FocalPointPicker, RangeControl, TabPanel } = wp.components;
    const { createElement: el, Fragment } = wp.element;

    function getRelativeUrl(url) {
        if (!url) return '';
        try {
            var parsed = new URL(url);
            return parsed.pathname + parsed.search + parsed.hash;
        } catch (e) {
            var currentOrigin = window.location.origin;
            if (url.indexOf(currentOrigin) === 0) {
                return url.substring(currentOrigin.length);
            }
            return url;
        }
    }

    const AlignSelectControl = function(props) {
        const [isOpen, setIsOpen] = wp.element.useState(false);
        const wrapperRef = wp.element.useRef(null);

        wp.element.useEffect(function() {
            const handleClickOutside = function(event) {
                if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                    setIsOpen(false);
                }
            };
            document.addEventListener('mousedown', handleClickOutside);
            return function() {
                document.removeEventListener('mousedown', handleClickOutside);
            };
        }, []);

        var selectedOpt = props.options.find(function(o) { return String(o.value) === String(props.value); });
        var displayLabel = selectedOpt ? selectedOpt.label : (props.options.length > 0 ? props.options[0].label : '');

        return el('div', { 
            className: 'align-custom-select-wrapper ' + (isOpen ? 'is-open' : ''), 
            ref: wrapperRef,
            style: { marginBottom: '16px', position: 'relative', fontFamily: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif' } 
        }, [
            props.label ? el('label', { key: 'label' }, props.label) : null,
            el('button', {
                key: 'button',
                className: 'align-select-btn',
                onClick: function(e) { e.preventDefault(); setIsOpen(!isOpen); },
                type: 'button'
            }, [
                el('span', { key: 'text' }, displayLabel),
                el('span', { key: 'arrow', className: 'align-custom-select-arrow' })
            ]),
            isOpen ? el('div', {
                key: 'dropdown',
                className: 'align-select-dropdown'
            }, props.options.map(function(opt, index) {
                return el('div', {
                    key: index,
                    className: 'align-select-opt ' + (opt.disabled ? 'is-disabled' : ''),
                    onClick: function() {
                        if (opt.disabled) return;
                        setIsOpen(false);
                        if (props.onChange) props.onChange(opt.value);
                    }
                }, opt.label);
            })) : null
        ]);
    };

    function EditInternal(props) {
        const { attributes, setAttributes } = props;
        const [activeTab, setActiveTab] = wp.element.useState('desktop');
        const [activeInspectorTab, setActiveInspectorTab] = wp.element.useState('editor');
        const currentDevice = wp.data.useSelect(function(select) {
            const editPostSelect = select('core/edit-post');
            if (editPostSelect && editPostSelect.__experimentalGetPreviewDeviceType) {
                return (editPostSelect.__experimentalGetPreviewDeviceType() || 'Desktop').toLowerCase();
            }
            const editorSelect = select('core/editor');
            if (editorSelect && editorSelect.getDeviceType) {
                return (editorSelect.getDeviceType() || 'Desktop').toLowerCase();
            }
            return 'desktop';
        }, []);

        wp.element.useEffect(function() {
            if (currentDevice) {
                setActiveTab(currentDevice);
            }
        }, [currentDevice]);
        const {
            mediaType,
            useFeaturedImage,
            customAttributes,
            mp4Url,
            webmUrl,
            posterUrl,
            videoTitle,
            loop,
            autoplay,
            muted,
            playsinline,
            preload,
            ariaHidden,
            videoClass,
            videoId,
            imageUrl,
            desktopImgUrl,
            imageAlt,
            ignoreLazyLoad,
            fetchPriority,
            figureClass,
            imgClass,
            imgId,
            focalPoint,
            tabletImgId,
            tabletImgUrl,
            mobileImgId,
            mobileImgUrl,
            tabletFocalPoint,
            mobileFocalPoint,
            tabletMp4Url,
            tabletWebmUrl,
            tabletPosterUrl,
            mobileMp4Url,
            mobileWebmUrl,
            mobilePosterUrl,
            imageOpacity,
            tabletOpacity,
            mobileOpacity,
            desktopSize,
            tabletSize,
            mobileSize
        } = attributes;

        const isAbsolute = (imgClass && imgClass.indexOf('absolute') !== -1) || 
                           (customAttributes && customAttributes.indexOf('absolute') !== -1);
        
        let blockClasses = '';
        let blockStyles = {};
        if (isAbsolute) {
            blockClasses = 'absolute inset-0 w-full h-full pointer-events-none z-0';
            blockStyles = { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, width: '100%', height: '100%', zIndex: 0, pointerEvents: 'none' };
        }

        const blockProps = useBlockProps({
            className: blockClasses,
            style: blockStyles
        });
        const videoRef = wp.element.useRef(null);

        // Fetch post editor details to resolve featured image realtime
        const currentPostType = wp.data.useSelect(select => select('core/editor').getCurrentPostType(), []);
        const currentFeaturedMediaId = wp.data.useSelect(select => select('core/editor').getEditedPostAttribute('featured_media'), []);
        const isTemplate = currentPostType === 'wp_template' || currentPostType === 'wp_template_part';
        
        const templateSlug = wp.data.useSelect(select => {
            if (isTemplate) {
                return select('core/editor').getEditedPostAttribute('slug');
            }
            return '';
        }, [isTemplate]);

        // Guess the target post type based on template slug
        let targetPostType = 'post';
        if (isTemplate && templateSlug) {
            if (templateSlug.indexOf('single-') === 0) {
                targetPostType = templateSlug.replace('single-', '');
            } else if (templateSlug.indexOf('archive-') === 0) {
                targetPostType = templateSlug.replace('archive-', '');
            } else if (templateSlug === 'page' || templateSlug.indexOf('page-') === 0) {
                targetPostType = 'page';
            }
        }

        // Fetch latest posts of targetPostType to get fallback featured image ONLY when editing a template
        const latestPosts = wp.data.useSelect(select => {
            if (useFeaturedImage && isTemplate) {
                return select('core').getEntityRecords('postType', targetPostType, {
                    per_page: 5,
                    status: 'publish'
                });
            }
            return null;
        }, [useFeaturedImage, isTemplate, targetPostType]);

        let fallbackFeaturedMediaId = 0;
        if (latestPosts && latestPosts.length > 0) {
            const postWithFeatured = latestPosts.find(p => p.featured_media > 0);
            if (postWithFeatured) {
                fallbackFeaturedMediaId = postWithFeatured.featured_media;
            }
        }

        // Determine active media ID to display
        // In template: use fallback from latest post
        // In page/post/custom post type: use current post's featured media (do not fallback to other posts)
        const activeMediaId = useFeaturedImage 
            ? (isTemplate ? fallbackFeaturedMediaId : (currentFeaturedMediaId || 0))
            : attributes.imageId;

        const activeMedia = wp.data.useSelect(function(select) {
            return activeMediaId ? select('core').getMedia(activeMediaId) : null;
        }, [activeMediaId]);

        const tabletMedia = wp.data.useSelect(function(select) {
            return tabletImgId ? select('core').getMedia(tabletImgId) : null;
        }, [tabletImgId]);

        const mobileMedia = wp.data.useSelect(function(select) {
            return mobileImgId ? select('core').getMedia(mobileImgId) : null;
        }, [mobileImgId]);

        const imageSizes = wp.data.useSelect(function(select) {
            const settings = select('core/block-editor') ? select('core/block-editor').getSettings() : (select('core') ? select('core').getSettings() : {});
            return settings.imageSizes || [];
        }, []);

        const sizeOptions = [
            { label: __('Default', 'align-function'), value: '' }
        ];
        if (imageSizes && imageSizes.length > 0) {
            imageSizes.forEach(function(size) {
                sizeOptions.push({ label: size.name, value: size.slug });
            });
            if (!sizeOptions.some(function(opt) { return opt.value === 'full'; })) {
                sizeOptions.push({ label: __('Full (Original)', 'align-function'), value: 'full' });
            }
        } else {
            sizeOptions.push(
                { label: __('Thumbnail', 'align-function'), value: 'thumbnail' },
                { label: __('Medium', 'align-function'), value: 'medium' },
                { label: __('Medium Large', 'align-function'), value: 'medium_large' },
                { label: __('Large', 'align-function'), value: 'large' },
                { label: __('Full', 'align-function'), value: 'full' }
            );
        }

        const objectFitOptions = [
            { label: __('Cover (Scale to fill)', 'align-function'), value: 'cover' },
            { label: __('Contain (Scale to fit)', 'align-function'), value: 'contain' },
            { label: __('Fill (Stretch)', 'align-function'), value: 'fill' },
            { label: __('None (Original size)', 'align-function'), value: 'none' }
        ];

        // 1. Resolve Desktop URL & Alt
        let resolvedImgUrl = '';
        let resolvedImgAlt = '';
        if (activeMedia) {
            if (activeMedia.media_details && activeMedia.media_details.sizes) {
                const sizes = activeMedia.media_details.sizes;
                const sizeToUse = desktopSize || 'large';
                if (sizes[sizeToUse]) {
                    resolvedImgUrl = sizes[sizeToUse].source_url;
                } else if (sizes.large) {
                    resolvedImgUrl = sizes.large.source_url;
                } else if (sizes.full) {
                    resolvedImgUrl = sizes.full.source_url;
                } else if (sizes.medium) {
                    resolvedImgUrl = sizes.medium.source_url;
                }
            }
            if (!resolvedImgUrl) {
                resolvedImgUrl = activeMedia.source_url;
            }
            resolvedImgAlt = activeMedia.alt_text || (activeMedia.title && activeMedia.title.rendered) || '';
        }
        if (!resolvedImgUrl) {
            resolvedImgUrl = desktopImgUrl;
        }

        // 2. Resolve Tablet URL
        let resolvedTabletUrl = '';
        if (tabletMedia) {
            if (tabletMedia.media_details && tabletMedia.media_details.sizes) {
                const sizes = tabletMedia.media_details.sizes;
                const sizeToUse = tabletSize || 'medium_large';
                if (sizes[sizeToUse]) {
                    resolvedTabletUrl = sizes[sizeToUse].source_url;
                } else if (sizes.large) {
                    resolvedTabletUrl = sizes.large.source_url;
                } else if (sizes.full) {
                    resolvedTabletUrl = sizes.full.source_url;
                }
            }
            if (!resolvedTabletUrl) {
                resolvedTabletUrl = tabletMedia.source_url;
            }
        }
        if (!resolvedTabletUrl) {
            resolvedTabletUrl = tabletImgUrl;
        }
        if (!resolvedTabletUrl && activeMedia) {
            if (activeMedia.media_details && activeMedia.media_details.sizes) {
                const sizes = activeMedia.media_details.sizes;
                const sizeToUse = tabletSize || 'medium_large';
                if (sizes[sizeToUse]) {
                    resolvedTabletUrl = sizes[sizeToUse].source_url;
                } else if (sizes.large) {
                    resolvedTabletUrl = sizes.large.source_url;
                } else if (sizes.full) {
                    resolvedTabletUrl = sizes.full.source_url;
                }
            }
        }
        if (!resolvedTabletUrl) {
            resolvedTabletUrl = resolvedImgUrl;
        }

        // 3. Resolve Mobile URL
        let resolvedMobileUrl = '';
        if (mobileMedia) {
            if (mobileMedia.media_details && mobileMedia.media_details.sizes) {
                const sizes = mobileMedia.media_details.sizes;
                const sizeToUse = mobileSize || 'medium';
                if (sizes[sizeToUse]) {
                    resolvedMobileUrl = sizes[sizeToUse].source_url;
                } else if (sizes.large) {
                    resolvedMobileUrl = sizes.large.source_url;
                } else if (sizes.full) {
                    resolvedMobileUrl = sizes.full.source_url;
                }
            }
            if (!resolvedMobileUrl) {
                resolvedMobileUrl = mobileMedia.source_url;
            }
        }
        if (!resolvedMobileUrl) {
            resolvedMobileUrl = mobileImgUrl;
        }
        if (!resolvedMobileUrl && activeMedia) {
            if (activeMedia.media_details && activeMedia.media_details.sizes) {
                const sizes = activeMedia.media_details.sizes;
                const sizeToUse = mobileSize || 'medium';
                if (sizes[sizeToUse]) {
                    resolvedMobileUrl = sizes[sizeToUse].source_url;
                } else if (sizes.large) {
                    resolvedMobileUrl = sizes.large.source_url;
                } else if (sizes.full) {
                    resolvedMobileUrl = sizes.full.source_url;
                }
            }
        }
        if (!resolvedMobileUrl) {
            resolvedMobileUrl = resolvedImgUrl;
        }

        wp.element.useEffect(function() {
            const video = videoRef.current;
            if (!video) {
                return;
            }

            // Sync loop and muted properties
            video.loop = !!loop;
            video.muted = !!muted;

            // Force browser to load the new video source on change
            video.load();

            // Handle autoplay and play/pause logic
            if (autoplay) {
                video.play().catch(function(error) {
                    // Try setting muted to true and playing again if browser blocks autoplay
                    video.muted = true;
                    video.play().catch(function(playError) {
                        console.warn("Autoplay failed even with muted=true:", playError);
                    });
                });
            } else {
                video.pause();
            }
        }, [autoplay, loop, muted, mp4Url, webmUrl]);

        const inspector = el(InspectorControls, {}, [
            TabPanel ? el(TabPanel, {
                className: 'align-tab-panel align-qb-tab-panel',
                activeClass: 'is-active',
                tabs: [
                    { name: 'editor', title: __('Editor', 'align-function'), className: 'align-qb-tab-item' },
                    { name: 'seo', title: __('SEO/ADA', 'align-function'), className: 'align-qb-tab-item' },
                    { name: 'technical', title: __('Technical', 'align-function'), className: 'align-qb-tab-item' }
                ]
            }, function(tab) {
                if (tab.name === 'editor') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('Editor', 'align-function'), initialOpen: true }, [
                            el(AlignSelectControl, {
                                label: __('Media Type', 'align-function'),
                                value: mediaType,
                                options: [
                                    { label: __('Video', 'align-function'), value: 'video' },
                                    { label: __('Image (Responsive Picture)', 'align-function'), value: 'image' }
                                ],
                                onChange: function(val) { setAttributes({ mediaType: val }); }
                            }),
                            
                            // Video editor fields
                            mediaType === 'video' ? el(Fragment, { key: 'video-editor-fields' }, [
                                el('div', { 
                                    key: 'video-responsive-tabs',
                                    className: 'align-media-tabs'
                                }, [
                                    ['desktop', __('Desktop', 'align-function')],
                                    ['tablet', __('Tablet', 'align-function')],
                                    ['mobile', __('Mobile', 'align-function')]
                                ].map(function(tabInfo) {
                                    const tabKey = tabInfo[0];
                                    const tabLabel = tabInfo[1];
                                    const isActive = activeTab === tabKey;
                                    return el('button', {
                                        key: tabKey,
                                        type: 'button',
                                        className: 'align-media-tab-item ' + (isActive ? 'is-active' : ''),
                                        onClick: function(e) { e.preventDefault(); setActiveTab(tabKey); }
                                    }, tabLabel);
                                })),

                                // Video Tab 1: Desktop
                                activeTab === 'desktop' ? el(Fragment, { key: 'video-desktop-tab' }, [
                                    el(BaseControl, { label: __('MP4 Video Source', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: mp4Url,
                                                onChange: function(val) { setAttributes({ mp4Url: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        var attrs = {
                                                            mp4Url: getRelativeUrl(media.url)
                                                        };
                                                        if (media && media.title) {
                                                            attrs.videoTitle = media.title;
                                                        }
                                                        setAttributes(attrs);
                                                    },
                                                    allowedTypes: ['video'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    el(BaseControl, { label: __('WebM Video Source (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: webmUrl,
                                                onChange: function(val) { setAttributes({ webmUrl: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        setAttributes({
                                                            webmUrl: getRelativeUrl(media.url)
                                                        });
                                                    },
                                                    allowedTypes: ['video'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    el(BaseControl, { label: __('Poster Image (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: posterUrl,
                                                onChange: function(val) { setAttributes({ posterUrl: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        setAttributes({
                                                            posterUrl: getRelativeUrl(media.url)
                                                        });
                                                    },
                                                    allowedTypes: ['image'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    (mp4Url || webmUrl || posterUrl) ? el('div', { style: { marginBottom: '16px' } }, [
                                        el(FocalPointPicker, {
                                            label: __('Focal Point', 'align-function'),
                                            url: posterUrl || mp4Url || webmUrl,
                                            value: focalPoint || { x: 0.5, y: 0.5 },
                                            onChange: function(val) { setAttributes({ focalPoint: val }); }
                                        })
                                    ]) : null
                                ]) : null,

                                // Video Tab 2: Tablet
                                activeTab === 'tablet' ? el(Fragment, { key: 'video-tablet-tab' }, [
                                    el(BaseControl, { label: __('Tablet MP4 Video Source (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: tabletMp4Url,
                                                onChange: function(val) { setAttributes({ tabletMp4Url: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        var attrs = {
                                                            tabletMp4Url: getRelativeUrl(media.url)
                                                        };
                                                        if (media && media.title) {
                                                            attrs.videoTitle = media.title;
                                                        }
                                                        setAttributes(attrs);
                                                    },
                                                    allowedTypes: ['video'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    el(BaseControl, { label: __('Tablet WebM Video Source (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: tabletWebmUrl,
                                                onChange: function(val) { setAttributes({ tabletWebmUrl: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        setAttributes({
                                                            tabletWebmUrl: getRelativeUrl(media.url)
                                                        });
                                                    },
                                                    allowedTypes: ['video'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    el(BaseControl, { label: __('Tablet Poster Image (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: tabletPosterUrl,
                                                onChange: function(val) { setAttributes({ tabletPosterUrl: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        setAttributes({
                                                            tabletPosterUrl: getRelativeUrl(media.url)
                                                        });
                                                    },
                                                    allowedTypes: ['image'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    (tabletMp4Url || tabletWebmUrl || tabletPosterUrl || mp4Url || webmUrl || posterUrl) ? el('div', { style: { marginBottom: '16px' } }, [
                                        el(FocalPointPicker, {
                                            label: __('Tablet Focal Point', 'align-function'),
                                            url: tabletPosterUrl || tabletMp4Url || tabletWebmUrl || posterUrl || mp4Url || webmUrl,
                                            value: tabletFocalPoint || { x: 0.5, y: 0.5 },
                                            onChange: function(val) { setAttributes({ tabletFocalPoint: val }); }
                                        })
                                    ]) : null
                                ]) : null,

                                // Video Tab 3: Mobile
                                activeTab === 'mobile' ? el(Fragment, { key: 'video-mobile-tab' }, [
                                    el(BaseControl, { label: __('Mobile MP4 Video Source (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: mobileMp4Url,
                                                onChange: function(val) { setAttributes({ mobileMp4Url: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        var attrs = {
                                                            mobileMp4Url: getRelativeUrl(media.url)
                                                        };
                                                        if (media && media.title) {
                                                            attrs.videoTitle = media.title;
                                                        }
                                                        setAttributes(attrs);
                                                    },
                                                    allowedTypes: ['video'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    el(BaseControl, { label: __('Mobile WebM Video Source (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: mobileWebmUrl,
                                                onChange: function(val) { setAttributes({ mobileWebmUrl: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        setAttributes({
                                                            mobileWebmUrl: getRelativeUrl(media.url)
                                                        });
                                                    },
                                                    allowedTypes: ['video'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    el(BaseControl, { label: __('Mobile Poster Image (Optional)', 'align-function') },
                                        el('div', { className: 'align-media-uploader-row' }, [
                                            el(TextControl, {
                                                value: mobilePosterUrl,
                                                onChange: function(val) { setAttributes({ mobilePosterUrl: val }); },
                                                style: { flex: 1, marginBottom: 0 }
                                            }),
                                            el(MediaUploadCheck, {},
                                                el(MediaUpload, {
                                                    onSelect: function(media) {
                                                        setAttributes({
                                                            mobilePosterUrl: getRelativeUrl(media.url)
                                                        });
                                                    },
                                                    allowedTypes: ['image'],
                                                    render: function(obj) {
                                                        return el(Button, { isSecondary: true, onClick: obj.open }, __('Select', 'align-function'));
                                                    }
                                                })
                                            )
                                        ])
                                    ),
                                    (mobileMp4Url || mobileWebmUrl || mobilePosterUrl || mp4Url || webmUrl || posterUrl) ? el('div', { style: { marginBottom: '16px' } }, [
                                        el(FocalPointPicker, {
                                            label: __('Mobile Focal Point', 'align-function'),
                                            url: mobilePosterUrl || mobileMp4Url || mobileWebmUrl || posterUrl || mp4Url || webmUrl,
                                            value: mobileFocalPoint || { x: 0.5, y: 0.5 },
                                            onChange: function(val) { setAttributes({ mobileFocalPoint: val }); }
                                        })
                                    ]) : null
                                ]) : null,

                                el(TextControl, {
                                    label: __('Video Title', 'align-function'),
                                    value: videoTitle,
                                    onChange: function(val) { setAttributes({ videoTitle: val }); }
                                }),
                                el(ToggleControl, {
                                    label: __('Autoplay', 'align-function'),
                                    checked: autoplay,
                                    onChange: function(val) { setAttributes({ autoplay: val }); }
                                }),
                                el(ToggleControl, {
                                    label: __('Loop', 'align-function'),
                                    checked: loop,
                                    onChange: function(val) { setAttributes({ loop: val }); }
                                }),
                                el(ToggleControl, {
                                    label: __('Muted', 'align-function'),
                                    checked: muted,
                                    onChange: function(val) { setAttributes({ muted: val }); }
                                }),
                                el(ToggleControl, {
                                    label: __('Playsinline', 'align-function'),
                                    checked: playsinline,
                                    onChange: function(val) { setAttributes({ playsinline: val }); }
                                }),
                                el(AlignSelectControl, {
                                    label: __('Preload', 'align-function'),
                                    value: preload,
                                    options: [
                                        { label: __('Auto', 'align-function'), value: 'auto' },
                                        { label: __('Metadata', 'align-function'), value: 'metadata' },
                                        { label: __('None', 'align-function'), value: 'none' }
                                    ],
                                    onChange: function(val) { setAttributes({ preload: val }); }
                                })
                            ]) : null,

                            // Image editor fields
                            mediaType === 'image' ? el(Fragment, { key: 'image-editor-fields' }, [
                                el('div', { 
                                    key: 'responsive-tabs',
                                    className: 'align-media-tabs'
                                }, [
                                    ['desktop', __('Desktop', 'align-function')],
                                    ['tablet', __('Tablet', 'align-function')],
                                    ['mobile', __('Mobile', 'align-function')]
                                ].map(function(tabInfo) {
                                    const tabKey = tabInfo[0];
                                    const tabLabel = tabInfo[1];
                                    const isActive = activeTab === tabKey;
                                    return el('button', {
                                        key: tabKey,
                                        type: 'button',
                                        className: 'align-media-tab-item ' + (isActive ? 'is-active' : ''),
                                        onClick: function(e) { e.preventDefault(); setActiveTab(tabKey); }
                                    }, tabLabel);
                                })),

                                // Tab 1: Desktop
                                activeTab === 'desktop' ? el(Fragment, { key: 'desktop-tab' }, [
                                    el(ToggleControl, {
                                        label: __('Use Featured Image', 'align-function'),
                                        checked: !!useFeaturedImage,
                                        onChange: function(val) { setAttributes({ useFeaturedImage: val }); }
                                    }),
                                    !useFeaturedImage ? el(BaseControl, { label: __('Desktop Image', 'align-function') },
                                        el(MediaUploadCheck, {},
                                            el(MediaUpload, {
                                                onSelect: function(media) {
                                                    var altText = media.alt || '';
                                                    if (!altText && media.url) {
                                                        var filename = media.url.substring(media.url.lastIndexOf('/') + 1);
                                                        filename = filename.substring(0, filename.lastIndexOf('.'));
                                                        altText = filename.replace(/[-_]/g, ' ');
                                                    }
                                                    setAttributes({
                                                        imageId: media.id,
                                                        desktopImgId: media.id,
                                                        imageUrl: getRelativeUrl(media.url),
                                                        desktopImgUrl: getRelativeUrl(media.url),
                                                        imageAlt: altText
                                                    });
                                                },
                                                allowedTypes: ['image'],
                                                value: attributes.imageId || attributes.desktopImgId,
                                                render: function(obj) {
                                                    if (!desktopImgUrl && !imageUrl) {
                                                        return el(Button, {
                                                            className: 'align-media-sidebar-dropzone',
                                                            onClick: obj.open
                                                        }, [
                                                            el('span', { className: 'dashicons dashicons-format-image' }),
                                                            el('span', {}, __('Select Desktop Image', 'align-function'))
                                                        ]);
                                                    }
                                                    return el('div', { className: 'align-media-sidebar-preview-wrapper' }, [
                                                        el('div', { 
                                                            className: 'align-media-sidebar-image-preview-clickable',
                                                            onClick: obj.open,
                                                            title: __('Click to edit image', 'align-function')
                                                        }, el('img', { src: desktopImgUrl || imageUrl, alt: '' })),
                                                        el(Button, {
                                                            className: 'align-media-sidebar-remove-btn',
                                                            onClick: function(e) {
                                                                e.stopPropagation();
                                                                setAttributes({
                                                                    imageUrl: '',
                                                                    imageId: 0,
                                                                    desktopImgUrl: '',
                                                                    desktopImgId: 0
                                                                });
                                                            },
                                                            title: __('Remove Image', 'align-function')
                                                        }, '×')
                                                    ]);
                                                }
                                            })
                                        )
                                    ) : null,
                                    el(AlignSelectControl, {
                                        label: __('Image Render Size', 'align-function'),
                                        value: desktopSize || '',
                                        options: sizeOptions,
                                        onChange: function(val) { setAttributes({ desktopSize: val }); }
                                    }),
                                    el(AlignSelectControl, {
                                        label: __('Object Fit', 'align-function'),
                                        value: attributes.objectFit || 'cover',
                                        options: objectFitOptions,
                                        onChange: function(val) { setAttributes({ objectFit: val }); }
                                    }),
                                    (resolvedImgUrl || desktopImgUrl) ? el('div', { style: { marginBottom: '16px' } }, [
                                        el(FocalPointPicker, {
                                            label: __('Focal Point', 'align-function'),
                                            url: resolvedImgUrl || desktopImgUrl,
                                            value: focalPoint || { x: 0.5, y: 0.5 },
                                            onChange: function(val) { setAttributes({ focalPoint: val }); }
                                        })
                                    ]) : null,
                                    el(RangeControl, {
                                        label: __('Image Opacity', 'align-function'),
                                        value: imageOpacity !== undefined ? imageOpacity : 1,
                                        onChange: function(val) { setAttributes({ imageOpacity: val }); },
                                        min: 0.1,
                                        max: 1.0,
                                        step: 0.05
                                    })
                                ]) : null,

                                // Tab 2: Tablet
                                activeTab === 'tablet' ? el(Fragment, { key: 'tablet-tab' }, [
                                    el(BaseControl, { label: __('Tablet Image (Optional)', 'align-function') },
                                        el(MediaUploadCheck, {},
                                            el(MediaUpload, {
                                                onSelect: function(media) {
                                                    setAttributes({
                                                        tabletImgId: media.id,
                                                        tabletImgUrl: getRelativeUrl(media.url)
                                                    });
                                                },
                                                allowedTypes: ['image'],
                                                value: attributes.tabletImgId,
                                                render: function(obj) {
                                                    if (!tabletImgUrl) {
                                                        return el(Button, {
                                                            className: 'align-media-sidebar-dropzone',
                                                            onClick: obj.open
                                                        }, [
                                                            el('span', { className: 'dashicons dashicons-format-image' }),
                                                            el('span', {}, __('Select Tablet Image', 'align-function'))
                                                        ]);
                                                    }
                                                    return el('div', { className: 'align-media-sidebar-preview-wrapper' }, [
                                                        el('div', { 
                                                            className: 'align-media-sidebar-image-preview-clickable',
                                                            onClick: obj.open,
                                                            title: __('Click to edit image', 'align-function')
                                                        }, el('img', { src: tabletImgUrl, alt: '' })),
                                                        el(Button, {
                                                            className: 'align-media-sidebar-remove-btn',
                                                            onClick: function(e) {
                                                                e.stopPropagation();
                                                                setAttributes({
                                                                    tabletImgUrl: '',
                                                                    tabletImgId: 0
                                                                });
                                                            },
                                                            title: __('Remove Image', 'align-function')
                                                        }, '×')
                                                    ]);
                                                }
                                            })
                                        )
                                    ),
                                    el(AlignSelectControl, {
                                        label: __('Image Render Size', 'align-function'),
                                        value: tabletSize || '',
                                        options: sizeOptions,
                                        onChange: function(val) { setAttributes({ tabletSize: val }); }
                                    }),
                                    el(AlignSelectControl, {
                                        label: __('Tablet Object Fit', 'align-function'),
                                        value: attributes.tabletObjectFit || attributes.objectFit || 'cover',
                                        options: objectFitOptions,
                                        onChange: function(val) { setAttributes({ tabletObjectFit: val }); }
                                    }),
                                    (tabletImgUrl || resolvedImgUrl || desktopImgUrl) ? el('div', { style: { marginBottom: '16px' } }, [
                                        el(FocalPointPicker, {
                                            label: __('Tablet Focal Point', 'align-function'),
                                            url: tabletImgUrl || resolvedImgUrl || desktopImgUrl,
                                            value: tabletFocalPoint || { x: 0.5, y: 0.5 },
                                            onChange: function(val) { setAttributes({ tabletFocalPoint: val }); }
                                        })
                                    ]) : null,
                                    el(RangeControl, {
                                        label: __('Tablet Image Opacity', 'align-function'),
                                        value: attributes.tabletOpacity !== undefined ? attributes.tabletOpacity : 1,
                                        onChange: function(val) { setAttributes({ tabletOpacity: val }); },
                                        min: 0.1,
                                        max: 1.0,
                                        step: 0.05
                                    })
                                ]) : null,

                                // Tab 3: Mobile
                                activeTab === 'mobile' ? el(Fragment, { key: 'mobile-tab' }, [
                                    el(BaseControl, { label: __('Mobile Image (Optional)', 'align-function') },
                                        el(MediaUploadCheck, {},
                                            el(MediaUpload, {
                                                onSelect: function(media) {
                                                    setAttributes({
                                                        mobileImgId: media.id,
                                                        mobileImgUrl: getRelativeUrl(media.url)
                                                    });
                                                },
                                                allowedTypes: ['image'],
                                                value: attributes.mobileImgId,
                                                render: function(obj) {
                                                    if (!mobileImgUrl) {
                                                        return el(Button, {
                                                            className: 'align-media-sidebar-dropzone',
                                                            onClick: obj.open
                                                        }, [
                                                            el('span', { className: 'dashicons dashicons-format-image' }),
                                                            el('span', {}, __('Select Mobile Image', 'align-function'))
                                                        ]);
                                                    }
                                                    return el('div', { className: 'align-media-sidebar-preview-wrapper' }, [
                                                        el('div', { 
                                                            className: 'align-media-sidebar-image-preview-clickable',
                                                            onClick: obj.open,
                                                            title: __('Click to edit image', 'align-function')
                                                        }, el('img', { src: mobileImgUrl, alt: '' })),
                                                        el(Button, {
                                                            className: 'align-media-sidebar-remove-btn',
                                                            onClick: function(e) {
                                                                e.stopPropagation();
                                                                setAttributes({
                                                                    mobileImgUrl: '',
                                                                    mobileImgId: 0
                                                                });
                                                            },
                                                            title: __('Remove Image', 'align-function')
                                                        }, '×')
                                                    ]);
                                                }
                                            })
                                        )
                                    ),
                                    el(AlignSelectControl, {
                                        label: __('Image Render Size', 'align-function'),
                                        value: mobileSize || '',
                                        options: sizeOptions,
                                        onChange: function(val) { setAttributes({ mobileSize: val }); }
                                    }),
                                    el(AlignSelectControl, {
                                        label: __('Mobile Object Fit', 'align-function'),
                                        value: attributes.mobileObjectFit || attributes.objectFit || 'cover',
                                        options: objectFitOptions,
                                        onChange: function(val) { setAttributes({ mobileObjectFit: val }); }
                                    }),
                                    (mobileImgUrl || resolvedImgUrl || desktopImgUrl) ? el('div', { style: { marginBottom: '16px' } }, [
                                        el(FocalPointPicker, {
                                            label: __('Mobile Focal Point', 'align-function'),
                                            url: mobileImgUrl || resolvedImgUrl || desktopImgUrl,
                                            value: mobileFocalPoint || { x: 0.5, y: 0.5 },
                                            onChange: function(val) { setAttributes({ mobileFocalPoint: val }); }
                                        })
                                    ]) : null,
                                    el(RangeControl, {
                                        label: __('Mobile Image Opacity', 'align-function'),
                                        value: attributes.mobileOpacity !== undefined ? attributes.mobileOpacity : 1,
                                        onChange: function(val) { setAttributes({ mobileOpacity: val }); },
                                        min: 0.1,
                                        max: 1.0,
                                        step: 0.05
                                    })
                                ]) : null,

                                el(ToggleControl, {
                                    label: __('Ignore Lazy Load', 'align-function'),
                                    checked: ignoreLazyLoad,
                                    onChange: function(val) { setAttributes({ ignoreLazyLoad: val }); }
                                }),
                                el(AlignSelectControl, {
                                    label: __('Fetch Priority', 'align-function'),
                                    value: fetchPriority,
                                    options: [
                                        { label: __('High', 'align-function'), value: 'high' },
                                        { label: __('Low', 'align-function'), value: 'low' },
                                        { label: __('Auto', 'align-function'), value: 'auto' }
                                    ],
                                    onChange: function(val) { setAttributes({ fetchPriority: val }); }
                                })
                            ]) : null
                        ])
                    ]);
                }
                if (tab.name === 'seo') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('SEO/ADA', 'align-function'), initialOpen: true }, [
                            mediaType === 'video' ? el(ToggleControl, {
                                key: 'video-aria-hidden',
                                label: __('Aria Hidden', 'align-function'),
                                checked: ariaHidden,
                                onChange: function(val) { setAttributes({ ariaHidden: val }); }
                            }) : null,

                            mediaType === 'image' ? el(TextControl, {
                                key: 'image-alt-text',
                                label: __('Alt Text', 'align-function'),
                                value: imageAlt,
                                onChange: function(val) { setAttributes({ imageAlt: val }); }
                            }) : null
                        ])
                    ]);
                }
                if (tab.name === 'technical') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('Technical', 'align-function'), initialOpen: true }, [
                            mediaType === 'video' ? el(Fragment, { key: 'video-tech-fields' }, [
                                el(TextControl, {
                                    label: __('Video CSS Class', 'align-function'),
                                    value: videoClass,
                                    onChange: function(val) { setAttributes({ videoClass: val }); }
                                }),
                                el(TextControl, {
                                    label: __('Video Element ID', 'align-function'),
                                    value: videoId,
                                    onChange: function(val) { setAttributes({ videoId: val }); }
                                })
                            ]) : null,

                            mediaType === 'image' ? el(Fragment, { key: 'image-tech-fields' }, [
                                el(TextControl, {
                                    label: __('Figure Wrapper Class', 'align-function'),
                                    value: figureClass,
                                    onChange: function(val) { setAttributes({ figureClass: val }); }
                                }),
                                el(TextControl, {
                                    label: __('Img Element CSS Class', 'align-function'),
                                    value: imgClass,
                                    onChange: function(val) { setAttributes({ imgClass: val }); }
                                }),
                                el(TextControl, {
                                    label: __('Img Element ID', 'align-function'),
                                    value: imgId,
                                    onChange: function(val) { setAttributes({ imgId: val }); }
                                })
                            ]) : null,

                            el(TextControl, {
                                key: 'custom-attributes',
                                label: __('Custom Attributes', 'align-function'),
                                value: customAttributes,
                                onChange: function(val) { setAttributes({ customAttributes: val }); },
                                help: __('e.g. data-aos="fade-up" data-custom="value"', 'align-function')
                            })
                        ])
                    ]);
                }
            }) : null
        ]);

        let previewEl = null;
        if (mediaType === 'video') {
            let activeMp4 = mp4Url;
            let activeWebm = webmUrl;
            let activePoster = posterUrl;
            let activeFocalPoint = focalPoint;

            if (currentDevice === 'mobile') {
                activeMp4 = mobileMp4Url || mp4Url;
                activeWebm = mobileWebmUrl || webmUrl;
                activePoster = mobilePosterUrl || posterUrl;
                activeFocalPoint = mobileFocalPoint;
            } else if (currentDevice === 'tablet') {
                activeMp4 = tabletMp4Url || mp4Url;
                activeWebm = tabletWebmUrl || webmUrl;
                activePoster = tabletPosterUrl || posterUrl;
                activeFocalPoint = tabletFocalPoint;
            }

            const hasVideo = activeMp4 || activeWebm;
            if (hasVideo) {
                let videoStyle = { 
                    display: 'block'
                };
                if (isAbsolute) {
                    videoStyle.width = '100%';
                    videoStyle.height = '100%';
                    videoStyle.objectFit = 'cover';
                } else {
                    videoStyle.maxWidth = '100%';
                    videoStyle.height = 'auto';
                }

                previewEl = el('video', {
                    ref: videoRef,
                    className: videoClass || undefined,
                    id: videoId || undefined,
                    src: activeMp4 || activeWebm,
                    poster: activePoster,
                    controls: false,
                    style: videoStyle,
                    autoPlay: !!autoplay,
                    muted: !!muted,
                    loop: !!loop,
                    playsInline: !!playsinline
                });
            } else {
                previewEl = el('div', { className: 'align-block-placeholder align-media-placeholder' }, [
                    el('div', { className: 'align-placeholder-header' }, [
                        el('span', { className: 'dashicons dashicons-video-alt3' }),
                        el('p', { className: 'align-placeholder-title' }, __('No Video Selected', 'align-function'))
                    ]),
                    el('p', { className: 'align-placeholder-desc' }, __('Configure video sources in sidebar.', 'align-function'))
                ]);
            }
        } else {
            let displayImgUrl = '';
            let displayOpacity = 1;
            let displayObjectFit = 'cover';
            if (currentDevice === 'mobile') {
                displayImgUrl = resolvedMobileUrl;
                activeFocalPoint = mobileFocalPoint;
                displayOpacity = mobileOpacity !== undefined ? mobileOpacity : (imageOpacity !== undefined ? imageOpacity : 1);
                displayObjectFit = attributes.mobileObjectFit || attributes.objectFit || 'cover';
            } else if (currentDevice === 'tablet') {
                displayImgUrl = resolvedTabletUrl;
                activeFocalPoint = tabletFocalPoint;
                displayOpacity = tabletOpacity !== undefined ? tabletOpacity : (imageOpacity !== undefined ? imageOpacity : 1);
                displayObjectFit = attributes.tabletObjectFit || attributes.objectFit || 'cover';
            } else {
                displayImgUrl = resolvedImgUrl;
                activeFocalPoint = focalPoint;
                displayOpacity = imageOpacity !== undefined ? imageOpacity : 1;
                displayObjectFit = attributes.objectFit || 'cover';
            }

            const displayImgAlt = useFeaturedImage ? resolvedImgAlt : imageAlt;

            if (displayImgUrl) {
                const x = activeFocalPoint && activeFocalPoint.x !== undefined ? activeFocalPoint.x * 100 : 50;
                const y = activeFocalPoint && activeFocalPoint.y !== undefined ? activeFocalPoint.y * 100 : 50;
                let imgStyle = { 
                    display: 'block',
                    objectPosition: x + '% ' + y + '%',
                    objectFit: displayObjectFit,
                    opacity: 1
                };
                if (isAbsolute) {
                    imgStyle.width = '100%';
                    imgStyle.height = '100%';
                    imgStyle.pointerEvents = 'none';
                } else {
                    imgStyle.maxWidth = '100%';
                    imgStyle.height = 'auto';
                }

                const overlayOpacity = Math.max(0, 1 - displayOpacity);
                const imgNode = el('img', {
                    className: imgClass || undefined,
                    id: imgId || undefined,
                    src: displayImgUrl,
                    alt: displayImgAlt,
                    style: imgStyle
                });

                if (overlayOpacity > 0) {
                    previewEl = el('div', {
                        className: 'align-media-preview-wrap',
                        style: { position: 'relative', display: 'inline-block', overflow: 'hidden', width: isAbsolute ? '100%' : 'auto', height: isAbsolute ? '100%' : 'auto' }
                    }, [
                        imgNode,
                        el('div', {
                            className: 'align-media-overlay-layer',
                            style: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: '#000', opacity: overlayOpacity, pointerEvents: 'none', zIndex: 2 }
                        })
                    ]);
                } else {
                    previewEl = imgNode;
                }
            } else {
                previewEl = el('div', { className: 'align-block-placeholder align-media-placeholder' }, [
                    el('div', { className: 'align-placeholder-header' }, [
                        el('span', { className: 'dashicons dashicons-format-image' }),
                        el('p', { className: 'align-placeholder-title' }, useFeaturedImage ? __('No Featured Image Found', 'align-function') : __('No Image Selected', 'align-function'))
                    ]),
                    el('p', { className: 'align-placeholder-desc' }, useFeaturedImage 
                        ? (isTemplate 
                            ? __('Ensure the latest post of type "' + targetPostType + '" has a featured image to preview.', 'align-function')
                            : __('Set a featured image for this post in the document sidebar to preview.', 'align-function'))
                        : __('Choose an image in sidebar.', 'align-function'))
                ]);
            }
        }

        return el(Fragment, {}, [
            inspector,
            el('div', blockProps, previewEl)
        ]);
    }

    class AlignErrorBoundary extends wp.element.Component {
        constructor(props) {
            super(props);
            this.state = { hasError: false, error: null };
        }
        static getDerivedStateFromError(error) {
            return { hasError: true, error: error };
        }
        componentDidCatch(error, errorInfo) {
            console.error("Align Media Block Crash:", error, errorInfo);
        }
        render() {
            if (this.state.hasError) {
                return el('div', { style: { padding: '10px', background: '#ff000020', border: '1px solid red', zIndex: 9999, position: 'relative' } }, 
                    el('h4', { style: { color: 'red', margin: '0 0 5px 0' } }, 'Block Error!'),
                    el('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '11px', margin: 0 } }, String(this.state.error))
                );
            }
            return this.props.children;
        }
    }

    const customLogoIcon = el('svg', {
        width: '24',
        height: '24',
        viewBox: '0 0 24 24',
        className: 'lucide lucide-image',
        style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' }
    }, [
        el('g', { transform: 'translate(2.4, 2.4) scale(0.8)' }, [
            el('rect', { width: '18', height: '18', x: '3', y: '3', rx: '2', ry: '2', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('circle', { cx: '9', cy: '9', r: '2', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('path', { d: 'm21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } })
        ])
    ]);

    registerBlockType('align/media', {
        icon: customLogoIcon,
        edit: function(props) {
            return el(AlignErrorBoundary, null, el(EditInternal, props));
        },
        save: function () {
            return null;
        }
    });
})();
