jQuery(document).ready(function($) {

    // Copy to clipboard
    $('.copy-target').on('click', function() {
        var $this = $(this);
        var text = $this.text().trim();

        if (!text) return;

        navigator.clipboard.writeText(text).then(function() {
            var notification = $('<span class="copy-notification">Nukopijuota: ' + text + '</span>');
            $this.append(notification);

            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 1000);
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
        });
    });

    // Send Order Popup
    var activeOrderId = null;
    var $popup = $('#dpd-fresh-send-order-popup');
    var $overlay = $('.dpd-fresh-popup-overlay');

    $('.btn-send-order').on('click', function(e) {
        e.preventDefault();
        activeOrderId = $(this).data('order-id');

        $('#dpd-fresh-tracking-number').val('');
        $('.dpd-fresh-popup-error').hide();

        $popup.css('display', 'flex');
        $overlay.show();

        $('#dpd-fresh-tracking-number').focus();
    });

    // Close popup
    $('.dpd-fresh-popup-close, .dpd-fresh-popup-overlay, .dpd-fresh-popup-cancel').on('click', function(e) {
        e.preventDefault();
        $popup.hide();
        $overlay.hide();
        activeOrderId = null;
    });

    // Submit
    $('#dpd-fresh-send-order-submit').on('click', function(e) {
        e.preventDefault();

        if (!activeOrderId) return;

        var trackingNumber = $('#dpd-fresh-tracking-number').val().trim();

        if (!trackingNumber) {
            alert('Įveskite siuntos numerį');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).addClass('updating');

        $.ajax({
            url: dpd_fresh_params.ajax_url,
            type: 'POST',
            data: {
                action: 'dpd_fresh_send_order',
                nonce: dpd_fresh_params.nonce,
                order_id: activeOrderId,
                tracking_number: trackingNumber
            },
            success: function(response) {
                if (response.success) {
                    $popup.hide();
                    $overlay.hide();

                    var $row = $('tr[data-order-id="' + activeOrderId + '"]');
                    var $statusCell = $row.find('.column-status .order-status');

                    $statusCell
                        .removeClass(function(index, className) {
                            return (className.match(/(^|\s)status-\S+/g) || []).join(' ');
                        })
                        .addClass('status-completed')
                        .text('Įvykdytas');

                    // Hide the send button
                    $row.find('.btn-send-order').hide();

                    alert('Užsakymas sėkmingai atnaujintas!');
                } else {
                    $('.dpd-fresh-popup-error').text(response.data.message || dpd_fresh_params.error_text).show();
                }
            },
            error: function() {
                $('.dpd-fresh-popup-error').text(dpd_fresh_params.error_text).show();
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('updating');
            }
        });
    });
});

