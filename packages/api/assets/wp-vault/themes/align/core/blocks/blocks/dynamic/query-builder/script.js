(function() {
    function initQueryBuilder() {
        const sliders = document.querySelectorAll('.align-splide-slider:not([data-initialized="true"]), .align-swiper-slider:not(.hero-swiper):not(.models-swiper):not([data-initialized="true"])');
        
        if (sliders.length > 0) {
            if (typeof Splide === 'undefined') {
                var checkSplide = setInterval(function() {
                    if (typeof Splide !== 'undefined') {
                        clearInterval(checkSplide);
                        initQueryBuilder();
                    }
                }, 50);
                return;
            }
            
            sliders.forEach(function(slider) {
                const parentContainer = slider.closest('.align-qb-container');
                let config = {};
                const sliderId = slider.getAttribute('data-slider-id');
                if (sliderId && window.alignSliderConfigs && window.alignSliderConfigs[sliderId]) {
                    config = window.alignSliderConfigs[sliderId];
                } else {
                    const configStr = slider.getAttribute('data-splide-config') || slider.getAttribute('data-swiper-config');
                    if (configStr) {
                        try {
                            config = JSON.parse(configStr);
                        } catch (e) {
                            console.error('Invalid Splide config', e);
                        }
                    }
                }

                const spvDesktop = config.slidesPerView === 'auto' ? 'auto' : (parseFloat(config.slidesPerView) || 1);
                const spvTablet = config.slidesPerViewTablet === 'auto' ? 'auto' : (parseFloat(config.slidesPerViewTablet) || 1);
                const spvMobile = config.slidesPerViewMobile === 'auto' ? 'auto' : (parseFloat(config.slidesPerViewMobile) || 1);

                const spaceBetween = config.spaceBetween !== undefined ? parseInt(config.spaceBetween, 10) : 20;
                const transitionEffect = config.transitionEffect || 'fade';
                const autoplayDelay = config.autoplayDelay ? parseInt(config.autoplayDelay, 10) : 0;

                const isLoop = config.loop !== false && config.loop !== 'false' && config.loop !== 0 && config.loop !== '0';
                
                let type = isLoop ? 'loop' : 'slide';
                if (transitionEffect === 'fade') {
                    type = 'fade';
                }

                const rawSlideCount = slider.querySelectorAll('.splide__list > li:not(.splide__slide--clone)').length;
                const maxSpv = Math.max(
                    typeof spvDesktop === 'number' ? spvDesktop : 1,
                    typeof spvTablet === 'number' ? spvTablet : 1,
                    typeof spvMobile === 'number' ? spvMobile : 1
                );

                const splideOptions = {
                    type: type,
                    rewind: type === 'fade' && isLoop,
                    speed: 700,
                    keyboard: 'global',
                    arrows: (config.navigation !== false) && !config.hookNavigator,
                    pagination: !!config.showDots,
                    mediaQuery: 'min',
                    focus: 0,
                    trimSpace: false,
                    perPage: spvMobile === 'auto' ? 1 : spvMobile,
                    autoWidth: spvMobile === 'auto',
                    gap: (spvMobile !== 'auto' && (spvMobile > 1 || spaceBetween > 0)) ? spaceBetween + 'px' : '0px',
                    breakpoints: {
                        768: {
                            perPage: spvTablet === 'auto' ? 1 : spvTablet,
                            autoWidth: spvTablet === 'auto',
                            gap: (spvTablet !== 'auto' && (spvTablet > 1 || spaceBetween > 0)) ? spaceBetween + 'px' : '0px',
                        },
                        1024: {
                            perPage: spvDesktop === 'auto' ? 1 : spvDesktop,
                            autoWidth: spvDesktop === 'auto',
                            gap: (spvDesktop !== 'auto' && (spvDesktop > 1 || spaceBetween > 0)) ? spaceBetween + 'px' : '0px',
                        }
                    }
                };

                if (isLoop && type === 'loop' && rawSlideCount > 0) {
                    splideOptions.clones = Math.max(rawSlideCount, Math.ceil(maxSpv) * 2, 4);
                }

                if (autoplayDelay > 0) {
                    splideOptions.autoplay = true;
                    splideOptions.interval = autoplayDelay;
                    splideOptions.pauseOnHover = true;
                }

                function updateCustomNavigator(splideInstance) {
                    if (!config.hookNavigator) return;
                    
                    let prevBtn = null;
                    let nextBtn = null;
                    const queryTarget = parentContainer || document;
                    
                    if (config.prevSelector && config.prevSelector.trim() !== '') {
                        try {
                            prevBtn = queryTarget.querySelector(config.prevSelector);
                        } catch (e) {
                            console.error('Error selecting prevBtn', e);
                        }
                    }
                    if (config.nextSelector && config.nextSelector.trim() !== '') {
                        try {
                            nextBtn = queryTarget.querySelector(config.nextSelector);
                        } catch (e) {
                            console.error('Error selecting nextBtn', e);
                        }
                    }
                    
                    const isBeginning = splideInstance.index === 0;
                    const isEnd = splideInstance.index >= splideInstance.length - (splideInstance.options.perPage || 1);
                    const isLoopMode = splideInstance.options.type === 'loop';

                    if (prevBtn) {
                        if (isBeginning && !isLoopMode) {
                            prevBtn.classList.add('opacity-0', 'invisible', 'pointer-events-none');
                            prevBtn.classList.remove('opacity-100');
                        } else {
                            prevBtn.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
                            prevBtn.classList.add('opacity-100');
                        }
                    }
                    if (nextBtn) {
                        if (isEnd && !isLoopMode) {
                            nextBtn.classList.add('opacity-0', 'invisible', 'pointer-events-none');
                            nextBtn.classList.remove('opacity-100');
                        } else {
                            nextBtn.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
                            nextBtn.classList.add('opacity-100');
                        }
                    }
                }

                function syncSwiperClasses(splideInstance) {
                    const activeIndex = splideInstance.index;
                    const totalSlides = splideInstance.length;
                    splideInstance.Components.Slides.get().forEach(function(slideObj) {
                        const slide = slideObj.slide;
                        const index = slideObj.index;
                        
                        const isActive = index === activeIndex;
                        const isNext = index === (activeIndex + 1) % totalSlides;
                        
                        if (isActive) {
                            slide.classList.add('swiper-slide-active');
                        } else {
                            slide.classList.remove('swiper-slide-active');
                        }
                        
                        if (isNext) {
                            slide.classList.add('swiper-slide-next');
                        } else {
                            slide.classList.remove('swiper-slide-next');
                        }
                    });
                }

                function playActiveVideo(splideInstance) {
                    if (window.alignPlayActiveSlideVideo) {
                        syncSwiperClasses(splideInstance);
                        const bp = window.ALIGN_BREAKPOINTS || { mobile: 589, tablet: 991 };
                        const swiperMock = {
                            $videoSourcesExtracted: splideInstance.$videoSourcesExtracted || false,
                            slides: splideInstance.Components.Slides.get().map(function(s) { return s.slide; }),
                            videoLazyload: config.videoLazyload !== undefined ? config.videoLazyload : true,
                            videoBreakpointMobile: bp.mobile,
                            videoBreakpointTablet: bp.tablet
                        };
                        window.alignPlayActiveSlideVideo(swiperMock);
                        splideInstance.$videoSourcesExtracted = swiperMock.$videoSourcesExtracted;
                    }
                }

                const splideInstance = new Splide(slider, splideOptions);

                if (config.showDots && config.useCustomDotLabels && config.dotLabels) {
                    const dotLabelsArr = config.dotLabels.split('||');
                    splideInstance.on('pagination:mounted', function(data) {
                        data.items.forEach(function(item) {
                            const label = (dotLabelsArr[item.page] && dotLabelsArr[item.page].trim() !== '') ? dotLabelsArr[item.page] : '';
                            if (label) {
                                item.button.textContent = label;
                                item.button.classList.add('align-qb-dot-text');
                            }
                        });
                    });
                }

                splideInstance.on('mounted', function() {
                    updateCustomNavigator(splideInstance);
                    playActiveVideo(splideInstance);
                });

                splideInstance.on('move', function() {
                    updateCustomNavigator(splideInstance);
                });

                splideInstance.on('moved', function() {
                    playActiveVideo(splideInstance);
                });

                splideInstance.mount();
                slider.splide = splideInstance;

                if (config.hookNavigator) {
                    const queryTarget = parentContainer || document;
                    if (config.prevSelector && config.prevSelector.trim() !== '') {
                        try {
                            const prevBtn = queryTarget.querySelector(config.prevSelector);
                            if (prevBtn) {
                                prevBtn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    splideInstance.go('<');
                                });
                            }
                        } catch (e) {
                            console.error('Invalid prevSelector: ' + config.prevSelector, e);
                        }
                    }
                    if (config.nextSelector && config.nextSelector.trim() !== '') {
                        try {
                            const nextBtn = queryTarget.querySelector(config.nextSelector);
                            if (nextBtn) {
                                nextBtn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    splideInstance.go('>');
                                });
                            }
                        } catch (e) {
                            console.error('Invalid nextSelector: ' + config.nextSelector, e);
                        }
                    }
                }

                slider.setAttribute('data-initialized', 'true');

                if (config.hookNavigator) {
                    slider.dispatchEvent(new CustomEvent('alignSplideInit', { detail: { splide: splideInstance } }));
                }
            });
        }

        // Grid Pagination & Search Handling
        const containers = document.querySelectorAll('.align-qb-container:not([data-initialized="true"])');
        
        if (containers.length > 0) {
            containers.forEach(function(container) {
                const grid = container.querySelector('.align-qb-wrap--grid');
                if (!grid) return;
                
                const searchInput = container.querySelector('.align-qb-search-input');
                const searchWrap = container.querySelector('.align-qb-search-wrap');
                
                const attributesStr = container.getAttribute('data-attributes');
                if (!attributesStr) return;
                
                const ajaxUrl = container.getAttribute('data-ajax-url') || (typeof alignQueryBuilderObj !== 'undefined' ? alignQueryBuilderObj.ajax_url : '/wp-admin/admin-ajax.php');
                
                let pagination = container.querySelector('.align-qb-pagination');
                let maxPages = pagination ? parseInt(pagination.getAttribute('data-max-pages'), 10) : 1;
                let currentPage = pagination ? parseInt(pagination.getAttribute('data-current-page'), 10) : 1;
                let currentSearchTerm = '';
                let isLoading = false;

                function debounce(func, wait) {
                    let timeout;
                    return function() {
                        const context = this;
                        const args = arguments;
                        clearTimeout(timeout);
                        timeout = setTimeout(function() {
                            func.apply(context, args);
                        }, wait);
                    };
                }

                function triggerLazyLoad() {
                    if (window.lazyLoadInstance && typeof window.lazyLoadInstance.update === 'function') {
                        window.lazyLoadInstance.update();
                    } else if (typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance && typeof lazyLoadInstance.update === 'function') {
                        lazyLoadInstance.update();
                    }
                }

                function getPageFromLink(link) {
                    if (link.classList.contains('prev')) {
                        return Math.max(1, currentPage - 1);
                    }
                    if (link.classList.contains('next')) {
                        return Math.min(maxPages, currentPage + 1);
                    }
                    const href = link.getAttribute('href');
                    if (href) {
                        try {
                            const urlObj = new URL(href, window.location.href);
                            if (urlObj.searchParams.has('paged')) {
                                return parseInt(urlObj.searchParams.get('paged'), 10);
                            }
                            if (urlObj.searchParams.has('page')) {
                                return parseInt(urlObj.searchParams.get('page'), 10);
                            }
                            const match = urlObj.pathname.match(/\/page\/(\d+)/);
                            if (match) {
                                return parseInt(match[1], 10);
                            }
                        } catch(e) {}
                    }
                    const textVal = parseInt(link.textContent.trim(), 10);
                    if (!isNaN(textVal)) {
                        return textVal;
                    }
                    return 1;
                }

                function updatePaginationUI(newPaginationHtml, newMaxPages, newPaged) {
                    maxPages = newMaxPages;
                    currentPage = newPaged;

                    const existingPagination = container.querySelector('.align-qb-pagination');
                    if (newPaginationHtml && newPaginationHtml.trim() !== '') {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = newPaginationHtml.trim();
                        const newPaginationElem = tempDiv.firstElementChild;
                        
                        if (existingPagination) {
                            existingPagination.replaceWith(newPaginationElem);
                        } else {
                            container.appendChild(newPaginationElem);
                        }
                        pagination = newPaginationElem;
                    } else {
                        if (existingPagination) {
                            existingPagination.remove();
                        }
                        pagination = null;
                    }
                    bindPaginationEvents();
                }

                function bindPaginationEvents() {
                    if (!pagination) return;

                    const loadMoreBtn = pagination.querySelector('.qb-load-more-btn');
                    if (loadMoreBtn) {
                        loadMoreBtn.onclick = function(e) {
                            e.preventDefault();
                            loadNextPage();
                        };
                    }

                    const pageLinks = pagination.querySelectorAll('a.page-numbers');
                    pageLinks.forEach(function(link) {
                        link.onclick = function(e) {
                            e.preventDefault();
                            const targetPage = getPageFromLink(link);
                            if (targetPage && targetPage !== currentPage) {
                                goToPage(targetPage);
                            }
                        };
                    });

                    const infiniteTrigger = pagination.querySelector('.qb-infinite-trigger');
                    if (infiniteTrigger && typeof IntersectionObserver !== 'undefined') {
                        if (container._infiniteObserver) {
                            container._infiniteObserver.disconnect();
                        }
                        const observer = new IntersectionObserver(function(entries) {
                            if (entries[0].isIntersecting) {
                                loadNextPage();
                            }
                        }, { rootMargin: '200px' });
                        observer.observe(infiniteTrigger);
                        container._infiniteObserver = observer;
                    }
                }

                function goToPage(targetPage) {
                    if (isLoading) return;
                    isLoading = true;
                    grid.style.opacity = '0.5';

                    const formData = new FormData();
                    formData.append('action', 'align_query_builder_load_more');
                    formData.append('nonce', (typeof alignQueryBuilderObj !== 'undefined' && alignQueryBuilderObj.nonce) ? alignQueryBuilderObj.nonce : '');
                    formData.append('attributes', attributesStr);
                    formData.append('paged', targetPage);
                    if (currentSearchTerm) {
                        formData.append('s', currentSearchTerm);
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success && response.data) {
                            const htmlContent = (typeof response.data === 'object' && response.data.html !== undefined) ? response.data.html : response.data;
                            const paginationHtml = (typeof response.data === 'object' && response.data.paginationHtml !== undefined) ? response.data.paginationHtml : '';
                            const newMaxPages = (typeof response.data === 'object' && response.data.maxPages !== undefined) ? parseInt(response.data.maxPages, 10) : 1;

                            grid.innerHTML = htmlContent;
                            triggerLazyLoad();
                            updatePaginationUI(paginationHtml, newMaxPages, targetPage);
                            
                            const rect = container.getBoundingClientRect();
                            if (rect.top < 0) {
                                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Error navigating page:', err);
                    })
                    .finally(() => {
                        isLoading = false;
                        grid.style.opacity = '1';
                    });
                }
                
                function loadNextPage() {
                    if (isLoading || currentPage >= maxPages) return;
                    isLoading = true;
                    
                    const loadMoreBtn = pagination ? pagination.querySelector('.qb-load-more-btn') : null;
                    if (loadMoreBtn) {
                        loadMoreBtn.textContent = 'Loading...';
                        loadMoreBtn.disabled = true;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'align_query_builder_load_more');
                    formData.append('nonce', (typeof alignQueryBuilderObj !== 'undefined' && alignQueryBuilderObj.nonce) ? alignQueryBuilderObj.nonce : '');
                    formData.append('attributes', attributesStr);
                    formData.append('paged', currentPage + 1);
                    if (currentSearchTerm) {
                        formData.append('s', currentSearchTerm);
                    }
                    
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success && response.data) {
                            const htmlContent = (typeof response.data === 'object' && response.data.html !== undefined) ? response.data.html : response.data;
                            const paginationHtml = (typeof response.data === 'object' && response.data.paginationHtml !== undefined) ? response.data.paginationHtml : '';
                            const newMaxPages = (typeof response.data === 'object' && response.data.maxPages !== undefined) ? parseInt(response.data.maxPages, 10) : maxPages;

                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = htmlContent;
                            while (tempDiv.firstChild) {
                                grid.appendChild(tempDiv.firstChild);
                            }
                            triggerLazyLoad();
                            currentPage++;
                            
                            updatePaginationUI(paginationHtml, newMaxPages, currentPage);
                        }
                    })
                    .catch(err => {
                        console.error('Error loading next page:', err);
                    })
                    .finally(() => {
                        isLoading = false;
                        if (loadMoreBtn) {
                            loadMoreBtn.textContent = 'Load More';
                            loadMoreBtn.disabled = false;
                        }
                    });
                }

                function performSearch() {
                    if (isLoading) return;
                    isLoading = true;
                    grid.style.opacity = '0.5';

                    const formData = new FormData();
                    formData.append('action', 'align_query_builder_load_more');
                    formData.append('nonce', (typeof alignQueryBuilderObj !== 'undefined' && alignQueryBuilderObj.nonce) ? alignQueryBuilderObj.nonce : '');
                    formData.append('attributes', attributesStr);
                    formData.append('paged', 1);
                    if (currentSearchTerm) {
                        formData.append('s', currentSearchTerm);
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(response => {
                        if (response.success && response.data) {
                            const htmlContent = (typeof response.data === 'object' && response.data.html !== undefined) ? response.data.html : response.data;
                            const paginationHtml = (typeof response.data === 'object' && response.data.paginationHtml !== undefined) ? response.data.paginationHtml : '';
                            const newMaxPages = (typeof response.data === 'object' && response.data.maxPages !== undefined) ? parseInt(response.data.maxPages, 10) : 1;

                            grid.innerHTML = htmlContent;
                            triggerLazyLoad();
                            updatePaginationUI(paginationHtml, newMaxPages, 1);
                        }
                    })
                    .catch(err => {
                        console.error('Error performing search:', err);
                    })
                    .finally(() => {
                        isLoading = false;
                        grid.style.opacity = '1';
                    });
                }

                if (searchInput) {
                    const debounceDelay = searchWrap ? (parseInt(searchWrap.getAttribute('data-debounce'), 10) || 400) : 400;
                    const debouncedSearch = debounce(function(e) {
                        const term = e.target.value.trim();
                        if (term !== currentSearchTerm) {
                            currentSearchTerm = term;
                            performSearch();
                        }
                    }, debounceDelay);

                    searchInput.addEventListener('input', debouncedSearch);
                }

                // Initial bind for pagination elements present on page load
                bindPaginationEvents();

                // Mark container as initialized
                container.setAttribute('data-initialized', 'true');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQueryBuilder);
    } else {
        initQueryBuilder();
    }
})();
