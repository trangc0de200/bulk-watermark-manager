<?php
/**
 * Plugin Name: Bulk Watermark Manager
 * Plugin URI:  https://github.com/
 * Description: Batch watermark images in your Media Library. Select images, choose a watermark, preview, then apply.
 * Version:     1.0.0
 * Author:      Trang Dev
 * Text Domain: bulk-watermark-manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BWM_VERSION',  '1.0.0' );
define( 'BWM_DIR',      plugin_dir_path( __FILE__ ) );
define( 'BWM_URL',      plugin_dir_url( __FILE__ ) );

/* ─── Admin menu ────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_media_page(
        'Bulk Watermark Manager',
        'Watermark Manager',
        'upload_files',
        'bulk-watermark-manager',
        'bwm_render_page'
    );
} );

/* ─── Enqueue assets ────────────────────────────────────── */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'media_page_bulk-watermark-manager' ) return;

    wp_enqueue_media();

    wp_enqueue_style(
        'bwm-style',
        BWM_URL . 'assets/style.css',
        [],
        BWM_VERSION
    );

    wp_enqueue_script(
        'bwm-script',
        BWM_URL . 'assets/script.js',
        [ 'jquery' ],
        BWM_VERSION,
        true
    );

    wp_localize_script( 'bwm-script', 'BWM', [
        'ajaxurl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'bwm_nonce' ),
        'mediaTitle'=> 'Select Watermark Image',
        'mediaBtn'  => 'Use this image',
    ] );
} );

