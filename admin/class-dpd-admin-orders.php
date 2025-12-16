<?php
/**
 * DPD Fresh Admin Orders Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPD_Fresh_Admin_Orders {

    public static function render() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Neturite teisių peržiūrėti šį puslapį.');
        }

        // Get filter values
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['order_status']) ? sanitize_text_field($_GET['order_status']) : 'wc-processing';
        $res_date = isset($_GET['reservation_date']) ? sanitize_text_field($_GET['reservation_date']) : '';
        $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'reservation_asc';

        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Parse sort
        switch ($sort) {
            case 'reservation_asc':
                $orderby = 'reservation_date';
                $order = 'ASC';
                break;
            case 'reservation_desc':
                $orderby = 'reservation_date';
                $order = 'DESC';
                break;
            case 'id_asc':
                $orderby = 'ID';
                $order = 'ASC';
                break;
            case 'id_desc':
            default:
                $orderby = 'ID';
                $order = 'DESC';
                break;
        }

        // Get orders
        $result = DPD_Fresh_Database::get_filtered_orders([
            'limit' => $per_page,
            'offset' => $offset,
            'status' => $status,
            'search' => $search,
            'date_from' => $res_date,
            'date_to' => $res_date,
            'orderby' => $orderby,
            'order' => $order
        ]);

        $orders = $result['orders'];
        $total = $result['total'];
        $total_pages = ceil($total / $per_page);

        $wc_statuses = wc_get_order_statuses();
        $available_dates = DPD_Fresh_Database::get_unique_reservation_dates();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">DPD Fresh Užsakymai</h1>
            <hr class="wp-header-end">

            <!-- Filters -->
            <div class="dpd-fresh-filters">
                <form method="get">
                    <input type="hidden" name="page" value="dpd-fresh-orders">

                    <div class="dpd-fresh-filters-row">
                        <div class="dpd-fresh-filter">
                            <label for="filter-status">Būsena:</label>
                            <select id="filter-status" name="order_status">
                                <option value="">Visos būsenos</option>
                                <?php foreach ($wc_statuses as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($status, $slug); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dpd-fresh-filter">
                            <label for="filter-search">Paieška:</label>
                            <input type="text" id="filter-search" name="s" value="<?php echo esc_attr($search); ?>"
                                placeholder="ID, Vardas, Tel. nr">
                        </div>

                        <div class="dpd-fresh-filter">
                            <label for="filter-reservation-date">Rezervacijos data:</label>
                            <select id="filter-reservation-date" name="reservation_date">
                                <option value="">Visos datos</option>
                                <?php foreach ($available_dates as $date): ?>
                                    <option value="<?php echo esc_attr($date); ?>" <?php selected($res_date, $date); ?>>
                                        <?php echo esc_html($date); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dpd-fresh-filter">
                            <label for="filter-sort">Rikiuoti pagal:</label>
                            <select id="filter-sort" name="sort">
                                <option value="reservation_desc" <?php selected($sort, 'reservation_desc'); ?>>
                                    Rezervacijos datą (Naujausi)
                                </option>
                                <option value="reservation_asc" <?php selected($sort, 'reservation_asc'); ?>>
                                    Rezervacijos datą (Seniausi)
                                </option>
                                <option value="id_desc" <?php selected($sort, 'id_desc'); ?>>
                                    Užsakymo ID (Naujausi)
                                </option>
                                <option value="id_asc" <?php selected($sort, 'id_asc'); ?>>
                                    Užsakymo ID (Seniausi)
                                </option>
                            </select>
                        </div>

                        <div class="dpd-fresh-filter">
                            <button type="submit" class="button">Filtruoti</button>
                            <a href="<?php echo admin_url('admin.php?page=dpd-fresh-orders'); ?>" class="button">Atstatyti</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Results count -->
            <div class="dpd-fresh-results-info">
                <p>Rodoma užsakymų: <?php echo $total; ?></p>
            </div>

            <!-- Orders table -->
            <table class="wp-list-table widefat fixed striped dpd-fresh-table">
                <thead>
                    <tr>
                        <th class="column-order">Užsakymas</th>
                        <th class="column-status">Būsena</th>
                        <th class="column-buyer">Pirkėjas</th>
                        <th class="column-shipping">Gavėjas</th>
                        <th class="column-items">Daiktai</th>
                        <th class="column-address">Pristatymo adresas</th>
                        <th class="column-reservation">Rezervacijos data</th>
                        <th class="column-actions">Veiksmai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">Užsakymų nerasta.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order):
                            $order_id = $order->get_id();
                            $order_status = $order->get_status();
                            $status_name = wc_get_order_status_name($order_status);
                            $edit_url = $order->get_edit_order_url();

                            $delivery_address = $order->get_meta('dpd_fresh_delivery_address', true);
                            $lat = $order->get_meta('dpd_fresh_latitude', true);
                            $lng = $order->get_meta('dpd_fresh_longitude', true);
                            $reservation_date = $order->get_meta('reservation_date', true);
                            $maps_url = ($lat && $lng) ? sprintf('https://www.google.com/maps?q=%s,%s', $lat, $lng) : '';
                            ?>
                            <tr data-order-id="<?php echo esc_attr($order_id); ?>">
                                <td class="column-order">
                                    <a href="<?php echo esc_url($edit_url); ?>" target="_blank">
                                        <strong>#<?php echo esc_html($order_id); ?></strong>
                                    </a><br>
                                    <span class="order-date"><?php echo esc_html($order->get_date_created()->date('Y-m-d H:i')); ?></span>
                                </td>

                                <td class="column-status">
                                    <span class="order-status status-<?php echo esc_attr($order_status); ?>">
                                        <?php echo esc_html($status_name); ?>
                                    </span>
                                </td>

                                <td class="column-buyer">
                                    <div class="mini-row copy-target"><?php echo esc_html($order->get_billing_first_name()); ?></div>
                                    <div class="mini-row copy-target"><?php echo esc_html($order->get_billing_last_name()); ?></div>
                                    <div class="mini-row copy-target"><?php echo esc_html($order->get_billing_phone()); ?></div>
                                </td>

                                <td class="column-shipping">
                                    <div class="mini-row copy-target"><?php echo esc_html($order->get_shipping_first_name()); ?></div>
                                    <div class="mini-row copy-target"><?php echo esc_html($order->get_shipping_last_name()); ?></div>
                                    <div class="mini-row copy-target"><?php echo esc_html($order->get_shipping_phone() ?: $order->get_billing_phone()); ?></div>
                                </td>

                                <td class="column-items">
                                    <?php foreach ($order->get_items() as $item): ?>
                                        <div class="mini-row">
                                            <strong><?php echo esc_html($item->get_name()); ?></strong> x <?php echo esc_html($item->get_quantity()); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="mini-row"><?php echo esc_html($order->get_shipping_method()); ?></div>
                                </td>

                                <td class="column-address">
                                    <div class="copy-target"><?php echo esc_html($delivery_address); ?></div>
                                </td>

                                <td class="column-reservation">
                                    <?php echo esc_html($reservation_date ? date('Y-m-d', strtotime($reservation_date)) : '-'); ?>
                                </td>

                                <td class="column-actions">
                                    <?php if ($order_status !== 'completed'): ?>
                                        <button type="button" class="button button-primary btn-send-order"
                                            data-order-id="<?php echo esc_attr($order_id); ?>">
                                            Išsiųsti užsakymą
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($maps_url): ?>
                                        <a href="<?php echo esc_url($maps_url); ?>" class="button" target="_blank">
                                            Žiūrėti žemėlapyje
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Ankstesnis',
                            'next_text' => 'Kitas &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'type' => 'plain'
                        ]);

                        if ($page_links) {
                            echo '<span class="displaying-num">' . sprintf('%s įrašų', number_format_i18n($total)) . '</span>';
                            echo $page_links;
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Export -->
            <div class="dpd-fresh-export-section">
                <h2>Eksportuoti užsakymus</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="dpd_fresh_export_orders">
                    <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                    <input type="hidden" name="order_status" value="<?php echo esc_attr($status); ?>">
                    <input type="hidden" name="reservation_date" value="<?php echo esc_attr($res_date); ?>">
                    <input type="hidden" name="sort" value="<?php echo esc_attr($sort); ?>">
                    <?php wp_nonce_field('dpd_fresh_export_orders', 'dpd_fresh_export_nonce'); ?>
                    <button type="submit" class="button button-primary">Eksportuoti į Excel</button>
                </form>
            </div>
        </div>

        <?php include DPD_FRESH_PLUGIN_DIR . 'admin/partials/popup-send-order.php'; ?>
        <?php
    }

    public static function export() {
        $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
        $status = isset($_POST['order_status']) ? sanitize_text_field($_POST['order_status']) : '';
        $res_date = isset($_POST['reservation_date']) ? sanitize_text_field($_POST['reservation_date']) : '';
        $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'reservation_desc';

        switch ($sort) {
            case 'reservation_asc':
                $orderby = 'reservation_date';
                $order = 'ASC';
                break;
            case 'id_asc':
                $orderby = 'ID';
                $order = 'ASC';
                break;
            case 'id_desc':
                $orderby = 'ID';
                $order = 'DESC';
                break;
            case 'reservation_desc':
            default:
                $orderby = 'reservation_date';
                $order = 'DESC';
                break;
        }

        $result = DPD_Fresh_Database::get_filtered_orders([
            'limit' => -1,
            'status' => $status,
            'search' => $search,
            'date_from' => $res_date,
            'date_to' => $res_date,
            'orderby' => $orderby,
            'order' => $order
        ]);

        $orders = $result['orders'];

        if (empty($orders)) {
            wp_die('Užsakymų eksportui nerasta.');
        }

        // Header row
        $data = [[
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>UŽSAKYMO ID</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>BŪSENA</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>PIRKĖJO VARDAS</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>PIRKĖJO PAVARDĖ</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>PIRKĖJO TELEFONAS</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>GAVĖJO VARDAS</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>GAVĖJO PAVARDĖ</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>GAVĖJO TELEFONAS</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>DAIKTAI</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>PRISTATYMO ADRESAS</b></center></style>',
            '<style bgcolor="#4472C4" color="#FFFFFF" font-size="12" border="thin"><center><b>REZERVACIJOS DATA</b></center></style>'
        ]];

        foreach ($orders as $order) {
            $items_str = '';
            foreach ($order->get_items() as $item) {
                $items_str .= $item->get_name() . ' x ' . $item->get_quantity() . '; ';
            }
            $items_str = rtrim($items_str, '; ');

            $res_date_val = $order->get_meta('reservation_date', true);
            $res_date_fmt = $res_date_val ? '<raw>' . date('Y-m-d', strtotime($res_date_val)) . '</raw>' : '';

            $billing_phone = $order->get_billing_phone();
            if ($billing_phone) {
                if (substr($billing_phone, 0, 1) !== '+') {
                    $billing_phone = '+' . $billing_phone;
                }
                $billing_phone = '<raw>' . $billing_phone . '</raw>';
            }

            $shipping_phone = $order->get_shipping_phone() ?: $order->get_billing_phone();
            if ($shipping_phone) {
                if (substr($shipping_phone, 0, 1) !== '+') {
                    $shipping_phone = '+' . $shipping_phone;
                }
                $shipping_phone = '<raw>' . $shipping_phone . '</raw>';
            }

            $data[] = [
                '<style font-size="12" border="thin">' . $order->get_id() . '</style>',
                '<style font-size="12" border="thin">' . wc_get_order_status_name($order->get_status()) . '</style>',
                '<style font-size="12" border="thin">' . $order->get_billing_first_name() . '</style>',
                '<style font-size="12" border="thin">' . $order->get_billing_last_name() . '</style>',
                '<style font-size="12" border="thin">' . $billing_phone . '</style>',
                '<style font-size="12" border="thin"><b>' . $order->get_shipping_first_name() . '</b></style>',
                '<style font-size="12" border="thin"><b>' . $order->get_shipping_last_name() . '</b></style>',
                '<style font-size="12" border="thin"><b>' . $shipping_phone . '</b></style>',
                '<style font-size="12" border="thin">' . $items_str . '</style>',
                '<style font-size="12" border="thin"><b>' . $order->get_meta('dpd_fresh_delivery_address', true) . '</b></style>',
                '<style font-size="12" border="thin">' . $res_date_fmt . '</style>'
            ];
        }

        $xlsx = Shuchkin\SimpleXLSXGen::fromArray($data);
        $xlsx->setDefaultFontSize(12);
        $xlsx->setColWidth(1, 15);
        $xlsx->setColWidth(2, 18);
        $xlsx->setColWidth(3, 20);
        $xlsx->setColWidth(4, 20);
        $xlsx->setColWidth(5, 18);
        $xlsx->setColWidth(6, 20);
        $xlsx->setColWidth(7, 20);
        $xlsx->setColWidth(8, 18);
        $xlsx->setColWidth(9, 40);
        $xlsx->setColWidth(10, 50);
        $xlsx->setColWidth(11, 15);

        $filename = 'dpd-fresh-orders-' . date('Y-m-d_H-i') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $xlsx->downloadAs($filename);
        exit;
    }
}

