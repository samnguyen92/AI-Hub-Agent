(function(wp) {
    if (!wp || !wp.element) return;

    const { getBlockElement, getActiveTab } = window.AlignElementUtils || {};

    function useSplideEffects(clientId) {
        wp.element.useEffect(function() {
            const blockEl = getBlockElement(clientId);
            if (!blockEl) return;
            
            const handleBtnClick = function(e) {
                const nextBtn = e.target.closest('.models-next');
                const prevBtn = e.target.closest('.models-prev');
                
                if (nextBtn || prevBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const splideWrapper = blockEl.querySelector('.splide__list');
                    if (splideWrapper) {
                        const scrollAmount = 350;
                        const currentScroll = splideWrapper.scrollLeft;
                        const targetScroll = nextBtn 
                            ? currentScroll + scrollAmount 
                            : currentScroll - scrollAmount;
                        
                        splideWrapper.scrollTo({
                            left: targetScroll,
                            behavior: 'smooth'
                        });
                    }
                }
            };
            
            blockEl.addEventListener('click', handleBtnClick);
            return function() {
                blockEl.removeEventListener('click', handleBtnClick);
            };
        }, [clientId]);
    }

    function useTabListeners(clientId) {
        wp.element.useEffect(function() {
            const blockEl = getBlockElement(clientId);
            if (!blockEl) return;
            const doc = blockEl.ownerDocument;
            if (!doc) return;

            // We register the listener below using a ref count check

            const handleTabClick = function(e) {
                // Check if clicking reset filters
                let isReset = e.target.closest('[id*="-reset-filters"]');
                if (!isReset && e.target.textContent && e.target.textContent.trim() === 'RESET FILTERS') {
                    isReset = e.target;
                }
                if (isReset) {
                    const allBtn = doc.querySelector('[data-filter="all"]');
                    if (allBtn) {
                        handleTabClick({ target: allBtn, preventDefault: () => {} });
                        return;
                    }
                }

                const tabHeader = e.target.closest('[data-tab], [data-tab-target]');
                const filterHeader = e.target.closest('[data-filter]');
                const letterHeader = e.target.closest('[data-letter]');
                
                if (tabHeader) {
                    const tabId = tabHeader.getAttribute('data-tab') || tabHeader.getAttribute('data-tab-target');
                    if (!tabId) return;
                    
                    let tabList = tabHeader.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]');
                    if (!tabList) {
                        tabList = tabHeader.closest('[role="tablist"]');
                    }
                    if (!tabList) {
                        let parent = tabHeader.parentElement;
                        while (parent && parent !== doc.body) {
                            if (parent.querySelectorAll('[data-tab], [data-tab-target]').length > 1) {
                                tabList = parent;
                                break;
                            }
                            parent = parent.parentElement;
                        }
                    }
                    const tabListId = tabList ? (tabList.id || tabList.getAttribute('data-block')) : 'default';
                    
                    window.__activeAlignTabs = window.__activeAlignTabs || {};
                    window.__activeAlignTabs[tabListId] = tabId;
                    
                    if (tabList) {
                        const tabs = tabList.querySelectorAll('[data-tab], [data-tab-target]');
                        tabs.forEach(function(tab) {
                            const currentTabId = tab.getAttribute('data-tab') || tab.getAttribute('data-tab-target');
                            if (currentTabId === tabId) {
                                tab.setAttribute('aria-selected', 'true');
                                tab.classList.add('text-[#3B4AFF]', 'border-[#3B4AFF]');
                                tab.classList.remove('text-[#0F142073]', 'border-transparent');
                            } else {
                                tab.setAttribute('aria-selected', 'false');
                                tab.classList.remove('text-[#3B4AFF]', 'border-[#3B4AFF]');
                                tab.classList.add('text-[#0F142073]', 'border-transparent');
                            }
                        });
                    }
                    
                    const panels = doc.querySelectorAll('[id^="align-panel-"], [data-tab-panel]');
                    panels.forEach(function(panel) {
                        const panelId = panel.getAttribute('id') || '';
                        if (panelId === 'align-panel-' + tabId || panelId === 'panel-' + tabId) {
                            panel.classList.remove('hidden', 'opacity-0');
                            panel.classList.add('flex', 'opacity-100', 'active-panel');
                        } else if (panelId.includes('align-panel-') || panelId.includes('panel-')) {
                            panel.classList.add('hidden', 'opacity-0');
                            panel.classList.remove('flex', 'opacity-100', 'active-panel');
                        }
                    });

                    const categories = doc.querySelectorAll('[data-category]');
                    categories.forEach(function(cat) {
                        const catVal = cat.getAttribute('data-category') || '';
                        const catList = catVal.split(',').map(function(c) { return c.trim().toLowerCase(); });
                        const cleanTabId = tabId.toLowerCase().trim();
                        if (cleanTabId === 'all' || catList.includes(cleanTabId)) {
                            cat.classList.remove('hidden', 'opacity-0');
                            cat.classList.add('opacity-100');
                            cat.style.display = '';
                        } else {
                            cat.classList.add('hidden', 'opacity-0');
                            cat.classList.remove('opacity-100');
                            cat.style.display = 'none';
                        }
                    });

                    // Force editor selection update to trigger re-render on siblings
                    const blockEditor = wp.data.dispatch('core/block-editor');
                    const tabBlockId = tabHeader.getAttribute('data-block');
                    if (blockEditor && tabBlockId) {
                        blockEditor.selectBlock(tabBlockId);
                    }
                } else if (filterHeader) {
                    const filterVal = filterHeader.getAttribute('data-filter');
                    if (!filterVal) return;
                    
                    let tabList = filterHeader.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]');
                    if (!tabList) {
                        let parent = filterHeader.parentElement;
                        while (parent && parent !== doc.body) {
                            if (parent.querySelectorAll('[data-filter]').length > 1) {
                                tabList = parent;
                                break;
                            }
                            parent = parent.parentElement;
                        }
                    }
                    const tabListId = tabList ? (tabList.id || tabList.getAttribute('data-block')) : 'default';
                    
                    window.__activeAlignTabs = window.__activeAlignTabs || {};
                    window.__activeAlignTabs[tabListId] = filterVal;
                    
                    if (tabList) {
                        const filters = tabList.querySelectorAll('[data-filter]');
                        filters.forEach(function(btn) {
                            const btnFilterVal = btn.getAttribute('data-filter');
                            if (btnFilterVal === filterVal) {
                                btn.setAttribute('aria-selected', 'true');
                                btn.classList.add('bg-[#3B4AFF]', 'border-[#3B4AFF]', 'text-white');
                                btn.classList.remove('bg-white', 'border-[#E0E3EC]', 'text-[#0F142080]', 'hover:border-[#3B4AFF]', 'hover:bg-[#F4F6FB]');
                            } else {
                                btn.setAttribute('aria-selected', 'false');
                                btn.classList.remove('bg-[#3B4AFF]', 'border-[#3B4AFF]', 'text-white');
                                btn.classList.add('bg-white', 'border-[#E0E3EC]', 'text-[#0F142080]', 'hover:border-[#3B4AFF]', 'hover:bg-[#F4F6FB]');
                            }
                        });
                    }
                    
                    const cleanFilter = (f) => {
                        if (!f) return '';
                        const clean = f.toLowerCase().trim();
                        if (clean === 'podcasts' || clean === 'podcast') return 'podcast';
                        return clean;
                    };
                    const cleanCategory = (c) => {
                        if (!c) return '';
                        const clean = c.toLowerCase().trim();
                        if (clean === 'podcasts' || clean === 'podcast') return 'podcast';
                        return clean;
                    };

                    const categories = doc.querySelectorAll('[data-category]');
                    categories.forEach(function(cat) {
                        const catVal = cat.getAttribute('data-category');
                        if (cleanFilter(filterVal) === cleanCategory(catVal)) {
                            cat.classList.remove('hidden', 'opacity-0');
                            cat.classList.add('opacity-100');
                            cat.style.display = '';
                        } else {
                            cat.classList.add('hidden', 'opacity-0');
                            cat.classList.remove('opacity-100');
                            cat.style.display = 'none';
                        }
                    });

                    // Force editor selection update to trigger re-render on siblings
                    const blockEditor = wp.data.dispatch('core/block-editor');
                    const tabBlockId = filterHeader.getAttribute('data-block');
                    if (blockEditor && tabBlockId) {
                        blockEditor.selectBlock(tabBlockId);
                    }
                }

                if (letterHeader) {
                    const letterVal = letterHeader.getAttribute('data-letter');
                    if (!letterVal) return;
                    
                    let tabList = letterHeader.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]');
                    if (!tabList) {
                        let parent = letterHeader.parentElement;
                        while (parent && parent !== doc.body) {
                            if (parent.querySelectorAll('[data-letter]').length > 1) {
                                tabList = parent;
                                break;
                            }
                            parent = parent.parentElement;
                        }
                    }
                    const tabListId = tabList ? (tabList.id || tabList.getAttribute('data-block')) : 'default';
                    
                    window.__activeAlignTabs = window.__activeAlignTabs || {};
                    window.__activeAlignTabs[tabListId] = letterVal;
                    
                    if (tabList) {
                        const buttons = tabList.querySelectorAll('[data-letter]');
                        buttons.forEach(function(btn) {
                            if (!btn.classList.contains('glossary-letter-btn')) return;
                            const btnLetter = btn.getAttribute('data-letter');
                            if (btnLetter === letterVal) {
                                btn.classList.add('active', 'bg-[#3B4AFF]', 'text-white');
                                btn.classList.remove('bg-white', 'border-[#E0E3EC]', 'text-[#0F1420B2]');
                            } else {
                                btn.classList.remove('active', 'bg-[#3B4AFF]', 'text-white');
                                btn.classList.add('bg-white', 'border-[#E0E3EC]', 'text-[#0F1420B2]');
                            }
                        });
                    }
                    
                    const rows = doc.querySelectorAll('.glossary-term-row');
                    rows.forEach(function(row) {
                        const rowLetter = row.getAttribute('data-letter') || '';
                        if (letterVal === 'All' || rowLetter.toUpperCase() === letterVal.toUpperCase()) {
                            row.classList.remove('hidden', 'opacity-0');
                            row.classList.add('opacity-100');
                            row.style.display = 'flex';
                        } else {
                            row.classList.add('hidden', 'opacity-0');
                            row.classList.remove('opacity-100');
                            row.style.display = 'none';
                        }
                    });

                    // Force editor selection update to trigger re-render on siblings
                    const blockEditor = wp.data.dispatch('core/block-editor');
                    const tabBlockId = letterHeader.getAttribute('data-block');
                    if (blockEditor && tabBlockId) {
                        blockEditor.selectBlock(tabBlockId);
                    }
                }
            };
            
            doc.__alignTabListenerRefCount = (doc.__alignTabListenerRefCount || 0) + 1;
            if (doc.__alignTabListenerRefCount === 1) {
                doc.addEventListener('click', handleTabClick, true);
                doc.__alignTabClickCallback = handleTabClick;
            }
            
            return function() {
                doc.__alignTabListenerRefCount = (doc.__alignTabListenerRefCount || 1) - 1;
                if (doc.__alignTabListenerRefCount === 0) {
                    if (doc.__alignTabClickCallback) {
                        doc.removeEventListener('click', doc.__alignTabClickCallback, true);
                        delete doc.__alignTabClickCallback;
                    }
                }
            };
        }, [clientId]);
    }

    function resolveTabAndFilterClasses(clientId, attributes, finalProps) {
        const { customAttributes, elementId, className } = attributes;

        // --- GLOSSARY LETTER SYSTEM EDITOR PERSISTENCE ---
        let letterTarget = null;
        if (customAttributes && typeof customAttributes === 'string') {
            const match = customAttributes.match(/data-letter="([^"]+)"/);
            if (match) {
                letterTarget = match[1];
            }
        }
        const isLetterButton = className && className.includes('glossary-letter-btn');
        const isGlossaryRow = className && className.includes('glossary-term-row');

        if (isLetterButton && letterTarget) {
            const blockEl = getBlockElement(clientId);
            let tabListEl = blockEl ? blockEl.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]') : null;
            const tabListId = tabListEl ? (tabListEl.id || tabListEl.getAttribute('data-block')) : 'default';
            const activeLetter = (window.__activeAlignTabs && window.__activeAlignTabs[tabListId]) || 'All';
            
            const isActive = letterTarget === activeLetter;
            
            let classes = finalProps.className || '';
            if (isActive) {
                classes = classes.replace('bg-white', '').replace('border-[#E0E3EC]', '').replace('text-[#0F1420B2]', '');
                if (!classes.includes('active')) classes += ' active';
                if (!classes.includes('bg-[#3B4AFF]')) classes += ' bg-[#3B4AFF]';
                if (!classes.includes('text-white')) classes += ' text-white';
            } else {
                classes = classes.replace('active', '').replace('bg-[#3B4AFF]', '').replace('text-white', '');
                if (!classes.includes('bg-white')) classes += ' bg-white';
                if (!classes.includes('border-[#E0E3EC]')) classes += ' border-[#E0E3EC]';
                if (!classes.includes('text-[#0F1420B2]')) classes += ' text-[#0F1420B2]';
            }
            finalProps.className = classes.replace(/\s+/g, ' ').trim();

            finalProps.onClick = function(e) {
                window.__activeAlignTabs = window.__activeAlignTabs || {};
                window.__activeAlignTabs[tabListId] = letterTarget;
                
                if (tabListEl) {
                    const buttons = tabListEl.querySelectorAll('[data-letter]');
                    buttons.forEach(function(btn) {
                        if (!btn.classList.contains('glossary-letter-btn')) return;
                        const btnLetter = btn.getAttribute('data-letter');
                        if (btnLetter === letterTarget) {
                            btn.classList.add('active', 'bg-[#3B4AFF]', 'text-white');
                            btn.classList.remove('bg-white', 'border-[#E0E3EC]', 'text-[#0F1420B2]');
                        } else {
                            btn.classList.remove('active', 'bg-[#3B4AFF]', 'text-white');
                            btn.classList.add('bg-white', 'border-[#E0E3EC]', 'text-[#0F1420B2]');
                        }
                    });
                }
                
                const doc = blockEl ? blockEl.ownerDocument : document;
                const rows = doc.querySelectorAll('.glossary-term-row');
                rows.forEach(function(row) {
                    const rowLetter = row.getAttribute('data-letter') || '';
                    if (letterTarget === 'All' || rowLetter.toUpperCase() === letterTarget.toUpperCase()) {
                        row.classList.remove('hidden', 'opacity-0');
                        row.classList.add('opacity-100');
                        row.style.display = 'flex';
                    } else {
                        row.classList.add('hidden', 'opacity-0');
                        row.classList.remove('opacity-100');
                        row.style.display = 'none';
                    }
                });

                const blockEditor = wp.data.dispatch('core/block-editor');
                if (blockEditor) {
                    blockEditor.selectBlock(clientId);
                }
            };
        }
        
        if (isGlossaryRow && letterTarget) {
            const blockEl = getBlockElement(clientId);
            let tabListEl = blockEl ? blockEl.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]') : null;
            const tabListId = tabListEl ? (tabListEl.id || tabListEl.getAttribute('data-block')) : 'default';
            const activeLetter = (window.__activeAlignTabs && window.__activeAlignTabs[tabListId]) || 'All';
            
            const isActive = activeLetter === 'All' || letterTarget.toUpperCase() === activeLetter.toUpperCase();
            
            let classes = finalProps.className || '';
            if (isActive) {
                classes = classes.replace('hidden', '').replace('opacity-0', '');
                if (!classes.includes('flex')) classes += ' flex';
                if (!classes.includes('opacity-100')) classes += ' opacity-100';
                finalProps.style = { ...finalProps.style, display: 'flex' };
            } else {
                classes = classes.replace('flex', '').replace('opacity-100', '');
                if (!classes.includes('hidden')) classes += ' hidden';
                if (!classes.includes('opacity-0')) classes += ' opacity-0';
                finalProps.style = { ...finalProps.style, display: 'none' };
            }
            finalProps.className = classes.replace(/\s+/g, ' ').trim();
        }

        // --- TAB SYSTEM EDITOR PERSISTENCE ---
        let tabTarget = null;
        if (customAttributes && typeof customAttributes === 'string') {
            const match = customAttributes.match(/data-tab(?:-target)?="([^"]+)"/);
            if (match) {
                tabTarget = match[1];
            }
        }
        if (tabTarget) {
            const blockEl = getBlockElement(clientId);
            let tabListEl = blockEl ? blockEl.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]') : null;
            if (!tabListEl && blockEl) {
                tabListEl = blockEl.closest('[role="tablist"]');
            }
            if (!tabListEl && blockEl) {
                let parent = blockEl.parentElement;
                const docEl = blockEl.ownerDocument || document;
                while (parent && parent !== docEl.body) {
                    if (parent.querySelectorAll('[data-tab], [data-tab-target]').length > 1) {
                        tabListEl = parent;
                        break;
                    }
                    parent = parent.parentElement;
                }
            }
            const tabListId = tabListEl ? (tabListEl.id || tabListEl.getAttribute('data-block')) : 'default';
            const activeTab = getActiveTab(tabListId, tabListEl);
            
            const isActive = activeTab ? (tabTarget === activeTab) : (tabTarget === 'tab-1' || tabTarget === 'operations');
            finalProps['aria-selected'] = isActive ? 'true' : 'false';
            
            let classes = finalProps.className || '';
            if (isActive) {
                classes = classes.replace('text-[#0F142073]', '').replace('border-transparent', '');
                if (!classes.includes('text-[#3B4AFF]')) classes += ' text-[#3B4AFF]';
                if (!classes.includes('border-[#3B4AFF]')) classes += ' border-[#3B4AFF]';
            } else {
                classes = classes.replace('text-[#3B4AFF]', '').replace('border-[#3B4AFF]', '');
                if (!classes.includes('text-[#0F142073]')) classes += ' text-[#0F142073]';
                if (!classes.includes('border-transparent')) classes += ' border-transparent';
            }
            finalProps.className = classes.replace(/\s+/g, ' ').trim();

            // Direct onClick handler for direct React event interception
            finalProps.onClick = function(e) {
                window.__activeAlignTabs = window.__activeAlignTabs || {};
                window.__activeAlignTabs[tabListId] = tabTarget;
                
                if (tabListEl) {
                    const tabs = tabListEl.querySelectorAll('[data-tab], [data-tab-target]');
                    tabs.forEach(function(tab) {
                        const currentTabId = tab.getAttribute('data-tab') || tab.getAttribute('data-tab-target');
                        if (currentTabId === tabTarget) {
                            tab.setAttribute('aria-selected', 'true');
                            tab.classList.add('text-[#3B4AFF]', 'border-[#3B4AFF]');
                            tab.classList.remove('text-[#0F142073]', 'border-transparent');
                        } else {
                            tab.setAttribute('aria-selected', 'false');
                            tab.classList.remove('text-[#3B4AFF]', 'border-[#3B4AFF]');
                            tab.classList.add('text-[#0F142073]', 'border-transparent');
                        }
                    });
                }
                
                const doc = blockEl ? blockEl.ownerDocument : document;
                const panels = doc.querySelectorAll('[id^="align-panel-"], [data-tab-panel]');
                panels.forEach(function(panel) {
                    const panelId = panel.getAttribute('id') || '';
                    if (panelId === 'align-panel-' + tabTarget || panelId === 'panel-' + tabTarget) {
                        panel.classList.remove('hidden', 'opacity-0');
                        panel.classList.add('flex', 'opacity-100', 'active-panel');
                    } else if (panelId.includes('align-panel-') || panelId.includes('panel-')) {
                        panel.classList.add('hidden', 'opacity-0');
                        panel.classList.remove('flex', 'opacity-100', 'active-panel');
                    }
                });

                const categories = doc.querySelectorAll('[data-category]');
                categories.forEach(function(cat) {
                    const catVal = cat.getAttribute('data-category') || '';
                    const catList = catVal.split(',').map(function(c) { return c.trim().toLowerCase(); });
                    const cleanTabTarget = tabTarget.toLowerCase().trim();
                    if (cleanTabTarget === 'all' || catList.includes(cleanTabTarget)) {
                        cat.classList.remove('hidden', 'opacity-0');
                        cat.classList.add('opacity-100');
                        cat.style.display = '';
                    } else {
                        cat.classList.add('hidden', 'opacity-0');
                        cat.classList.remove('opacity-100');
                        cat.style.display = 'none';
                    }
                });

                // Force editor selection update to trigger re-render on siblings
                const blockEditor = wp.data.dispatch('core/block-editor');
                if (blockEditor) {
                    blockEditor.selectBlock(clientId);
                }
            };
        }

        let panelTabId = null;
        if (elementId && (elementId.startsWith('align-panel-') || elementId.startsWith('panel-'))) {
            panelTabId = elementId.replace('align-panel-', '').replace('panel-', '');
        } else if (className && className.includes('tab-panel')) {
            if (customAttributes && typeof customAttributes === 'string') {
                const match = customAttributes.match(/aria-labelledby="tab-([^"]+)"/);
                if (match) {
                    panelTabId = match[1];
                }
            }
        }
        if (panelTabId) {
            const blockEl = getBlockElement(clientId);
            const doc = blockEl ? blockEl.ownerDocument : document;
            let tabListEl = blockEl ? blockEl.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]') : null;
            if (!tabListEl) {
                tabListEl = doc.querySelector('[role="tablist"]');
            }
            const tabListId = tabListEl ? (tabListEl.id || tabListEl.getAttribute('data-block')) : 'default';
            const activeTab = getActiveTab(tabListId, tabListEl);
            
            const isActive = activeTab ? (panelTabId === activeTab) : (panelTabId === 'tab-1' || panelTabId === 'operations');
            
            let classes = finalProps.className || '';
            if (isActive) {
                classes = classes.replace('hidden', '').replace('opacity-0', '');
                if (!classes.includes('flex')) classes += ' flex';
                if (!classes.includes('opacity-100')) classes += ' opacity-100';
                if (!classes.includes('active-panel')) classes += ' active-panel';
            } else {
                classes = classes.replace('flex', '').replace('opacity-100', '').replace('active-panel', '');
                if (!classes.includes('hidden')) classes += ' hidden';
                if (!classes.includes('opacity-0')) classes += ' opacity-0';
            }
            finalProps.className = classes.replace(/\s+/g, ' ').trim();
        }

        // Resources Page Category Filtering support
        let filterValue = null;
        if (customAttributes && typeof customAttributes === 'string') {
            const match = customAttributes.match(/data-filter="([^"]+)"/);
            if (match) {
                filterValue = match[1];
            }
        }
        if (filterValue) {
            const blockEl = getBlockElement(clientId);
            let tabListEl = blockEl ? blockEl.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]') : null;
            const tabListId = tabListEl ? (tabListEl.id || tabListEl.getAttribute('data-block')) : 'default';
            const activeFilter = (window.__activeAlignTabs && window.__activeAlignTabs[tabListId]) || 'all';
            
            const isActive = filterValue === activeFilter;
            finalProps['aria-selected'] = isActive ? 'true' : 'false';
            
            let classes = finalProps.className || '';
            if (isActive) {
                classes = classes.replace('bg-white', '').replace('border-[#E0E3EC]', '').replace('text-[#0F142080]', '').replace('hover:border-[#3B4AFF]', '').replace('hover:bg-[#F4F6FB]', '');
                if (!classes.includes('bg-[#3B4AFF]')) classes += ' bg-[#3B4AFF]';
                if (!classes.includes('border-[#3B4AFF]')) classes += ' border-[#3B4AFF]';
                if (!classes.includes('text-white')) classes += ' text-white';
            } else {
                classes = classes.replace('bg-[#3B4AFF]', '').replace('border-[#3B4AFF]', '').replace('text-white', '');
                if (!classes.includes('bg-white')) classes += ' bg-white';
                if (!classes.includes('border-[#E0E3EC]')) classes += ' border-[#E0E3EC]';
                if (!classes.includes('text-[#0F142080]')) classes += ' text-[#0F142080]';
            }
            finalProps.className = classes.replace(/\s+/g, ' ').trim();

            // Direct onClick handler for direct React event interception
            finalProps.onClick = function(e) {
                window.__activeAlignTabs = window.__activeAlignTabs || {};
                window.__activeAlignTabs[tabListId] = filterValue;
                
                if (tabListEl) {
                    const filters = tabListEl.querySelectorAll('[data-filter]');
                    filters.forEach(function(btn) {
                        const btnFilterVal = btn.getAttribute('data-filter');
                        if (btnFilterVal === filterValue) {
                            btn.setAttribute('aria-selected', 'true');
                            btn.classList.add('bg-[#3B4AFF]', 'border-[#3B4AFF]', 'text-white');
                            btn.classList.remove('bg-white', 'border-[#E0E3EC]', 'text-[#0F142080]', 'hover:border-[#3B4AFF]', 'hover:bg-[#F4F6FB]');
                        } else {
                            btn.setAttribute('aria-selected', 'false');
                            btn.classList.remove('bg-[#3B4AFF]', 'border-[#3B4AFF]', 'text-white');
                            btn.classList.add('bg-white', 'border-[#E0E3EC]', 'text-[#0F142080]', 'hover:border-[#3B4AFF]', 'hover:bg-[#F4F6FB]');
                        }
                    });
                }
                
                const cleanFilter = (f) => {
                    if (!f) return '';
                    const clean = f.toLowerCase().trim();
                    if (clean === 'podcasts' || clean === 'podcast') return 'podcast';
                    return clean;
                };
                const cleanCategory = (c) => {
                    if (!c) return '';
                    const clean = c.toLowerCase().trim();
                    if (clean === 'podcasts' || clean === 'podcast') return 'podcast';
                    return clean;
                };

                const categories = doc.querySelectorAll('[data-category]');
                categories.forEach(function(cat) {
                    const catVal = cat.getAttribute('data-category') || '';
                    const catList = catVal.split(',').map(function(c) { return cleanCategory(c); });
                    const cleanFilterVal = cleanFilter(filterValue);
                    if (cleanFilterVal === 'all' || catList.includes(cleanFilterVal)) {
                        cat.classList.remove('hidden', 'opacity-0');
                        cat.classList.add('opacity-100');
                        cat.style.display = '';
                    } else {
                        cat.classList.add('hidden', 'opacity-0');
                        cat.classList.remove('opacity-100');
                        cat.style.display = 'none';
                    }
                });

                // Force editor selection update to trigger re-render on siblings
                const blockEditor = wp.data.dispatch('core/block-editor');
                if (blockEditor) {
                    blockEditor.selectBlock(clientId);
                }
            };
        }

        let categoryValue = null;
        if (customAttributes && typeof customAttributes === 'string') {
            const match = customAttributes.match(/data-category="([^"]+)"/);
            if (match) {
                categoryValue = match[1];
            }
        }
        if (categoryValue) {
            const blockEl = getBlockElement(clientId);
            let tabListEl = blockEl ? blockEl.closest('section, .wp-block-group, [id^="align-section-"], [id^="section-"], [id^="align-wall-of-love-"], [id^="wall-of-love-"], [id^="align-glossary-"], [id^="glossary-"]') : null;
            const tabListId = tabListEl ? (tabListEl.id || tabListEl.getAttribute('data-block')) : 'default';
            const activeFilter = (window.__activeAlignTabs && window.__activeAlignTabs[tabListId]) || 'all';
            
            const cleanFilter = (f) => {
                if (!f) return '';
                const clean = f.toLowerCase().trim();
                if (clean === 'podcasts' || clean === 'podcast') return 'podcast';
                return clean;
            };
            const cleanCategory = (c) => {
                if (!c) return '';
                const clean = c.toLowerCase().trim();
                if (clean === 'podcasts' || clean === 'podcast') return 'podcast';
                return clean;
            };

            const filterClean = cleanFilter(activeFilter);
            const catCleanList = categoryValue.split(',').map(function(c) { return cleanCategory(c); });
            const isActive = filterClean === 'all' || catCleanList.includes(filterClean);
            
            let classes = finalProps.className || '';
            if (isActive) {
                classes = classes.replace('hidden', '').replace('opacity-0', '');
                if (!classes.includes('flex') && !classes.includes('block') && !classes.includes('grid')) classes += ' block';
                if (!classes.includes('opacity-100')) classes += ' opacity-100';
                finalProps.style = { ...finalProps.style, display: '' };
            } else {
                classes = classes.replace('flex', '').replace('block', '').replace('grid', '').replace('opacity-100', '');
                if (!classes.includes('hidden')) classes += ' hidden';
                if (!classes.includes('opacity-0')) classes += ' opacity-0';
                finalProps.style = { ...finalProps.style, display: 'none' };
            }
            finalProps.className = classes.replace(/\s+/g, ' ').trim();
        }
    }

    window.AlignElementTabEffects = {
        useSplideEffects: useSplideEffects,
        useTabListeners: useTabListeners,
        resolveTabAndFilterClasses: resolveTabAndFilterClasses
    };
})(window.wp);
