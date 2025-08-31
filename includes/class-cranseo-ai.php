<?php
class CranSEO_AI {
    private $api_key;
    private $model;
    private $has_valid_license;

    public function __construct() {
        // Use your API key from wp-config.php
        $this->api_key = defined('CRANSEO_OPENAI_KEY') ? CRANSEO_OPENAI_KEY : '';
        
        // Get the selected model
        $this->model = get_option('cranseo_openai_model', 'gpt-3.5-turbo');
        
        // Check if user has a valid subscription
        $this->has_valid_license = cra_fs()->can_use_premium_code();
    }

    public function generate_content($post_id, $content_type) {
        // Check license first
        if (!$this->has_valid_license) {
            throw new Exception(__('Valid subscription required for AI features. Please upgrade your plan.', 'cranseo'));
        }
        
        if (empty($this->api_key)) {
            throw new Exception(__('AI service not configured', 'cranseo'));
        }

        $product = wc_get_product($post_id);

        if (!$product) {
            throw new Exception(__('Product not found', 'cranseo'));
        }

        switch ($content_type) {
            case 'title':
                return $this->generate_title($product);
            case 'short_description':
                return $this->generate_short_description($product);
            case 'full_description':
                return $this->generate_full_description($product);
            default:
                throw new Exception(__('Invalid content type', 'cranseo'));
        }
    }

    private function generate_title($product) {
        $prompt = $this->build_title_prompt($product);
        $result = $this->call_openai($prompt, 60);
        $this->track_usage(60); // Estimate token usage
        return $result;
    }

    private function generate_short_description($product) {
        $prompt = $this->build_short_desc_prompt($product);
        $result = $this->call_openai($prompt, 200);
        $this->track_usage(200); // Estimate token usage
        return $result;
    }

    private function generate_full_description($product) {
        $prompt = $this->build_full_desc_prompt($product);
        $result = $this->call_openai($prompt, 1200);
        $this->track_usage(1200); // Estimate token usage
        return $result;
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

    private function call_openai($prompt, $max_tokens) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $this->model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are an expert SEO content writer specializing in e-commerce product descriptions. ' .
                                    'Always respond with properly formatted HTML including headings, paragraphs, and lists. ' .
                                    'Follow the exact structure specified in the prompt. ' .
                                    'Use H2 headings exactly as requested and include minimum 5 list items per section.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => $max_tokens,
                'temperature' => 0.8
            )),
            'timeout' => 45
        ));

        if (is_wp_error($response)) {
            throw new Exception('Network error: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_message = 'API error (Code: ' . $status_code . ')';
            if (isset($body['error']['message'])) {
                $error_message = $body['error']['message'];
            } elseif (isset($body['error']['code'])) {
                $error_message = $body['error']['code'] . ': ' . ($body['error']['message'] ?? 'Unknown error');
            }
            throw new Exception($error_message);
        }

        if (isset($body['error'])) {
            throw new Exception($body['error']['message']);
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response format from OpenAI');
        }

        $content = trim($body['choices'][0]['message']['content']);
        
        // Ensure proper HTML structure
        $content = $this->validate_html_structure($content);
        
        return $content;
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

    // Track usage for billing/analytics
    private function track_usage($tokens_used) {
        if (!function_exists('cra_fs')) {
            return;
        }
        
        $customer_id = cra_fs()->get_user()->id;
        $usage = get_option('cranseo_ai_usage_' . $customer_id, 0);
        $usage += $tokens_used;
        update_option('cranseo_ai_usage_' . $customer_id, $usage);
        
        // Optional: Send to your analytics service
        // $this->send_usage_to_analytics($customer_id, $tokens_used);
    }
}