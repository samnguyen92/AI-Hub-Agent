(function() {
    const { createElement: el } = wp.element;

    window.AlignSelectControl = function(props) {
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

    window.AlignLinkSearchControl = function(props) {
        const [searchQuery, setSearchQuery] = wp.element.useState(props.value || '');
        const [results, setResults] = wp.element.useState([]);
        const [isLoading, setIsLoading] = wp.element.useState(false);
        const [isOpen, setIsOpen] = wp.element.useState(false);
        const [pageTitle, setPageTitle] = wp.element.useState('');
        const wrapperRef = wp.element.useRef(null);
        const debounceTimer = wp.element.useRef(null);

        // Sync local state when external value changes
        wp.element.useEffect(function() {
            setSearchQuery(props.value || '');
        }, [props.value]);

        // Auto-resolve dynamic permalink if hrefId is present on load/mount
        wp.element.useEffect(function() {
            if (props.hrefId && props.hrefId > 0) {
                if (wp.apiFetch) {
                    wp.apiFetch({ path: '/custom/v1/permalink?id=' + props.hrefId })
                        .then(function(res) {
                            if (res && res.url) {
                                // Normalize trailing slashes to avoid infinite comparison loops
                                const normalize = function(url) {
                                    return url ? url.replace(/\/+$/, '') : '';
                                };
                                if (normalize(res.url) !== normalize(props.value)) {
                                    setSearchQuery(res.url);
                                    if (props.onChange) {
                                        props.onChange(res.url, props.hrefId);
                                    }
                                }
                                if (res.title) {
                                    setPageTitle(res.title);
                                }
                            }
                        })
                        .catch(function(err) {
                            console.error('Error fetching dynamic permalink:', err);
                        });
                }
            }
        }, [props.hrefId]);

        // Clean up debounce timer on unmount
        wp.element.useEffect(function() {
            return function() {
                if (debounceTimer.current) {
                    clearTimeout(debounceTimer.current);
                }
            };
        }, []);

        // Close dropdown when clicking outside
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

        // Perform the API search request
        const performSearch = function(query) {
            setIsLoading(true);
            setIsOpen(true);

            if (wp.apiFetch) {
                wp.apiFetch({ path: '/wp/v2/search?search=' + encodeURIComponent(query) + '&per_page=20' })
                    .then(function(res) {
                        setResults(res || []);
                        setIsLoading(false);
                    })
                    .catch(function(err) {
                        console.error('Error fetching search results:', err);
                        setResults([]);
                        setIsLoading(false);
                    });
            } else {
                const url = window.location.origin + '/wp-json/wp/v2/search?search=' + encodeURIComponent(query) + '&per_page=20';
                window.fetch(url)
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        setResults(res || []);
                        setIsLoading(false);
                    })
                    .catch(function(err) {
                        console.error('Error fetching search results:', err);
                        setResults([]);
                        setIsLoading(false);
                    });
            }
        };

        // Handle typing in input field with debouncing
        const handleInputChange = function(value) {
            let formatted = value;
            try {
                const currentOrigin = window.location.origin;
                if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('//')) {
                    const parsed = new URL(value, currentOrigin);
                    if (parsed.origin === currentOrigin) {
                        formatted = parsed.pathname + parsed.search + parsed.hash;
                    }
                }
            } catch (e) {}

            // Prevent prepending slash for javascript/mailto/tel/hash or search queries with space
            const isSpecialLink = formatted.startsWith('/') || 
                                  formatted.startsWith('http://') || 
                                  formatted.startsWith('https://') || 
                                  formatted.startsWith('#') || 
                                  formatted.startsWith('javascript:') || 
                                  formatted.startsWith('mailto:') || 
                                  formatted.startsWith('tel:');

            if (formatted && !isSpecialLink) {
                if (!formatted.includes(' ')) {
                    formatted = '/' + formatted;
                }
            }

            setSearchQuery(formatted);
            if (props.onChange) {
                props.onChange(formatted, 0);
            }

            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }

            if (!value || value.trim().length < 2) {
                setResults([]);
                setIsLoading(false);
                setIsOpen(false);
                return;
            }

            setIsLoading(true);
            setIsOpen(true);

            debounceTimer.current = setTimeout(function() {
                performSearch(value);
            }, 400);
        };

        // Normalize URL to domain-agnostic format
        const handleSelect = function(url, id, title) {
            if (!url) return;
            let relativePath = url;
            try {
                const parsed = new URL(url);
                relativePath = parsed.pathname + parsed.search + parsed.hash;
            } catch (e) {
                const currentOrigin = window.location.origin;
                if (url.indexOf(currentOrigin) === 0) {
                    relativePath = url.substring(currentOrigin.length);
                }
            }
            
            if (relativePath && !relativePath.startsWith('/') && !relativePath.startsWith('http://') && !relativePath.startsWith('https://') && !relativePath.startsWith('#')) {
                relativePath = '/' + relativePath;
            }

            setSearchQuery(relativePath);
            if (title) {
                setPageTitle(title);
            }
            setIsOpen(false);
            if (props.onChange) {
                props.onChange(relativePath, id || 0);
            }
        };

        // If hrefId is present and resolved, render dynamic link badge instead of input field
        if (props.hrefId && props.hrefId > 0) {
            return el('div', {
                className: 'align-link-search-wrapper align-link-search-linked',
                style: { position: 'relative', marginBottom: '16px' }
            }, [
                el('label', { 
                    className: 'components-base-control__label align-menu-item-label-el',
                    style: { display: 'block' }
                }, props.label || 'Link URL'),
                el('div', {
                    style: {
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        background: '#e7f5fe',
                        border: '1px solid #b3e0ff',
                        padding: '5px 12px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        color: '#005c99',
                        height: '40px',
                        boxSizing: 'border-box',
                        marginTop: '8px'
                    }
                }, [
                    el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px' } }, [
                        el('span', { className: 'dashicons dashicons-admin-page', style: { fontSize: '14px', width: '14px', height: '14px', color: '#0073aa' } }),
                        el('span', { style: { fontWeight: '600' } }, pageTitle || 'Linked Page'),
                        el('span', { 
                            style: { 
                                fontSize: '9px', 
                                background: '#b3e0ff', 
                                padding: '1px 4px', 
                                borderRadius: '2px', 
                                color: '#004773',
                                fontWeight: 'bold'
                            } 
                        }, `ID: ${props.hrefId}`)
                    ]),
                    el(wp.components.Button, {
                        isSmall: true,
                        isDestructive: true,
                        variant: 'link',
                        onClick: function() {
                            setPageTitle('');
                            setSearchQuery('');
                            if (props.onChange) {
                                props.onChange('', 0);
                            }
                        },
                        style: { padding: 0, height: 'auto', minWidth: 'auto', textDecoration: 'none', color: '#d94f4f', fontSize: '11px' }
                    }, 'Disconnect')
                ])
            ]);
        }



        return el('div', {
            className: 'align-link-search-wrapper ' + (isOpen ? 'is-open' : ''),
            ref: wrapperRef,
            style: { position: 'relative' }
        }, [
            el(wp.components.TextControl, {
                key: 'input',
                label: props.label || 'Link URL',
                value: searchQuery,
                onChange: handleInputChange,
                onFocus: function() {
                    if (results.length > 0) {
                        setIsOpen(true);
                    }
                }
            }),
            isOpen ? el('div', {
                key: 'dropdown',
                className: 'align-link-search-dropdown'
            }, [
                isLoading ? el('div', {
                    key: 'loading',
                    className: 'align-link-search-status'
                }, [
                    el(wp.components.Spinner, { key: 'spinner' }),
                    el('span', { key: 'text' }, 'Searching...')
                ]) : null,
                !isLoading && results.length === 0 ? el('div', {
                    key: 'no-results',
                    className: 'align-link-search-status'
                }, 'No results found') : null,
                !isLoading && results.length > 0 ? results.map(function(item, index) {
                    let typeLabel = item.subtype || item.type || 'page';
                    typeLabel = typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1);
                    
                    let displayUrl = item.url;
                    try {
                        const parsed = new URL(item.url);
                        displayUrl = parsed.pathname + parsed.search + parsed.hash;
                    } catch (e) {}

                    return el('div', {
                        key: index,
                        className: 'align-link-search-item',
                        onClick: function() { handleSelect(item.url, item.id, item.title); }
                    }, [
                        el('span', { key: 'title', className: 'align-link-search-item-title' }, item.title || 'Untitled'),
                        el('span', { key: 'meta', className: 'align-link-search-item-meta' }, typeLabel + ' • ' + displayUrl)
                    ]);
                }) : null
            ]) : null
        ]);
    };

    window.AlignErrorBoundary = class AlignErrorBoundary extends wp.element.Component {
        constructor(props) {
            super(props);
            this.state = { hasError: false, error: null };
        }
        static getDerivedStateFromError(error) {
            return { hasError: true, error: error };
        }
        componentDidCatch(error, errorInfo) {
            console.error("Align Element Block Crash:", error, errorInfo);
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
    };
})();
