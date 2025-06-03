jQuery(document).ready(function($) {
    // Clear cache button
    $('.rcs-clear-cache').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        
        $spinner.addClass('is-active');
        $button.prop('disabled', true);
        
        $.post(rucsAdmin.ajaxurl, {
            action: 'rucs_clear_cache',
            nonce: rucsAdmin.clearCacheNonce
        }, function(response) {
            $spinner.removeClass('is-active');
            
            if (response.success) {
                alert(rucsAdmin.successText);
                location.reload();
            } else {
                alert(rucsAdmin.errorText);
                $button.prop('disabled', false);
            }
        }).fail(function() {
            $spinner.removeClass('is-active');
            alert(rucsAdmin.errorText);
            $button.prop('disabled', false);
        });
    });
});