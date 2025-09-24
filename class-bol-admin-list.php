<?php
namespace WCBol;

if ( ! defined('ABSPATH') ) exit;

class Admin_List {
    private string $meta_key;
    private string $nonce_key;

    public function __construct( string $meta_key, string $nonce_key ) {
        $this->meta_key  = $meta_key;
        $this->nonce_key = $nonce_key;

        // Columns
        add_filter('manage_edit-product_columns',        [$this, 'add_column'], 20);
        add_action('manage_product_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_action('admin_head-edit.php',                [$this, 'column_css']);

        // Filter dropdown + apply filter
        add_action('restrict_manage_posts',              [$this, 'add_filter_dropdown']);
        add_action('pre_get_posts',                      [$this, 'apply_filter_to_query']);

        // Quick/Bulk UI (saving handled via AJAX/JS)
        add_action('quick_edit_custom_box',              [$this, 'quick_edit_box'], 10, 2);
        add_action('bulk_edit_custom_box',               [$this, 'bulk_edit_box'], 10, 2);

        // Inline JS printed only on the Products list
        add_action('admin_footer-edit.php',              [$this, 'print_inline_js']);
    }

    /* ========== Column ========== */

    public function add_column( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( $key === 'product_tag' ) {
                $new['bol_sync'] = __('Bol', 'wc-bol-sync');
            }
            $new[$key] = $label;
        }
        if ( ! isset($new['bol_sync']) ) {
            $new['bol_sync'] = __('Bol', 'wc-bol-sync');
        }
        return $new;
    }

    public function render_column( $column, $post_id ) {
        if ( $column !== 'bol_sync' ) return;

        $val       = get_post_meta($post_id, $this->meta_key, true);
        $checked   = $val === 'true' ? 'checked' : '';
        $row_nonce = wp_create_nonce( $this->nonce_key . '_' . $post_id );

        printf(
            '<label class="bol-sync-label" data-bol="%s" data-id="%d" data-nonce="%s" title="%s">
                <input type="checkbox" class="bol-sync-toggle" %s />
                <span>%s</span>
            </label>',
            esc_attr( $val === 'true' ? 'true' : 'false' ),
            (int) $post_id,
            esc_attr( $row_nonce ),
            esc_attr__('Toggle Bol Sync for this product', 'wc-bol-sync'),
            $checked,
            esc_html__('Sync', 'wc-bol-sync')
        );
    }

    public function column_css() {
        if ( ($_GET['post_type'] ?? '') !== 'product' ) return;
        echo '<style>
            .column-bol_sync{ width:86px; text-align:center; }
            .column-bol_sync .bol-sync-label{ display:inline-flex; gap:6px; align-items:center; cursor:pointer; }
            .column-bol_sync input[type=checkbox]{ transform:scale(1.1); cursor:pointer; }
        </style>';
    }

    /* ========== Filter dropdown ========== */

    public function add_filter_dropdown( $post_type ) {
        if ( $post_type !== 'product' ) return;

        $current = isset($_GET['bol_sync_filter']) ? sanitize_text_field($_GET['bol_sync_filter']) : '';
        ?>
        <select name="bol_sync_filter" id="bol-sync-filter" class="bol-sync-filter">
            <option value=""><?php esc_html_e('Bol Sync: All', 'wc-bol-sync'); ?></option>
            <option value="true"  <?php selected($current, 'true');  ?>><?php esc_html_e('Bol Sync: Enabled', 'wc-bol-sync'); ?></option>
            <option value="false" <?php selected($current, 'false'); ?>><?php esc_html_e('Bol Sync: Disabled', 'wc-bol-sync'); ?></option>
        </select>
        <?php
    }

    public function apply_filter_to_query( \WP_Query $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( ($query->get('post_type') ?? '') !== 'product' ) return;

        $choice = isset($_GET['bol_sync_filter']) ? sanitize_text_field($_GET['bol_sync_filter']) : '';
        if ( $choice !== 'true' && $choice !== 'false' ) return;

        // Use meta_query to filter by "true"/"false"
        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = [
            'key'   => $this->meta_key,
            'value' => $choice,
            'compare' => '=',
        ];
        $query->set('meta_query', $meta_query);
    }

    /* ========== Quick/Bulk UI shells ========== */

    public function quick_edit_box( $column_name, $post_type ) {
        if ( $post_type !== 'product' || $column_name !== 'bol_sync' ) return;
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label class="alignleft">
                    <input type="checkbox" name="bol_sync" value="true">
                    <span class="checkbox-title"><?php esc_html_e('Bol Sync', 'wc-bol-sync'); ?></span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    public function bulk_edit_box( $column_name, $post_type ) {
        if ( $post_type !== 'product' || $column_name !== 'bol_sync' ) return;
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label class="alignleft">
                    <span class="title"><?php esc_html_e('Bol Sync', 'wc-bol-sync'); ?></span>
                    <select name="bol_sync_bulk">
                        <option value=""><?php esc_html_e('— No change —', 'wc-bol-sync'); ?></option>
                        <option value="true"><?php esc_html_e('Enable', 'wc-bol-sync'); ?></option>
                        <option value="false"><?php esc_html_e('Disable', 'wc-bol-sync'); ?></option>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /* ========== Inline JS printer ========== */

    public function print_inline_js() {
        if ( ($_GET['post_type'] ?? '') !== 'product' ) return;
        require WCBOLSYNC_PATH . 'includes/view-admin-js.php';
    }
}
