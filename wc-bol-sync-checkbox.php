<?php
/**
 * Plugin Name: WooCommerce Bol Sync Checkbox
 * Description: Adds a Bol Sync checkbox (list/quick/bulk) with instant save + an admin filter for Bol Sync status.
 * Version:     1.4.0
 * Author:      Saru Bureau
 * License:     GPL-2.0+
 */

if ( ! defined('ABSPATH') ) exit;

define( 'WCBOLSYNC_PATH', plugin_dir_path(__FILE__) );
define( 'WCBOLSYNC_URL',  plugin_dir_url(__FILE__) );
define( 'WCBOLSYNC_VER',  '1.4.0' );

final class WC_Bol_Sync {
    const META_KEY  = '_bol_sync';
    const NONCE_KEY = 'bol_sync_toggle';

    public function __construct() {
        add_action('init', [$this, 'register_meta']);

        // Load admin parts only in wp-admin
        if ( is_admin() ) {
            require_once WCBOLSYNC_PATH . 'includes/class-bol-admin-list.php';
            require_once WCBOLSYNC_PATH . 'includes/class-bol-ajax.php';

            // Init classes and pass constants
            new WCBol\Admin_List( self::META_KEY, self::NONCE_KEY );
            new WCBol\Ajax( self::META_KEY, self::NONCE_KEY );
        }
    }

    public function register_meta() {
        register_post_meta('product', self::META_KEY, [
            'type'              => 'string', // 'true' or 'false'
            'single'            => true,
            'default'           => 'false',
            'sanitize_callback' => function($v){ return $v === 'true' ? 'true' : 'false'; },
            'auth_callback'     => function( $allowed, $meta_key, $post_id ){
                return current_user_can('edit_post', $post_id);
            },
            'show_in_rest'      => false,
        ]);
    }
}
new WC_Bol_Sync();
