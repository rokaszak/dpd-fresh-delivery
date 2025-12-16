<?php
/**
 * The public-facing email functionality of the plugin.
 *
 * @link       https://proven.lt
 * @since      2.0.0
 *
 * @package    DPD_Fresh_Delivery
 * @subpackage DPD_Fresh_Delivery/public
 */

/**
 * The public-facing email functionality of the plugin.
 *
 * Defines email hooks for adding tracking number and delivery address to WooCommerce emails.
 *
 * @package    DPD_Fresh_Delivery
 * @subpackage DPD_Fresh_Delivery/public
 * @author     Rokas Zakarauskas
 */
class DPD_Fresh_Public_Email {

	/**
	 * The ID of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Add tracking number to email before order table.
	 *
	 * @since    2.0.0
	 * @param      WC_Order    $order           The order object.
	 * @param      bool        $sent_to_admin   Whether email is sent to admin.
	 * @param      bool        $plain_text      Whether email is plain text.
	 * @param      WC_Email    $email           The email object.
	 */
	public function add_dpd_fresh_tracking_number( $order, $sent_to_admin, $plain_text, $email ) {
		$tracking = $order->get_meta('dpd_fresh_tracking_number', true);
		
		if (empty($tracking)) {
			return;
		}

		if ($plain_text) {
			echo "\n\n" . __( 'DPD Fresh siuntos numeris:', 'dpd-fresh-delivery' ) . ' ' . esc_html($tracking) . "\n\n";
		} else {
			echo '<p>' . __( 'DPD Fresh siuntos numeris:', 'dpd-fresh-delivery' ) . ' <strong>' . esc_html($tracking) . '</strong></p>';
		}
	}

	/**
	 * Add delivery address info to email after order table.
	 *
	 * @since    2.0.0
	 * @param      WC_Order    $order           The order object.
	 * @param      bool        $sent_to_admin   Whether email is sent to admin.
	 * @param      bool        $plain_text      Whether email is plain text.
	 * @param      WC_Email    $email           The email object.
	 */
	public function add_dpd_fresh_delivery_address( $order, $sent_to_admin, $plain_text, $email ) {
		$delivery_address = $order->get_meta('dpd_fresh_delivery_address', true);
		
		if (empty($delivery_address)) {
			return;
		}

		if ($plain_text) {
			echo "\n\n" . __( 'Pristatymo adresas:', 'dpd-fresh-delivery' ) . "\n";
			echo esc_html($delivery_address) . "\n\n";
		} else {
			echo '<h2>' . __( 'Pristatymo adresas', 'dpd-fresh-delivery' ) . ':</h2>';
			echo '<p>' . esc_html($delivery_address) . '</p>';
		}
	}
}

