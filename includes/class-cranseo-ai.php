<?php
class CranSEO_AI {
    private $api_key;
    private $api_url = 'https://cranseo.com/wp-json/cranseo/v1/generate';
    private $quota_check_url = 'https://cranseo.com/wp-json/cranseo/v1/quota-check';
    private $license_key;

    public function __construct() {
        $this->api_key = get_option('cranseo_openai_key');
        $this->license_key = get_option('cranseo_saas_license_key');
    }

    /**
     * Get remaining quota by checking with API server
     */
    public function get_remaining_quota() {
        $quota_info = $this->check_quota();
        return $quota_info['remaining'] ?? 0;
    }

    /**
     * Check quota with API server
     */
    private function check_quota() {
        if (empty($this->license_key)) {
            return array(
                'within_quota' => false,
                'remaining' => 0,
                'limit' => 10,
                'message' => 'No license key found'
            );
        }

        $response = wp_remote_post($this->quota_check_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'license_key' => $this->license_key,
                'site_url' => home_url()
            )),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            error_log('Quota check failed: ' . $response->get_error_message());
            return array(
                'within_quota' => false,
                'remaining' => 0,
                'limit' => 10,
                'message' => 'Quota check failed'
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code === 200) {
            return array(
                'within_quota' => $body['within_quota'] ?? false,
                'remaining' => $body['remaining'] ?? 0,
                'limit' => $body['limit'] ?? 10,
                'message' => $body['message'] ?? '',
                'license_tier' => $body['license_tier'] ?? 'basic'
            );
        }