/* ─── Render page ───────────────────────────────────────── */
function bwm_render_page() {
    $total     = wp_count_attachments( 'image' );
    $total_img = array_sum( (array) $total );
    ?>
    <div class="bwm-wrap">
        <div class="bwm-header">
            <div class="bwm-header-left">
                <span class="bwm-icon">🌊</span>
                <div>
                    <h1>Bulk Watermark Manager</h1>
                    <p>Select images → choose watermark → preview → apply in bulk</p>
                </div>
            </div>
            <div class="bwm-header-stats">
                <div class="bwm-stat">
                    <span id="bwm-selected-count">0</span>
                    <label>selected</label>
                </div>
                <div class="bwm-stat">
                    <span><?php echo esc_html( $total_img ); ?></span>
                    <label>total images</label>
                </div>
            </div>
        </div>

        <div class="bwm-toolbar">
            <div class="bwm-toolbar-left">
                <input type="text" id="bwm-search" placeholder="🔍  Search images...">
                <select id="bwm-filter-cat">
                    <option value="">All dates</option>
                    <?php
                    $months = $GLOBALS['wpdb']->get_results(
                        "SELECT DISTINCT YEAR(post_date) y, MONTH(post_date) m
                         FROM {$GLOBALS['wpdb']->posts}
                         WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'
                         ORDER BY y DESC, m DESC LIMIT 24"
                    );
                    foreach ( $months as $row ) {
                        $label = sprintf( '%04d/%02d', $row->y, $row->m );
                        echo "<option value=\"{$label}\">{$label}</option>";
                    }
                    ?>
                </select>
                <select id="bwm-filter-wm">
                    <option value="">All statuses</option>
                    <option value="wm">Watermarked</option>
                    <option value="nowm">Not watermarked</option>
                </select>
            </div>
            <div class="bwm-toolbar-right">
                <button id="bwm-select-all" class="bwm-btn bwm-btn-ghost">
                    <span class="dashicons dashicons-yes-alt"></span> Select All
                </button>
                <button id="bwm-deselect-all" class="bwm-btn bwm-btn-ghost">
                    <span class="dashicons dashicons-dismiss"></span> Deselect
                </button>
                <button id="bwm-open-wm-panel" class="bwm-btn bwm-btn-primary" disabled>
                    <span class="dashicons dashicons-edit"></span> Set Watermark (<span id="bwm-sel-cnt">0</span>)
                </button>
            </div>
        </div>

        <div id="bwm-grid-wrap">
            <div id="bwm-grid"></div>
            <div id="bwm-load-more-wrap">
                <button id="bwm-load-more" class="bwm-btn bwm-btn-ghost">Load more</button>
            </div>
        </div>

        <!-- Watermark Panel -->
        <div id="bwm-wm-panel" class="bwm-panel" style="display:none">
            <div class="bwm-panel-inner">
                <button class="bwm-panel-close" id="bwm-close-panel">&times;</button>
                <h2>Watermark Configuration</h2>

                <div class="bwm-panel-grid">
                    <!-- Left: settings -->
                    <div class="bwm-panel-settings">
                        <div class="bwm-form-row">
                            <label>Watermark Image</label>
                            <div class="bwm-wm-picker">
                                <div id="bwm-wm-thumb" class="bwm-wm-thumb-empty">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <p>Not selected</p>
                                </div>
                                <button id="bwm-pick-wm" class="bwm-btn bwm-btn-ghost">Choose from Media</button>
                                <input type="hidden" id="bwm-wm-id" value="">
                                <input type="hidden" id="bwm-wm-url" value="">
                            </div>
                        </div>

                        <div class="bwm-form-row">
                            <label>Position</label>
                            <div class="bwm-position-grid">
                                <?php
                                $positions = [
                                    'top-left'=>'↖','top-center'=>'↑','top-right'=>'↗',
                                    'center-left'=>'←','center'=>'✛','center-right'=>'→',
                                    'bottom-left'=>'↙','bottom-center'=>'↓','bottom-right'=>'↘',
                                ];
                                foreach ( $positions as $val => $icon ) {
                                    $checked = $val === 'bottom-right' ? 'checked' : '';
                                    echo "<label class='bwm-pos-btn'><input type='radio' name='bwm_pos' value='{$val}' {$checked}><span>{$icon}</span></label>";
                                }
                                ?>
                            </div>
                        </div>

                        <div class="bwm-form-row">
                            <label>Watermark Size <span id="bwm-size-val">25%</span></label>
                            <input type="range" id="bwm-wm-size" min="5" max="80" value="25" step="1">
                        </div>

                        <div class="bwm-form-row">
                            <label>Opacity <span id="bwm-opacity-val">80%</span></label>
                            <input type="range" id="bwm-wm-opacity" min="10" max="100" value="80" step="1">
                        </div>

                        <div class="bwm-form-row">
                            <label>Edge Padding <span id="bwm-pad-val">10px</span></label>
                            <input type="range" id="bwm-wm-pad" min="0" max="60" value="10" step="1">
                        </div>

                        <div class="bwm-form-row bwm-backup-row">
                            <label class="bwm-checkbox-label">
                                <input type="checkbox" id="bwm-backup" checked>
                                Save original (adds <code>_orig</code> suffix)
                            </label>
                        </div>
                    </div>

                    <!-- Right: preview -->
                    <div class="bwm-panel-preview">
                        <label>Preview</label>
                        <div class="bwm-preview-box">
                            <canvas id="bwm-preview-canvas"></canvas>
                            <div id="bwm-preview-placeholder">
                                <span class="dashicons dashicons-format-image"></span>
                                <p>Select a watermark image to preview</p>
                            </div>
                        </div>
                        <p class="bwm-preview-hint">Preview is based on the first selected image</p>
                    </div>
                </div>

                <div class="bwm-panel-footer">
                    <button id="bwm-cancel-panel" class="bwm-btn bwm-btn-ghost">Cancel</button>
                    <button id="bwm-apply-wm" class="bwm-btn bwm-btn-primary" disabled>
                        <span class="dashicons dashicons-controls-play"></span>
                        Apply to <span id="bwm-apply-cnt">0</span> images
                    </button>
                </div>
            </div>
        </div>

        <!-- Progress overlay -->
        <div id="bwm-progress-overlay" style="display:none">
            <div class="bwm-progress-box">
                <h3 id="bwm-prog-title">Processing...</h3>
                <div class="bwm-progress-bar"><div id="bwm-prog-bar"></div></div>
                <p id="bwm-prog-status">0 / 0</p>
                <div id="bwm-prog-log"></div>
            </div>
        </div>
    </div>
    <?php
}

