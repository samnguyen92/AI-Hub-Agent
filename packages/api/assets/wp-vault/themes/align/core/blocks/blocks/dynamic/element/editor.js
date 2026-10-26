(function(wp) {
    if (!wp || !wp.blocks || !wp.element) return;

    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;
    const { AlignErrorBoundary } = window;
    
    const CONTAINER_TAGS = window.AlignElementUtils.CONTAINER_TAGS;

    const customLogoIcon = el('svg', {
        width: '24',
        height: '24',
        viewBox: '0 0 24 24',
        className: 'lucide lucide-box',
        style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' }
    }, [
        el('g', { transform: 'translate(2.4, 2.4) scale(0.8)' }, [
            el('path', { d: 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('path', { d: 'm3.3 7 8.7 5 8.7-5', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } }),
            el('path', { d: 'M12 22V12', style: { fill: 'none', stroke: 'currentColor', strokeWidth: '2px' } })
        ])
    ]);

    registerBlockType('align/element', {
        icon: customLogoIcon,
        __experimentalLabel: function(attributes, { context }) {
            if (attributes.metadata && attributes.metadata.name) {
                return attributes.metadata.name;
            }
            if (attributes.tag) {
                return attributes.tag.charAt(0).toUpperCase() + attributes.tag.slice(1);
            }
            return 'Element';
        },
        edit: function(props) {
            return el(AlignErrorBoundary, null, el(window.AlignElementEditInternal, props));
        },
        save: function ({ attributes }) {
            const { tag, type } = attributes;
            const isContainer = CONTAINER_TAGS.includes(tag) || type === 'inner';
            
            if (isContainer) {
                return el(wp.blockEditor.InnerBlocks.Content, {});
            }
            return null;
        }
    });
})(window.wp);
