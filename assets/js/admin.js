jQuery(document).ready(function($) {
    // Recheck functionality
    $('.cranseo-recheck').on('click', function() {
        var $button = $(this);
        var postId = $button.data('post-id');
        
        $button.prop('disabled', true).text('Checking...');
        
        $.post(cranseo_ajax.ajax_url, {
            action: 'cranseo_check_product',
            nonce: cranseo_ajax.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $('.cranseo-rules').html(response.data.html);
            } else {
                alert('Error: ' + response.data);
            }
        }).always(function() {
            $button.prop('disabled', false).text('Recheck');
        });
    });

    // AI Content Generation
    $('#cranseo-generate-content').on('click', function() {
        var $button = $(this);
        var contentType = $('#cranseo-content-type').val();
        var postId = cranseo_ajax.post_id;
        
        if (!contentType) {
            alert('Please select content type');
            return;
        }
        
        $button.prop('disabled', true).text('Generating...');
        $('#cranseo-ai-result').addClass('loading').show();
        $('#cranseo-ai-result pre').html('<em>Generating content... This may take a moment.</em>');
        
        $.post(cranseo_ajax.ajax_url, {
            action: 'cranseo_generate_content',
            nonce: cranseo_ajax.nonce,
            post_id: postId,
            content_type: contentType
        }, function(response) {
            if (response.success) {
                // Format the content for better display
                var formattedContent = response.data.content
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\n/g, '<br>')
                    .replace(/  /g, ' &nbsp;');
                
                $('#cranseo-ai-result pre').html(formattedContent);
                
                // Show preview toggle
                $('#cranseo-preview-toggle').show();
            } else {
                alert('Error: ' + response.data);
                $('#cranseo-ai-result').hide();
            }
        }).always(function() {
            $button.prop('disabled', false).text('Generate Content');
            $('#cranseo-ai-result').removeClass('loading');
        });
    });

    // Insert AI content
    $('#cranseo-insert-content').on('click', function() {
        var contentType = $('#cranseo-content-type').val();
        var content = $('#cranseo-ai-result pre').text();
        
        if (!content || content === '[Product overview content]') {
            alert('Please generate content first');
            return;
        }
        
        switch (contentType) {
            case 'title':
                $('#title').val(content);
                break;
            case 'short_description':
                if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                    tinymce.get('excerpt').setContent(content);
                } else {
                    $('#excerpt').val(content);
                }
                break;
            case 'full_description':
                if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                    tinymce.get('content').setContent(content);
                } else {
                    $('#content').val(content);
                }
                break;
        }
        
        alert('Content inserted! Don\'t forget to save the product.');
        
        // Auto-recheck after insertion
        $('.cranseo-recheck').click();
    });

    // Preview toggle functionality
    $(document).on('click', '#cranseo-preview-toggle', function() {
        var $pre = $('#cranseo-ai-result pre');
        var isHtml = $pre.data('is-html');
        
        if (isHtml) {
            // Switch to code view
            var content = $pre.html().replace(/<br>/g, '\n').replace(/&nbsp;/g, ' ');
            content = content.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
            $pre.html(content);
            $pre.data('is-html', false);
            $(this).text('Preview HTML');
        } else {
            // Switch to HTML preview
            var content = $pre.text();
            var formattedContent = content
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\n/g, '<br>')
                .replace(/  /g, ' &nbsp;');
            $pre.html(formattedContent);
            $pre.data('is-html', true);
            $(this).text('View Code');
        }
    });
});