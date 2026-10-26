<?php
/**
 * Align Child Theme functions and definitions
 *
 * Note: Theme Core (TemplateAssetsLoader) automatically handles:
 * - General CSS/JS in assets/general/ (including custom.css)
 * - Dynamic Template CSS/JS in assets/template/{template_name}/
 *
 * @package Align Child
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Enqueue child theme main stylesheet.
 */
function align_child_enqueue_assets()
{
	$style_css = get_stylesheet_directory() . '/style.css';
	if (file_exists($style_css)) {
		wp_enqueue_style(
			'align-child-style',
			get_stylesheet_uri(),
			array(),
			filemtime($style_css)
		);
	}
}
// add_action( 'wp_enqueue_scripts', 'align_child_enqueue_assets', 15 );


// Hàm chứa mã JavaScript để đếm số từ (được đẩy xuống cuối trang web để không bị Builder xóa)
function tu_reading_time_js() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        function calculateReadingTime() {
            // Tìm vùng chứa nội dung bài viết
            var articleBody = document.querySelector('.article__body');
            var timeElement = document.getElementById('tu-read-time');
            
            if (articleBody && timeElement) {
                // Lấy toàn bộ chữ, bỏ HTML
                var text = articleBody.innerText || articleBody.textContent;
                
                // Xoá các khoảng trắng thừa, dấu xuống dòng, &nbsp; và tách thành mảng các từ
                text = text.replace(/[\r\n\t\u00A0]/g, " ");
                var words = text.trim().split(/\s+/).filter(function(word) {
                    return word.length > 0;
                });
                
                // Tính số phút (200 từ / phút)
                var wordCount = words.length;
                var readingTime = Math.ceil(wordCount / 200);
                
                // Cập nhật lên màn hình (tối thiểu 1 phút)
                timeElement.innerText = Math.max(1, readingTime);
            }
        }
        
        // Chạy ngay khi DOM sẵn sàng
        calculateReadingTime();
        
        // Chạy dự phòng thêm 1 lần sau 1.5s phòng trường hợp Page Builder render nội dung chậm
        setTimeout(calculateReadingTime, 1500);
    });
    </script>
    <?php
}

function tu_article_hero_shortcode( $atts ) {
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return '';
    }

    // ==========================================
    // 1. FIX TITLE: Xóa &nbsp; và in nghiêng
    // ==========================================
    $title_raw = get_the_title( $post_id );
    
    // Đổi các entity HTML (&nbsp;) thành ký tự thực, sau đó đổi thành khoảng trắng thường
    $title_raw = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
    $title_raw = str_replace( array( "\xC2\xA0", '&nbsp;' ), ' ', $title_raw );
    
    // Tách chuỗi bằng dấu :
    $title_parts = explode( ':', $title_raw, 2 );
    
    if ( count( $title_parts ) > 1 ) {
        $part1 = trim( $title_parts[0] );
        $part2 = trim( $title_parts[1] ); // Đã bị dọn sạch &nbsp;
        $title_html = esc_html( $part1 ) . ': <em>' . esc_html( $part2 ) . '</em>';
    } else {
        $title_html = esc_html( $title_raw );
    }

    // ==========================================
    // 2. DYNAMIC TAG / CATEGORY
    // ==========================================
    $categories = get_the_category( $post_id );
    $tag_name   = ! empty( $categories ) ? esc_html( $categories[0]->name ) : '';

    // ==========================================
    // 3. DYNAMIC EXCERPT
    // ==========================================
    $excerpt = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( get_post_field( 'post_content', $post_id ), 30, '...' );

    // ==========================================
    // 4. DYNAMIC FEATURED IMAGE
    // ==========================================
    $image_html = '';
    if ( has_post_thumbnail( $post_id ) ) {
        $image_id  = get_post_thumbnail_id( $post_id );
        $image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
        $image_alt = ! empty( $image_alt ) ? esc_attr( $image_alt ) : esc_attr( $title_raw );
        $image_src = get_the_post_thumbnail_url( $post_id, 'full' );

        $image_html = sprintf(
            '<div class="band article-lead"><img src="%s" alt="%s" width="1920" height="1000" loading="lazy" decoding="async" class="loaded"></div>',
            esc_url( $image_src ),
            $image_alt
        );
    }

    // Breadcrumbs
    $home_url     = esc_url( home_url( '/' ) );
    $insights_url = esc_url( home_url( '/insights/' ) );

    // Gắn hook JS vào footer chỉ 1 lần duy nhất trên trang
    if ( ! has_action( 'wp_footer', 'tu_reading_time_js' ) ) {
        add_action( 'wp_footer', 'tu_reading_time_js', 99 );
    }

    ob_start();
    ?>
    <section class="section article-hero">
        <div class="wrap">
            <div class="article-hero__in reveal is-in">
                <nav class="crumbs" aria-label="Breadcrumb">
                    <a href="<?php echo $home_url; ?>">Home</a>
                    <span class="crumbs__sep">/</span>
                    <a href="<?php echo $insights_url; ?>">Insights</a>
                    <?php if ( $tag_name ) : ?>
                        <span class="crumbs__sep">/</span>
                        <span aria-current="page"><?php echo $tag_name; ?></span>
                    <?php endif; ?>
                </nav>
                
                <?php if ( $tag_name ) : ?>
                    <p class="eyebrow article-hero__tag"><?php echo $tag_name; ?></p>
                <?php endif; ?>

                <h1 class="h1 article-hero__h1"><?php echo $title_html; ?></h1>

                <?php if ( ! empty( $excerpt ) ) : ?>
                    <p class="lede article-hero__lede"><?php echo esc_html( $excerpt ); ?></p>
                <?php endif; ?>

                <!-- Bọc số phút trong span ID để JS đếm thực tế -->
                <p class="article-meta">Trusted Union · <span id="tu-read-time">1</span> min read</p>
            </div>
        </div>
    </section>

    <?php echo $image_html; ?>
    
    <?php
    return ob_get_clean();
}