        error_log('Quota check failed with status: ' . $status_code);
        return array(
            'within_quota' => false,
            'remaining' => 0,
            'limit' => 10,
            'message' => 'Quota check failed'
        );
    }

    /**
     * Update quota usage - REMOVED because quota is now managed by API server
     */
    public function update_quota_usage() {
        // Quota is now managed by the API server, so this method is no longer needed
        // The API server updates quota when content is generated
        return true;
    }

    /**
     * Reset quota usage - REMOVED because quota is now managed by API server
     */
    public function reset_quota_usage() {
        // Quota is now managed by the API server
        return true;
    }

    public function generate_content($post_id, $content_type) {
        // Check if API key is configured
        if (empty($this->api_key)) {
            throw new Exception(__('OpenAI API key not configured', 'cranseo'));
        }

        // Check quota with API server
        $quota_info = $this->check_quota();
        if (!$quota_info['within_quota']) {
            throw new Exception(__('You have exceeded your monthly quota. Please upgrade your plan to generate more content.', 'cranseo'));
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            throw new Exception(__('Product not found', 'cranseo'));
        }

        // Build the prompt based on content type
        $prompt = $this->build_prompt($product, $content_type);
        $max_tokens = $this->get_max_tokens($content_type);

        // Send request to CranSEO API server
        $response = $this->call_cranseo_api($prompt, $max_tokens, $content_type);

        return $response;
    }

    private function build_prompt($product, $content_type) {
        switch ($content_type) {
            case 'title':
                return $this->build_title_prompt($product);
            case 'short_description':
                return $this->build_short_desc_prompt($product);
            case 'full_description':
                return $this->build_full_desc_prompt($product);
            default:
                throw new Exception(__('Invalid content type', 'cranseo'));
        }
    }

    private function get_max_tokens($content_type) {
        $tokens = array(
            'title' => 60,
            'short_description' => 200,
            'full_description' => 1200
        );
        
        return isset($tokens[$content_type]) ? $tokens[$content_type] : 200;
    }

    private function build_title_prompt($product) {
        $attributes = $this->get_product_attributes($product);
        
        return sprintf(
            "Create an SEO-optimized product title between 40-70 characters for a %s.\n" .
            "Product Name: %s\n" .
            "Key Features: %s\n" .
            "Target Audience: %s\n" .
            "Current Title: %s\n\n" .
            "Requirements:\n" .
            "- Include primary keywords\n" .
            "- Be compelling and descriptive\n" .
            "- Length: 40-70 characters\n" .
            "- No special characters or emojis",
            $product->get_type(),
            $product->get_name(),
            implode(', ', array_slice($attributes, 0, 3)),
            $this->get_target_audience($product),
            $product->get_name()
        );
    }

    private function build_short_desc_prompt($product) {
        $attributes = $this->get_product_attributes($product);
        
        return sprintf(
            "Write an engaging product short description (50-150 words) in HTML format.\n" .
            "Product: %s\n" .
            "Price: %s\n" .
            "Key Benefits: %s\n" .
            "Target Audience: %s\n" .
            "Current Description: %s\n\n" .
            "Requirements:\n" .
            "- Use <p> tags for paragraphs\n" .
            "- Include 2-3 key benefits as bullet points using <ul><li>\n" .
            "- Focus on customer benefits, not just features\n" .
            "- Include a call-to-action\n" .
            "- Length: 50-150 words",
            $product->get_name(),
            $product->get_price() ? wc_price($product->get_price()) : 'Not specified',
            implode(', ', array_slice($attributes, 0, 3)),
            $this->get_target_audience($product),
            $product->get_short_description()
        );
    }

    private function build_full_desc_prompt($product) {
        $attributes = $this->get_product_attributes($product);
        $category = $this->get_product_category($product);
        
        return sprintf(
            "Create a comprehensive product description in HTML format with the following EXACT structure:\n\n" .
            "## Product Overview\n" .
            "[2-3 paragraph introduction about the product and its main benefits]\n\n" .
            "## Product Features\n" .
            "<ul>\n" .
            "<li>[Feature 1 with benefits]</li>\n" .
            "<li>[Feature 2 with benefits]</li>\n" .
            "<li>[Feature 3 with benefits]</li>\n" .
            "<li>[Feature 4 with benefits]</li>\n" .
            "<li>[Feature 5 with benefits]</li>\n" .
            "</ul>\n\n" .
            "## Product Details\n" .
            "<ul>\n" .
            "<li>[Detail 1: Material/Construction]</li>\n" .
            "<li>[Detail 2: Dimensions/Size]</li>\n" .
            "<li>[Detail 3: Color/Design]</li>\n" .
            "<li>[Detail 4: Technical Specifications]</li>\n" .
            "<li>[Detail 5: Usage/Application]</li>\n" .
            "</ul>\n\n" .
            "## Frequently Asked Questions\n" .
            "<ul>\n" .
            "<li><strong>Q: [Question 1]</strong><br>A: [Answer 1]</li>\n" .
            "<li><strong>Q: [Question 2]</strong><br>A: [Answer 2]</li>\n" .
            "<li><strong>Q: [Question 3]</strong><br>A: [Answer 3]</li>\n" .
            "<li><strong>Q: [Question 4]</strong><br>A: [Answer 4]</li>\n" .
            "<li><strong>Q: [Question 5]</strong><br>A: [Answer 5]</li>\n" .
            "</ul>\n\n" .
            "Product Information:\n" .
            "Name: %s\n" .
            "Category: %s\n" .
            "Price: %s\n" .
            "Key Attributes: %s\n" .
            "Target Audience: %s\n" .
            "Current Description: %s\n\n" .
            "Requirements:\n" .
            "- Use EXACT H2 headings as shown above\n" .
            "- Each H2 section must contain exactly 5 list items\n" .
            "- Use proper HTML formatting with <ul><li> for lists\n" .
            "- Include benefits and practical applications\n" .
            "- Total length: 250-600 words\n" .
            "- Write in a professional, engaging tone",
            $product->get_name(),
            $category,
            $product->get_price() ? wc_price($product->get_price()) : 'Not specified',
            implode(', ', $attributes),
            $this->get_target_audience($product),
            $product->get_description()
        );
    }

    private function call_cranseo_api($prompt, $max_tokens, $content_type) {
        $request_data = array(
            'prompt' => $prompt,
            'max_tokens' => $max_tokens,
            'content_type' => $content_type,
            'license_key' => $this->license_key,
            'site_url' => home_url(),
            'api_key' => $this->api_key
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            throw new Exception('Network error: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_message = 'API error (Code: ' . $status_code . ')';
            if (isset($body['error'])) {
                $error_message = $body['error'];
            } elseif (isset($body['message'])) {
                $error_message = $body['message'];
            }
            throw new Exception($error_message);
        }

        if (!isset($body['content'])) {
            throw new Exception('Invalid response format from CranSEO API');
        }

        $content = trim($body['content']);
        
        // Validate and clean up HTML structure
        $content = $this->validate_html_structure($content);
        
        return $content;
    }

    private function get_product_attributes($product) {
        $attributes = array();
        
        // Get product attributes
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                foreach ($variation['attributes'] as $attribute) {
                    if ($attribute) $attributes[] = $attribute;
                }
            }
        } else {
            $product_attributes = $product->get_attributes();
            foreach ($product_attributes as $attribute) {
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                    foreach ($terms as $term) {
                        $attributes[] = $term->name;
                    }
                } else {
                    $options = $attribute->get_options();
                    $attributes = array_merge($attributes, $options);
                }
            }
        }
        
        // Get product tags
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        $attributes = array_merge($attributes, $tags);
        
        // Get product categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $attributes = array_merge($attributes, $categories);
        
        return array_unique(array_filter($attributes));
    }

    private function get_product_category($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        return !empty($categories) ? $categories[0] : 'General';
    }

    private function get_target_audience($product) {
        $price = $product->get_price();
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        
        if ($price > 100) {
            return 'Premium customers looking for quality';
        } elseif ($price > 50) {
            return 'Mid-range buyers seeking value';
        } else {
            return 'Budget-conscious shoppers';
        }
    }

    private function validate_html_structure($content) {
        // Ensure H2 headings are properly formatted
        $content = preg_replace('/<h2[^>]*>(.*?)<\/h2>/i', '<h2>$1</h2>', $content);
        
        // Ensure lists are properly formatted
        $content = preg_replace('/<ul>\s*<li>/i', '<ul><li>', $content);
        $content = preg_replace('/<\/li>\s*<\/ul>/i', '</li></ul>', $content);
        
        // Add missing HTML tags if necessary
        if (strpos($content, '<h2>') === false) {
            $sections = array(
                'Product Overview',
                'Product Features',
                'Product Details', 
                'Frequently Asked Questions'
            );
            
            $structured_content = '';
            foreach ($sections as $section) {
                $structured_content .= "<h2>{$section}</h2>\n";
                if ($section === 'Product Overview') {
                    $structured_content .= "<p>[Product overview content]</p>\n";
                } else {
                    $structured_content .= "<ul>\n<li>[List item 1]</li>\n<li>[List item 2]</li>\n<li>[List item 3]</li>\n<li>[List item 4]</li>\n<li>[List item 5]</li>\n</ul>\n";
                }
            }
            $content = $structured_content;
        }
        
        return $content;
    }
}