<?php
class CranSEO_Optimizer {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'cranseo-optimizer',
            __('CranSEO Optimizer', 'cranseo'),
            array($this, 'render_metabox'),
            'product',
            'normal',
            'high'
        );
    }

    public function render_metabox($post) {
        $results = $this->check_product($post->ID);
        ?>
        <div class="cranseo-optimizer">
            <div class="cranseo-scorecard">
                <h3><?php _e('SEO Scorecard', 'cranseo'); ?></h3>
                <div class="cranseo-rules">
                    <?php foreach ($results as $rule => $result): ?>
                        <div class="cranseo-rule">
                            <span class="cranseo-status <?php echo $result['passed'] ? 'passed' : 'failed'; ?>">
                                <?php echo $result['passed'] ? '✓' : '✗'; ?>
                            </span>
                            <span class="cranseo-rule-text"><?php echo $result['message']; ?></span>
                            <?php if (isset($result['current'])): ?>
                                <span class="cranseo-current">(<?php echo $result['current']; ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button button-primary cranseo-recheck" data-post-id="<?php echo $post->ID; ?>">
                    <?php _e('Recheck', 'cranseo'); ?>
                </button>
            </div>

            <div class="cranseo-ai-panel">
                <h4><?php _e('AI Content Writer', 'cranseo'); ?></h4>
                <div class="cranseo-ai-actions">
                    <select id="cranseo-content-type">
                        <option value=""><?php _e('Select content to generate', 'cranseo'); ?></option>
                        <option value="title"><?php _e('Rewrite Title', 'cranseo'); ?></option>
                        <option value="short_description"><?php _e('Rewrite Short Description', 'cranseo'); ?></option>
                        <option value="full_description"><?php _e('Rewrite Full Description', 'cranseo'); ?></option>
                    </select>
                    <button type="button" class="button button-secondary" id="cranseo-generate-content">
                        <?php _e('Generate Content', 'cranseo'); ?>
                    </button>
                </div>
            
<button type="button" class="button button-secondary" id="cranseo-preview-toggle" style="display: none; margin-left: 10px;">
    <?php _e('Preview HTML', 'cranseo'); ?>
</button>
                <div id="cranseo-ai-result" style="display: none;">
                    <h5><?php _e('Generated Content:', 'cranseo'); ?></h5>
                    <pre style="white-space: pre-wrap; background: #f6f6f6; padding: 10px; border-radius: 4px;"></pre>
                    <button type="button" class="button button-primary cranseo-ai-insert" id="cranseo-insert-content">
                        <?php _e('Insert into Product', 'cranseo'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public function check_product($post_id) {
        $product = wc_get_product($post_id);
        if (!$product) {
            return array();
        }

        $results = array();

        // Check title
        $title = $product->get_name();
        $title_length = mb_strlen($title);
        $results['title'] = array(
            'passed' => $title_length >= 40 && $title_length <= 70,
            'message' => __('Product Title: 40-70 characters', 'cranseo'),
            'current' => sprintf(__('%d characters', 'cranseo'), $title_length)
        );

        // Check short description
        $short_desc = $product->get_short_description();
        $short_desc_words = str_word_count(strip_tags($short_desc));
        $results['short_desc'] = array(
            'passed' => $short_desc_words >= 50 && $short_desc_words <= 150,
            'message' => __('Short Description: 50-150 words', 'cranseo'),
            'current' => sprintf(__('%d words', 'cranseo'), $short_desc_words)
        );

        // Check full description
        $full_desc = $product->get_description();
        $full_desc_words = str_word_count(strip_tags($full_desc));
        $results['full_desc'] = array(
            'passed' => $full_desc_words >= 250 && $full_desc_words <= 600,
            'message' => __('Full Description: 250-600 words', 'cranseo'),
            'current' => sprintf(__('%d words', 'cranseo'), $full_desc_words)
        );

        // Check H2 sections
        $h2_sections = $this->check_h2_sections($full_desc);
        $results = array_merge($results, $h2_sections);

        return $results;
    }

    private function check_h2_sections($content) {
        $results = array();
        
        // Extract H2 sections
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $content, $h2_matches);
        $h2_titles = array_map('strip_tags', $h2_matches[1]);

        // Check features section
        $features_found = false;
        $features_items = 0;
        foreach ($h2_titles as $index => $title) {
            if (preg_match('/features|key features|product features/i', $title)) {
                $features_found = true;
                $features_items = $this->count_list_items_after_h2($content, $h2_matches[0][$index]);
                break;
            }
        }

        $results['features'] = array(
            'passed' => $features_found && $features_items >= 5,
            'message' => __('Features H2 section with 5+ list items', 'cranseo'),
            'current' => $features_found ? sprintf(__('%d items', 'cranseo'), $features_items) : __('Not found', 'cranseo')
        );

        // Check details section
        $details_found = false;
        $details_items = 0;
        foreach ($h2_titles as $index => $title) {
            if (preg_match('/details|product details|description|characteristics/i', $title)) {
                $details_found = true;
                $details_items = $this->count_list_items_after_h2($content, $h2_matches[0][$index]);
                break;
            }
        }

        $results['details'] = array(
            'passed' => $details_found && $details_items >= 5,
            'message' => __('Details H2 section with 5+ list items', 'cranseo'),
            'current' => $details_found ? sprintf(__('%d items', 'cranseo'), $details_items) : __('Not found', 'cranseo')
        );

       // Check FAQ section
$faq_found = false;
$faq_items = 0;
foreach ($h2_titles as $index => $title) {
    if (preg_match('/faq|frequently asked questions|questions.*answers/i', $title)) {
        $faq_found = true;
        $faq_items = $this->count_faq_items_after_h2($content, $h2_matches[0][$index]);
        break;
    }
}

$results['faq'] = array(
    'passed' => $faq_found && $faq_items >= 5,
    'message' => __('FAQ H2 section with 5+ list items', 'cranseo'),
    'current' => $faq_found ? sprintf(__('%d items', 'cranseo'), $faq_items) : __('Not found', 'cranseo')
);

        return $results;
    }

    private function count_list_items_after_h2($content, $h2_tag) {
    $position = strpos($content, $h2_tag);
    if ($position === false) {
        return 0;
    }

    $sub_content = substr($content, $position + strlen($h2_tag));
    
    // Stop at next H2 to avoid counting items from other sections
    $next_h2_pos = stripos($sub_content, '<h2');
    if ($next_h2_pos !== false) {
        $sub_content = substr($sub_content, 0, $next_h2_pos);
    }
    
    // Count list items in various formats
    $item_count = 0;
    
    // Standard <li> items
    preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $sub_content, $li_matches);
    $item_count += count($li_matches[0]);
    
    // Div-based list items (common in page builders)
    if ($item_count === 0) {
        preg_match_all('/<div[^>]*class=[\'"][^\'"]*(?:list|item|point)[^\'"]*[\'"][^>]*>.*?<\/div>/is', $sub_content, $div_matches);
        $item_count += count($div_matches[0]);
    }
    
    // Paragraph-based list items
    if ($item_count === 0) {
        preg_match_all('/<p[^>]*>•\s.*?<\/p>|<p[^>]*>-\s.*?<\/p>|<p[^>]*>\d+\.\s.*?<\/p>/is', $sub_content, $p_matches);
        $item_count += count($p_matches[0]);
    }
    
    return $item_count;
}

    private function count_faq_items_after_h2($content, $h2_tag) {
    $position = strpos($content, $h2_tag);
    if ($position === false) {
        return 0;
    }

    $sub_content = substr($content, $position + strlen($h2_tag));
    
    // Count FAQ items in various formats
    $faq_count = 0;
    
    // Format 1: Regular list items <li>Q: ... A: ...</li>
    preg_match_all('/<li[^>]*>.*?(?:Q:|Question:|<strong>Q:).*?<\/li>/is', $sub_content, $li_matches);
    $faq_count += count($li_matches[0]);
    
    // Format 2: Div-based FAQ items
    if ($faq_count === 0) {
        preg_match_all('/<div[^>]*class=[\'"][^\'"]*faq[^\'"]*[\'"][^>]*>.*?<\/div>/is', $sub_content, $div_matches);
        $faq_count += count($div_matches[0]);
    }
    
    // Format 3: Paragraph-based FAQ (Q: ... A: ...)
    if ($faq_count === 0) {
        preg_match_all('/<p[^>]*>(?:<strong>)?\s*(?:Q|Question)[:\-]\s*(?:<\/strong>)?.*?<\/p>\s*<p[^>]*>(?:<strong>)?\s*(?:A|Answer)[:\-]\s*(?:<\/strong>)?.*?<\/p>/is', $sub_content, $p_matches);
        $faq_count += count($p_matches[0]) / 2; // Each FAQ has Q and A paragraphs
    }
    
    // Format 4: Simple Q/A pattern in any container
    if ($faq_count === 0) {
        preg_match_all('/(?:Q|Question)[:\-].*?(?:A|Answer)[:\-]/is', $sub_content, $text_matches);
        $faq_count += count($text_matches[0]);
    }
    
    return $faq_count;
}
}
