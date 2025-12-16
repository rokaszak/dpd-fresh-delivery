<?php
/**
 * DPD Fresh Shipping Method
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_DPD_Fresh_Shipping_Method')) {
    return;
}

class WC_DPD_Fresh_Shipping_Method extends WC_Shipping_Method {

    public function __construct($instance_id = 0) {
        $this->id = 'dpd_fresh_delivery';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'DPD Fresh pristatymas';
        $this->method_description = 'DPD Fresh pristatymas su žemėlapio adreso pasirinkimu';
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', 'DPD Fresh pristatymas');

        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->instance_form_fields = [
            'title' => [
                'title' => 'Pavadinimas',
                'type' => 'text',
                'description' => 'Pavadinimas, kurį mato pirkėjas.',
                'default' => 'DPD Fresh pristatymas',
                'desc_tip' => true
            ],
            'description' => [
                'title' => 'Aprašymas',
                'type' => 'text',
                'description' => 'Aprašymas, kurį mato pirkėjas.',
                'default' => 'Pristatymas per 1-2 darbo dienas',
                'desc_tip' => true
            ],
            'fee' => [
                'title' => 'Pristatymo kaina (be mokesčių)',
                'type' => 'price',
                'description' => 'Mokesčiai bus apskaičiuoti automatiškai.',
                'default' => 0,
                'desc_tip' => true,
                'placeholder' => '0.00'
            ],
            'min_amount_for_free_shipping' => [
                'title' => 'Minimali suma nemokamam pristatymui',
                'type' => 'price',
                'description' => 'Palikite tuščią, jei nėra nemokamo pristatymo.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Nėra'
            ],
            'minimum_weight' => [
                'title' => 'Minimalus svoris (kg)',
                'type' => 'number',
                'description' => 'Palikite tuščią, jei nėra minimumo.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => '0'
            ],
            'maximum_weight' => [
                'title' => 'Maksimalus svoris (kg)',
                'type' => 'number',
                'description' => 'Palikite tuščią, jei nėra maksimumo.',
                'default' => 30,
                'desc_tip' => true,
                'placeholder' => '0'
            ],
            'google_maps_api_key' => [
                'title' => 'Google Maps API raktas',
                'type' => 'text',
                'description' => 'Google Maps API raktas žemėlapiui. Gaukite: https://console.cloud.google.com/',
                'default' => '',
                'desc_tip' => true
            ],
        ];

        // Add shipping classes support
        $shipping_classes = $this->get_shipping_classes();
        if (!empty($shipping_classes)) {
            $options = [];
            foreach ($shipping_classes as $class) {
                $options[$class->term_id] = $class->name;
            }

            $this->instance_form_fields['ignore_shipping_classes'] = [
                'title' => 'Išjungti šioms siuntimo klasėms',
                'type' => 'multiselect',
                'description' => 'Jei bent vienas produktas turi pasirinktą siuntimo klasę, metodas bus išjungtas.',
                'default' => [],
                'desc_tip' => true,
                'options' => $options
            ];
        }
    }

    public function calculate_shipping($package = []) {
        // Check weight restrictions
        $cart_weight = WC()->cart->cart_contents_weight;
        if (get_option('woocommerce_weight_unit') === 'g') {
            $cart_weight /= 1000;
        }

        $min_weight = (float) $this->get_option('minimum_weight');
        $max_weight = (float) $this->get_option('maximum_weight');

        if ($max_weight > 0 && $cart_weight > $max_weight) {
            return;
        }

        if ($min_weight > 0 && $cart_weight < $min_weight) {
            return;
        }

        // Check shipping classes
        $ignored_classes = $this->get_option('ignore_shipping_classes', []);
        if (!is_array($ignored_classes)) {
            $ignored_classes = [];
        }

        if (!empty($ignored_classes)) {
            foreach ($package['contents'] as $item) {
                if ($item['data']->needs_shipping()) {
                    $class_id = $item['data']->get_shipping_class_id();
                    if (in_array($class_id, $ignored_classes)) {
                        return;
                    }
                }
            }
        }

        $cost = (float) $this->get_option('fee', 0);

        // Check for free shipping
        $min_for_free = (float) $this->get_option('min_amount_for_free_shipping');
        if ($min_for_free > 0) {
            $cart_total = WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax();
            if ($cart_total >= $min_for_free) {
                $cost = 0;
            }
        }

        // Check for free shipping coupons
        foreach (WC()->cart->get_applied_coupons() as $code) {
            $coupon = new WC_Coupon($code);
            if ($coupon->get_free_shipping()) {
                $cost = 0;
                break;
            }
        }

        $this->add_rate([
            'id' => $this->get_rate_id(),
            'label' => $this->get_option('title'),
            'cost' => $cost,
            'package' => $package,
        ]);
    }

    private function get_shipping_classes() {
        global $wpdb;
        return $wpdb->get_results("
            SELECT t.* FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'product_shipping_class'
        ");
    }
}

