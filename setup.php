<?php
/**
 * Builds the Navi Shop WATCHes store inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Product photos are read from /wordpress/ns-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Fast demo build: the photos are already web-sized, so skip making thumbnails of each one.
// (Real hosting can regenerate thumbnails later.)
if ( defined( 'NS_FAST' ) && NS_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

// Store settings.
foreach ( [
	'blogname'                               => 'Navi Shop WATCHes',
	'blogdescription'                        => 'Curren, Naviforce and Mini Focus watches in Nairobi. Cash on delivery.',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'yes',
	'woocommerce_notify_low_stock_amount'    => '1',
	'woocommerce_notify_no_stock_amount'     => '0',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => '',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_enable_coupons'             => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Categories (menu order = order in the header and on the home page).
$cat = [];
$i   = 0;
foreach ( [
	'mens-watches'   => [ 'Men\'s Watches', 'Steel, leather and mesh watches for men: chronographs, dual displays and everyday dress pieces.' ],
	'womens-watches' => [ 'Women\'s Watches', 'Slim bracelets, mesh straps and sparkling dials for every occasion.' ],
	'chronographs'   => [ 'Chronographs', 'Stopwatch sub-dials and tachymeter bezels, on steel or leather.' ],
	'dual-display'   => [ 'Digital & Analogue', 'Hands and a digital screen in one: alarm, stopwatch, day and date.' ],
	'automatic'      => [ 'Automatic & Skeleton', 'Self-winding mechanical movements you can see at work through the dial. No battery needed.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Strap materials, used by the strap filter pills.
$tag = [];
foreach ( [ 'steel' => [ 'stainless-steel', 'Stainless Steel' ], 'leather' => [ 'leather', 'Leather' ], 'mesh' => [ 'mesh', 'Mesh' ], 'rubber' => [ 'rubber', 'Rubber' ] ] as $key => [ $slug, $name ] ) {
	$t = term_exists( $slug, 'product_tag' ) ?: wp_insert_term( $name, 'product_tag', [ 'slug' => $slug ] );
	$tag[ $key ] = (int) $t['term_id'];
}

// Delivery. PLACEHOLDER prices, to confirm with the client.
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Nairobi' );
$zone->add_location( 'KE:KE30', 'state' );
$zone->save();
$id = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$id}_settings", [ 'title' => 'Free Nairobi delivery', 'requires' => 'min_amount', 'min_amount' => '5000' ] );
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Same-day Nairobi delivery', 'cost' => '250', 'tax_status' => 'none' ] );

$rest = new WC_Shipping_Zone();
$rest->set_zone_name( 'Rest of Kenya' );
$rest->add_location( 'KE', 'country' );
$rest->save();
$id = $rest->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Countrywide courier (1–2 days)', 'cost' => '400', 'tax_status' => 'none' ] );

// Payment: cash on delivery, as on the client's WhatsApp profile.
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Cash on delivery',
	'description'        => 'Check the watch when it arrives, then pay. We will call to confirm your order and a delivery time.',
	'instructions'       => 'We will call you to confirm your order and arrange delivery.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );

// Products: the client's WhatsApp catalogue ("navyspotwatches", +254 741 653165), at the prices listed there.
// Brand-copy listings in that catalogue (RM, Rado, Versace, Rolex, TAG Heuer, Patek Philippe) are left out on purpose.
// Photos are <slug>.webp, <slug>-2.webp…; each option's number is the photo that shows it.
// Spec lines are only what the listing or its photos state.
$products = [
	[
		'slug' => 'curren-chronograph-steel', 'name' => 'Curren Chronograph Steel Watch', 'brand' => 'Curren', 'cats' => [ 'mens-watches', 'chronographs' ], 'strap' => [ 'steel' ], 'price' => 3499,
		'desc' => 'A big, bold 48 mm chronograph with three sub-dials, a tachymeter bezel and a date window, on a solid stainless steel bracelet.',
		'attr' => 'Colour', 'options' => [ 'Blue' => 1, 'Black' => 4, 'White & rose gold' => 3 ],
		'specs' => [ 'Model' => 'Curren M8363', 'Movement' => 'Quartz chronograph', 'Case' => 'Alloy, 48 mm, 14 mm thick', 'Strap' => 'Stainless steel, 24 mm wide', 'Glass' => 'Mineral glass', 'Water resistance' => '3 ATM (splashes and rain)', 'Weight' => '172 g' ],
	],
	[
		'slug' => 'curren-ladies-bracelet-watch', 'name' => 'Curren Ladies Bracelet Watch', 'brand' => 'Curren', 'cats' => [ 'womens-watches' ], 'strap' => [ 'steel' ], 'price' => 2999,
		'desc' => 'A sparkling crystal-set dial with a date window, on a slim stainless steel bracelet.',
		'attr' => 'Colour', 'options' => [ 'Silver & blue' => 1, 'Gold & champagne' => 4 ],
		'specs' => [ 'Movement' => 'Quartz', 'Dial' => 'Crystal markers, date', 'Strap' => 'Stainless steel' ],
	],
	[
		'slug' => 'naviforce-dual-display-steel', 'name' => 'Naviforce Dual-Display Steel Watch', 'brand' => 'Naviforce', 'cats' => [ 'mens-watches', 'dual-display' ], 'strap' => [ 'steel' ], 'price' => 3499,
		'desc' => 'Analogue hands over two digital screens, with a tachymeter bezel and a two-tone stainless steel bracelet. Hardened mineral glass.',
		'attr' => 'Colour', 'options' => [ 'Blue & silver' => 1, 'Coffee & silver' => 6 ],
		'specs' => [ 'Display' => 'Analogue and digital', 'Movement' => 'Quartz', 'Strap' => 'Stainless steel, two-tone', 'Glass' => 'Hardened mineral glass', 'Water resistance' => '3 ATM' ],
	],
	[
		'slug' => 'mini-focus-ladies-watch', 'name' => 'Mini Focus Ladies Watch', 'brand' => 'Mini Focus', 'cats' => [ 'womens-watches' ], 'strap' => [ 'steel' ], 'price' => 2900,
		'desc' => 'A clean dial with a small seconds sub-dial, on a steel bracelet.',
		'attr' => 'Colour', 'options' => [ 'Silver' => 1, 'Black & rose gold' => 2, 'Rose gold' => 4, 'Gold' => 5 ],
		'specs' => [ 'Movement' => 'Quartz', 'Dial' => 'Small seconds sub-dial', 'Strap' => 'Stainless steel' ],
	],
	[
		'slug' => 'forsining-automatic-skeleton', 'name' => 'Forsining Automatic Skeleton Watch', 'brand' => 'Forsining', 'cats' => [ 'mens-watches', 'automatic' ], 'strap' => [ 'leather' ], 'price' => 6500, 'sale' => 6000,
		'desc' => 'A self-winding automatic with an open skeleton dial, so you can see the movement at work. It winds as you wear it: no battery needed.',
		'attr' => 'Colour', 'options' => [ 'Silver, black strap' => 1, 'Silver, brown strap' => 2, 'Gold, black strap' => 3, 'Black, black strap' => 4 ],
		'specs' => [ 'Movement' => 'Automatic (self-winding)', 'Dial' => 'Skeleton, see-through', 'Strap' => 'Leather' ],
	],
	[
		'slug' => 'curren-ladies-slim-bracelet', 'name' => 'Curren Ladies Slim Bracelet Watch', 'brand' => 'Curren', 'cats' => [ 'womens-watches' ], 'strap' => [ 'steel' ], 'price' => 2499,
		'desc' => 'A minimalist dial on a fine link bracelet with a crystal-set band. Light, elegant and easy to wear every day.',
		'attr' => 'Colour', 'options' => [ 'Gold & white' => 1, 'Silver' => 2, 'Black & rose gold' => 4 ],
		'specs' => [ 'Movement' => 'Quartz', 'Strap' => 'Stainless steel with crystals' ],
	],
	[
		'slug' => 'naviforce-dual-display-mesh', 'name' => 'Naviforce Sports Mesh Watch', 'brand' => 'Naviforce', 'cats' => [ 'mens-watches', 'dual-display' ], 'strap' => [ 'mesh' ], 'price' => 3499,
		'desc' => 'Sports-style dual display on a steel mesh strap: hands plus a digital screen for alarm, stopwatch, day and date.',
		'attr' => 'Colour', 'options' => [ 'Coffee' => 1, 'Black' => 8, 'Gold' => 4, 'Silver' => 9 ],
		'specs' => [ 'Display' => 'Analogue and digital', 'Functions' => 'Alarm, stopwatch, day and date', 'Movement' => 'Quartz', 'Strap' => 'Stainless steel mesh', 'Water resistance' => 'Water resistant' ],
	],
	[
		'slug' => 'naviforce-ladies-mesh-watch', 'name' => 'Naviforce Ladies Mesh Watch', 'brand' => 'Naviforce', 'cats' => [ 'womens-watches' ], 'strap' => [ 'mesh' ], 'price' => 3500,
		'desc' => 'Roman numerals and a fine steel mesh strap. Stainless steel, water resistant.',
		'attr' => 'Colour', 'options' => [ 'Green & rose gold' => 1, 'Silver' => 2 ],
		'specs' => [ 'Movement' => 'Quartz', 'Strap' => 'Stainless steel mesh', 'Water resistance' => 'Water resistant' ],
	],
	[
		'slug' => 'curren-leather-chronograph', 'name' => 'Curren Leather Chronograph', 'brand' => 'Curren', 'cats' => [ 'mens-watches', 'chronographs' ], 'strap' => [ 'leather' ], 'price' => 3499,
		'desc' => 'A cushion-shaped chronograph with big numerals and a stitched leather strap. Sub-dials for the stopwatch, 24-hour time and date.',
		'attr' => 'Colour', 'options' => [ 'Green' => 1, 'Blue & grey' => 3, 'Black & tan' => 4 ],
		'specs' => [ 'Movement' => 'Quartz chronograph', 'Functions' => 'Stopwatch, 24-hour dial, date', 'Strap' => 'Leather' ],
	],
	[
		'slug' => 'curren-ladies-floral-dial', 'name' => 'Curren Ladies Floral-Dial Watch', 'brand' => 'Curren', 'cats' => [ 'womens-watches' ], 'strap' => [ 'steel' ], 'price' => 2499,
		'desc' => 'A soft dial with a raised flower pattern, on a slim steel bracelet.',
		'attr' => 'Colour', 'options' => [ 'Ice blue & silver' => 1, 'Rose gold' => 2 ],
		'specs' => [ 'Movement' => 'Quartz', 'Dial' => 'Raised floral pattern', 'Strap' => 'Stainless steel' ],
	],
	[
		'slug' => 'naviforce-leather-field-watch', 'name' => 'Naviforce Leather Field Watch', 'brand' => 'Naviforce', 'cats' => [ 'mens-watches' ], 'strap' => [ 'leather' ], 'price' => 3499,
		'desc' => 'A clean, military-style field watch with luminous hands, a date window and a thick leather strap.',
		'attr' => 'Colour', 'options' => [ 'Green' => 1, 'Black' => 3, 'Brown' => 7 ],
		'specs' => [ 'Movement' => 'Quartz', 'Functions' => 'Date', 'Strap' => 'Leather' ],
	],
	[
		'slug' => 'curren-ladies-classic', 'name' => 'Curren Classic Ladies Watch', 'brand' => 'Curren', 'cats' => [ 'womens-watches' ], 'strap' => [ 'steel' ], 'price' => 2500,
		'desc' => 'Classy watch for ladies, with a crystal-set bezel and a slim bracelet. Fade free and water resistant.',
		'attr' => 'Colour', 'options' => [ 'Blue' => 1, 'Rose gold & white' => 2, 'Gold' => 3 ],
		'specs' => [ 'Movement' => 'Quartz', 'Strap' => 'Stainless steel', 'Finish' => 'Fade free', 'Water resistance' => 'Water resistant' ],
	],
	[
		'slug' => 'naviforce-leather-dual-display', 'name' => 'Naviforce Leather Dual-Display Watch', 'brand' => 'Naviforce', 'cats' => [ 'mens-watches', 'dual-display' ], 'strap' => [ 'leather' ], 'price' => 3500,
		'desc' => 'Digital and analogue display in a black case, on a leather strap.',
		'attr' => 'Colour', 'options' => [ 'Brown & rose gold' => 1, 'Black' => 2 ],
		'specs' => [ 'Display' => 'Analogue and digital', 'Movement' => 'Quartz', 'Strap' => 'Leather' ],
	],
	[
		'slug' => 'naviforce-ladies-silver-mesh', 'name' => 'Naviforce Ladies Silver Mesh Watch', 'brand' => 'Naviforce', 'cats' => [ 'womens-watches' ], 'strap' => [ 'mesh' ], 'price' => 3000,
		'desc' => 'A crisp white dial with a date window in a silver case, on a matching steel mesh strap.',
		'specs' => [ 'Movement' => 'Quartz', 'Dial' => 'White, date', 'Strap' => 'Stainless steel mesh' ],
	],
	[
		'slug' => 'curren-two-tone-green', 'name' => 'Curren Two-Tone Watch, Green Dial', 'brand' => 'Curren', 'cats' => [ 'mens-watches' ], 'strap' => [ 'steel' ], 'price' => 3500,
		'desc' => 'A green sunburst dial in a silver and gold two-tone case and bracelet. Analogue display, water resistant, fade free.',
		'specs' => [ 'Display' => 'Analogue', 'Movement' => 'Quartz', 'Strap' => 'Stainless steel, silver and gold', 'Finish' => 'Fade free', 'Water resistance' => 'Water resistant' ],
	],
	[
		'slug' => 'naviforce-mens-watches', 'name' => 'Naviforce Men\'s Watches – 9 Designs', 'brand' => 'Naviforce', 'cats' => [ 'mens-watches', 'dual-display' ], 'strap' => [ 'leather', 'steel' ], 'price' => 3200,
		'desc' => 'Nine Naviforce designs at one price, most with digital and analogue display. Pick the one you like.',
		'attr' => 'Design', 'options' => [ 'Black steel, red hands' => 1, 'Brown leather' => 2, 'Black leather, compass bezel' => 3, 'Dusty pink leather' => 4, 'Black leather' => 5, 'Grey, black strap' => 6, 'Silver steel' => 7, 'Black, big numerals' => 8, 'Cream dial, brown leather' => 9 ],
		'specs' => [ 'Display' => 'Analogue and digital (most designs)', 'Movement' => 'Quartz', 'Strap' => 'Leather or steel, by design' ],
	],
	[
		'slug' => 'curren-mens-watches', 'name' => 'Curren Men\'s Watches – 10 Designs', 'brand' => 'Curren', 'cats' => [ 'mens-watches' ], 'strap' => [ 'leather', 'steel', 'rubber' ], 'price' => 2500,
		'desc' => 'Curren genuine classy watches: ten designs at one price, from dress to sport. Pick the one you like.',
		'attr' => 'Design', 'options' => [ 'Silver, tan leather' => 1, 'Grey leather' => 2, 'Black & rose gold, tan leather' => 3, 'Blue & rose gold, steel' => 4, 'Black & silver, black leather' => 5, 'Black, tan leather, big numerals' => 6, 'Black & gold, rubber' => 7, 'Gold & black, black leather' => 8, 'Gold & white, brown leather' => 9, 'Rose gold & navy, leather' => 10 ],
		'specs' => [ 'Display' => 'Analogue with date', 'Movement' => 'Quartz', 'Strap' => 'Leather, steel or rubber, by design' ],
	],
];

// PLACEHOLDER stock: 3 of each colour or design, 5 of single-colour watches. Staff set the real numbers.
const NS_STOCK_EACH   = 3;
const NS_STOCK_SIMPLE = 5;

function ns_attach_images( $pid, $slug, $name ) {
	$num   = fn( $f ) => preg_match( '#^' . preg_quote( $slug, '#' ) . '(?:-(\d+))?\.webp$#', basename( $f ), $m ) ? (int) ( $m[1] ?? 1 ) : 0;
	$files = array_values( array_filter( glob( "/wordpress/ns-images/$slug*.webp" ) ?: [], fn( $f ) => $num( $f ) > 0 ) );
	usort( $files, fn( $a, $b ) => $num( $a ) <=> $num( $b ) );
	$ids = [];
	foreach ( $files as $file ) {
		$base = basename( $file );
		$tmp  = wp_tempnam( $base );
		copy( $file, $tmp );
		$n   = $num( $file );
		$att = media_handle_sideload( [ 'name' => $base, 'tmp_name' => $tmp ], $pid, $name . ( $n > 1 ? ' – photo ' . $n : '' ) );
		if ( ! is_wp_error( $att ) ) $ids[ $n ] = $att;
	}
	return $ids; // photo number => attachment id
}

foreach ( $products as $order => $d ) {
	$variable = ! empty( $d['options'] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_description( $d['desc'] );
	$p->set_short_description( $d['desc'] );
	$p->set_category_ids( array_map( fn( $c ) => $cat[ $c ], $d['cats'] ) );
	$p->set_tag_ids( array_map( fn( $s ) => $tag[ $s ], $d['strap'] ) );
	$p->set_featured( true );
	$p->update_meta_data( '_ns_specs', implode( "\n", array_map( fn( $k, $v ) => "$k: $v", array_keys( $d['specs'] ), $d['specs'] ) ) );
	if ( $variable ) {
		$a = new WC_Product_Attribute();
		$a->set_name( $d['attr'] );
		$a->set_options( array_keys( $d['options'] ) );
		$a->set_visible( true );
		$a->set_variation( true );
		$p->set_attributes( [ $a ] );
		$p->set_default_attributes( [ sanitize_title( $d['attr'] ) => array_key_first( $d['options'] ) ] );
	} else {
		$p->set_regular_price( $d['price'] );
		if ( ! empty( $d['sale'] ) ) $p->set_sale_price( $d['sale'] );
		$p->set_manage_stock( true );
		$p->set_stock_quantity( NS_STOCK_SIMPLE );
	}
	$pid = $p->save();

	if ( $d['brand'] && taxonomy_exists( 'product_brand' ) ) {
		wp_set_object_terms( $pid, $d['brand'], 'product_brand' );
	}

	$imgs = ns_attach_images( $pid, $d['slug'], $d['name'] );
	if ( $imgs ) {
		$p = wc_get_product( $pid );
		$p->set_image_id( $imgs[1] ?? reset( $imgs ) );
		$p->set_gallery_image_ids( array_values( array_diff_key( $imgs, [ 1 => true ] ) ) );
		$p->save();
	}

	if ( $variable ) {
		$key = sanitize_title( $d['attr'] );
		$i   = 0;
		foreach ( $d['options'] as $label => $photo ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $pid );
			$v->set_attributes( [ $key => $label ] );
			$v->set_regular_price( $d['price'] );
			if ( ! empty( $d['sale'] ) ) $v->set_sale_price( $d['sale'] );
			$v->set_manage_stock( true );
			$v->set_stock_quantity( NS_STOCK_EACH );
			$v->set_menu_order( $i++ );
			if ( isset( $imgs[ $photo ] ) ) $v->set_image_id( $imgs[ $photo ] );
			$v->save();
		}
		WC_Product_Variable::sync( $pid );
	}
}

// Pages.
function ns_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
ns_page( 'wishlist', 'Your wishlist', "<!-- wp:shortcode -->\n[ns_wishlist]\n<!-- /wp:shortcode -->" );
$shop_page = (int) wc_get_page_id( 'shop' );
if ( $shop_page > 0 ) wp_update_post( [ 'ID' => $shop_page, 'post_title' => 'All watches' ] );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );
