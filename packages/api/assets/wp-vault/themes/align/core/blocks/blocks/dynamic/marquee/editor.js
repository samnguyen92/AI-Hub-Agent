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
    const useBlockProps = blockEditor && blockEditor.useBlockProps ? blockEditor.useBlockProps : function(props) { return props || {}; };
    const components = wp.components || {};
    const PanelBody = components.PanelBody || function (props) { return el('div', props, props.children); };
    const TextControl = components.TextControl;
    const ToggleControl = components.ToggleControl;
    const RangeControl = components.RangeControl;
    
    // Custom select control component matching other custom blocks
    const AlignSelectControl = helpers.AlignSelectControl;
    const useHideAdvancedPanel = helpers.useHideAdvancedPanel;

    function Edit(props) {
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;

        useHideAdvancedPanel();

        const inspector = InspectorControls ? el(InspectorControls, {}, [
            el(PanelBody, { title: __('Marquee Settings', 'align-function'), initialOpen: true }, [
                el(RangeControl, {
                    label: __('Scroll Speed', 'align-function'),
                    value: attributes.speed !== undefined ? attributes.speed : 1,
                    min: 0.1,
                    max: 10,
                    step: 0.1,
                    onChange: function (value) { setAttributes({ speed: value }); },
                    help: __('Pixels per frame. Higher values scroll faster.', 'align-function')
                }),
                el(AlignSelectControl, {
                    label: __('Scroll Direction', 'align-function'),
                    value: attributes.direction || 'ltr',
                    options: [
                        { label: __('Left to Right (LTR)', 'align-function'), value: 'ltr' },
                        { label: __('Right to Left (RTL)', 'align-function'), value: 'rtl' }
                    ],
                    onChange: function (value) { setAttributes({ direction: value }); }
                }),
                el(ToggleControl, {
                    label: __('Pause on Hover', 'align-function'),
                    checked: attributes.pauseOnHover !== undefined ? attributes.pauseOnHover : true,
                    onChange: function (value) { setAttributes({ pauseOnHover: value }); }
                }),
                el(RangeControl, {
                    label: __('Space Between Items (px)', 'align-function'),
                    value: attributes.spaceBetween !== undefined ? attributes.spaceBetween : 20,
                    min: 0,
                    max: 100,
                    onChange: function (value) { setAttributes({ spaceBetween: value }); }
                })
            ]),
            el(PanelBody, { title: __('Items Per View', 'align-function'), initialOpen: false }, [
                el(AlignSelectControl, {
                    label: __('Desktop Slides', 'align-function'),
                    value: attributes.slidesPerView || 'auto',
                    options: [
                        { label: __('Auto (Natural Width)', 'align-function'), value: 'auto' },
                        { label: '1', value: '1' },
                        { label: '2', value: '2' },
                        { label: '3', value: '3' },
                        { label: '4', value: '4' },
                        { label: '5', value: '5' },
                        { label: '6', value: '6' }
                    ],
                    onChange: function (value) { setAttributes({ slidesPerView: value }); }
                }),
                el(AlignSelectControl, {
                    label: __('Tablet Slides', 'align-function'),
                    value: attributes.slidesPerViewTablet || 'auto',
                    options: [
                        { label: __('Auto (Natural Width)', 'align-function'), value: 'auto' },
                        { label: '1', value: '1' },
                        { label: '2', value: '2' },
                        { label: '3', value: '3' },
                        { label: '4', value: '4' },
                        { label: '5', value: '5' }
                    ],
                    onChange: function (value) { setAttributes({ slidesPerViewTablet: value }); }
                }),
                el(AlignSelectControl, {
                    label: __('Mobile Slides', 'align-function'),
                    value: attributes.slidesPerViewMobile || 'auto',
                    options: [
                        { label: __('Auto (Natural Width)', 'align-function'), value: 'auto' },
                        { label: '1', value: '1' },
                        { label: '2', value: '2' },
                        { label: '3', value: '3' },
                        { label: '4', value: '4' }
                    ],
                    onChange: function (value) { setAttributes({ slidesPerViewMobile: value }); }
                })
            ])
        ]) : null;

        // Custom style tags to preview slides in the editor
        let editorStyleRules = '';
        const _spv = attributes.slidesPerView === 'auto' ? 'auto' : (parseFloat(attributes.slidesPerView) || 1);
        const _gap = attributes.spaceBetween !== undefined ? attributes.spaceBetween : 20;
        const _sel = '[data-block="' + props.clientId + '"]';

        editorStyleRules += _sel + ' .align-marquee-editor-list { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: ' + _gap + 'px !important; }';
        if (_spv === 'auto') {
            editorStyleRules += _sel + ' .splide__slide { width: auto !important; flex: 0 0 auto !important; }';
        } else {
            const _totalGap = _gap * (_spv - 1);
            const _w = 'calc((100% - ' + _totalGap + 'px) / ' + _spv + ')';
            editorStyleRules += _sel + ' .splide__slide { width: ' + _w + ' !important; flex: 0 0 ' + _w + ' !important; }';
        }

        const editorStyle = el('style', { key: 'marquee-preview-style' }, editorStyleRules);

        const innerBlocksProps = blockEditor.useInnerBlocksProps
            ? blockEditor.useInnerBlocksProps(
                  { className: 'align-marquee-editor-list splide__list align_custom_block' },
                  {
                      allowedBlocks: ['align/slide'],
                      template: [['align/slide', {}]]
                  }
              )
            : null;

        let innerBlocksContent;
        if (innerBlocksProps) {
            innerBlocksContent = el('div', innerBlocksProps);
        } else {
            innerBlocksContent = el(InnerBlocks, {
                allowedBlocks: ['align/slide'],
                template: [['align/slide', {}]]
            });
        }

        const blockProps = useBlockProps({
            className: 'align-marquee-editor splide',
            key: 'marquee-editor-wrap'
        });

        return [
            inspector,
            editorStyle,
            el('div', blockProps, el('div', { className: 'splide__track' }, innerBlocksContent))
        ];
    }

    registerBlockType('align/marquee', {
        icon: 'slides',
        edit: Edit,
        save: function () {
            return el(wp.blockEditor.InnerBlocks.Content, {});
        }
    });

})(window.wp, window.alignQueryBuilderHelpers);
