(function() {
    function initAlignMarquees() {
        const containers = document.querySelectorAll('.align-marquee-container:not([data-initialized="true"])');
        if (containers.length === 0) {
            return;
        }

        if (typeof Splide === 'undefined' || !window.splide || !window.splide.Extensions || !window.splide.Extensions.AutoScroll) {
            var checkDependencies = setInterval(function() {
                if (typeof Splide !== 'undefined' && window.splide && window.splide.Extensions && window.splide.Extensions.AutoScroll) {
                    clearInterval(checkDependencies);
                    initAlignMarquees();
                }
            }, 50);
            return;
        }

        containers.forEach(function(container) {
            let config = {};
            const configStr = container.getAttribute('data-config');
            if (configStr) {
                try {
                    config = JSON.parse(configStr);
                } catch (e) {
                    console.error('Invalid Marquee config', e);
                }
            }

            const speed = parseFloat(config.speed) || 1;
            const direction = config.direction || 'ltr';
            const spaceBetween = config.spaceBetween !== undefined ? parseInt(config.spaceBetween, 10) : 20;
            const pauseOnHover = config.pauseOnHover !== false;
            
            const spvDesktop = config.slidesPerView === 'auto' ? 'auto' : (parseFloat(config.slidesPerView) || 'auto');
            const spvTablet = config.slidesPerViewTablet === 'auto' ? 'auto' : (parseFloat(config.slidesPerViewTablet) || 'auto');
            const spvMobile = config.slidesPerViewMobile === 'auto' ? 'auto' : (parseFloat(config.slidesPerViewMobile) || 'auto');

            // speed > 0 moves right-to-left (RTL flow), speed < 0 moves left-to-right (LTR flow)
            const autoScrollSpeed = direction === 'ltr' ? -speed : speed;

            // Eagerly resolve lazy-loaded media inside the marquee container before Splide mounts.
            // This ensures cloned slides have correct sources and Splide calculates widths accurately.
            container.querySelectorAll('img[data-src], img[data-srcset]').forEach(function(img) {
                if (img.getAttribute('data-src')) {
                    img.setAttribute('src', img.getAttribute('data-src'));
                    img.removeAttribute('data-src');
                }
                if (img.getAttribute('data-srcset')) {
                    img.setAttribute('srcset', img.getAttribute('data-srcset'));
                    img.removeAttribute('data-srcset');
                }
                img.classList.remove('lazy');
            });
            container.querySelectorAll('source[data-src], source[data-srcset]').forEach(function(source) {
                if (source.getAttribute('data-src')) {
                    source.setAttribute('src', source.getAttribute('data-src'));
                    source.removeAttribute('data-src');
                }
                if (source.getAttribute('data-srcset')) {
                    source.setAttribute('srcset', source.getAttribute('data-srcset'));
                    source.removeAttribute('data-srcset');
                }
            });
            container.querySelectorAll('video[data-poster], video.lazy').forEach(function(video) {
                if (video.getAttribute('data-poster')) {
                    video.setAttribute('poster', video.getAttribute('data-poster'));
                    video.removeAttribute('data-poster');
                }
                video.classList.remove('lazy');
            });

            const splideOptions = {
                type: 'loop',
                drag: 'free',
                focus: 0,
                arrows: false,
                pagination: false,
                clones: 30,
                autoWidth: spvDesktop === 'auto',
                perPage: spvDesktop === 'auto' ? undefined : spvDesktop,
                gap: spaceBetween + 'px',
                autoScroll: {
                    speed: autoScrollSpeed,
                    pauseOnHover: pauseOnHover,
                    pauseOnFocus: false,
                },
                breakpoints: {
                    768: {
                        autoWidth: spvTablet === 'auto',
                        perPage: spvTablet === 'auto' ? undefined : spvTablet,
                    },
                    1024: {
                        autoWidth: spvDesktop === 'auto',
                        perPage: spvDesktop === 'auto' ? undefined : spvDesktop,
                    }
                }
            };

            if (spvMobile !== 'auto') {
                splideOptions.breakpoints[480] = {
                    autoWidth: false,
                    perPage: spvMobile,
                };
            } else {
                splideOptions.breakpoints[480] = {
                    autoWidth: true,
                };
            }

            try {
                const splideInstance = new Splide(container, splideOptions);
                splideInstance.mount(window.splide.Extensions);
                container.setAttribute('data-initialized', 'true');
                container.splide = splideInstance;

                // Track image loads to trigger autoWidth recalculations (prevent gap/misalignment bugs)
                const images = container.querySelectorAll('img');
                let loadedCount = 0;
                if (images.length > 0) {
                    images.forEach(function(img) {
                        if (img.complete) {
                            loadedCount++;
                        } else {
                            img.addEventListener('load', function() {
                                loadedCount++;
                                if (loadedCount === images.length) {
                                    splideInstance.refresh();
                                }
                            });
                            img.addEventListener('error', function() {
                                loadedCount++;
                                if (loadedCount === images.length) {
                                    splideInstance.refresh();
                                }
                            });
                        }
                    });
                    if (loadedCount === images.length) {
                        splideInstance.refresh();
                    }
                }
            } catch (err) {
                console.error('Failed to initialize Splide Marquee:', err);
            }
        });
    }

    // Initialize on window load (after images and resources are loaded)
    if (document.readyState === 'complete') {
        initAlignMarquees();
    } else {
        window.addEventListener('load', initAlignMarquees);
    }

    // Support standard dynamic rendering / AJAX
    if (window.jQuery) {
        jQuery(document).on('post-load', function() {
            initAlignMarquees();
        });
    }
})();
