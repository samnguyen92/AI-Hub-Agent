/**
 * Align Theme - Gutenberg Editor Page Settings
 *
 * Adds an 'Align Page Settings' panel to the Document sidebar (Page/Post/CPTs)
 * allowing users to toggle per-page settings such as 'Load jQuery'.
 * Universal Password Lock is managed exclusively via list table Quick Edit.
 *
 * @package Align
 */

( function() {
	'use strict';

	if ( ! window.wp || ! wp.plugins || ! wp.plugins.registerPlugin ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = ( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) ||
		( wp.editor && wp.editor.PluginDocumentSettingPanel );
	var useEntityProp = wp.coreData && wp.coreData.useEntityProp;
	var useSelect = wp.data && wp.data.useSelect;
	var useDispatch = wp.data && wp.data.useDispatch;
	var useEffect = wp.element && wp.element.useEffect;
	var el = wp.element && wp.element.createElement;
	var ToggleControl = wp.components && wp.components.ToggleControl;

	if ( ! PluginDocumentSettingPanel || ! useEntityProp || ! useSelect || ! el || ! ToggleControl ) {
		return;
	}

	/**
	 * Align Page Settings Sidebar Panel Component.
	 */
	function AlignPageSettingsPanel() {
		var postInfo = useSelect( function( select ) {
			var editor = select( 'core/editor' );
			return {
				postType: editor ? editor.getCurrentPostType() : 'page',
				nativePassword: editor ? editor.getEditedPostAttribute( 'password' ) : '',
			};
		}, [] );

		var postType = postInfo.postType;
		var nativePassword = postInfo.nativePassword;

		// Suppress native WordPress password if set in store
		var editorDispatch = useDispatch( 'core/editor' );
		useEffect( function() {
			if ( nativePassword && editorDispatch && typeof editorDispatch.editPost === 'function' ) {
				console.log( '[Align PasswordLock] Suppressing native password from Gutenberg store' );
				editorDispatch.editPost( { password: '' } );
			}
		}, [ nativePassword, editorDispatch ] );

		var entity = useEntityProp( 'postType', postType, 'meta' );
		var meta = entity ? entity[0] || {} : {};
		var setMeta = entity ? entity[1] : null;

		var loadJquery = Boolean( meta && meta.align_load_jquery );

		var handleToggleJquery = function( newValue ) {
			if ( typeof setMeta !== 'function' ) {
				return;
			}
			console.log( '[Align PageSettings] Toggling align_load_jquery to:', newValue );
			var updatedMeta = Object.assign( {}, meta );
			updatedMeta.align_load_jquery = Boolean( newValue );
			setMeta( updatedMeta );
		};

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'align-page-settings-panel',
				title: 'Align Page Settings',
				icon: 'admin-generic',
				initialOpen: false,
			},
			el(
				'div',
				{
					className: 'align-page-settings-wrap',
					style: { paddingTop: '8px' },
				},
				el( ToggleControl, {
					label: 'Load jQuery',
					help: loadJquery
						? 'WordPress jQuery is forced to load in the footer on this page.'
						: 'WordPress jQuery is stripped for non-logged-in users unless enabled.',
					checked: loadJquery,
					onChange: handleToggleJquery,
				} )
			)
		);
	}

	registerPlugin( 'align-page-settings', {
		render: AlignPageSettingsPanel,
		icon: 'admin-generic',
	} );
} )();