// Xóa shortcode cũ (nếu có) và đăng ký lại
remove_shortcode( 'article_hero' );
add_shortcode( 'article_hero', 'tu_article_hero_shortcode' );

// Tạo shortcode: [tu_insights_grid] với bộ lọc ngôn ngữ tự động (EN / ZH / All)
function tu_dynamic_insights_shortcode( $atts ) {
    // 1. Tự động phát hiện ngôn ngữ hiện tại
    $current_lang = "";
    if ( class_exists( "Align_Template_Language" ) ) {
        $current_lang = Align_Template_Language::get_current_lang();
    }
    if ( empty( $current_lang ) ) {
        $current_post_id = get_the_ID();
        if ( $current_post_id ) {
            $current_lang = get_post_meta( $current_post_id, "_align_lang", true );
        }
    }
    if ( empty( $current_lang ) && isset( $_SERVER["REQUEST_URI"] ) ) {
        $current_lang = ( strpos( $_SERVER["REQUEST_URI"], "/zh/" ) !== false || strpos( $_SERVER["REQUEST_URI"], "/zh" ) === 0 ) ? "zh" : "en";
    }
    if ( empty( $current_lang ) ) {
        $current_lang = "en";
    }

    $atts = shortcode_atts( array(
        "posts_per_page" => -1,
        "category"       => "",
        "post_type"      => "post",
        "lang"           => $current_lang,
    ), $atts, "tu_insights_grid" );

    $lang_filter = strtolower( trim( $atts["lang"] ) );
    $is_zh       = ( "zh" === $lang_filter );

    $args = array(
        "post_type"      => $atts["post_type"],
        "posts_per_page" => (int) $atts["posts_per_page"],
        "post_status"    => "publish",
        "orderby"        => "date",
        "order"          => "DESC",
    );

    if ( ! empty( $atts["category"] ) ) {
        $args["category_name"] = $atts["category"];
    }

    // 2. Bộ lọc ngôn ngữ qua meta_query
    if ( "zh" === $lang_filter ) {
        $args["meta_query"] = array(
            array(
                "key"   => "_align_lang",
                "value" => "zh",
            ),
        );
    } elseif ( "all" !== $lang_filter ) {
        $args["meta_query"] = array(
            "relation" => "OR",
            array(
                "key"     => "_align_lang",
                "compare" => "NOT EXISTS",
            ),
            array(
                "key"   => "_align_lang",
                "value" => "en",
            ),
            array(
                "key"   => "_align_lang",
                "value" => "",
            ),
        );
    }

    $query = new WP_Query( $args );

    ob_start();

    if ( $query->have_posts() ) :
        ?>
        <div class="tiles tiles--3 reveal is-in" style="margin-top:24px">
            <?php while ( $query->have_posts() ) : $query->the_post(); 
                
                $title = get_the_title();
                $link = get_permalink();
                
                $excerpt = get_the_excerpt();
                if ( empty( $excerpt ) ) {
                    $excerpt = wp_trim_words( wp_strip_all_tags( get_the_content() ), 20, "..." );
                }

                $categories = get_the_category();
                $category_name = ! empty( $categories ) ? esc_html( $categories[0]->name ) : ( $is_zh ? "见解" : "Insight" );

                if ( has_post_thumbnail() ) {
                    $thumb_url = get_the_post_thumbnail_url( get_the_ID(), "large" );
                    $thumb_alt = get_post_meta( get_post_thumbnail_id(), "_wp_attachment_image_alt", true );
                } else {
                    $thumb_url = "/wp-content/uploads/placeholder.webp"; 
                    $thumb_alt = $title;
                }
            ?>
                
            <article class="tile tile--media">
                <a class="tile__img" href="<?php echo esc_url( $link ); ?>"><img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $thumb_alt ); ?>" loading="lazy" decoding="async" class="loaded"></a>
                <div class="tile__in">
                    <p class="tile__tag"><?php echo $category_name; ?></p>
                    <h3><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a></h3>
                    <p><?php echo esc_html( $excerpt ); ?></p>
                    <div class="tile__foot"><a class="link link--sm" href="<?php echo esc_url( $link ); ?>"><?php echo $is_zh ? "阅读见解" : "Read the insight"; ?> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></a></div>
                </div>
            </article>

            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
    else :
        echo "<p>" . ( $is_zh ? "暂无见解文章。" : "No insights found." ) . "</p>";
    endif;

    return ob_get_clean();
}
add_shortcode( "tu_insights_grid", "tu_dynamic_insights_shortcode" );

