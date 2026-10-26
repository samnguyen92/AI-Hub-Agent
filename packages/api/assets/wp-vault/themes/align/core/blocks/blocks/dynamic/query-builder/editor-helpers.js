window.alignQueryBuilderHelpers = (function (wp) {
    if (!wp || !wp.element) {
        return {};
    }

    const el = wp.element.createElement;
    const __ = wp.i18n ? wp.i18n.__ : function(text) { return text; };

    function getBlockElement(clientId) {
        var foundEl = document.querySelector('[data-block="' + clientId + '"]');
        if (foundEl) return foundEl;
        var iframes = document.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
            try {
                var doc = iframes[i].contentDocument || iframes[i].contentWindow.document;
                foundEl = doc.querySelector('[data-block="' + clientId + '"]');
                if (foundEl) return foundEl;
            } catch (e) {}
        }
        return null;
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

    const AlignMultiSelectControl = function(props) {
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

        var selectedValues = Array.isArray(props.value) ? props.value : [];
        
        var displayLabel = '';
        if (selectedValues.includes('all')) {
            displayLabel = __('All Post Types', 'align-function');
        } else if (selectedValues.length === 0) {
            displayLabel = props.placeholder || __('Select options...', 'align-function');
        } else if (selectedValues.length === 1) {
            var found = props.options.find(function(o) { return String(o.value) === String(selectedValues[0]); });
            displayLabel = found ? found.label : selectedValues[0];
        } else {
            var labels = selectedValues.map(function(val) {
                var foundOpt = props.options.find(function(o) { return String(o.value) === String(val); });
                return foundOpt ? foundOpt.label : val;
            });
            displayLabel = labels.join(', ');
        }

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
                el('span', { key: 'text', style: { overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, displayLabel),
                el('span', { key: 'arrow', className: 'align-custom-select-arrow' })
            ]),
            isOpen ? el('div', {
                key: 'dropdown',
                className: 'align-select-dropdown'
            }, props.options.map(function(opt, index) {
                var isChecked = selectedValues.includes(opt.value);
                return el('div', {
                    key: index,
                    className: 'align-select-opt ' + (isChecked ? 'is-selected' : ''),
                    onClick: function(e) {
                        e.stopPropagation();
                        if (props.onChange) props.onChange(opt.value, !isChecked);
                    },
                    style: { display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }
                }, [
                    el('input', {
                        key: 'chk',
                        type: 'checkbox',
                        checked: isChecked,
                        readOnly: true,
                        style: { margin: 0, pointerEvents: 'none' }
                    }),
                    el('span', { key: 'lbl' }, opt.label)
                ]);
            })) : null
        ]);
    };

    function useHideAdvancedPanel() {
        wp.element.useEffect(function() {
            var hideAdvanced = function() {
                var inspector = document.querySelector('.block-editor-block-inspector');
                if (!inspector) return;
                var panels = inspector.querySelectorAll('.components-panel__body');
                panels.forEach(function(panel) {
                    var btn = panel.querySelector(':scope > .components-panel__body-title button');
                    if (btn && btn.textContent.trim() === 'Advanced') { panel.style.display = 'none'; }
                });
            };
            var timer = setTimeout(hideAdvanced, 50);
            return function() { clearTimeout(timer); };
        });
    }

    class AlignSSRErrorBoundary extends wp.element.Component {
        constructor(props) {
            super(props);
            this.state = { hasError: false };
        }
        static getDerivedStateFromError() {
            return { hasError: true };
        }
        componentDidCatch(error, errorInfo) {
            console.warn('Query Builder SSR Error:', error);
        }
        componentDidUpdate(prevProps) {
            if (prevProps.key !== this.props.key && this.state.hasError) {
                this.setState({ hasError: false });
            }
        }
        render() {
            if (this.state.hasError) {
                setTimeout(() => { if (this.state.hasError) this.setState({ hasError: false }); }, 500);
                return el('div', { 
                    style: { padding: '20px', textAlign: 'center', background: '#f0f0f0', borderRadius: '4px', color: '#666' } 
                }, 'Refreshing preview...');
            }
            return this.props.children;
        }
    }

    const customLogoIcon = el('svg', {
        width: '24',
        height: '24',
        viewBox: '0 0 24 24',
        className: 'lucide lucide-server',
        style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' }
    }, [
        el('g', { transform: 'translate(2.4, 2.4) scale(0.8)' }, [
            el('rect', { width: '20', height: '8', x: '2', y: '2', rx: '2', ry: '2', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('rect', { width: '20', height: '8', x: '2', y: '14', rx: '2', ry: '2', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('line', { x1: '6', x2: '6.01', y1: '6', y2: '6', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('line', { x1: '6', x2: '6.01', y1: '18', y2: '18', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } })
        ])
    ]);

    const customLogoIconSlide = el('svg', {
        width: '24',
        height: '24',
        viewBox: '0 0 24 24',
        className: 'lucide lucide-columns-2',
        style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' }
    }, [
        el('g', { transform: 'translate(2.4, 2.4) scale(0.8)' }, [
            el('rect', { width: '18', height: '18', x: '3', y: '3', rx: '2', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('path', { d: 'M12 3v18', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } })
        ])
    ]);

    return {
        getBlockElement: getBlockElement,
        AlignSelectControl: AlignSelectControl,
        AlignMultiSelectControl: AlignMultiSelectControl,
        useHideAdvancedPanel: useHideAdvancedPanel,
        AlignSSRErrorBoundary: AlignSSRErrorBoundary,
        customLogoIcon: customLogoIcon,
        customLogoIconSlide: customLogoIconSlide
    };
})(window.wp);
