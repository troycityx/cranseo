jQuery(document).ready(function($) {
    // Real-time API key validation
    $('input[name="cranseo_openai_key"]').on('input', function() {
        var apiKey = $(this).val();
        var $status = $('.cranseo-status-indicator');
        
        if (apiKey.length === 0) {
            $status.removeClass('status-valid status-invalid').addClass('status-missing')
                  .text('Not configured');
            return;
        }
        
        // Simple format validation
        if (/^sk-[a-zA-Z0-9]{48}$/.test(apiKey)) {
            $status.removeClass('status-missing status-invalid').addClass('status-valid')
                  .text('Valid format');
        } else {
            $status.removeClass('status-missing status-valid').addClass('status-invalid')
                  .text('Invalid format');
        }
    });

    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 20
            }, 1000);
        }
    });

    // Enhanced submit button animation
    $('.cranseo-settings-form').on('submit', function() {
        var $button = $('#submit');
        $button.prop('disabled', true)
               .text('Saving...')
               .css('opacity', '0.8');
        
        setTimeout(function() {
            $button.css({
                'background': 'linear-gradient(135deg, #4CAF50 0%, #45a049 100%)',
                'border-color': '#4CAF50',
                'transform': 'scale(0.98)'
            });
        }, 100);
    });

    // Tooltip functionality
    $('.cranseo-card h3').on('mouseenter', function() {
        $(this).css('cursor', 'help');
    }).on('click', function() {
        var $content = $(this).next('.cranseo-card-content');
        $content.slideToggle(300);
    });

    // Auto-focus on API key field if empty
    if ($('input[name="cranseo_openai_key"]').val().length === 0) {
        $('input[name="cranseo_openai_key"]').focus();
    }
});