// Đăng ký shortcode [related_insights]
function custom_related_insights_shortcode($atts) {
    global $post;

    $current_post_id = ( $post && isset( $post->ID ) ) ? $post->ID : get_the_ID();
    
    // Auto-resolve a sample post if in FSE template preview or invalid post context
    if ( ! $current_post_id || 'post' !== get_post_type( $current_post_id ) ) {
        $lang = class_exists( 'Align_Template_Language' ) ? Align_Template_Language::get_current_lang() : 'en';
        if ( $current_post_id && get_post_meta( $current_post_id, '_align_lang', true ) === 'zh' ) {
            $lang = 'zh';
        }
        $sample_args = array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1 );
        if ( 'zh' === $lang ) {
            $sample_args['meta_query'] = array( array( 'key' => '_align_lang', 'value' => 'zh' ) );
        } else {
            $sample_args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_align_lang', 'value' => 'en' ),
                array( 'key' => '_align_lang', 'value' => '' ),
            );
        }
        $sample_posts = get_posts( $sample_args );
        if ( ! empty( $sample_posts ) ) {
            $current_post_id = $sample_posts[0]->ID;
        }
    }

    $post_lang = $current_post_id ? ( get_post_meta( $current_post_id, '_align_lang', true ) ?: 'en' ) : 'en';
    $is_zh     = ( 'zh' === $post_lang );

    $atts = shortcode_atts(
        array(
            'posts_per_page' => 3,
            'label'          => $is_zh ? '更多见解' : 'More Insights',
            'title'          => $is_zh ? '继续阅读。' : 'Continue reading.',
        ), 
        $atts, 
        'related_insights'
    );

    $category_ids = $current_post_id ? wp_get_post_categories($current_post_id) : array();

    $args = array(
        'post_type'           => 'post',
        'category__in'        => $category_ids,
        'post__not_in'        => $current_post_id ? array($current_post_id) : array(),
        'posts_per_page'      => $atts['posts_per_page'],
        'ignore_sticky_posts' => 1,
        'post_status'         => 'publish'
    );

    if ( $is_zh ) {
        $args['meta_query'] = array( array( 'key' => '_align_lang', 'value' => 'zh' ) );
    } else {
        $args['meta_query'] = array(
            'relation' => 'OR',
            array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ),
            array( 'key' => '_align_lang', 'value' => 'en' ),
            array( 'key' => '_align_lang', 'value' => '' ),
        );
    }

    if ( empty($category_ids) ) {
        unset($args['category__in']);
    }

    $related_query = new WP_Query($args);

    if ( ! $related_query->have_posts() ) {
        unset($args['category__in']);
        $related_query = new WP_Query($args);
        if ( ! $related_query->have_posts() ) {
            return '';
        }
    }

    ob_start();
    ?>

    <section class="section related-insights">
        <div class="wrap">
            <div class="lead reveal is-in">
                <p class="label"><?php echo esc_html($atts['label']); ?></p>
                <h2 class="h2"><?php echo esc_html($atts['title']); ?></h2>
            </div>
            
            <div class="tiles tiles--3 reveal is-in" style="margin-top:24px">
                <?php while ($related_query->have_posts()) : $related_query->the_post(); ?>
                    <?php
                        $categories = get_the_category();
                        $category_name = !empty($categories) ? esc_html($categories[0]->name) : ( $is_zh ? '文章' : 'Article' );
                    ?>
                    
                    <article class="tile tile--media">
                        <a class="tile__img" href="<?php the_permalink(); ?>">
                            <?php 
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('large', array(
                                    'loading' => 'lazy',
                                    'alt'     => get_the_title()
                                ));
                            } else {
                                echo '<img src="' . esc_url(site_url('/wp-content/uploads/default-image.jpg')) . '" alt="' . esc_attr(get_the_title()) . '" width="900" height="678" loading="lazy">';
                            }
                            ?>
                        </a>
                        <div class="tile__in">
                            <p class="tile__tag"><?php echo $category_name; ?></p>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p><?php echo wp_trim_words(get_the_excerpt(), 15, '…'); ?></p>
                            <div class="tile__foot">
                                <a class="link link--sm" href="<?php the_permalink(); ?>"><?php echo $is_zh ? '阅读见解' : 'Read the insight'; ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>

                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('related_insights', 'custom_related_insights_shortcode');