/* ─── AJAX: load images ─────────────────────────────────── */
add_action( 'wp_ajax_bwm_load_images', function () {
    check_ajax_referer( 'bwm_nonce', 'nonce' );

    $page    = max( 1, intval( $_POST['page'] ?? 1 ) );
    $search  = sanitize_text_field( $_POST['search'] ?? '' );
    $per     = 32;

    $args = [
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => $per,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ( $search ) {
        $args['s'] = $search;
    }

    $month = sanitize_text_field( $_POST['month'] ?? '' );
    if ( $month && preg_match( '/^(\d{4})\/(\d{2})$/', $month, $m ) ) {
        $args['date_query'] = [[
            'year'  => (int) $m[1],
            'month' => (int) $m[2],
        ]];
    }

    $query = new WP_Query( $args );
    $items = [];

    foreach ( $query->posts as $post ) {
        $thumb = wp_get_attachment_image_src( $post->ID, 'medium' );
        $full  = wp_get_attachment_image_src( $post->ID, 'full' );
        $has_wm = get_post_meta( $post->ID, '_bwm_watermarked', true );

        $items[] = [
            'id'          => $post->ID,
            'title'       => $post->post_title ?: basename( get_attached_file( $post->ID ) ),
            'thumb'       => $thumb ? $thumb[0] : '',
            'full'        => $full  ? $full[0]  : '',
            'watermarked' => (bool) $has_wm,
        ];
    }

    $filter_wm = sanitize_text_field( $_POST['filter_wm'] ?? '' );
    if ( $filter_wm === 'wm' ) {
        $items = array_values( array_filter( $items, fn($i) => $i['watermarked'] ) );
    } elseif ( $filter_wm === 'nowm' ) {
        $items = array_values( array_filter( $items, fn($i) => ! $i['watermarked'] ) );
    }

    wp_send_json_success( [
        'items'    => $items,
        'has_more' => $query->max_num_pages > $page,
    ] );
} );

/* ─── AJAX: apply watermark ─────────────────────────────── */
add_action( 'wp_ajax_bwm_apply_watermark', function () {
    check_ajax_referer( 'bwm_nonce', 'nonce' );
    if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'Permission denied' );

    $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
    $wm_id         = intval( $_POST['wm_id'] ?? 0 );
    $position      = sanitize_text_field( $_POST['position'] ?? 'bottom-right' );
    $size_pct      = max( 5, min( 80, intval( $_POST['size'] ?? 25 ) ) );
    $opacity       = max( 0.1, min( 1.0, floatval( $_POST['opacity'] ?? 0.8 ) ) );
    $padding       = max( 0, min( 100, intval( $_POST['padding'] ?? 10 ) ) );
    $do_backup     = ! empty( $_POST['backup'] );

    if ( ! $attachment_id || ! $wm_id ) {
        wp_send_json_error( 'Missing params' );
    }

    $file    = get_attached_file( $attachment_id );
    $wm_file = get_attached_file( $wm_id );

    if ( ! $file || ! file_exists( $file ) ) wp_send_json_error( 'Source file not found' );
    if ( ! $wm_file || ! file_exists( $wm_file ) ) wp_send_json_error( 'Watermark file not found' );

    $mime = mime_content_type( $file );

    // Load base image
    $base = null;
    switch ( $mime ) {
        case 'image/jpeg': $base = imagecreatefromjpeg( $file ); break;
        case 'image/png':  $base = imagecreatefrompng( $file );  break;
        case 'image/webp': $base = imagecreatefromwebp( $file ); break;
        default: wp_send_json_error( 'Unsupported image type: ' . $mime );
    }
    if ( ! $base ) wp_send_json_error( 'Cannot load base image' );

    // Load watermark
    $wm_mime = mime_content_type( $wm_file );
    $wm = null;
    switch ( $wm_mime ) {
        case 'image/jpeg': $wm = imagecreatefromjpeg( $wm_file ); break;
        case 'image/png':  $wm = imagecreatefrompng( $wm_file );  break;
        case 'image/webp': $wm = imagecreatefromwebp( $wm_file ); break;
        default: wp_send_json_error( 'Unsupported watermark type' );
    }
    if ( ! $wm ) wp_send_json_error( 'Cannot load watermark' );

    $bw = imagesx( $base );
    $bh = imagesy( $base );
    $ww = imagesx( $wm );
    $wh = imagesy( $wm );

    // Resize watermark
    $new_ww = intval( $bw * $size_pct / 100 );
    $new_wh = intval( $wh * ( $new_ww / $ww ) );
    $wm_resized = imagecreatetruecolor( $new_ww, $new_wh );
    imagealphablending( $wm_resized, false );
    imagesavealpha( $wm_resized, true );
    imagecopyresampled( $wm_resized, $wm, 0, 0, 0, 0, $new_ww, $new_wh, $ww, $wh );

    // Apply opacity via pixel manipulation
    if ( $opacity < 1.0 ) {
        for ( $y = 0; $y < $new_wh; $y++ ) {
            for ( $x = 0; $x < $new_ww; $x++ ) {
                $color = imagecolorat( $wm_resized, $x, $y );
                $a = ( $color >> 24 ) & 0x7F;
                $new_a = min( 127, $a + intval( ( 1 - $opacity ) * 127 ) );
                $r = ( $color >> 16 ) & 0xFF;
                $g = ( $color >> 8 )  & 0xFF;
                $b = $color & 0xFF;
                imagesetpixel( $wm_resized, $x, $y, imagecolorallocatealpha( $wm_resized, $r, $g, $b, $new_a ) );
            }
        }
    }

    // Calculate position
    [$pos_h, $pos_v] = bwm_parse_position( $position );

    $dx = $pos_h === 'left'   ? $padding
        : ( $pos_h === 'right'  ? $bw - $new_ww - $padding
        : intval( ( $bw - $new_ww ) / 2 ) );

    $dy = $pos_v === 'top'    ? $padding
        : ( $pos_v === 'bottom' ? $bh - $new_wh - $padding
        : intval( ( $bh - $new_wh ) / 2 ) );

    // Backup original
    if ( $do_backup ) {
        $ext     = pathinfo( $file, PATHINFO_EXTENSION );
        $base_fn = pathinfo( $file, PATHINFO_FILENAME );
        $dir     = pathinfo( $file, PATHINFO_DIRNAME );
        $backup  = $dir . '/' . $base_fn . '_orig.' . $ext;
        if ( ! file_exists( $backup ) ) {
            copy( $file, $backup );
        }
    }

    // Merge
    imagealphablending( $base, true );
    imagecopy( $base, $wm_resized, $dx, $dy, 0, 0, $new_ww, $new_wh );

    // Save
    $ok = false;
    switch ( $mime ) {
        case 'image/jpeg': $ok = imagejpeg( $base, $file, 92 ); break;
        case 'image/png':  $ok = imagepng( $base, $file, 6 );   break;
        case 'image/webp': $ok = imagewebp( $base, $file, 90 ); break;
    }

    imagedestroy( $base );
    imagedestroy( $wm );
    imagedestroy( $wm_resized );

    if ( ! $ok ) wp_send_json_error( 'Failed to save image' );

    // Mark as watermarked
    update_post_meta( $attachment_id, '_bwm_watermarked', 1 );
    update_post_meta( $attachment_id, '_bwm_wm_id', $wm_id );

    // Clear WordPress image cache
    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );

    $new_url = wp_get_attachment_url( $attachment_id );
    wp_send_json_success( [ 'url' => $new_url . '?v=' . time() ] );
} );

function bwm_parse_position( string $pos ): array {
    $map = [
        'top-left'      => ['left',   'top'],
        'top-center'    => ['center', 'top'],
        'top-right'     => ['right',  'top'],
        'center-left'   => ['left',   'center'],
        'center'        => ['center', 'center'],
        'center-right'  => ['right',  'center'],
        'bottom-left'   => ['left',   'bottom'],
        'bottom-center' => ['center', 'bottom'],
        'bottom-right'  => ['right',  'bottom'],
    ];
    return $map[ $pos ] ?? ['right', 'bottom'];
}
