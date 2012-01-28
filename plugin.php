<?php
namespace MultiFeedReader;

$plugin_file = plugin_dir_path( __FILE__ ) . 'multi-feed-reader.php';
register_activation_hook(   $plugin_file, __NAMESPACE__ . '\activate' );
register_deactivation_hook( $plugin_file, __NAMESPACE__ . '\deactivate' );
register_uninstall_hook(    $plugin_file, __NAMESPACE__ . '\uninstall' );

function initialize() {	
	add_shortcode( 'multi-feed-reader', 'MultiFeedReader\shortcode' );
	add_action( 'admin_menu', 'MultiFeedReader\add_menu_entry' );
}
add_action( 'plugins_loaded', 'MultiFeedReader\initialize' );


function activate() {
	MultiFeedReader\Models\Feed::build();
	MultiFeedReader\Models\FeedCollection::build();
}

function deactivate() {

}

function uninstall() {
	MultiFeedReader\Models\Feed::destroy();
	MultiFeedReader\Models\FeedCollection::destroy();
}

function shortcode( $attributes ) {
	extract(
		shortcode_atts(
			array(
				'template' => DEFAULT_TEMPLATE
			),
			$attributes
		)
	);
	
	$cache_key = get_cache_key( $template );
    if ( false === ( $out = get_transient( $cache_key ) ) ) {
        $out = generate_html_by_template( $template );
        set_transient( $cache_key, $out, 60 * 5 ); // 5 minutes
    }

	echo $out;
}

function get_cache_key( $template ) {
    return 'multi_feed_result_for_' . substr( sha1( $template ), 0, 6 );
}

function generate_html_by_template( $template ) {
    $collection = Models\FeedCollection::find_one_by_name( $template );
	$feeds      = $collection->feeds();

	$feed_items = array();
	foreach ( $feeds as $feed ) {
		$parsed = $feed->parse();
		$feed_items = array_merge( $feed_items, $parsed[ 'items' ] );
	}

	// order by publication date
	usort( $feed_items, function ( $a, $b ) {
	    if ( $a[ 'pubDateTime' ] == $b[ 'pubDateTime' ] ) {
	        return 0;
	    }
	    return ( $a[ 'pubDateTime' ] > $b[ 'pubDateTime' ] ) ? -1 : 1;
	} );
	
	$out = $collection->before_template;
	foreach ( $feed_items as $item ) {
		$out .= Parser\parse( $collection->body_template, $item );
	}
	$out .= $collection->after_template;
	
	return $out;
}

function add_menu_entry() {
	add_submenu_page( 'options-general.php', PLUGIN_NAME, PLUGIN_NAME, 'manage_options', \MultiFeedReader\Settings\HANDLE, 'MultiFeedReader\Settings\initialize' );
}