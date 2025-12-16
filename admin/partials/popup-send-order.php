<?php
/**
 * Send Order Popup Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="dpd-fresh-popup-overlay" style="display:none;"></div>

<div id="dpd-fresh-send-order-popup" class="dpd-fresh-popup" style="display:none;">
    <div class="dpd-fresh-popup-header">
        <h3>Išsiųsti užsakymą</h3>
        <button type="button" class="dpd-fresh-popup-close">&times;</button>
    </div>

    <div class="dpd-fresh-popup-content">
        <div class="dpd-fresh-form-group">
            <label for="dpd-fresh-tracking-number">Siuntos numeris</label>
            <input type="text" id="dpd-fresh-tracking-number" class="regular-text"
                placeholder="Įveskite siuntos numerį">
        </div>

        <div class="dpd-fresh-popup-error" style="display:none; color: red; margin-top: 10px;"></div>
    </div>

    <div class="dpd-fresh-popup-footer">
        <button type="button" class="button dpd-fresh-popup-cancel">Atšaukti</button>
        <button type="button" class="button button-primary" id="dpd-fresh-send-order-submit">Išsiųsti užsakymą</button>
    </div>
</div>

