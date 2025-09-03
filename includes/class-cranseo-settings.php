<?php
class CranSEO_Settings {
    private $api_url;
    
    public function __construct() {
        $this->api_url = CRANSEO_API_URL;
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_styles'));
        add_action('wp_ajax_cranseo_regenerate_sitemap', array($this, 'ajax_regenerate_sitemap'));
        add_action('wp_ajax_cranseo_validate_license', array($this, 'ajax_validate_license'));
        add_action('wp_ajax_cranseo_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_cranseo_get_quota_info', array($this, 'ajax_get_quota_info'));
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
        
        // Localize script for AJAX
        wp_localize_script('cranseo-settings', 'cranseo_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'get_quota_info' => wp_create_nonce('cranseo_get_quota_info'),
                'regenerate_sitemap' => wp_create_nonce('cranseo_regenerate_sitemap'),
                'validate_license' => wp_create_nonce('cranseo_validate_license'),
                'activate_license' => wp_create_nonce('cranseo_activate_license')
            )
        ));
        
        // Add inline styles for color scheme
        $custom_css = "
            .cranseo-settings-wrap {
                --primary-color: #7bd0ad;
                --secondary-color: #1d617a;
                --accent-color: #3a9e7d;
                --light-bg: #f8faf9;
                --border-color: #e0e6e3;
            }
            
            .cranseo-header {
                background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            }
            
            .button-primary, .cranseo-stat-card {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
            }
            
            .button-primary:hover {
                background-color: var(--secondary-color);
                border-color: var(--secondary-color);
            }
            
            .cranseo-card h3 {
                color: var(--secondary-color);
                border-bottom: 2px solid var(--primary-color);
            }
            
            .cranseo-plan-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 6px 12px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 14px;
            }
            
            .cranseo-plan-badge.plan-basic {
                background: #e3f2fd;
                color: #1976d2;
            }
            
            .cranseo-plan-badge.plan-pro {
                background: #f3e5f5;
                color: #7b1fa2;
            }
            
            .cranseo-plan-badge.plan-agency {
                background: #fff3e0;
                color: #f57c00;
            }
        ";
        wp_add_inline_style('cranseo-settings', $custom_css);
    }

    public function settings_init() {
        register_setting('cranseo_settings', 'cranseo_sitemap_post_types');
        register_setting('cranseo_settings', 'cranseo_saas_license_key');

        // SaaS License Section
        add_settings_section(
            'cranseo_saas_section',
            __('CranSEO.com License', 'cranseo'),
            array($this, 'saas_section_callback'),
            'cranseo_settings'
        );

        add_settings_field(
            'cranseo_saas_license_key',
            __('License Key', 'cranseo'),
            array($this, 'saas_license_key_field'),
            'cranseo_settings',
            'cranseo_saas_section'
        );

        // Sitemap Section
        add_settings_section(
            'cranseo_sitemap_section',
            __('XML Sitemap Settings', 'cranseo'),
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

    public function saas_section_callback() {
        $quota_info = $this->get_quota_info();
        $tier = $quota_info['tier'];
        $remaining = $quota_info['remaining'];
        $limit = $quota_info['limit'];
        $used = $limit - $remaining;
        
        echo '<div class="cranseo-section-description">';
        echo '<p>' . __('Connect your WordPress site to your CranSEO.com account to enable AI content generation. Find your license key in your <a href="https://cranseo.com/account/" target="_blank">account dashboard</a>.', 'cranseo') . '</p>';
        
        // Display quota information
        echo '<div class="cranseo-quota-info">';
        echo '<div class="cranseo-quota-progress">';
        echo '<div class="cranseo-quota-bar">';
        $percentage = $limit > 0 ? min(100, ($used / $limit) * 100) : 0;
        echo '<div class="cranseo-quota-progress-bar" style="width: ' . $percentage . '%"></div>';
        echo '</div>';
        echo '<div class="cranseo-quota-text">';
        echo sprintf(__('%d of %d generations used this month (%s plan)', 'cranseo'), 
            $used, 
            $limit, 
            ucfirst($tier)
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function sitemap_section_callback() {
        echo '<div class="cranseo-section-description">';
        echo '<p>' . __('Manage your XML sitemap settings to improve search engine visibility and indexing of your content.', 'cranseo') . '</p>';
        echo '</div>';
    }

    public function saas_license_key_field() {
        $value = get_option('cranseo_saas_license_key');
        $status = $this->get_license_key_status($value);
        
        echo '<div class="cranseo-field-group">';
        echo '<input type="password" name="cranseo_saas_license_key" value="' . esc_attr($value) . '" class="regular-text" placeholder="cseo_...">';
        echo '<span class="cranseo-status-indicator ' . $status['class'] . '">' . $status['text'] . '</span>';
        echo '</div>';
        echo '<p class="description">' . __('Your unique license key from CranSEO.com. This links your site to your subscription and enables AI features.', 'cranseo') . '</p>';
        
        if ($value) {
            echo '<div class="cranseo-test-area">';
           // echo '<button type="button" class="button button-secondary" id="cranseo-validate-license">' . __('Validate Key', 'cranseo') . '</button>';
            echo '<button type="button" class="button button-primary" id="cranseo-activate-license">' . __('Activate Key', 'cranseo') . '</button>';
            echo '<span id="cranseo-validation-result"></span>';
            echo '</div>';
        }
    }

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

    private function get_license_key_status($license_key) {
        if (empty($license_key)) {
            return array(
                'class' => 'status-missing',
                'text' => __('Not connected', 'cranseo')
            );
        }
        
        // Check if it looks like a valid key
        if (preg_match('/^[a-zA-Z0-9]{40,}$/', $license_key)) {
            return array(
                'class' => 'status-valid',
                'text' => __('Valid format', 'cranseo')
            );
        }
        
        return array(
            'class' => 'status-invalid',
            'text' => __('Invalid format', 'cranseo')
        );
    }

    public function ajax_validate_license() {
        check_ajax_referer('cranseo_validate_license', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error(__('License key is empty', 'cranseo'));
        }
        
        // Send request to CranSEO API server
        $api_response = wp_remote_post($this->api_url . '/validate-license', array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'license_key' => $license_key,
                'site_url' => home_url()
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($api_response)) {
            wp_send_json_error('Connection failed: ' . $api_response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($api_response);
        $body = json_decode(wp_remote_retrieve_body($api_response), true);
        
        if ($status_code === 200 && isset($body['valid']) && $body['valid']) {
            // Cache the validation result
            set_transient('cranseo_license_validation', $body, 15 * MINUTE_IN_SECONDS);
            
            wp_send_json_success(array(
                'message' => __('License validated successfully!', 'cranseo'),
                'tier' => $body['tier'] ?? 'basic',
                'remaining_quota' => $body['remaining_quota'] ?? 0
            ));
        } else {
            $error_message = __('Invalid license key', 'cranseo');
            if (isset($body['error'])) {
                $error_message = $body['error'];
            } elseif (isset($body['message'])) {
                $error_message = $body['message'];
            }
            wp_send_json_error($error_message);
        }
    }

    public function ajax_activate_license() {
        check_ajax_referer('cranseo_activate_license', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error(__('License key is empty', 'cranseo'));
        }
        
        // Send activation request to CranSEO API server
        $api_response = wp_remote_post($this->api_url . '/activate-license', array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'license_key' => $license_key,
                'site_url' => home_url()
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($api_response)) {
            wp_send_json_error('Connection failed: ' . $api_response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($api_response);
        $body = json_decode(wp_remote_retrieve_body($api_response), true);
        
        if ($status_code === 200 && isset($body['success']) && $body['success']) {
            // Save the license key and cache the data
            update_option('cranseo_saas_license_key', $license_key);
            set_transient('cranseo_license_data', $body, 15 * MINUTE_IN_SECONDS);
            set_transient('cranseo_license_tier', $body['tier'] ?? 'basic', 15 * MINUTE_IN_SECONDS);
            
            // Clear quota cache to force refresh
            delete_transient('cranseo_quota_cache');
            
            wp_send_json_success(array(
                'message' => __('License activated successfully!', 'cranseo'),
                'tier' => $body['tier'] ?? 'basic',
                'install_id' => $body['install_id'] ?? '',
                'uuid' => $body['uuid'] ?? ''
            ));
        } else {
            $error_message = __('License activation failed', 'cranseo');
            if (isset($body['error'])) {
                $error_message = $body['error'];
            } elseif (isset($body['message'])) {
                $error_message = $body['message'];
            }
            wp_send_json_error($error_message);
        }
    }

    public function ajax_get_quota_info() {
        check_ajax_referer('cranseo_get_quota_info', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }
        
        $quota_info = $this->get_quota_info();
        wp_send_json_success($quota_info);
    }

    public function ajax_regenerate_sitemap() {
        check_ajax_referer('cranseo_regenerate_sitemap', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }
        
        $sitemap = new CranSEO_Sitemap();
        $success = $sitemap->regenerate_sitemap();
        
        if ($success) {
            wp_send_json_success(__('Sitemap regenerated successfully!', 'cranseo'));
        } else {
            wp_send_json_error(__('Failed to regenerate sitemap', 'cranseo'));
        }
    }

    private function get_quota_info() {
        $license_key = get_option('cranseo_saas_license_key');
        
        // Return basic info if no license key
        if (empty($license_key)) {
            return array(
                'remaining' => 0,
                'limit' => 10,
                'used' => 0,
                'tier' => 'basic'
            );
        }
        
        // Check cache first
        $cached = get_transient('cranseo_quota_cache');
        if ($cached !== false) {
            return $cached;
        }
        
        // Call API server for real-time quota data
        try {
            $api_response = wp_remote_post($this->api_url . '/quota-check', array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode(array(
                    'license_key' => $license_key,
                    'site_url' => home_url()
                )),
                'timeout' => 10
            ));
            
            if (!is_wp_error($api_response) && wp_remote_retrieve_response_code($api_response) === 200) {
                $body = json_decode(wp_remote_retrieve_body($api_response), true);
                
                $quota_info = array(
                    'remaining' => $body['remaining'] ?? 0,
                    'limit' => $body['limit'] ?? 10,
                    'used' => ($body['limit'] ?? 10) - ($body['remaining'] ?? 0),
                    'tier' => $body['license_tier'] ?? 'basic'
                );
                
                // Cache for 2 minutes
                set_transient('cranseo_quota_cache', $quota_info, 2 * MINUTE_IN_SECONDS);
                return $quota_info;
            }
        } catch (Exception $e) {
            error_log('Quota check failed: ' . $e->getMessage());
        }
        
        // Fallback to basic info
        return array(
            'remaining' => 0,
            'limit' => 10,
            'used' => 0,
            'tier' => 'basic'
        );
    }

    private function get_user_tier() {
        $license_key = get_option('cranseo_saas_license_key');
        if (empty($license_key)) {
            return 'basic';
        }
        
        // Check cache first
        $cached_tier = get_transient('cranseo_license_tier');
        if ($cached_tier !== false) {
            return $cached_tier;
        }
        
        // Fallback to validation
        $validation = get_transient('cranseo_license_validation');
        if ($validation && isset($validation['tier'])) {
            return $validation['tier'];
        }
        
        return 'basic';
    }

    private function get_quota_limit() {
        $tier = $this->get_user_tier();
        $limits = array(
            'basic' => 10,
            'pro' => 500,
            'agency' => 1000
        );
        
        return $limits[$tier] ?? 10;
    }

    public function settings_page() {
        $sitemap_url = home_url('/sitemap-cranseo.xml');
        $sitemap_exists = file_exists(ABSPATH . 'sitemap-cranseo.xml');
        $quota_info = $this->get_quota_info();
        $remaining_quota = $quota_info['remaining'];
        $tier = $quota_info['tier'];
        $quota_limit = $quota_info['limit'];
        $license_key = get_option('cranseo_saas_license_key');
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
                        <span class="cranseo-stat-number"><?php echo $this->count_sitemap_urls(); ?></span>
                        <span class="cranseo-stat-label"><?php _e('URLs in Sitemap', 'cranseo'); ?></span>
                    </div>
                    <div class="cranseo-stat-card">
                        <span class="cranseo-stat-number" id="cranseo-quota-remaining"><?php echo $remaining_quota; ?></span>
                        <span class="cranseo-stat-label"><?php _e('AI Generations Left', 'cranseo'); ?></span>
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
                        <h3>Sitemap Tools</h3>
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
                        <h3>
                            Your Plan: 
                            <span class="cranseo-plan-badge plan-<?php echo $tier; ?>">
                                <span class="cranseo-plan-icon">
                                    <?php echo ($tier === 'agency') ? '🏆' : (($tier === 'pro') ? '⭐' : '🔹'); ?>
                                </span>
                                <span class="cranseo-plan-name"><?php echo ucfirst($tier); ?></span>
                            </span>
                        </h3>
                        <div class="cranseo-card-content">
                            <div class="cranseo-plan-details">
                                <div class="cranseo-plan-feature">
                                    <span class="cranseo-feature-icon">✅</span>
                                    <span class="cranseo-feature-text">
                                        <?php printf(__('%d AI generations per month', 'cranseo'), $quota_limit); ?>
                                    </span>
                                </div>
                                <div class="cranseo-plan-feature">
                                    <span class="cranseo-feature-icon">✅</span>
                                    <span class="cranseo-feature-text">
                                        <?php _e('SEO optimization tools', 'cranseo'); ?>
                                    </span>
                                </div>
                                <div class='cranseo-plan-feature'>
                                    <span class='cranseo-feature-icon'>✅</span>
                                    <span class='cranseo-feature-text'>
                                        <?php _e('XML sitemap generation', 'cranseo'); ?>
                                    </span>
                                </div>
                                
                                <?php if ($tier === 'basic' && empty($license_key)) : ?>
                                <div class="cranseo-upgrade-cta">
                                    <p><?php _e('Ready to get started?', 'cranseo'); ?></p>
                                    <a href="<?php echo $this->get_upgrade_url(); ?>" class="button button-primary" target="_blank">
                                        <?php _e('Get License Key', 'cranseo'); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="cranseo-card">
                        <h3>Quick Stats</h3>
                        <div class="cranseo-card-content">
                            <div class="cranseo-stats-grid">
                                <div class="cranseo-stat-item">
                                    <span class="cranseo-stat-number"><?php echo $this->count_total_products(); ?></span>
                                    <span class="cranseo-stat-label"><?php _e('Total Products', 'cranseo'); ?></span>
                                </div>
                                <div class="cranseo-stat-item">
                                    <span class="cranseo-stat-number"><?php echo $this->count_published_posts(); ?></span>
                                    <span class="cranseo-stat-label"><?php _e('Blog Posts', 'cranseo'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Quota auto-refresh
            function refreshQuotaDisplay() {
                $.post(cranseo_settings.ajax_url, {
                    action: 'cranseo_get_quota_info',
                    nonce: cranseo_settings.nonces.get_quota_info
                }, function(response) {
                    if (response.success) {
                        $('#cranseo-quota-remaining').text(response.data.remaining);
                        $('.cranseo-quota-text').text(
                            response.data.used + ' of ' + response.data.limit + 
                            ' generations used this month (' + response.data.tier + ' plan)'
                        );
                    }
                });
            }

            // Refresh every 30 seconds
            setInterval(refreshQuotaDisplay, 30000);

            $('#cranseo-regenerate-sitemap').click(function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Regenerating...');
                
                $.post(ajaxurl, {
                    action: 'cranseo_regenerate_sitemap',
                    nonce: cranseo_settings.nonces.regenerate_sitemap
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

            // License validation AJAX handler
            $('#cranseo-validate-license').click(function() {
                var $button = $(this);
                var $result = $('#cranseo-validation-result');
                var licenseKey = $('input[name="cranseo_saas_license_key"]').val();
                
                $button.prop('disabled', true).text('Validating...');
                $result.html('<span class="testing">Validating license key...</span>');
                
                $.post(ajaxurl, {
                    action: 'cranseo_validate_license',
                    nonce: cranseo_settings.nonces.validate_license,
                    license_key: licenseKey
                }, function(response) {
                    if (response.success) {
                        var message = '✅ ' + response.data.message;
                        if (response.data.tier) {
                            message += ' - ' + response.data.tier + ' plan';
                        }
                        if (response.data.remaining_quota) {
                            message += ' - ' + response.data.remaining_quota + ' generations remaining';
                        }
                        $result.html('<span class="success">' + message + '</span>');
                    } else {
                        var errorMsg = response.data;
                        $result.html('<span class="error">❌ ' + errorMsg + '</span>');
                    }
                }).fail(function(xhr, status, error) {
                    $result.html('<span class="error">❌ Validation failed: ' + error + '</span>');
                }).always(function() {
                    $button.prop('disabled', false).text('Validate Key');
                });
            });

            // License activation AJAX handler
            $('#cranseo-activate-license').click(function() {
                var $button = $(this);
                var $result = $('#cranseo-validation-result');
                var licenseKey = $('input[name="cranseo_saas_license_key"]').val();
                
                $button.prop('disabled', true).text('Activating...');
                $result.html('<span class="testing">Activating license key...</span>');
                
                $.post(ajaxurl, {
                    action: 'cranseo_activate_license',
                    nonce: cranseo_settings.nonces.activate_license,
                    license_key: licenseKey
                }, function(response) {
                    if (response.success) {
                        var message = '✅ ' + response.data.message;
                        if (response.data.tier) {
                            message += ' - ' + response.data.tier + ' plan activated';
                        }
                        $result.html('<span class="success">' + message + '</span>');
                        // Refresh quota display and reload
                        refreshQuotaDisplay();
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        var errorMsg = response.data;
                        $result.html('<span class="error">❌ ' + errorMsg + '</span>');
                    }
                }).fail(function(xhr, status, error) {
                    $result.html('<span class="error">❌ Activation failed: ' + error + '</span>');
                }).always(function() {
                    $button.prop('disabled', false).text('Activate Key');
                });
            });
        });
        </script>
        <?php
    }

    private function get_upgrade_url() {
        return 'https://cranseo.com/pricing';
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

    private function count_total_products() {
        $count = wp_count_posts('product');
        return $count->publish;
    }

    private function count_published_posts() {
        $count = wp_count_posts('post');
        return $count->publish;
    }
}