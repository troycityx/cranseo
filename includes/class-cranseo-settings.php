<?php
class CranSEO_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_styles'));
        add_action('wp_ajax_cranseo_test_ai_connection', array($this, 'ajax_test_ai_connection'));
        add_action('wp_ajax_cranseo_regenerate_sitemap', array($this, 'ajax_regenerate_sitemap'));
        add_action('wp_ajax_cranseo_activate_license', array($this, 'ajax_activate_license'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('CranSEO Settings', 'cranseo'),
            __('CranSEO', 'cranseo'),
            'manage_options',
            'cranseo-settings',
            array($this, 'settings_page'),
            'dashicons-chart-area',
            56
        );
    }

    public function enqueue_settings_styles($hook) {
        if ($hook !== 'toplevel_page_cranseo-settings') {
            return;
        }
        
        wp_enqueue_style('cranseo-settings', CRANSEO_PLUGIN_URL . 'assets/css/settings.css', array(), CRANSEO_VERSION);
        wp_enqueue_script('cranseo-settings', CRANSEO_PLUGIN_URL . 'assets/js/settings.js', array('jquery'), CRANSEO_VERSION, true);
        
        // Get trial data
        $trial_data = $this->get_trial_status();
        
        wp_localize_script('cranseo-settings', 'cranseo_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cranseo_settings_nonce'),
            'trial_data' => $trial_data,
            'has_premium' => cra_fs()->can_use_premium_code(),
            'upgrade_url' => cra_fs()->get_upgrade_url()
        ));
    }

    public function settings_init() {
        // REMOVED: register_setting('cranseo_settings', 'cranseo_openai_key');
        register_setting('cranseo_settings', 'cranseo_openai_model');
        register_setting('cranseo_settings', 'cranseo_sitemap_post_types');

        // License section
        add_settings_section(
            'cranseo_license_section',
            __('🔑 License & Trial', 'cranseo'),
            array($this, 'license_section_callback'),
            'cranseo_settings'
        );

        add_settings_field(
            'cranseo_license_key',
            __('License Key', 'cranseo'),
            array($this, 'license_key_field'),
            'cranseo_settings',
            'cranseo_license_section'
        );


        // REMOVED: The openai_key_field registration

        // Sitemap section
        add_settings_section(
            'cranseo_sitemap_section',
            __('🗺️ XML Sitemap Settings', 'cranseo'),
            array($this, 'sitemap_section_callback'),
            'cranseo_settings'
        );

        add_settings_field(
            'cranseo_sitemap_post_types',
            __('Include Post Types', 'cranseo'),
            array($this, 'sitemap_post_types_field'),
            'cranseo_settings',
            'cranseo_sitemap_section'
        );
    }

    public function license_section_callback() {
        $trial_data = $this->get_trial_status();
        $has_premium = cra_fs()->can_use_premium_code();
        
        echo '<div class="cranseo-section-description">';
        
        if ($has_premium) {
            echo '<p>' . __('🎉 Your premium license is active! Enjoy unlimited AI content generation.', 'cranseo') . '</p>';
        } else {
            echo '<p>' . __('Subscribe to unlock unlimited AI content generation. You have ' . $trial_data['remaining'] . ' free trials remaining.', 'cranseo') . '</p>';
            
            // Display trial status
            echo '<div class="cranseo-trial-status">';
            echo '<div class="cranseo-progress-bar">';
            echo '<div class="cranseo-progress-fill" style="width: ' . (($trial_data['used'] / CRANSEO_AI_TRIAL_LIMIT) * 100) . '%"></div>';
            echo '</div>';
            echo '<div class="cranseo-trial-count">';
            echo sprintf(__('%d of %d trials used', 'cranseo'), $trial_data['used'], CRANSEO_AI_TRIAL_LIMIT);
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    public function ai_section_callback() {
        $has_premium = cra_fs()->can_use_premium_code();
        
        echo '<div class="cranseo-section-description">';
        
        if ($has_premium) {
            echo '<p>' . __('Supercharge your product content with AI-powered optimization. No API key needed - everything works automatically with your subscription!', 'cranseo') . '</p>';
        } else {
            echo '<p>' . __('Subscribe to unlock AI-powered content optimization. No technical setup required - we handle everything for you.', 'cranseo') . '</p>';
        }
        
        echo '</div>';
    }

    public function sitemap_section_callback() {
        echo '<div class="cranseo-section-description">';
        echo '<p>' . __('Manage your XML sitemap settings to improve search engine visibility and indexing of your content.', 'cranseo') . '</p>';
        echo '</div>';
    }

    public function license_key_field() {
        $has_premium = cra_fs()->can_use_premium_code();
        $trial_data = $this->get_trial_status();
        
        if ($has_premium) {
            // Show premium active status
            echo '<div class="cranseo-license-status-active">';
            echo '<span class="cranseo-status-indicator status-valid">' . __('Premium License Active', 'cranseo') . '</span>';
            
            // Show account management link
            $account_url = cra_fs()->get_account_url();
            echo '<p class="cranseo-account-link">';
            echo '<a href="' . esc_url($account_url) . '" target="_blank">' . __('Manage your account', 'cranseo') . '</a>';
            echo '</p>';
            echo '</div>';
        } else {
            // Display upgrade prompt if trials are exhausted
            if ($trial_data['remaining'] === 0) {
                echo '<div class="cranseo-upgrade-prompt">';
                echo '<div class="cranseo-upgrade-icon">💡</div>';
                echo '<div class="cranseo-upgrade-content">';
                echo '<h4>' . __('Need a subscription?', 'cranseo') . '</h4>';
                echo '<p>' . __('You\'ve used all free trials. Subscribe to unlock unlimited AI content generation.', 'cranseo') . '</p>';
                echo '<a href="' . esc_url(cra_fs()->get_upgrade_url()) . '" target="_blank" class="button button-primary">' . __('View Plans', 'cranseo') . '</a>';
                echo '</div>';
                echo '</div>';
            }
        }
    }

    public function openai_model_field() {
        $has_premium = cra_fs()->can_use_premium_code();
        $selected_model = get_option('cranseo_openai_model', 'gpt-3.5-turbo');
        $models = array(
            'gpt-5' => 'GPT-5',
            'gpt-4.1' => 'GPT-4.1',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-o3' => 'GPT-O3',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
        );
        
        if ($has_premium) {
            echo '<div class="cranseo-field-group">';
            echo '<select name="cranseo_openai_model" class="regular-text cranseo-model-select">';
            foreach ($models as $value => $label) {
                $selected = selected($selected_model, $value, false);
                echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<p class="description">' . __('Select which OpenAI model to use for content generation', 'cranseo') . '</p>';
            
            // Test connection button for premium users
            echo '<div class="cranseo-test-area">';
            echo '<button type="button" class="button button-secondary" id="cranseo-test-ai">' . __('Test AI Connection', 'cranseo') . '</button>';
            echo '<span id="cranseo-test-result"></span>';
            echo '</div>';
        } else {
            echo '<div class="cranseo-upgrade-prompt">';
            echo '<div class="cranseo-upgrade-icon">🔒</div>';
            echo '<div class="cranseo-upgrade-content">';
            echo '<h4>' . __('Subscribe to unlock AI model selection', 'cranseo') . '</h4>';
            echo '<p>' . __('Choose from multiple AI models to optimize your content generation based on your needs.', 'cranseo') . '</p>';
            echo '<a href="' . esc_url(cra_fs()->get_upgrade_url()) . '" class="button button-primary">' . __('View Plans', 'cranseo') . '</a>';
            echo '</div>';
            echo '</div>';
        }
    }

    // REMOVED: The openai_key_field method entirely

    public function sitemap_post_types_field() {
        $selected = get_option('cranseo_sitemap_post_types', array('product', 'post', 'page'));
        $post_types = get_post_types(array('public' => true), 'objects');
        
        echo '<div class="cranseo-checkbox-grid">';
        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') continue;
            
            $checked = in_array($post_type->name, $selected) ? 'checked="checked"' : '';
            echo '<label class="cranseo-checkbox-item">';
            echo '<input type="checkbox" name="cranseo_sitemap_post_types[]" value="' . $post_type->name . '" ' . $checked . '>';
            echo '<span class="cranseo-checkbox-label">' . $post_type->label . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }

    public function ajax_test_ai_connection() {
        check_ajax_referer('cranseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }
        
        // Check license first
        if (!cra_fs()->can_use_premium_code()) {
            wp_send_json_error(__('Valid subscription required to test AI connection', 'cranseo'));
        }
        
        $model = sanitize_text_field($_POST['model']);
        $api_key = defined('CRANSEO_OPENAI_KEY') ? CRANSEO_OPENAI_KEY : '';
        
        if (empty($api_key)) {
            wp_send_json_error(__('AI service not configured', 'cranseo'));
        }
        
        // Test with a simple, fast endpoint - models list with GET request
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200) {
            $model_count = count($data['data'] ?? []);
            
            // Check if the selected model is available
            $model_available = false;
            foreach ($data['data'] as $available_model) {
                if ($available_model['id'] === $model) {
                    $model_available = true;
                    break;
                }
            }
            
            wp_send_json_success(array(
                'message' => __('AI connection successful!', 'cranseo'),
                'model_count' => $model_count,
                'model_available' => $model_available,
                'model' => $model
            ));
        } else {
            $error_message = __('API error', 'cranseo');
            
            if (isset($data['error']['message'])) {
                $error_message = $data['error']['message'];
            } else {
                $error_message .= ' (Code: ' . $status_code . ')';
            }
            
            wp_send_json_error($error_message);
        }
    }

    public function ajax_activate_license() {
        check_ajax_referer('cranseo_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error(__('License key is empty', 'cranseo'));
        }
        
        // Use Freemius to activate the license
        $fs = cra_fs();
        
        try {
            // First, try to activate the license
            $result = $fs->activate_license($license_key);
            
            if ($result->error) {
                wp_send_json_error($result->error->message);
            }
            
            // Save the license key
            update_option('cranseo_license_key', $license_key);
            
            wp_send_json_success(array(
                'message' => __('License activated successfully!', 'cranseo'),
                'redirect' => true
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function settings_page() {
        $sitemap_url = home_url('/sitemap-cranseo.xml');
        $sitemap_exists = file_exists(ABSPATH . 'sitemap-cranseo.xml');
        $trial_data = $this->get_trial_status();
        $has_premium = cra_fs()->can_use_premium_code();
        ?>
        <div class="wrap cranseo-settings-wrap">
            <div class="cranseo-header">
                <div class="cranseo-header-content">
                    <h1 class="cranseo-title">
                        <span class="cranseo-icon">🚀</span>
                        <?php _e('CranSEO Settings', 'cranseo'); ?>
                    </h1>
                    <p class="cranseo-subtitle"><?php _e('Optimize your WooCommerce products for maximum SEO impact', 'cranseo'); ?></p>
                </div>
                <div class="cranseo-header-stats">
                    <div class="cranseo-stat-card">
                        <span class="cranseo-stat-number"><?php echo $this->count_optimized_products(); ?></span>
                        <span class="cranseo-stat-label"><?php _e('Products Optimized', 'cranseo'); ?></span>
                    </div>
                    <div class="cranseo-stat-card">
                        <span class="cranseo-stat-number"><?php echo $trial_data['remaining']; ?></span>
                        <span class="cranseo-stat-label"><?php _e('AI Trials Left', 'cranseo'); ?></span>
                    </div>
                    <div class="cranseo-stat-card">
                        <span class="cranseo-stat-number"><?php echo $this->count_sitemap_urls(); ?></span>
                        <span class="cranseo-stat-label"><?php _e('URLs in Sitemap', 'cranseo'); ?></span>
                    </div>
                </div>
            </div>

            <div class="cranseo-content">
                <div class="cranseo-main-settings">
                    <form method="post" action="options.php" class="cranseo-settings-form">
                        <?php
                        settings_fields('cranseo_settings');
                        do_settings_sections('cranseo_settings');
                        submit_button(__('Save Settings', 'cranseo'), 'primary', 'submit', false);
                        ?>
                    </form>
                </div>

                <div class="cranseo-sidebar">
                    <div class="cranseo-card">
                        <h3>🛠️ Sitemap Tools</h3>
                        <div class="cranseo-card-content">
                            <p class="cranseo-sitemap-status">
                                <strong><?php _e('Status:', 'cranseo'); ?></strong>
                                <span class="status-<?php echo $sitemap_exists ? 'active' : 'inactive'; ?>">
                                    <?php echo $sitemap_exists ? __('Active', 'cranseo') : __('Not generated', 'cranseo'); ?>
                                </span>
                            </p>
                            
                            <div class="cranseo-action-buttons">
                                <a href="<?php echo $sitemap_url; ?>" target="_blank" class="button button-secondary">
                                    <?php _e('View Sitemap', 'cranseo'); ?>
                                </a>
                                <button type="button" class="button button-primary" id="cranseo-regenerate-sitemap">
                                    <?php _e('Regenerate', 'cranseo'); ?>
                                </button>
                            </div>
                            
                            <div class="cranseo-sitemap-info">
                                <p><strong><?php _e('Last Updated:', 'cranseo'); ?></strong> 
                                <?php echo $sitemap_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime(ABSPATH . 'sitemap-cranseo.xml')) : __('Never', 'cranseo'); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="cranseo-card">
                        <h3>📊 AI Usage</h3>
                        <div class="cranseo-card-content">
                            <div class="cranseo-stats-grid">
                                <div class="cranseo-stat-item">
                                    <span class="cranseo-stat-number"><?php echo $trial_data['used']; ?></span>
                                    <span class="cranseo-stat-label"><?php _e('Trials Used', 'cranseo'); ?></span>
                                </div>
                                <div class="cranseo-stat-item">
                                    <span class="cranseo-stat-number"><?php echo $trial_data['remaining']; ?></span>
                                    <span class="cranseo-stat-label"><?php _e('Trials Left', 'cranseo'); ?></span>
                                </div>
                            </div>
                            <?php if ($has_premium): ?>
                                <div class="cranseo-license-status">
                                    <span class="cranseo-status-indicator status-valid"><?php _e('Premium License Active', 'cranseo'); ?></span>
                                    <p class="cranseo-account-link">
                                        <a href="<?php echo esc_url(cra_fs()->get_account_url()); ?>" target="_blank"><?php _e('Manage account', 'cranseo'); ?></a>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="cranseo-upgrade-prompt-small">
                                    <p><?php _e('Subscribe for unlimited AI access', 'cranseo'); ?></p>
                                    <a href="<?php echo esc_url(cra_fs()->get_upgrade_url()); ?>" class="button button-small"><?php _e('Upgrade', 'cranseo'); ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cranseo-card">
                        <h3>💡 Tips & Best Practices</h3>
                        <div class="cranseo-card-content">
                            <ul class="cranseo-tips-list">
                                <li>✅ Use descriptive, keyword-rich product titles</li>
                                <li>✅ Include 5+ features and details in lists</li>
                                <li>✅ Add comprehensive FAQ sections</li>
                                <li>✅ Keep sitemap updated regularly</li>
                                <li>✅ Use AI to optimize existing content</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#cranseo-regenerate-sitemap').click(function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Regenerating...');
                
                $.post(ajaxurl, {
                    action: 'cranseo_regenerate_sitemap',
                    nonce: '<?php echo wp_create_nonce("cranseo_settings_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('Regenerate');
                });
            });

            $('#cranseo-test-ai').click(function() {
                var $button = $(this);
                var $result = $('#cranseo-test-result');
                var model = $('select[name="cranseo_openai_model"]').val();
                
                $button.prop('disabled', true).text('Testing...');
                $result.html('<span class="testing">Testing AI connection...</span>');
                
                $.post(ajaxurl, {
                    action: 'cranseo_test_ai_connection',
                    nonce: '<?php echo wp_create_nonce("cranseo_settings_nonce"); ?>',
                    model: model
                }, function(response) {
                    if (response.success) {
                        var message = '✅ ' + response.data.message;
                        if (response.data.model_count) {
                            message += ' - ' + response.data.model_count + ' models available';
                        }
                        if (response.data.model_available) {
                            message += ' - Selected model (' + response.data.model + ') is available';
                        } else {
                            message += ' - Warning: Selected model (' + response.data.model + ') may not be available';
                        }
                        $result.html('<span class="success">' + message + '</span>');
                    } else {
                        var errorMsg = response.data;
                        // Provide user-friendly error messages
                        if (errorMsg.includes('Incorrect API key')) {
                            errorMsg = 'Service configuration error. Please contact support.';
                        } else if (errorMsg.includes('rate limit')) {
                            errorMsg = 'Rate limit exceeded. Please try again later.';
                        } else if (errorMsg.includes('quota')) {
                            errorMsg = 'Service quota exceeded. Please try again later.';
                        } else if (errorMsg.includes('401')) {
                            errorMsg = 'Authentication failed. Please contact support.';
                        } else if (errorMsg.includes('403')) {
                            errorMsg = 'Access forbidden. Please contact support.';
                        } else if (errorMsg.includes('404')) {
                            errorMsg = 'Endpoint not found. Please try again.';
                        } else if (errorMsg.includes('429')) {
                            errorMsg = 'Too many requests. Please try again later.';
                        } else if (errorMsg.includes('500')) {
                            errorMsg = 'AI service error. Please try again later.';
                        } else if (errorMsg.includes('Valid subscription required')) {
                            errorMsg = 'Valid subscription required to test AI connection';
                        }
                        $result.html('<span class="error">❌ ' + errorMsg + '</span>');
                    }
                }).fail(function(xhr, status, error) {
                    $result.html('<span class="error">❌ Connection failed: ' + error + '</span>');
                }).always(function() {
                    $button.prop('disabled', false).text('Test AI Connection');
                });
            });

            $('#cranseo-activate-license').click(function() {
                var $button = $(this);
                var $result = $('#cranseo-license-result');
                var licenseKey = $('input[name="cranseo_license_key"]').val();
                
                $button.prop('disabled', true).text('Activating...');
                $result.html('<span class="testing">Activating license...</span>');
                
                $.post(ajaxurl, {
                    action: 'cranseo_activate_license',
                    nonce: '<?php echo wp_create_nonce("cranseo_settings_nonce"); ?>',
                    license_key: licenseKey
                }, function(response) {
                    if (response.success) {
                        $result.html('<span class="success">✅ ' + response.data.message + '</span>');
                        if (response.data.redirect) {
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        $result.html('<span class="error">❌ ' + response.data + '</span>');
                    }
                }).fail(function(xhr, status, error) {
                    $result.html('<span class="error">❌ Activation failed: ' + error + '</span>');
                }).always(function() {
                    $button.prop('disabled', false).text('Activate License');
                });
            });
        });
        </script>
        <?php
    }

    private function count_optimized_products() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => '_sku', 'compare' => 'EXISTS'),
                array('key' => '_regular_price', 'compare' => 'EXISTS')
            )
        );
        
        $products = get_posts($args);
        return count($products);
    }

    private function count_sitemap_urls() {
        $sitemap_file = ABSPATH . 'sitemap-cranseo.xml';
        if (!file_exists($sitemap_file)) {
            return 0;
        }
        
        $content = file_get_contents($sitemap_file);
        preg_match_all('/<url>/', $content, $matches);
        return count($matches[0]);
    }

    // Trial management methods
    private function get_trial_status() {
        // Check if user has already used trial
        $used = get_option('cranseo_ai_trial_used', 0);
        
        return array(
            'remaining' => max(0, CRANSEO_AI_TRIAL_LIMIT - $used),
            'used' => $used,
            'limit' => CRANSEO_AI_TRIAL_LIMIT
        );
    }
}