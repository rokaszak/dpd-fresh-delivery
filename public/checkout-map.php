<?php
/**
 * DPD Fresh Map Selector Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wc-dpd-fresh-map-selector" id="dpd-fresh-map-wrapper" style="display:none;">
    <h3>Pasirinkite pristatymo vietą žemėlapyje</h3>

    <div id="dpd_fresh_map" style="height: 400px; width: 100%; margin-bottom: 15px;"></div>

    <input type="hidden" id="dpd_fresh_latitude" name="dpd_fresh_latitude" value="">
    <input type="hidden" id="dpd_fresh_longitude" name="dpd_fresh_longitude" value="">

    <div id="dpd_fresh_info_label" style="border-radius: 1rem; background-color: red; color: white; padding: 1rem; margin: 0 0 1rem 0; line-height: 1.25; font-weight: 500;">
        DPD Fresh kurjeris pristatys jūsų užsakymą į nurodytą adresą pasirinktą rezervacijos dieną. Būtinai įsitikinkite, kad galėsite priimti siuntą tą dieną - ją privaloma paimti iš karto, kai kurjeris atveš. Pakartotinis pristatymas negalimas dėl trumpo galiojimo produktų.
    </div>

    <div>
        <?php
        woocommerce_form_field('dpd_fresh_delivery_address', [
            'type' => 'text',
            'class' => ['form-row-wide'],
            'label' => 'Pristatymo adresas',
            'placeholder' => 'Įveskite adresą arba pasirinkite vietą žemėlapyje',
            'required' => true,
            'custom_attributes' => [
                'id' => 'dpd_fresh_delivery_address',
                'autocomplete' => 'off'
            ]
        ], WC()->checkout->get_value('dpd_fresh_delivery_address'));
        ?>
    </div>
</div>

