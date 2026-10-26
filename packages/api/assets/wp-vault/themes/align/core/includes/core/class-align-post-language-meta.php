<?php
/**
 * Align Theme - Post Multi-Language Management for Gutenberg & WP Admin
 *
 * Adds Language Sidebar Panel to Gutenberg for 'post' post type.
 * Adds Language column and filter views in /wp-admin/edit.php.
 * Ensures zero Align custom blocks on posts while providing full multi-language capabilities.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Post_Language_Meta {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes',                  array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post',                       array( __CLASS__, 'save_post_lang' ) );
		add_filter( 'manage_post_posts_columns',       array( __CLASS__, 'add_lang_column' ) );
		add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'render_lang_column' ), 10, 2 );
		add_filter( 'views_edit-post',                 array( __CLASS__, 'add_lang_views' ) );
		add_action( 'pre_get_posts',                   array( __CLASS__, 'filter_posts_by_lang' ) );
		add_action( 'wp_ajax_align_create_post_translation', array( __CLASS__, 'ajax_create_translation' ) );

		// Permalink & Rewrite for language prefix (e.g. /zh/post-slug/, /vi/page-slug/)
		add_action( 'init',                            array( __CLASS__, 'register_post_lang_rewrites' ) );
		add_filter( 'post_link',                       array( __CLASS__, 'filter_post_link' ), 10, 2 );
		add_filter( 'page_link',                       array( __CLASS__, 'filter_page_link' ), 10, 3 );
		add_filter( 'query_vars',                      array( __CLASS__, 'register_query_vars' ) );
		add_action( 'pre_get_posts',                   array( __CLASS__, 'resolve_post_by_lang_slug' ) );
		add_filter( 'redirect_canonical',              array( __CLASS__, 'filter_redirect_canonical' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Gutenberg Sidebar Meta Box
	// -------------------------------------------------------------------------

	/**
	 * Register Language Meta Box for posts in the block editor sidebar.
	 */
	public static function register_meta_box() {
		if ( ! class_exists( 'Align_Language_Manager' ) ) return;
		$langs = Align_Language_Manager::get_languages();
		if ( count( $langs ) < 2 ) return;

		add_meta_box(
			'align_post_language',
			__( 'Language', 'align' ),
			array( __CLASS__, 'render_meta_box' ),
			array( 'post' ),
			'side',
			'high'
		);
	}

	/**
	 * Render the Language Meta Box in Gutenberg.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'align_save_post_lang', 'align_post_lang_nonce' );

		$current_lang = Align_Language_Manager::get_post_lang( $post->ID );
		if ( empty( $current_lang ) ) {
			$current_lang = Align_Language_Manager::get_default_lang();
		}

		$group        = get_post_meta( $post->ID, Align_Language_Manager::META_GROUP, true );
		$all_langs    = Align_Language_Manager::get_languages();
		$translations = Align_Language_Manager::get_translations( $post->ID );
		$trans_by_lang = array();
		foreach ( $translations as $tr ) {
			$trans_by_lang[ $tr['lang'] ] = $tr;
		}

		?>
		<div class="align-post-lang-wrap" style="padding: 4px 0;">
			<style>
				.align-post-lang-wrap .lang-row { margin-bottom: 12px; }
				.align-post-lang-wrap label { font-weight: 600; font-size: 12px; display: block; margin-bottom: 4px; color: #1e293b; }
				.align-post-lang-wrap select { width: 100%; height: 32px; border-radius: 4px; font-size: 13px; }
				.align-trans-list { list-style: none; margin: 8px 0 0; padding: 0; }
				.align-trans-item { display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; margin-bottom: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px; }
				.align-trans-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-weight: 500; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #0284c7; }
				.align-trans-btn:hover { background: #f0f9ff; border-color: #0284c7; }
				.align-trans-add-btn { background: #0284c7; color: #fff; border-color: #0284c7; }
				.align-trans-add-btn:hover { background: #0369a1; color: #fff; }
			</style>

			<div class="lang-row">
				<label for="align_post_lang_select"><?php esc_html_e( 'Current Language:', 'align' ); ?></label>
				<select name="align_post_lang" id="align_post_lang_select">
					<?php foreach ( $all_langs as $l ) : ?>
						<option value="<?php echo esc_attr( $l['code'] ); ?>" <?php selected( $current_lang, $l['code'] ); ?>>
							<?php echo esc_html( ( $l['flag'] ?? '' ) . ' ' . $l['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="lang-row">
				<label><?php esc_html_e( 'Translations:', 'align' ); ?></label>
				<ul class="align-trans-list">
					<?php foreach ( $all_langs as $l ) :
						$code = $l['code'];
						if ( $code === $current_lang ) continue;
						$has_tr = isset( $trans_by_lang[ $code ] );
					?>
						<li class="align-trans-item">
							<span><?php echo esc_html( ( $l['flag'] ?? '' ) . ' ' . $l['name'] ); ?></span>
							<?php if ( $has_tr ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $trans_by_lang[ $code ]['post_id'] ) ); ?>" class="align-trans-btn">
									✏️ <?php esc_html_e( 'Edit', 'align' ); ?>
								</a>
							<?php else : ?>
								<button type="button" class="align-trans-btn align-trans-add-btn align-create-post-tr" data-source-id="<?php echo esc_attr( $post->ID ); ?>" data-lang="<?php echo esc_attr( $code ); ?>">
									+ <?php esc_html_e( 'Add', 'align' ); ?>
								</button>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<?php if ( isset( $trans_by_lang['en'] ) && $current_lang !== 'en' ) : ?>
			<div class="lang-row" style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #e2e8f0;">
				<button type="button" class="align-trans-btn align-copy-from-en-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>" style="width: 100%; justify-content: center; height: 28px;">
					🔄 <?php esc_html_e( 'Copy Content from English', 'align' ); ?>
				</button>
			</div>
			<?php endif; ?>
		</div>

		<script>
		(function() {
			document.addEventListener('click', function(e) {
				var copyBtn = e.target.closest('.align-copy-from-en-btn');
				if (copyBtn) {
					e.preventDefault();
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to copy content from the English post? Any unsaved edits will be replaced.', 'align' ) ); ?>')) return;
					copyBtn.disabled = true;
					copyBtn.textContent = 'Copying...';
					fetch(<?php echo wp_json_encode( esc_url_raw( rest_url( 'align-builder/v1/copy-original-post' ) ) ); ?>, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' },
						body: JSON.stringify({ post_id: <?php echo (int) $post->ID; ?> })
					})
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (res && res.success && res.post_content) {
							if (window.wp && wp.data && wp.blocks) {
								try {
									var parsed = wp.blocks.parse(res.post_content);
									wp.data.dispatch('core/block-editor').resetBlocks(parsed);
									alert('<?php echo esc_js( __( 'Successfully copied content from English post! You can now translate or edit.', 'align' ) ); ?>');
								} catch (e) {
									window.location.reload();
								}
							} else {
								window.location.reload();
							}
						} else {
							alert((res && res.message) || 'Failed to copy.');
							copyBtn.disabled = false;
							copyBtn.textContent = '🔄 <?php echo esc_js( __( 'Copy Content from English', 'align' ) ); ?>';
						}
					})
					.catch(function(err) {
						alert('Error: ' + err.message);
						copyBtn.disabled = false;
						copyBtn.textContent = '🔄 <?php echo esc_js( __( 'Copy Content from English', 'align' ) ); ?>';
					});
					return;
				}

				var btn = e.target.closest('.align-create-post-tr');
				if (!btn) return;
				e.preventDefault();

				var sourceId = btn.getAttribute('data-source-id');
				var lang = btn.getAttribute('data-lang');
				btn.disabled = true;
				btn.textContent = 'Creating...';

				var formData = new FormData();
				formData.append('action', 'align_create_post_translation');
				formData.append('source_id', sourceId);
				formData.append('target_lang', lang);
				formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'align_create_tr_nonce' ) ); ?>');

				fetch(ajaxurl, { method: 'POST', body: formData })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (res.success && res.data && res.data.edit_url) {
							window.location.href = res.data.edit_url;
						} else {
							alert((res.data && res.data.message) || 'Failed to create translation.');
							btn.disabled = false;
							btn.textContent = '+ Add';
						}
					})
					.catch(function(err) {
						alert('Network error while creating translation.');
						btn.disabled = false;
						btn.textContent = '+ Add';
					});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Save post language on post save.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_post_lang( $post_id ) {
		if ( ! isset( $_POST['align_post_lang_nonce'] ) || ! wp_verify_nonce( $_POST['align_post_lang_nonce'], 'align_save_post_lang' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$lang = sanitize_key( $_POST['align_post_lang'] ?? '' );
		if ( $lang ) {
			update_post_meta( $post_id, Align_Language_Manager::META_LANG, $lang );

			// Ensure post has a group
			$group = get_post_meta( $post_id, Align_Language_Manager::META_GROUP, true );
			if ( ! $group ) {
				$group = 'grp-' . substr( md5( uniqid( 'align_lang_', true ) ), 0, 12 );
				update_post_meta( $post_id, Align_Language_Manager::META_GROUP, $group );
			}
			error_log( '[Align PostLang] Saved post ID ' . $post_id . ' with lang: ' . $lang . ' | group: ' . $group );
		}
	}

	/**
	 * AJAX: Create translation for a post and return edit URL.
	 */
	public static function ajax_create_translation() {
		check_ajax_referer( 'align_create_tr_nonce', 'nonce' );
		$source_id   = intval( $_POST['source_id'] ?? 0 );
		$target_lang = sanitize_key( $_POST['target_lang'] ?? '' );

		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$result = Align_Language_Manager::create_translation( $source_id, $target_lang );
		if ( is_wp_error( $result ) ) {
			$existing_id = $result->get_error_data()['existing_id'] ?? null;
			if ( $existing_id ) {
				wp_send_json_success( array( 'edit_url' => get_edit_post_link( $existing_id, 'raw' ) ) );
			}
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'new_id'   => $result,
			'edit_url' => get_edit_post_link( $result, 'raw' ),
		) );
	}

	// -------------------------------------------------------------------------
	// Admin Post List Columns & Filter Views
	// -------------------------------------------------------------------------

	/**
	 * Add Languages column to /wp-admin/edit.php.
	 */
	public static function add_lang_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $k => $v ) {
			$new_columns[ $k ] = $v;
			if ( 'title' === $k ) {
				$new_columns['align_langs'] = __( 'Languages', 'align' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render language flags & translation status in post list.
	 */
	public static function render_lang_column( $column, $post_id ) {
		if ( 'align_langs' !== $column ) return;

		$current_lang = Align_Language_Manager::get_post_lang( $post_id ) ?: Align_Language_Manager::get_default_lang();
		$langs        = Align_Language_Manager::get_languages();
		$translations = Align_Language_Manager::get_translations( $post_id );
		$trans_by_lang = array();
		foreach ( $translations as $tr ) {
			$trans_by_lang[ $tr['lang'] ] = $tr;
		}

		echo '<div style="display:flex;align-items:center;gap:6px;">';
		foreach ( $langs as $l ) {
			$code = $l['code'];
			$flag = $l['flag'] ?? strtoupper( $code );
			if ( $code === $current_lang ) {
				echo '<span title="' . esc_attr( $l['name'] . ' (Current)' ) . '" style="padding:2px 4px;background:#e0f2fe;border:1px solid #bae6fd;border-radius:4px;font-size:12px;font-weight:600;">' . esc_html( $flag ) . '</span>';
			} elseif ( isset( $trans_by_lang[ $code ] ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $trans_by_lang[ $code ]['post_id'] ) ) . '" title="' . esc_attr( 'Edit ' . $l['name'] ) . '" style="padding:2px 4px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:4px;font-size:12px;text-decoration:none;">' . esc_html( $flag ) . '</a>';
			} else {
				echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=align_create_post_translation&source_id=' . $post_id . '&target_lang=' . $code ), 'align_create_tr_nonce', 'nonce' ) ) . '" class="align-create-post-tr" data-source-id="' . esc_attr( $post_id ) . '" data-lang="' . esc_attr( $code ) . '" title="' . esc_attr( 'Add ' . $l['name'] . ' translation' ) . '" style="padding:2px 4px;border:1px dashed #cbd5e1;border-radius:4px;font-size:11px;color:#94a3b8;text-decoration:none;">+' . esc_html( $flag ) . '</a>';
			}
		}
		echo '</div>';
	}

	/**
	 * Add language filter tabs in post list table header.
	 */
	public static function add_lang_views( $views ) {
		$langs = Align_Language_Manager::get_languages();
		if ( count( $langs ) < 2 ) return $views;

		$current_view = sanitize_key( $_GET['lang'] ?? '' );
		foreach ( $langs as $l ) {
			$code  = $l['code'];
			$count = self::count_posts_by_lang( $code );
			$url   = add_query_arg( array( 'post_type' => 'post', 'lang' => $code ), admin_url( 'edit.php' ) );
			$class = ( $current_view === $code ) ? ' class="current"' : '';
			$views[ 'lang_' . $code ] = sprintf( '<a href="%s"%s>%s %s <span class="count">(%d)</span></a>', esc_url( $url ), $class, esc_html( $l['flag'] ?? '' ), esc_html( $l['name'] ), $count );
		}
		return $views;
	}

	/**
	 * Filter posts by lang query parameter in /wp-admin/edit.php.
	 */
	public static function filter_posts_by_lang( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		$post_type = $query->get( 'post_type' );
		if ( 'post' !== $post_type ) return;

		$lang = sanitize_key( $_GET['lang'] ?? '' );
		if ( ! empty( $lang ) ) {
			$meta_query = $query->get( 'meta_query' ) ?: array();
			$default_lang = Align_Language_Manager::get_default_lang();
			if ( $lang === $default_lang ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array( 'key' => Align_Language_Manager::META_LANG, 'value' => $lang ),
					array( 'key' => Align_Language_Manager::META_LANG, 'compare' => 'NOT EXISTS' ),
				);
			} else {
				$meta_query[] = array( 'key' => Align_Language_Manager::META_LANG, 'value' => $lang );
			}
			$query->set( 'meta_query', $meta_query );
		}
	}

	private static function count_posts_by_lang( $lang ) {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$default_lang = Align_Language_Manager::get_default_lang();
		if ( $lang === $default_lang ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => Align_Language_Manager::META_LANG, 'value' => $lang ),
				array( 'key' => Align_Language_Manager::META_LANG, 'compare' => 'NOT EXISTS' ),
			);
		} else {
			$args['meta_query'] = array( array( 'key' => Align_Language_Manager::META_LANG, 'value' => $lang ) );
		}
		$posts = get_posts( $args );
		return count( $posts );
	}

	/**
	 * Register query vars 'align_lang' and 'align_lang_home'.
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'align_lang';
		$vars[] = 'align_lang_home';
		return $vars;
	}

	/**
	 * Register rewrite rules for pages and posts in secondary languages.
	 * Routes /<slug>/, /<slug>/<custom-post-prefix>/<slug>/, and /<slug>/<path>/.
	 */
	public static function register_post_lang_rewrites() {
		if ( ! class_exists( 'Align_Language_Manager' ) ) return;
		$langs        = Align_Language_Manager::get_languages();
		$default_lang = Align_Language_Manager::get_default_lang();
		$structure    = trim( (string) get_option( 'permalink_structure' ), '/' );

		foreach ( $langs as $lang ) {
			$code = $lang['code'];
			$slug = ! empty( $lang['slug'] ) ? trim( $lang['slug'], '/' ) : $code;
			if ( $code !== $default_lang && '' !== $slug ) {
				$slug_regex = preg_quote( $slug, '#' );

				// Rule 1: Language front page (/vi/)
				add_rewrite_rule(
					'^' . $slug_regex . '/?$',
					'index.php?align_lang=' . $code . '&align_lang_home=1',
					'top'
				);

				// Rule 2: Custom post permalink structure with static prefix before %postname% (e.g. ^zh/insights/([^/]+)/?$)
				if ( ! empty( $structure ) && false !== strpos( $structure, '%postname%' ) ) {
					$prefix_pattern = preg_replace( '#%postname%#', '([^/]+)', preg_quote( $structure, '#' ) );
					if ( '([^/]+)' !== $prefix_pattern ) {
						add_rewrite_rule(
							'^' . $slug_regex . '/' . $prefix_pattern . '/?$',
							'index.php?name=$matches[1]&align_lang=' . $code,
							'top'
						);
					}
				}

				// Rule 3: Single-level or hierarchical pages & root-level posts (/vi/course-001-vi/ or /vi/parent/child/)
				add_rewrite_rule(
					'^' . $slug_regex . '/(.+?)/?$',
					'index.php?pagename=$matches[1]&align_lang=' . $code,
					'top'
				);
			}
		}
	}

	/**
	 * Prepend language prefix to page permalinks for secondary languages.
	 */
	public static function filter_page_link( $permalink, $post_id, $sample ) {
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return $permalink;
		}
		if ( false !== strpos( (string) $permalink, '?page_id=' ) ) {
			return $permalink;
		}
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return $permalink;
		}

		$post_lang = get_post_meta( $post->ID, Align_Language_Manager::META_LANG, true );
		if ( empty( $post_lang ) || $post_lang === Align_Language_Manager::get_default_lang() ) {
			return $permalink;
		}

		$lang_cfg = Align_Language_Manager::get_language( $post_lang );
		if ( ! $lang_cfg ) {
			return $permalink;
		}

		$slug = ! empty( $lang_cfg['slug'] ) ? trim( $lang_cfg['slug'], '/' ) : $post_lang;
		if ( '' === $slug ) {
			return $permalink;
		}

		if ( Align_Language_Manager::is_language_front_page( (int) $post->ID, $post_lang ) ) {
			return home_url( '/' . $slug . '/' );
		}

		$home = rtrim( home_url(), '/' );
		if ( 0 === strpos( $permalink, $home ) ) {
			$path = substr( $permalink, strlen( $home ) );
			if ( '' === $path || '/' !== $path[0] ) {
				$path = '/' . ltrim( $path, '/' );
			}
			if ( $path === '/' . $slug . '/' || 0 === strpos( $path, '/' . $slug . '/' ) ) {
				return $permalink;
			}
			return $home . '/' . $slug . $path;
		}

		return $permalink;
	}

	/**
	 * Prepend language prefix to post permalinks for secondary languages.
	 */
	public static function filter_post_link( $permalink, $post ) {
		if ( ! is_object( $post ) || 'post' !== $post->post_type ) {
			return $permalink;
		}

		$post_lang = get_post_meta( $post->ID, Align_Language_Manager::META_LANG, true );
		if ( empty( $post_lang ) ) return $permalink;

		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		if ( $post_lang === $default_lang ) return $permalink;

		$lang_cfg = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_language( $post_lang ) : null;
		$slug     = ( $lang_cfg && ! empty( $lang_cfg['slug'] ) ) ? $lang_cfg['slug'] : $post_lang;
		if ( empty( $slug ) ) return $permalink;

		$home = home_url();
		if ( 0 === strpos( $permalink, $home ) ) {
			$path = substr( $permalink, strlen( $home ) );
			if ( 0 !== strpos( $path, '/' . $slug . '/' ) ) {
				$permalink = $home . '/' . $slug . $path;
			}
		}

		return $permalink;
	}

	/**
	 * Resolve page or post when viewing a URL with align_lang query var.
	 */
	public static function resolve_post_by_lang_slug( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) return;
		$align_lang = sanitize_key( (string) $query->get( 'align_lang' ) );
		if ( empty( $align_lang ) || ! class_exists( 'Align_Language_Manager' ) ) return;

		// Case 1: Language front page (/vi/)
		if ( $query->get( 'align_lang_home' ) ) {
			$front_trans_id = Align_Language_Manager::get_language_front_page_id( (string) $align_lang );
			if ( $front_trans_id > 0 ) {
				$query->set( 'page_id', $front_trans_id );
				$query->set( 'post_type', 'page' );
				$query->set( 'pagename', '' );
				$query->set( 'name', '' );
				unset( $query->query_vars['pagename'], $query->query_vars['name'] );
				$query->is_page     = true;
				$query->is_singular = true;
				$query->is_home     = false;
				$query->is_archive  = false;
				$query->is_404      = false;
			}
			return;
		}

		// Case 2: Page or Post accessed via /<slug>/<path>
		$raw_path = $query->get( 'pagename' ) ?: $query->get( 'name' );
		if ( empty( $raw_path ) ) return;

		$leaf_slug       = sanitize_title( basename( trim( (string) $raw_path, '/' ) ) );
		$suffix          = '-' . $align_lang;
		$candidate_slugs = array( $leaf_slug );

		if ( substr( $leaf_slug, -strlen( $suffix ) ) !== $suffix ) {
			$candidate_slugs[] = $leaf_slug . $suffix;
		} else {
			$stripped = substr( $leaf_slug, 0, -strlen( $suffix ) );
			if ( '' !== $stripped ) {
				$candidate_slugs[] = $stripped;
			}
		}

		$statuses = ( is_user_logged_in() && current_user_can( 'edit_pages' ) )
			? array( 'publish', 'draft', 'private', 'pending' )
			: array( 'publish' );

		$matches = get_posts( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => $statuses,
			'post_name__in'  => array_values( array_unique( $candidate_slugs ) ),
			'posts_per_page' => 5,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => Align_Language_Manager::META_LANG,
					'value' => $align_lang,
				),
			),
		) );

		if ( ! empty( $matches ) ) {
			$matched = $matches[0];
			foreach ( $matches as $m ) {
				if ( 'page' === $m->post_type && $m->post_name === $leaf_slug ) {
					$matched = $m;
					break;
				}
			}

			if ( 'page' === $matched->post_type ) {
				$query->set( 'page_id', (int) $matched->ID );
				$query->set( 'pagename', '' );
				$query->set( 'name', '' );
				unset( $query->query_vars['pagename'], $query->query_vars['name'] );
				$query->is_page     = true;
				$query->is_singular = true;
				$query->is_home     = false;
				$query->is_404      = false;
			} else {
				$query->set( 'p', (int) $matched->ID );
				$query->set( 'post_type', 'post' );
				$query->set( 'pagename', '' );
				$query->set( 'name', $matched->post_name );
				unset( $query->query_vars['pagename'] );
				$query->is_single   = true;
				$query->is_singular = true;
				$query->is_home     = false;
				$query->is_404      = false;
			}
		}
	}

	/**
	 * Prevent canonical redirect from stripping language prefix or redirecting /<slug>/ to /.
	 */
	public static function filter_redirect_canonical( $redirect_url, $requested_url ) {
		$align_lang = get_query_var( 'align_lang' );
		if ( ! empty( $align_lang ) ) {
			if ( get_query_var( 'align_lang_home' ) ) {
				return false;
			}
			if ( is_singular() && get_queried_object_id() > 0 && ! empty( $redirect_url ) ) {
				$lang_cfg = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_language( $align_lang ) : null;
				$slug     = ( $lang_cfg && ! empty( $lang_cfg['slug'] ) ) ? trim( $lang_cfg['slug'], '/' ) : $align_lang;
				if ( false === strpos( (string) $redirect_url, '/' . $slug . '/' ) ) {
					return false;
				}
			}
		}
		return $redirect_url;
	}
}
