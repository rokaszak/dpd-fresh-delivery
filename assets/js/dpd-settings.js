jQuery(document).ready(function($) {
    var rowIndex = $('#dpd-fresh-dates-list .dpd-fresh-date-row').length;
    
    // Add new date row
    $('#dpd-add-date-row').on('click', function(e) {
        e.preventDefault();
        
        var newRow = $('<tr class="dpd-fresh-date-row" data-index="' + rowIndex + '">' +
            '<td><input type="text" name="dpd_fresh_settings[reservation_dates][' + rowIndex + '][date]" value="" placeholder="MM/dd/YYYY" class="regular-text"></td>' +
            '<td><input type="text" name="dpd_fresh_settings[reservation_dates][' + rowIndex + '][text]" value="" placeholder="Mėnesis d." class="regular-text"></td>' +
            '<td><button type="button" class="button button-secondary dpd-remove-date-row">Pašalinti</button></td>' +
            '</tr>');
        
        $('#dpd-fresh-dates-list').append(newRow);
        rowIndex++;
    });
    
    // Remove date row
    $(document).on('click', '.dpd-remove-date-row', function(e) {
        e.preventDefault();
        
        var $row = $(this).closest('.dpd-fresh-date-row');
        $row.fadeOut(300, function() {
            $(this).remove();
            reindexRows();
        });
    });
    
    // Re-index rows after removal
    function reindexRows() {
        $('#dpd-fresh-dates-list .dpd-fresh-date-row').each(function(index) {
            var $row = $(this);
            $row.attr('data-index', index);
            
            // Update input names
            $row.find('input[name*="[date]"]').attr('name', 'dpd_fresh_settings[reservation_dates][' + index + '][date]');
            $row.find('input[name*="[text]"]').attr('name', 'dpd_fresh_settings[reservation_dates][' + index + '][text]');
        });
        
        // Update rowIndex to prevent conflicts
        rowIndex = $('#dpd-fresh-dates-list .dpd-fresh-date-row').length;
    }
    
    // Form validation
    $('#dpd-fresh-settings-form').on('submit', function(e) {
        var hasError = false;
        var errorMessages = [];
        
        $('#dpd-fresh-dates-list .dpd-fresh-date-row').each(function() {
            var $row = $(this);
            var date = $row.find('input[name*="[date]"]').val().trim();
            var text = $row.find('input[name*="[text]"]').val().trim();
            
            if (date && !text) {
                hasError = true;
                errorMessages.push('Kaiviena data turi turėti rodomą tekstą.');
                return false;
            }
            
            if (text && !date) {
                hasError = true;
                errorMessages.push('Kai vienas tekstas turi turėti datą.');
                return false;
            }
            
            // Validate date format (MM/DD/YYYY)
            if (date) {
                var dateRegex = /^(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])\/\d{4}$/;
                if (!dateRegex.test(date)) {
                    hasError = true;
                    errorMessages.push('Data "' + date + '" turi būti formatu MM/DD/YYYY (pvz., 11/28/2025).');
                }
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Rastos klaidos:\n\n' + errorMessages.join('\n'));
            return false;
        }
    });
});

