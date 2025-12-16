<?php
/**
 * Plugin Name: DPD Fresh Delivery
 * Description: DPD Fresh pristatymas su žemėlapio adreso pasirinkimu
 * Version: 2.0.0
 * Author: Rokas Zakarauskas
 * Author URI: https://proven.lt
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.4
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('DPD_FRESH_VERSION', '2.0.0');
define('DPD_FRESH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DPD_FRESH_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Activation hook
register_activation_hook(__FILE__, 'dpd_fresh_activate');
function dpd_fresh_activate() {
    require_once DPD_FRESH_PLUGIN_DIR . 'includes/class-dpd-database.php';
    DPD_Fresh_Database::create_table();
    update_option('dpd_fresh_version', DPD_FRESH_VERSION);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'dpd_fresh_deactivate');
function dpd_fresh_deactivate() {
    // Keep data on deactivation
}

// Initialize plugin
add_action('plugins_loaded', 'dpd_fresh_init');
function dpd_fresh_init() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Load required files
    require_once DPD_FRESH_PLUGIN_DIR . 'includes/class-dpd-database.php';
    require_once DPD_FRESH_PLUGIN_DIR . 'public/class-dpd-fresh-public-email.php';
    
    require_once DPD_FRESH_PLUGIN_DIR . 'admin/class-dpd-admin-settings.php';

    // Admin functionality
    if (is_admin()) {
        require_once DPD_FRESH_PLUGIN_DIR . 'admin/class-dpd-admin-orders.php';
        DPD_Fresh_Admin_Settings::init();
    }

    // Initialize email functionality
    $dpd_fresh_email = new DPD_Fresh_Public_Email('dpd-fresh-delivery', DPD_FRESH_VERSION);
    add_action('woocommerce_email_before_order_table', array($dpd_fresh_email, 'add_dpd_fresh_tracking_number'), 10, 4);
    add_action('woocommerce_email_after_order_table', array($dpd_fresh_email, 'add_dpd_fresh_delivery_address'), 10, 4);
    
    add_filter('thwcfe_input_field_options_reservation_date', 'dpd_fresh_filter_reservation_date_options', 10, 1);
}

// Register shipping method
add_action('woocommerce_shipping_init', 'dpd_fresh_shipping_init');
function dpd_fresh_shipping_init() {
    require_once DPD_FRESH_PLUGIN_DIR . 'includes/class-dpd-shipping-method.php';
}

add_filter('woocommerce_shipping_methods', 'dpd_fresh_add_shipping_method');
function dpd_fresh_add_shipping_method($methods) {
    $methods['dpd_fresh_delivery'] = 'WC_DPD_Fresh_Shipping_Method';
    return $methods;
}

// Admin menu
add_action('admin_menu', 'dpd_fresh_admin_menu');
function dpd_fresh_admin_menu() {
    add_submenu_page(
        'woocommerce',
        'DPD Fresh Užsakymai',
        'DPD Fresh Užsakymai',
        'manage_woocommerce',
        'dpd-fresh-orders',
        'dpd_fresh_render_orders_page'
    );
    
    add_submenu_page(
        'woocommerce',
        'DPD Fresh Nustatymai',
        'DPD Fresh Nustatymai',
        'manage_woocommerce',
        'dpd-fresh-settings',
        'dpd_fresh_render_settings_page'
    );
}

function dpd_fresh_render_orders_page() {
    if (class_exists('DPD_Fresh_Admin_Orders')) {
        DPD_Fresh_Admin_Orders::render();
    }
}

function dpd_fresh_render_settings_page() {
    if (class_exists('DPD_Fresh_Admin_Settings')) {
        DPD_Fresh_Admin_Settings::render();
    }
}

// Admin assets
add_action('admin_enqueue_scripts', 'dpd_fresh_admin_assets');
function dpd_fresh_admin_assets($hook) {
    // Orders page assets
    if ($hook === 'woocommerce_page_dpd-fresh-orders') {
        wp_enqueue_style('dpd-fresh-admin', DPD_FRESH_PLUGIN_URL . 'assets/css/dpd-admin.css', [], DPD_FRESH_VERSION);
        wp_enqueue_script('dpd-fresh-admin', DPD_FRESH_PLUGIN_URL . 'assets/js/dpd-admin.js', ['jquery'], DPD_FRESH_VERSION, true);

        wp_localize_script('dpd-fresh-admin', 'dpd_fresh_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpd_fresh_send_order'),
            'copied_text' => 'Nukopijuota!',
            'error_text' => 'Klaida'
        ]);
    }
    
    // Settings page assets
    if ($hook === 'woocommerce_page_dpd-fresh-settings') {
        wp_enqueue_style('dpd-fresh-settings', DPD_FRESH_PLUGIN_URL . 'assets/css/dpd-settings.css', [], DPD_FRESH_VERSION);
        wp_enqueue_script('dpd-fresh-settings', DPD_FRESH_PLUGIN_URL . 'assets/js/dpd-settings.js', ['jquery'], DPD_FRESH_VERSION, true);
    }
}

// Frontend assets (checkout only)
add_action('wp_enqueue_scripts', 'dpd_fresh_frontend_assets');
function dpd_fresh_frontend_assets() {
    // Only enqueue on checkout (including block checkout)
    if (!is_checkout() && !has_block('woocommerce/checkout')) {
        return;
    }

    wp_enqueue_style('dpd-fresh-map', DPD_FRESH_PLUGIN_URL . 'assets/css/dpd-map.css', [], DPD_FRESH_VERSION);

    // Get API key from any DPD Fresh shipping method instance
    $api_key = dpd_fresh_get_api_key();

    if ($api_key) {
        wp_enqueue_script('google-maps-dpd', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=places', [], null, true);
        wp_enqueue_script('dpd-fresh-map', DPD_FRESH_PLUGIN_URL . 'assets/js/dpd-map.js', ['jquery', 'google-maps-dpd', 'wc-checkout'], DPD_FRESH_VERSION, true);
        
        // Add localized script data (like Multiparcels pattern)
        wp_localize_script('dpd-fresh-map', 'dpd_fresh_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'text' => [
                'select_location' => __('Prašome pasirinkti pristatymo vietą žemėlapyje', 'dpd-fresh-delivery')
            ]
        ]);
    }
}

function dpd_fresh_get_api_key() {
    $zones = WC_Shipping_Zones::get_zones();
    foreach ($zones as $zone) {
        foreach ($zone['shipping_methods'] as $method) {
            if ($method->id === 'dpd_fresh_delivery') {
                $key = $method->get_option('google_maps_api_key');
                if ($key) {
                    return $key;
                }
            }
        }
    }
    // Check zone 0 (locations not covered)
    $zone_0 = WC_Shipping_Zones::get_zone(0);
    foreach ($zone_0->get_shipping_methods() as $method) {
        if ($method->id === 'dpd_fresh_delivery') {
            $key = $method->get_option('google_maps_api_key');
            if ($key) {
                return $key;
            }
        }
    }
    return '';
}

// Display map selector before payment methods
add_action('init', function() {
    add_action('woocommerce_review_order_before_payment', 'dpd_fresh_display_map', 10);
});

function dpd_fresh_display_map() {
    if (!is_checkout()) {
        return;
    }

    // ALWAYS render template (hidden by default with CSS, shown by JavaScript when method selected)
    include DPD_FRESH_PLUGIN_DIR . 'public/checkout-map.php';
}

// Validate delivery address
// Use earlier validation hook (before woocommerce processes)
add_action('woocommerce_after_checkout_validation', 'dpd_fresh_validate_checkout', 10, 2);
function dpd_fresh_validate_checkout($fields, $errors) {
    $chosen = WC()->session->get('chosen_shipping_methods');
    
    if (!$chosen) {
        return;
    }
    
    $is_dpd_fresh = false;
    foreach ($chosen as $method) {
        if (strpos($method, 'dpd_fresh_delivery') !== false) {
            $is_dpd_fresh = true;
            break;
        }
    }

    if ($is_dpd_fresh) {
        if (empty($_POST['dpd_fresh_delivery_address'])) {
            $errors->add('validation', __('Prašome pasirinkti pristatymo vietą žemėlapyje', 'dpd-fresh-delivery'));
        }
        if (empty($_POST['dpd_fresh_latitude']) || empty($_POST['dpd_fresh_longitude'])) {
            $errors->add('validation', __('Prašome pasirinkti pristatymo vietą žemėlapyje', 'dpd-fresh-delivery'));
        }
    }
}

// Save delivery data
add_action('woocommerce_checkout_create_order', 'dpd_fresh_save_order_data', 20, 2);
function dpd_fresh_save_order_data($order, $data) {
    $shipping_methods = $order->get_shipping_methods();
    $is_dpd_fresh = false;

    foreach ($shipping_methods as $method) {
        if (strpos($method->get_method_id(), 'dpd_fresh_delivery') !== false) {
            $is_dpd_fresh = true;
            break;
        }
    }

    if (!$is_dpd_fresh) {
        return;
    }

    // Save delivery data
    if (!empty($_POST['dpd_fresh_delivery_address'])) {
        $order->update_meta_data('dpd_fresh_delivery_address', sanitize_text_field($_POST['dpd_fresh_delivery_address']));
    }

    if (!empty($_POST['dpd_fresh_latitude'])) {
        $lat = sanitize_text_field($_POST['dpd_fresh_latitude']);
        if (is_numeric($lat) && $lat >= -90 && $lat <= 90) {
            $order->update_meta_data('dpd_fresh_latitude', $lat);
        }
    }

    if (!empty($_POST['dpd_fresh_longitude'])) {
        $lng = sanitize_text_field($_POST['dpd_fresh_longitude']);
        if (is_numeric($lng) && $lng >= -180 && $lng <= 180) {
            $order->update_meta_data('dpd_fresh_longitude', $lng);
        }
    }
}

// Insert to database after order is created
add_action('woocommerce_checkout_order_created', 'dpd_fresh_insert_to_database', 20);
function dpd_fresh_insert_to_database($order) {
    DPD_Fresh_Database::insert_order($order->get_id());
}

// Block checkout support
add_action('woocommerce_store_api_checkout_update_order_from_request', 'dpd_fresh_save_block_checkout', 20, 2);
function dpd_fresh_save_block_checkout($order, $request) {
    $shipping_methods = $order->get_shipping_methods();
    $is_dpd_fresh = false;

    foreach ($shipping_methods as $method) {
        if (strpos($method->get_method_id(), 'dpd_fresh_delivery') !== false) {
            $is_dpd_fresh = true;
            break;
        }
    }

    if (!$is_dpd_fresh) {
        return;
    }

    $body = $request->get_body();
    $fields = json_decode($body, true);

    if (!is_array($fields)) {
        return;
    }

    if (isset($fields['shipping_address']['dpd_fresh_delivery_address'])) {
        $order->update_meta_data('dpd_fresh_delivery_address', sanitize_text_field($fields['shipping_address']['dpd_fresh_delivery_address']));
    }

    if (isset($fields['shipping_address']['dpd_fresh_latitude'])) {
        $order->update_meta_data('dpd_fresh_latitude', sanitize_text_field($fields['shipping_address']['dpd_fresh_latitude']));
    }

    if (isset($fields['shipping_address']['dpd_fresh_longitude'])) {
        $order->update_meta_data('dpd_fresh_longitude', sanitize_text_field($fields['shipping_address']['dpd_fresh_longitude']));
    }

    $order->save();
    DPD_Fresh_Database::insert_order($order->get_id());
}

// AJAX handler for send order
add_action('wp_ajax_dpd_fresh_send_order', 'dpd_fresh_ajax_send_order');
function dpd_fresh_ajax_send_order() {
    check_ajax_referer('dpd_fresh_send_order', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Neturite teisių']);
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $tracking = isset($_POST['tracking_number']) ? sanitize_text_field($_POST['tracking_number']) : '';

    if (!$order_id || empty($tracking)) {
        wp_send_json_error(['message' => 'Neteisingi duomenys']);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Užsakymas nerastas']);
    }

    // Save tracking number
    $order->update_meta_data('dpd_fresh_tracking_number', $tracking);
    $order->save();

    // Update status to completed
    $order->update_status('completed', 'Išsiųsta per DPD Fresh.');

    // Add order note
    $order->add_order_note(sprintf('DPD Fresh siuntos numeris: %s', $tracking));

    wp_send_json_success();
}

// Export handler
add_action('admin_post_dpd_fresh_export_orders', 'dpd_fresh_handle_export');
function dpd_fresh_handle_export() {
    if (!isset($_POST['dpd_fresh_export_nonce']) ||
        !wp_verify_nonce($_POST['dpd_fresh_export_nonce'], 'dpd_fresh_export_orders') ||
        !current_user_can('manage_woocommerce')) {
        wp_die('Neteisingas užklausimas');
    }

    require_once DPD_FRESH_PLUGIN_DIR . 'includes/SimpleXLSXGen.php';
    require_once DPD_FRESH_PLUGIN_DIR . 'admin/class-dpd-admin-orders.php';

    DPD_Fresh_Admin_Orders::export();
}

// Display DPD Fresh info in order edit page
add_action('woocommerce_admin_order_data_after_shipping_address', 'dpd_fresh_order_display');
function dpd_fresh_order_display($order) {
    $address = $order->get_meta('dpd_fresh_delivery_address', true);
    if (empty($address)) {
        return;
    }

    $lat = $order->get_meta('dpd_fresh_latitude', true);
    $lng = $order->get_meta('dpd_fresh_longitude', true);
    $tracking = $order->get_meta('dpd_fresh_tracking_number', true);

    echo '<div class="dpd-fresh-order-info" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border-left: 4px solid #dc1e28;">';
    echo '<h3 style="margin-top: 0;">DPD Fresh pristatymas</h3>';
    echo '<p><strong>Pristatymo adresas:</strong><br>' . esc_html($address) . '</p>';

    if ($lat && $lng) {
        $maps_url = sprintf('https://www.google.com/maps?q=%s,%s', $lat, $lng);
        echo '<p><strong>Koordinatės:</strong> ' . esc_html($lat) . ', ' . esc_html($lng) . '</p>';
        echo '<p><a href="' . esc_url($maps_url) . '" target="_blank" class="button">Žiūrėti žemėlapyje</a></p>';
    }

    if ($tracking) {
        echo '<p><strong>Siuntos numeris:</strong> ' . esc_html($tracking) . '</p>';
    }

    echo '</div>';
}

// Add logo to shipping method label
add_filter('woocommerce_cart_shipping_method_full_label', 'dpd_fresh_add_logo', 10, 2);
function dpd_fresh_add_logo($label, $method) {
    if (strpos($method->id, 'dpd_fresh_delivery') === false) {
        return $label;
    }

    if (strpos($label, 'dpd-fresh-logo') !== false) {
        return $label;
    }

    $logo = '<img src="https://www.dpd.com/wp-content/uploads/sites/232/2023/08/DPD_fresh_logo.png" alt="DPD Fresh" class="dpd-fresh-logo" style="height: 25px; vertical-align: middle; margin-right: 8px;">';

    return '<span class="dpd-fresh-logo-wrapper">' . $logo . $label . '</span>';
}


add_filter('woocommerce_update_order_review_fragments', 'dpd_fresh_update_checkout_fragments', 10, 1);
function dpd_fresh_update_checkout_fragments($fragments) {
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    $is_dpd_fresh = false;
    
    if (!empty($chosen_shipping_methods) && is_array($chosen_shipping_methods)) {
        foreach ($chosen_shipping_methods as $method) {
            if (strpos($method, 'dpd_fresh_delivery') !== false) {
                $is_dpd_fresh = true;
                break;
            }
        }
    }
    
    if ($is_dpd_fresh) {
        ob_start();
        ?>
        <div class="woocommerce-shipping-fields__field-wrapper">
            <?php
            $checkout = WC()->checkout();
            $fields = $checkout->get_checkout_fields('shipping');
            foreach ($fields as $key => $field) {
                woocommerce_form_field($key, $field, $checkout->get_value($key));
            }
            ?>
        </div>
        <?php
        $fragments['.woocommerce-shipping-fields__field-wrapper'] = ob_get_clean();
    }
    
    return $fragments;
}

/**
 * Filter reservation_date field options based on DPD Fresh settings
 * Only applies when DPD Fresh shipping method is selected and custom dates are enabled
 */
