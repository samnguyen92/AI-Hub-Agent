(function (wp, helpers) {
    if (!wp || !wp.blocks || !wp.element || !helpers) {
        return;
    }

    const registerBlockType = wp.blocks.registerBlockType;
    const el = wp.element.createElement;
    const __ = wp.i18n.__;

    const blockEditor = wp.blockEditor || wp.editor;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const InnerBlocks = blockEditor ? blockEditor.InnerBlocks : null;

    const components = wp.components || {};
    const PanelBody = components.PanelBody || function (props) { return el('div', props, props.children); };
    const TextControl = components.TextControl;

    const useHideAdvancedPanel = helpers.useHideAdvancedPanel;
    const customLogoIconSlide = helpers.customLogoIconSlide;

    function EditSlide(props) {
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;

        useHideAdvancedPanel();

        const inspector = InspectorControls ? el(InspectorControls, {},
            el(PanelBody, { title: __('Slide Settings', 'align-function'), initialOpen: true }, [
                el(TextControl, {
                    label: __('CSS Class', 'align-function'),
                    value: attributes.className || '',
                    onChange: function(value) { setAttributes({ className: value }); },
                    help: __('Separate multiple classes with spaces.', 'align-function')
                })
            ])
        ) : null;

        const editorContent = el('div', { className: 'align-splide-slide-editor', style: { minHeight: '100px', position: 'relative', height: '100%' } }, [
            el('div', { style: { position: 'relative', zIndex: 1, height: '100%' } }, 
                el(InnerBlocks, { template: [['core/paragraph', { placeholder: __('Add slide content...', 'align-function') }]] })
            )
        ]);

        const blockProps = wp.blockEditor.useBlockProps({
            className: (props.className || '') + ' splide__slide'
        });

        return [
            inspector,
            el('div', blockProps, editorContent)
        ];
    }

    registerBlockType('align/slide', {
        icon: customLogoIconSlide,
        edit: EditSlide,
        save: function () { return el(wp.blockEditor.InnerBlocks.Content, {}); }
    });

})(window.wp, window.alignQueryBuilderHelpers);
