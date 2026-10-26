/**
 * Align Theme - Typography & Dynamic Slot Font Manager Frontend Controller
 *
 * Handles multi-file font uploading, automatic weight and style detection,
 * live preview rendering, one-click CSS copying, and dynamic slot additions.
 *
 * @package Align
 */

( function( $ ) {
	'use strict';

	var supportedWeights = {
		'100': 'Thin (100)',
		'200': 'ExtraLight (200)',
		'300': 'Light (300)',
		'400': 'Regular (400)',
		'500': 'Medium (500)',
		'600': 'SemiBold (600)',
		'700': 'Bold (700)',
		'800': 'ExtraBold (800)',
		'900': 'Black (900)'
	};

	/**
	 * Detect weight from filename string.
	 *
	 * @param {string} filename
	 * @return {string} Weight number string.
	 */
	function detectWeight( filename ) {
		var lower = filename.toLowerCase();
		if ( lower.indexOf( 'black' ) !== -1 || lower.indexOf( 'heavy' ) !== -1 || lower.indexOf( '900' ) !== -1 ) {
			return '900';
		}
		if ( lower.indexOf( 'extrabold' ) !== -1 || lower.indexOf( 'extra-bold' ) !== -1 || lower.indexOf( '800' ) !== -1 ) {
			return '800';
		}
		if ( lower.indexOf( 'semibold' ) !== -1 || lower.indexOf( 'semi-bold' ) !== -1 || lower.indexOf( 'demibold' ) !== -1 || lower.indexOf( '600' ) !== -1 ) {
			return '600';
		}
		if ( lower.indexOf( 'bold' ) !== -1 || lower.indexOf( '700' ) !== -1 ) {
			return '700';
		}
		if ( lower.indexOf( 'medium' ) !== -1 || lower.indexOf( '500' ) !== -1 ) {
			return '500';
		}
		if ( lower.indexOf( 'extralight' ) !== -1 || lower.indexOf( 'extra-light' ) !== -1 || lower.indexOf( '200' ) !== -1 ) {
			return '200';
		}
		if ( lower.indexOf( 'light' ) !== -1 || lower.indexOf( '300' ) !== -1 ) {
			return '300';
		}
		if ( lower.indexOf( 'thin' ) !== -1 || lower.indexOf( '100' ) !== -1 ) {
			return '100';
		}
		return '400';
	}

	/**
	 * Detect style from filename string.
	 *
	 * @param {string} filename
	 * @return {string} 'normal' or 'italic'.
	 */
	function detectStyle( filename ) {
		var lower = filename.toLowerCase();
		if ( lower.indexOf( 'italic' ) !== -1 || lower.indexOf( 'oblique' ) !== -1 ) {
			return 'italic';
		}
		return 'normal';
	}

	/**
	 * Detect base family name from filename (e.g. 'Barlow-SemiBold.woff2' -> 'Barlow').
	 *
	 * @param {string} filename
	 * @return {string}
	 */
	function detectBaseFamily( filename ) {
		var base = filename.replace( /\.(woff2|woff|ttf|otf|eot)$/i, '' );
		base = base.replace( /[-_](Thin|ExtraLight|Light|Regular|Medium|SemiBold|Bold|ExtraBold|Black|Heavy|Italic|Normal|[0-9]{3})/gi, '' );
		return base.trim();
	}

	/**
	 * Inject dynamic @font-face rule into document head for live preview.
	 *
	 * @param {string} fontName
	 * @param {string} url
	 * @param {string} weight
	 * @param {string} style
	 */
	function injectLiveFontFace( fontName, url, weight, style ) {
		var styleTag = document.getElementById( 'align-live-font-preview-css' );
		if ( ! styleTag ) {
			styleTag = document.createElement( 'style' );
			styleTag.id = 'align-live-font-preview-css';
			document.head.appendChild( styleTag );
		}
		var rule = "@font-face { font-family: '" + fontName + "'; src: url('" + url + "'); font-weight: " + weight + "; font-style: " + style + "; font-display: swap; }\n";
		styleTag.appendChild( document.createTextNode( rule ) );
	}

	function initFontManager() {
		// Multi-file upload trigger from dropzone
		$( document ).on( 'click', '.align-font-dropzone', function( e ) {
			e.preventDefault();
			var $dropzone = $( this );
			var $card = $dropzone.closest( '.align-font-slot-card' );
			var slotKey = $card.data( 'slot' );
			var slotNum = $card.data( 'slot-num' );
			var fontName = $card.find( '.align-font-name-input' ).val() || ( 'font-primary-' + slotNum );
			var $list = $card.find( '.align-font-files-list' );

			var mediaFrame = wp.media( {
				title: 'Select or Upload Font File(s)',
				button: { text: 'Use Selected Fonts' },
				multiple: true
			} );

			mediaFrame.on( 'select', function() {
				var attachments = mediaFrame.state().get( 'selection' ).toJSON();
				if ( ! attachments || ! attachments.length ) {
					return;
				}

				var siteUrl = window.location.origin;
				var detectedFamily = '';

				$.each( attachments, function( index, attachment ) {
					if ( ! attachment || ! attachment.url ) {
						return;
					}

					var fullUrl = attachment.url;
					var relativeUrl = fullUrl.indexOf( siteUrl ) === 0 ? fullUrl.replace( siteUrl, '' ) : fullUrl;
					var rawFileName = attachment.filename || attachment.name || fullUrl.substring( fullUrl.lastIndexOf( '/' ) + 1 );

					var weight = detectWeight( rawFileName );
					var style = detectStyle( rawFileName );
					if ( ! detectedFamily ) {
						detectedFamily = detectBaseFamily( rawFileName );
					}

					var rowIndex = $list.find( '.align-font-file-row' ).length + '_' + Date.now() + '_' + index;

					// Build options HTML for weight
					var optionsHtml = '';
					$.each( supportedWeights, function( val, label ) {
						optionsHtml += '<option value="' + val + '" ' + ( val === weight ? 'selected' : '' ) + '>' + label + '</option>';
					} );

					var rowHtml = [
						'<div class="align-font-file-row" style="display: flex; gap: 10px; align-items: center; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px;">',
						'  <select name="align_font_slots[' + slotKey + '][files][' + rowIndex + '][weight]" style="height: 32px; width: 145px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 500;">' + optionsHtml + '</select>',
						'  <select name="align_font_slots[' + slotKey + '][files][' + rowIndex + '][style]" style="height: 32px; width: 95px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 500;">',
						'    <option value="normal" ' + ( style === 'normal' ? 'selected' : '' ) + '>Normal</option>',
						'    <option value="italic" ' + ( style === 'italic' ? 'selected' : '' ) + '>Italic</option>',
						'  </select>',
						'  <input type="text" name="align_font_slots[' + slotKey + '][files][' + rowIndex + '][url]" value="' + relativeUrl + '" class="align-font-file-url widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px; flex: 1;" />',
						'  <button type="button" class="button button-danger align-remove-font-row-btn" style="height: 32px; border-radius: 6px; font-size: 12.5px; font-weight: 600; padding: 0 10px;" title="Remove file row">&times;</button>',
						'</div>'
					].join( '\n' );

					$list.append( rowHtml );
					injectLiveFontFace( fontName, fullUrl, weight, style );
					if ( detectedFamily && detectedFamily !== fontName ) {
						injectLiveFontFace( detectedFamily, fullUrl, weight, style );
					}

					console.log( '[Align FontManager] Added font weight file: ' + rawFileName + ' | Weight: ' + weight + ' | Style: ' + style );
				} );

				// Update detected family label if previously empty
				if ( detectedFamily ) {
					$card.find( '.align-font-original-name-input' ).val( detectedFamily );
					var $origNameSpan = $card.find( '.align-font-orig-name' );
					if ( ! $origNameSpan.length ) {
						$card.find( '.align-font-slug-badge' ).after( '<span class="align-font-orig-name" style="font-size: 12.5px; color: #64748b; font-weight: 500;"> (' + detectedFamily + ')</span>' );
					} else {
						$origNameSpan.text( '(' + detectedFamily + ')' );
					}
				}

				// Reveal live preview box
				var $previewBox = $card.find( '.align-font-preview-box' );
				$previewBox.show();
				$card.find( '.align-font-preview-text' ).css( 'font-family', "'" + fontName + "', sans-serif" );
			} );

			mediaFrame.open();
		} );

		// Remove file row
		$( document ).on( 'click', '.align-remove-font-row-btn', function( e ) {
			e.preventDefault();
			var $card = $( this ).closest( '.align-font-slot-card' );
			$( this ).closest( '.align-font-file-row' ).remove();
			if ( ! $card.find( '.align-font-file-row' ).length ) {
				$card.find( '.align-font-preview-box' ).hide();
			}
		} );

		// 1-Click Copy CSS to clipboard
		$( document ).on( 'click', '.align-copy-css-btn', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var copyText = $btn.data( 'copy' );
			if ( ! copyText ) {
				return;
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( copyText ).then( function() {
					flashCopySuccess( $btn );
				} ).catch( function() {
					fallbackCopy( copyText, $btn );
				} );
			} else {
				fallbackCopy( copyText, $btn );
			}
		} );

		function fallbackCopy( text, $btn ) {
			var tempInput = document.createElement( 'input' );
			tempInput.value = text;
			document.body.appendChild( tempInput );
			tempInput.select();
			document.execCommand( 'copy' );
			document.body.removeChild( tempInput );
			flashCopySuccess( $btn );
		}

		function flashCopySuccess( $btn ) {
			var originalText = $btn.text();
			$btn.text( 'Copied!' ).css( { 'color': '#059669', 'border-color': '#a7f3d0' } );
			setTimeout( function() {
				$btn.text( originalText ).css( { 'color': '', 'border-color': '' } );
			}, 1800 );
		}

		// Advanced Options Accordion Toggle
		$( document ).on( 'click', '.align-font-advanced-toggle', function( e ) {
			e.preventDefault();
			var $card = $( this ).closest( '.align-font-slot-card' );
			var $panel = $card.find( '.align-font-advanced-panel' );
			var $arrow = $( this ).find( '.align-accordion-arrow' );

			$panel.slideToggle( 180, function() {
				if ( $panel.is( ':visible' ) ) {
					$arrow.css( 'transform', 'rotate(180deg)' );
				} else {
					$arrow.css( 'transform', 'rotate(0deg)' );
				}
			} );
		} );

		// Remove Slot
		$( document ).on( 'click', '.align-remove-slot-btn', function( e ) {
			e.preventDefault();
			if ( confirm( 'Are you sure you want to remove this font slot?' ) ) {
				var $card = $( this ).closest( '.align-font-slot-card' );
				$card.fadeOut( 200, function() {
					$card.remove();
				} );
			}
		} );

		// Add New Font Slot Dynamically
		$( document ).on( 'click', '#align-add-font-slot-btn', function( e ) {
			e.preventDefault();
			var $container = $( '#align-font-slots-container' );
			var maxNum = 0;

			$container.find( '.align-font-slot-card' ).each( function() {
				var num = parseInt( $( this ).data( 'slot-num' ), 10 );
				if ( ! isNaN( num ) && num > maxNum ) {
					maxNum = num;
				}
			} );

			var newNum = maxNum + 1;
			var newSlotKey = 'font_' + newNum;
			var newVarName = '--font-primary-' + newNum;
			var newSlug = 'font-primary-' + newNum;

			var cardHtml = [
				'<div class="align-font-slot-card" data-slot="' + newSlotKey + '" data-slot-num="' + newNum + '" style="border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; padding: 20px;">',
				'  <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">',
				'    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">',
				'      <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a;">Font ' + newNum + '</h4>',
				'      <code class="align-font-slug-badge" style="background: #f1f5f9; color: #0284c7; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">' + newSlug + '</code>',
				'    </div>',
				'    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">',
				'      <code style="background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; padding: 4px 10px; border-radius: 6px; font-size: 12px;">font-family: var(' + newVarName + ');</code>',
				'      <button type="button" class="button button-secondary align-copy-css-btn" data-copy="font-family: var(' + newVarName + ');" style="height: 32px; border-radius: 6px; font-size: 12px; font-weight: 600;">Copy CSS</button>',
				'      <button type="button" class="button button-danger align-remove-slot-btn" data-slot="' + newSlotKey + '" style="height: 32px; border-radius: 6px; font-size: 12px; font-weight: 600; padding: 0 10px;" title="Remove slot">&times;</button>',
				'    </div>',
				'  </div>',
				'  <div class="align-font-dropzone" data-slot="' + newSlotKey + '" style="border: 2px dashed #bae6fd; border-radius: 8px; padding: 18px 20px; text-align: center; background: #f0f9ff; cursor: pointer; transition: all 0.2s ease; margin-bottom: 16px;">',
				'    <div style="font-size: 13.5px; font-weight: 600; color: #0284c7; margin-bottom: 4px;">+ Upload Font Files (.woff2, .woff, .ttf, .otf)</div>',
				'    <div style="font-size: 12px; color: #64748b;">Click to select single or multiple font weights at once. Weights and styles are auto-detected.</div>',
				'  </div>',
				'  <input type="hidden" name="align_font_slots[' + newSlotKey + '][name]" value="' + newSlug + '" class="align-font-name-input" />',
				'  <input type="hidden" name="align_font_slots[' + newSlotKey + '][original_name]" value="" class="align-font-original-name-input" />',
				'  <div class="align-font-files-wrapper">',
				'    <div class="align-font-files-list" style="display: flex; flex-direction: column; gap: 8px;"></div>',
				'  </div>',
				'  <div class="align-font-preview-box" style="margin-top: 16px; padding: 14px 16px; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1; display: none;">',
				'    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px;">Live Preview:</span>',
				'    <p class="align-font-preview-text" style="margin: 6px 0 0; font-family: \'' + newSlug + '\', sans-serif; font-size: 18px; color: #0f172a; line-height: 1.4;">The quick brown fox jumps over the lazy dog. 0123456789</p>',
				'  </div>',
				'  <div class="align-font-advanced-wrap" style="margin-top: 14px; border-top: 1px solid #f1f5f9; padding-top: 10px;">',
				'    <div class="align-font-advanced-toggle" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 600; color: #64748b; user-select: none;">',
				'      <span style="display: flex; align-items: center; gap: 6px;">Advanced Options (Fallback, Alias, Loading Strategy) <span class="align-accordion-arrow" style="font-size: 10px; transition: transform 0.2s ease;">&#9660;</span></span>',
				'    </div>',
				'    <div class="align-font-advanced-panel" style="display: none; margin-top: 12px; background: #f8fafc; padding: 14px 16px; border-radius: 6px; border: 1px solid #e2e8f0;">',
				'      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">',
				'        <div><label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px;">Font Family Alias</label><input type="text" name="align_font_slots[' + newSlotKey + '][name]" value="' + newSlug + '" class="widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px;" /></div>',
				'        <div><label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px;">Generic Fallback</label><select name="align_font_slots[' + newSlotKey + '][fallback]" class="widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px;"><option value="sans-serif" selected>sans-serif</option><option value="serif">serif</option><option value="monospace">monospace</option></select></div>',
				'        <div><label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px;">Loading Strategy</label><select name="align_font_slots[' + newSlotKey + '][loading]" class="widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px;"><option value="preload">Preload (Top 1 Priority)</option><option value="normal" selected>Normal (On-Demand)</option><option value="delay">Delay (User Interaction)</option></select></div>',
				'      </div>',
				'    </div>',
				'  </div>',
				'</div>'
			].join( '\n' );

			var $newCard = $( cardHtml );
			$container.append( $newCard );

			$( 'html, body' ).animate( {
				scrollTop: $newCard.offset().top - 100
			}, 300 );

			console.log( '[Align FontManager] Added dynamic font slot: ' + newSlotKey );
		} );
	}

	$( document ).ready( initFontManager );
} )( jQuery );

