<?php
/**
 * DPD Fresh Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPD_Fresh_Admin_Settings {

    private static $option_name = 'dpd_fresh_settings';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function register_settings() {
        register_setting(
            'dpd_fresh_settings_group',
            self::$option_name,
            [__CLASS__, 'sanitize_settings']
        );
    }

    public static function sanitize_settings($input) {
        // Checkbox: if not set or not '1', it's disabled (0)
        $enable_custom_dates = 0;
        if (isset($input['enable_custom_dates']) && $input['enable_custom_dates'] == '1') {
            $enable_custom_dates = 1;
        }
        
        $sanitized = [
            'enable_custom_dates' => $enable_custom_dates,
            'reservation_dates' => []
        ];

        if (isset($input['reservation_dates']) && is_array($input['reservation_dates'])) {
            foreach ($input['reservation_dates'] as $date_entry) {
                if (!empty($date_entry['date']) && !empty($date_entry['text'])) {
                    $sanitized['reservation_dates'][] = [
                        'date' => sanitize_text_field($date_entry['date']),
                        'text' => sanitize_text_field($date_entry['text'])
                    ];
                }
            }
        }

        return $sanitized;
    }

    public static function get_settings() {
        $defaults = [
            'enable_custom_dates' => 0,
            'reservation_dates' => []
        ];

        $settings = get_option(self::$option_name, $defaults);
        return wp_parse_args($settings, $defaults);
    }

    public static function render() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Neturite teisių peržiūrėti šį puslapį.');
        }

        // Handle form submission
        if (isset($_POST['dpd_fresh_save_settings']) && check_admin_referer('dpd_fresh_settings_nonce')) {
            $input_data = isset($_POST['dpd_fresh_settings']) ? $_POST['dpd_fresh_settings'] : [];
            $settings = self::sanitize_settings($input_data);
            update_option(self::$option_name, $settings);
            echo '<div class="notice notice-success is-dismissible"><p>Nustatymai išsaugoti!</p></div>';
        }

        $settings = self::get_settings();
        $reservation_dates = $settings['reservation_dates'];
        if (empty($reservation_dates)) {
            $reservation_dates = [['date' => '', 'text' => '']];
        }

        ?>
        <div class="wrap">
            <h1>DPD Fresh Nustatymai</h1>
            
            <form method="post" action="" id="dpd-fresh-settings-form">
                <?php wp_nonce_field('dpd_fresh_settings_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_custom_dates">Naudoti tik nurodytas reservation_date</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="dpd_fresh_settings[enable_custom_dates]" 
                                           id="enable_custom_dates"
                                           value="1" 
                                           <?php checked($settings['enable_custom_dates'], 1); ?>>
                                    Įjungti privalomas rezervacijos datas
                                </label>
                                <p class="description">
                                    Jei įjungta, tik nurodytos datos bus rodomos reservation_date lauke, kai pasirinktas DPD Fresh pristatymas.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2>Rezervacijos datos</h2>
                <table class="wp-list-table widefat fixed striped" id="dpd-fresh-dates-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Data (MM/DD/YYYY)</th>
                            <th style="width: 45%;">Rodomas tekstas</th>
                            <th style="width: 20%;">Veiksmai</th>
                        </tr>
                    </thead>
                    <tbody id="dpd-fresh-dates-list">
                        <?php foreach ($reservation_dates as $index => $date_entry): ?>
                            <tr class="dpd-fresh-date-row" data-index="<?php echo esc_attr($index); ?>">
                                <td>
                                    <input type="text" 
                                           name="dpd_fresh_settings[reservation_dates][<?php echo esc_attr($index); ?>][date]" 
                                           value="<?php echo esc_attr($date_entry['date']); ?>" 
                                           placeholder="MM/dd/YYYY"
                                           class="regular-text">
                                </td>
                                <td>
                                    <input type="text" 
                                           name="dpd_fresh_settings[reservation_dates][<?php echo esc_attr($index); ?>][text]" 
                                           value="<?php echo esc_attr($date_entry['text']); ?>" 
                                           placeholder="Mėnesis d."
                                           class="regular-text">
                                </td>
                                <td>
                                    <button type="button" class="button button-secondary dpd-remove-date-row">Pašalinti</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" class="button button-secondary" id="dpd-add-date-row">Pridėti datą</button>
                </p>

                <p class="submit">
                    <input type="submit" name="dpd_fresh_save_settings" class="button button-primary" value="Išsaugoti nustatymus">
                </p>
            </form>
        </div>
        <?php
    }
}

