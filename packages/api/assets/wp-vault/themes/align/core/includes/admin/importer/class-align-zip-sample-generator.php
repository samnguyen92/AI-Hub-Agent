<?php
/**
 * Align Theme Core Engine - ZIP Importer Sample Template Generator
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Sample_Generator {

	/**
	 * Generate and stream standard starter ZIP template to browser.
	 */
	public static function generate_and_stream_zip(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive PHP extension is required to generate sample template.', 'align' ), 500 );
		}

		$temp_file = wp_tempnam( 'align-sample-template' ) . '.zip';
		$zip       = new ZipArchive();

		if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Failed to create temporary ZIP archive.', 'align' ), 500 );
		}

		// 1. Global Assets
		$global_css = "/* Align Theme - Global Shared Stylesheet */\n:root {\n  --primary: #0284c7;\n  --primary-hover: #0369a1;\n  --dark: #0f172a;\n  --gray: #64748b;\n  --light: #f8fafc;\n  --font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;\n}\n\n* {\n  box-sizing: border-box;\n  margin: 0;\n  padding: 0;\n}\n\nbody {\n  font-family: var(--font-family);\n  color: #334155;\n  line-height: 1.6;\n  background: #ffffff;\n}\n\n.container {\n  max-width: 1140px;\n  margin: 0 auto;\n  padding: 0 1.5rem;\n}\n";
		$global_js  = "// Align Theme - Global Shared JavaScript\ndocument.addEventListener('DOMContentLoaded', () => {\n  console.log('[Align Theme] Global template assets loaded successfully.');\n});\n";
		$zip->addFromString( 'assets/css/global.css', $global_css );
		$zip->addFromString( 'assets/js/global.js', $global_js );

		// 2. Global Header Part
		$header_html = "<header class=\"site-header\">\n  <div class=\"header-container\">\n    <a href=\"/\" class=\"brand-logo\">Align<span>Starter</span></a>\n    <nav class=\"main-nav\">\n      <ul>\n        <li><a href=\"/\">Home</a></li>\n        <li><a href=\"/about-us/\">About Us</a></li>\n        <li><a href=\"/contact/\">Contact</a></li>\n      </ul>\n    </nav>\n    <a href=\"/contact/\" class=\"btn-header-cta\">Get Started</a>\n  </div>\n</header>\n";
		$header_css  = ".site-header { background: #0f172a; padding: 1.25rem 2rem; border-bottom: 1px solid #1e293b; position: sticky; top: 0; z-index: 100; }\n.header-container { max-width: 1140px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }\n.brand-logo { font-size: 1.35rem; font-weight: 700; color: #ffffff; text-decoration: none; }\n.brand-logo span { color: #38bdf8; }\n.main-nav ul { display: flex; gap: 2rem; list-style: none; }\n.main-nav a { color: #cbd5e1; text-decoration: none; font-weight: 500; transition: color 0.2s ease; }\n.main-nav a:hover { color: #38bdf8; }\n.btn-header-cta { background: #0284c7; color: #ffffff; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; transition: background 0.2s; }\n.btn-header-cta:hover { background: #0369a1; }\n";
		$zip->addFromString( 'header/index.html', $header_html );
		$zip->addFromString( 'header/style.css', $header_css );

		// 3. Global Footer Part
		$footer_html = "<footer class=\"site-footer\">\n  <div class=\"footer-container\">\n    <div class=\"footer-brand\">\n      <h3>Align Starter</h3>\n      <p>Clean code, modular architecture, and instant FSE compatibility.</p>\n    </div>\n    <div class=\"footer-links\">\n      <h4>Navigation</h4>\n      <ul>\n        <li><a href=\"/\">Home</a></li>\n        <li><a href=\"/about-us/\">About Us</a></li>\n        <li><a href=\"/contact/\">Contact</a></li>\n      </ul>\n    </div>\n  </div>\n  <div class=\"footer-bottom\">\n    <p>&copy; " . gmdate( 'Y' ) . " Align Theme. All rights reserved.</p>\n  </div>\n</footer>\n";
		$footer_css  = ".site-footer { background: #0f172a; color: #94a3b8; padding: 4rem 2rem 2rem; border-top: 1px solid #1e293b; margin-top: 4rem; }\n.footer-container { max-width: 1140px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; margin-bottom: 3rem; }\n.footer-brand h3 { color: #ffffff; margin-bottom: 0.75rem; }\n.footer-links h4 { color: #ffffff; margin-bottom: 1rem; }\n.footer-links ul { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }\n.footer-links a { color: #94a3b8; text-decoration: none; }\n.footer-links a:hover { color: #38bdf8; }\n.footer-bottom { border-top: 1px solid #1e293b; padding-top: 2rem; text-align: center; font-size: 14px; }\n";
		$zip->addFromString( 'footer/index.html', $footer_html );
		$zip->addFromString( 'footer/style.css', $footer_css );

		// 4. Reusable Block: CTA Banner
		$cta_html = "<div class=\"cta-banner-card\">\n  <h2>Ready to build fast with Align?</h2>\n  <p>Standardized HTML, CSS, and JS import directly to WordPress FSE.</p>\n  <a href=\"/contact/\" class=\"btn-cta-action\">Get Started Today ↗</a>\n</div>\n";
		$cta_css  = ".cta-banner-card { background: linear-gradient(135deg, #0284c7, #6366f1); color: #ffffff; padding: 3.5rem 2rem; border-radius: 16px; text-align: center; margin: 3rem 0; box-shadow: 0 10px 30px rgba(2, 132, 199, 0.2); }\n.cta-banner-card h2 { font-size: 2rem; font-weight: 700; margin-bottom: 0.75rem; }\n.cta-banner-card p { font-size: 1.15rem; color: #e0f2fe; max-width: 600px; margin: 0 auto 1.75rem; }\n.btn-cta-action { display: inline-block; background: #ffffff; color: #0284c7; padding: 12px 28px; border-radius: 8px; font-weight: 700; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; }\n.btn-cta-action:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }\n";
		$zip->addFromString( 'reuse-blocks/cta-banner/index.html', $cta_html );
		$zip->addFromString( 'reuse-blocks/cta-banner/style.css', $cta_css );

		// 5. Reusable Block: Pricing Table
		$pricing_html = "<div class=\"pricing-grid\">\n  <div class=\"pricing-card\">\n    <h3>Starter</h3>\n    <div class=\"pricing-cost\">$19<span>/mo</span></div>\n    <p>Essential components for rapid prototyping.</p>\n  </div>\n  <div class=\"pricing-card highlighted\">\n    <div class=\"badge-popular\">Most Popular</div>\n    <h3>Professional</h3>\n    <div class=\"pricing-cost\">$49<span>/mo</span></div>\n    <p>Full suite for high performance client websites.</p>\n  </div>\n</div>\n";
		$pricing_css  = ".pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 2rem; margin: 3rem 0; }\n.pricing-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem 2rem; text-align: center; background: #ffffff; position: relative; }\n.pricing-card.highlighted { border-color: #0284c7; box-shadow: 0 8px 30px rgba(2, 132, 199, 0.15); }\n.badge-popular { position: absolute; top: -12px; right: 20px; background: #0284c7; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; }\n.pricing-card h3 { font-size: 1.35rem; color: #0f172a; margin-bottom: 0.5rem; }\n.pricing-cost { font-size: 2.5rem; font-weight: 800; color: #0f172a; margin: 1rem 0; }\n.pricing-cost span { font-size: 1rem; color: #64748b; font-weight: 400; }\n";
		$zip->addFromString( 'reuse-blocks/pricing-table/index.html', $pricing_html );
		$zip->addFromString( 'reuse-blocks/pricing-table/style.css', $pricing_css );

		// 6. Home Page
		$home_html = "<section class=\"hero-section\">\n  <div class=\"container\">\n    <span class=\"hero-badge\">🚀 Next-Gen Web Builder</span>\n    <h1>Build & Import Websites Seamlessly</h1>\n    <p class=\"hero-desc\">Export your clean HTML, CSS, and JS into a structured ZIP, and Align Theme will automatically generate pages, template parts, and reusable blocks.</p>\n    <div class=\"hero-buttons\">\n      <a href=\"/about-us/\" class=\"btn-primary\">Explore Features</a>\n      <a href=\"/contact/\" class=\"btn-outline\">Contact Us</a>\n    </div>\n  </div>\n</section>\n<section class=\"features-section\">\n  <div class=\"container\">\n    <h2 class=\"section-title\">Key Architecture Highlights</h2>\n    <div class=\"features-grid\">\n      <div class=\"feature-box\">\n        <div class=\"feature-icon\">⚡</div>\n        <h3>Zero-Wrapper DOM</h3>\n        <p>Clean HTML structure directly inside Gutenberg custom blocks.</p>\n      </div>\n      <div class=\"feature-box\">\n        <div class=\"feature-icon\">🎨</div>\n        <h3>Scoped CSS & JS</h3>\n        <p>Independent styles per page and block without namespace pollution.</p>\n      </div>\n      <div class=\"feature-box\">\n        <div class=\"feature-icon\">🧩</div>\n        <h3>Reusable Patterns</h3>\n        <p>Create synchronized components with shortcode embedding.</p>\n      </div>\n    </div>\n  </div>\n</section>\n";
		$home_css  = ".hero-section { padding: 6rem 1rem 4rem; text-align: center; background: radial-gradient(circle at center, #f0f9ff 0%, #ffffff 70%); }\n.hero-badge { display: inline-block; background: #e0f2fe; color: #0284c7; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-bottom: 1.5rem; }\n.hero-section h1 { font-size: 3.25rem; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 1.25rem; letter-spacing: -0.02em; }\n.hero-desc { font-size: 1.2rem; color: #475569; max-width: 680px; margin: 0 auto 2.5rem; line-height: 1.7; }\n.hero-buttons { display: flex; justify-content: center; gap: 1rem; }\n.btn-primary { background: #0284c7; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background 0.2s; }\n.btn-primary:hover { background: #0369a1; }\n.btn-outline { border: 1px solid #cbd5e1; color: #334155; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s; }\n.btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }\n.features-section { padding: 5rem 1rem; }\n.section-title { text-align: center; font-size: 2.25rem; font-weight: 700; color: #0f172a; margin-bottom: 3.5rem; }\n.features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }\n.feature-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; text-align: center; transition: transform 0.2s, box-shadow 0.2s; }\n.feature-box:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.06); }\n.feature-icon { font-size: 2.5rem; margin-bottom: 1rem; }\n.feature-box h3 { font-size: 1.25rem; color: #0f172a; margin-bottom: 0.75rem; }\n.feature-box p { color: #64748b; font-size: 15px; }\n";
		$zip->addFromString( 'home/index.html', $home_html );
		$zip->addFromString( 'home/style.css', $home_css );

		// 7. About Us Page
		$about_html = "<section class=\"page-header\">\n  <div class=\"container\">\n    <h1>About Align Theme</h1>\n    <p>Engineering the fastest visual editing workflow for developers.</p>\n  </div>\n</section>\n<section class=\"about-body\">\n  <div class=\"container text-content\">\n    <h2>Our Vision</h2>\n    <p>We bridge the gap between static design code and dynamic WordPress publishing. Build clean HTML templates and let Align handle everything.</p>\n  </div>\n</section>\n";
		$about_css  = ".page-header { background: #f8fafc; padding: 4rem 1rem; text-align: center; border-bottom: 1px solid #e2e8f0; }\n.page-header h1 { font-size: 2.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; }\n.page-header p { color: #64748b; font-size: 1.15rem; }\n.about-body { padding: 4rem 1rem; }\n.text-content { max-width: 800px; margin: 0 auto; }\n.text-content h2 { font-size: 1.75rem; color: #0f172a; margin-bottom: 1rem; }\n.text-content p { color: #475569; font-size: 1.1rem; line-height: 1.8; }\n";
		$zip->addFromString( 'about-us/index.html', $about_html );
		$zip->addFromString( 'about-us/style.css', $about_css );

		// 8. Contact Page
		$contact_html = "<section class=\"page-header\">\n  <div class=\"container\">\n    <h1>Contact Our Team</h1>\n    <p>We would love to hear from you. Send us a message below.</p>\n  </div>\n</section>\n<section class=\"contact-body\">\n  <div class=\"container\">\n    <form class=\"contact-form\" onsubmit=\"return false;\">\n      <div class=\"form-group\">\n        <label>Full Name</label>\n        <input type=\"text\" placeholder=\"John Doe\" required>\n      </div>\n      <div class=\"form-group\">\n        <label>Email Address</label>\n        <input type=\"email\" placeholder=\"john@example.com\" required>\n      </div>\n      <div class=\"form-group\">\n        <label>Message</label>\n        <textarea rows=\"4\" placeholder=\"How can we help?\" required></textarea>\n      </div>\n      <button type=\"submit\" class=\"btn-submit\">Send Message</button>\n    </form>\n  </div>\n</section>\n";
		$contact_css  = ".contact-body { padding: 4rem 1rem; }\n.contact-form { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); }\n.form-group { margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; }\n.form-group label { font-size: 14px; font-weight: 600; color: #1e293b; }\n.form-group input, .form-group textarea { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; font-family: inherit; transition: border-color 0.2s; }\n.form-group input:focus, .form-group textarea:focus { border-color: #0284c7; outline: none; }\n.btn-submit { background: #0284c7; color: #ffffff; padding: 12px; border: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; transition: background 0.2s; }\n.btn-submit:hover { background: #0369a1; }\n";
		$zip->addFromString( 'contact/index.html', $contact_html );
		$zip->addFromString( 'contact/style.css', $contact_css );

		// 9. Hierarchical Services & Sub-page
		$services_html   = "<section class=\"page-header\">\n  <div class=\"container\">\n    <h1>Our Services</h1>\n    <p>Discover our capabilities: <a href=\"web-design/\">Web Design &amp; Architecture</a>.</p>\n  </div>\n</section>\n";
		$web_design_html = "<section class=\"page-header\">\n  <div class=\"container\">\n    <h1>Web Design &amp; Development</h1>\n    <p>Return to <a href=\"../\">Services Overview</a> or contact our <a href=\"../../contact/\">Support Team</a>.</p>\n  </div>\n</section>\n";
		$zip->addFromString( 'services/index.html', $services_html );
		$zip->addFromString( 'services/web-design/index.html', $web_design_html );

		// 10. Blog Archive (Static Page) & Blog Post
		$blog_index_html = "<section class=\"page-header\">\n  <div class=\"container\">\n    <h1>Our Blog</h1>\n    <p>Latest articles and thoughts from the Align team.</p>\n    <article>\n      <h2><a href=\"hello-world.html\">Hello World Post</a></h2>\n      <p>Introductory post created via Align Builder.</p>\n    </article>\n  </div>\n</section>\n";
		$blog_post_html  = "<section class=\"post-body\">\n  <div class=\"container\">\n    <h1>Hello World</h1>\n    <time datetime=\"2026-10-22\">October 22, 2026</time>\n    <p>Welcome to our first blog post created seamlessly with Align Builder.</p>\n    <p><a href=\"../about-us/\">&larr; Back to About Us</a> | <a href=\"./\">&larr; Back to Blog Archive</a></p>\n  </div>\n</section>\n";
		$zip->addFromString( 'blog/index.html', $blog_index_html );
		$zip->addFromString( 'blog/hello-world.html', $blog_post_html );

		// 11. Multilingual Support (Vietnamese mirror structure)
		$vi_home_html     = "<section class=\"hero-section\">\n  <div class=\"container\">\n    <h1>Trang Chủ Tiếng Việt</h1>\n    <p>Nội dung trang chủ tiếng Việt đồng bộ cấu trúc.</p>\n    <a href=\"about-us/\" class=\"btn-primary\">Về Chúng Tôi</a>\n  </div>\n</section>\n";
		$vi_about_html    = "<section class=\"page-header\">\n  <div class=\"container\">\n    <h1>Về Chúng Tôi</h1>\n    <p>Giới thiệu phiên bản tiếng Việt.</p>\n  </div>\n</section>\n";
		$vi_blog_post_htm = "<section class=\"post-body\">\n  <div class=\"container\">\n    <h1>Xin Chào</h1>\n    <time datetime=\"2026-10-22\">22 Tháng 10, 2026</time>\n    <p>Bài viết tiếng Việt đầu tiên nhập từ file ZIP.</p>\n  </div>\n</section>\n";
		$zip->addFromString( 'vi/index.html', $vi_home_html );
		$zip->addFromString( 'vi/about-us/index.html', $vi_about_html );
		$zip->addFromString( 'vi/blog/xin-chao.html', $vi_blog_post_htm );

		$zip->close();

		// Stream to browser
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="align-sample-template.zip"' );
		header( 'Content-Length: ' . filesize( $temp_file ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $temp_file );
		@unlink( $temp_file );
		exit;
	}
}