function dpd_fresh_filter_reservation_date_options($options) {
    // Ensure we have valid options array - plugin expects array
    if (!is_array($options)) {
        $options = [];
    }
    
    // Only run on frontend checkout or AJAX requests (not in admin pages)
    if (is_admin() && !wp_doing_ajax()) {
        return $options;
    }
    
    // Check if class exists
    if (!class_exists('DPD_Fresh_Admin_Settings')) {
        return $options;
    }
    
    try {
        // Get DPD Fresh settings
        $settings = DPD_Fresh_Admin_Settings::get_settings();
        
        // If custom dates feature is disabled, return original options
        if (empty($settings['enable_custom_dates'])) {
            return $options;
        }
        
        // Check if WooCommerce is available
        if (!function_exists('WC') || !WC()) {
            return $options;
        }
        
        // Check if cart exists and has items
        if (!WC()->cart || WC()->cart->is_empty()) {
            return $options;
        }
        
        // Check if session is available
        if (!WC()->session) {
            return $options;
        }
        
        // Get the selected shipping method
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        
        // If no shipping method selected yet, return original options
        if (empty($chosen_shipping_methods) || !is_array($chosen_shipping_methods)) {
            return $options;
        }
        
        $selected_shipping_method = !empty($chosen_shipping_methods[0]) ? $chosen_shipping_methods[0] : '';
        
        // Check if DPD Fresh shipping method is selected
        if (empty($selected_shipping_method) || strpos($selected_shipping_method, 'dpd_fresh_delivery') === false) {
            // Not DPD Fresh, return original options
            return $options;
        }
        
        $reservation_dates = !empty($settings['reservation_dates']) && is_array($settings['reservation_dates']) 
            ? $settings['reservation_dates'] 
            : [];
        
        if (empty($reservation_dates)) {
            return [];
        }
        
        $custom_options = [];
        foreach ($reservation_dates as $date_entry) {
            if (!empty($date_entry['date']) && !empty($date_entry['text'])) {
                $date_key = sanitize_text_field($date_entry['date']);
                $date_text = sanitize_text_field($date_entry['text']);
                
                if (!empty($date_key) && !empty($date_text)) {
                    $custom_options[$date_key] = [
                        'key' => $date_key,
                        'text' => $date_text,
                        'price' => '',
                        'price_type' => ''
                    ];
                }
            }
        }
        
        return !empty($custom_options) ? $custom_options : [];
        
    } catch (Exception $e) {
        return $options;
    }
}