<?php
/**
 * DPD Fresh Database Handler
 * HPOS-compatible database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPD_Fresh_Database {

    private static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'dpd_fresh_orders';
    }

    public static function create_table() {
        global $wpdb;

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function insert_order($order_id) {
        global $wpdb;

        $table = self::table_name();

        // Check if exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE order_id = %d",
            $order_id
        ));

        if ($exists) {
            return $wpdb->update(
                $table,
                ['created_at' => current_time('mysql')],
                ['order_id' => $order_id],
                ['%s'],
                ['%d']
            );
        }

        return $wpdb->insert(
            $table,
            [
                'order_id' => $order_id,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s']
        );
    }

    public static function get_order_ids() {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_col("SELECT order_id FROM $table");
    }

    /**
     * Get filtered orders using HPOS-compatible queries
     */
    public static function get_filtered_orders($args = []) {
        $defaults = [
            'limit' => 50,
            'offset' => 0,
            'status' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'reservation_date',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        // Get all DPD Fresh order IDs from our table
        $dpd_order_ids = self::get_order_ids();

        if (empty($dpd_order_ids)) {
            return ['orders' => [], 'total' => 0];
        }

        // Build WC query args
        $wc_args = [
            'limit' => -1, // Get all, we'll filter and paginate
            'post__in' => $dpd_order_ids,
            'return' => 'objects',
        ];

        // Status filter
        if (!empty($args['status'])) {
            $wc_args['status'] = str_replace('wc-', '', $args['status']);
        }

        $orders = wc_get_orders($wc_args);

        // Apply additional filters
        $filtered = [];
        foreach ($orders as $order) {
            // Search filter
            if (!empty($args['search'])) {
                $search = strtolower($args['search']);
                $searchable = strtolower(implode(' ', [
                    $order->get_id(),
                    $order->get_billing_first_name(),
                    $order->get_billing_last_name(),
                    $order->get_billing_phone(),
                    $order->get_shipping_first_name(),
                    $order->get_shipping_last_name(),
                    $order->get_shipping_phone(),
                ]));

                if (strpos($searchable, $search) === false) {
                    continue;
                }
            }

            // Reservation date filter
            $res_date = $order->get_meta('reservation_date', true);
            if (!empty($args['date_from']) && !empty($res_date)) {
                if ($res_date < $args['date_from']) {
                    continue;
                }
            }
            if (!empty($args['date_to']) && !empty($res_date)) {
                if ($res_date > $args['date_to']) {
                    continue;
                }
            }

            $filtered[] = $order;
        }

        // Sort
        usort($filtered, function ($a, $b) use ($args) {
            if ($args['orderby'] === 'reservation_date') {
                $val_a = $a->get_meta('reservation_date', true) ?: '0000-00-00';
                $val_b = $b->get_meta('reservation_date', true) ?: '0000-00-00';
            } else {
                $val_a = $a->get_id();
                $val_b = $b->get_id();
            }

            if ($args['order'] === 'ASC') {
                return $val_a <=> $val_b;
            }
            return $val_b <=> $val_a;
        });

        $total = count($filtered);

        // Paginate
        if ($args['limit'] > 0) {
            $filtered = array_slice($filtered, $args['offset'], $args['limit']);
        }

        return ['orders' => $filtered, 'total' => $total];
    }

    /**
     * Get unique reservation dates for filter dropdown
     */
    public static function get_unique_reservation_dates() {
        $order_ids = self::get_order_ids();
        if (empty($order_ids)) {
            return [];
        }

        $dates = [];
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $res_date = $order->get_meta('reservation_date', true);
                if ($res_date && !in_array($res_date, $dates)) {
                    $dates[] = $res_date;
                }
            }
        }

        rsort($dates);
        return $dates;
    }

    public static function delete_order($order_id) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->delete(
            $table,
            ['order_id' => $order_id],
            ['%d']
        );
    }
}

