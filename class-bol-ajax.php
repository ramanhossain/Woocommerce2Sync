<?php
namespace WCBol;

if ( ! defined('ABSPATH') ) exit;

class Ajax {
    private string $meta_key;
    private string $nonce_key;

    public function __construct( string $meta_key, string $nonce_key ) {
        $this->meta_key  = $meta_key;
        $this->nonce_key = $nonce_key;

        add_action('wp_ajax_bol_sync_toggle',      [$this, 'toggle']);
        add_action('wp_ajax_bol_sync_bulk_toggle', [$this, 'bulk_toggle']);
    }

    public function toggle() {
        $post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $row_nonce = $_POST['_ajax_nonce'] ?? '';

        if ( ! $post_id || ! wp_verify_nonce($row_nonce, $this->nonce_key . '_' . $post_id) ) {
            wp_send_json_error(['message' => 'Bad nonce'], 403);
        }
        if ( ! current_user_can('edit_post', $post_id) ) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        $value = ( $_POST['value'] ?? 'false' ) === 'true' ? 'true' : 'false';
        update_post_meta( $post_id, $this->meta_key, $value );

        wp_send_json_success(['post_id' => $post_id, 'value' => $value]);
    }

    public function bulk_toggle() {
        $ids       = array_map('absint', (array) ($_POST['ids'] ?? []));
        $nonce_map = (array) ($_POST['nonceMap'] ?? []);
        $value     = ( $_POST['value'] ?? 'false' ) === 'true' ? 'true' : 'false';

        if ( empty($ids) ) wp_send_json_error(['message' => 'No IDs'], 400);

        foreach ( $ids as $post_id ) {
            $n = $nonce_map[$post_id] ?? '';
            if ( ! $n || ! wp_verify_nonce($n, $this->nonce_key . '_' . $post_id) ) continue;
            if ( current_user_can('edit_post', $post_id) ) {
                update_post_meta( $post_id, $this->meta_key, $value );
            }
        }
        wp_send_json_success(['ids' => $ids, 'value' => $value]);
    }
}
