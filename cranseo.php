<?php
/**
* Plugin Name: CranSEO
* Description: Optimize WooCommerce products for Search Engines and LLM, automatic AI content generation and XML sitemap features.
* Requires Plugin: WooCommerce 
* Version: 2.0.3
* Plugin URI: https://cranseo.com
* Author: Kijana Omollo
* Author URI: https://profiles.wordpress.org/chiqi/ 
* License: GPL-2.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CRANSEO_VERSION', '2.0.3');
define('CRANSEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CRANSEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CRANSEO_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CRANSEO_API_URL', 'https://cranseo.com/wp-json/cranseo/v1');

// Check if WooCommerce is active
register_activation_hook(__FILE__, 'cranseo_activation_check');
function cranseo_activation_check() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('CranSEO requires WooCommerce to be installed and activated.', 'cranseo'));
    }
}

// Main plugin class
class CranSEO {
    private static $instance = null;
    private $optimizer;
    private $ai_writer;
    private $sitemap;
    private $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    private function load_dependencies() {
        require_once CRANSEO_PLUGIN_DIR . 'includes/class-cranseo-optimizer.php';
        require_once CRANSEO_PLUGIN_DIR . 'includes/class-cranseo-ai.php';
        require_once CRANSEO_PLUGIN_DIR . 'includes/class-cranseo-sitemap.php';
        require_once CRANSEO_PLUGIN_DIR . 'includes/class-cranseo-settings.php';
    }

    private function init_components() {
        $this->optimizer = new CranSEO_Optimizer();
        $this->ai_writer = new CranSEO_AI();
        $this->sitemap = new CranSEO_Sitemap();
        $this->settings = new CranSEO_Settings();
    }

    private function register_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_cranseo_check_product', array($this, 'ajax_check_product_handler'));
        add_action('wp_ajax_cranseo_generate_content', array($this, 'ajax_generate_content_handler'));
        add_action('wp_ajax_cranseo_dismiss_notice', array($this, 'ajax_dismiss_notice_handler'));
        add_action('admin_notices', array($this, 'display_quota_notices'));
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        global $post;
        if (!$post || 'product' !== $post->post_type) {
            return;
        }

        wp_enqueue_style('cranseo-admin', CRANSEO_PLUGIN_URL . 'assets/css/admin.css', array(), CRANSEO_VERSION);
        wp_enqueue_script('cranseo-admin', CRANSEO_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CRANSEO_VERSION, true);
        
        // Get user's quota info
        $quota_info = $this->ai_writer->get_quota_info();
        $remaining_quota = $quota_info['remaining'];
        $user_tier = $quota_info['license_tier'] ?? 'basic';
        $has_license = $quota_info['has_license'] ?? false;
        $quota_limits = $this->get_quota_limits();
        
        wp_localize_script('cranseo-admin', 'cranseo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cranseo_nonce'),
            'dismiss_nonce' => wp_create_nonce('cranseo_dismiss_notice'),
            'post_id' => $post->ID,
            'remaining_quota' => $remaining_quota,
            'user_tier' => $user_tier,
            'has_license' => $has_license,
            'quota_limit' => $quota_limits[$user_tier] ?? 3,
            'error_message' => $this->ai_writer->get_error_message(),
            'upgrade_url' => $this->get_upgrade_url(),
            'pricing_url' => $this->get_pricing_url()
        ));
    }

    public function ajax_check_product_handler() {
        check_ajax_referer('cranseo_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }

        $post_id = intval($_POST['post_id']);
        
        if (empty($post_id)) {
            wp_send_json_error(__('Invalid product ID', 'cranseo'));
        }

        $results = $this->optimizer->check_product($post_id);

        ob_start();
        ?>
        <div class="cranseo-rules">
            <?php foreach ($results as $rule => $result): ?>
                <div class="cranseo-rule">
                    <span class="cranseo-status <?php echo $result['passed'] ? 'passed' : 'failed'; ?>">
                        <?php echo $result['passed'] ? '✓' : '✗'; ?>
                    </span>
                    <span class="cranseo-rule-text"><?php echo esc_html($result['message']); ?></span>
                    <?php if (isset($result['current'])): ?>
                        <span class="cranseo-current">(<?php echo esc_html($result['current']); ?>)</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_generate_content_handler() {
        check_ajax_referer('cranseo_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : '';
        
        if (empty($post_id) || empty($content_type)) {
            wp_send_json_error(__('Missing required parameters', 'cranseo'));
        }

        // Validate content type
        $valid_content_types = array('title', 'short_description', 'full_description');
        if (!in_array($content_type, $valid_content_types)) {
            wp_send_json_error(__('Invalid content type', 'cranseo'));
        }
        
        try {
            // Check if user can generate content
            if (!$this->ai_writer->can_generate_content()) {
                $error_message = $this->ai_writer->get_error_message();
                wp_send_json_error($error_message);
            }
            
            $content = $this->ai_writer->generate_content($post_id, $content_type);
            
            // Update quota usage
            $this->ai_writer->update_quota_usage();
            
            wp_send_json_success(array(
                'content' => $content,
                'remaining_quota' => $this->ai_writer->get_remaining_quota()
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle notice dismissal
     */
    public function ajax_dismiss_notice_handler() {
        check_ajax_referer('cranseo_dismiss_notice', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }
        
        $notice_key = sanitize_text_field($_POST['notice_key']);
        $user_id = get_current_user_id();
        $dismissed_notices = get_user_meta($user_id, 'cranseo_dismissed_notices', true) ?: array();
        
        if (!in_array($notice_key, $dismissed_notices)) {
            $dismissed_notices[] = $notice_key;
            update_user_meta($user_id, 'cranseo_dismissed_notices', $dismissed_notices);
        }
        
        wp_send_json_success();
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('CranSEO requires WooCommerce to be installed and activated.', 'cranseo'); ?></p>
        </div>
        <?php
    }

    /**
     * Display admin notices for quota limits and upgrade prompts
     */
    public function display_quota_notices() {
        global $pagenow;
        
        // Only show on relevant pages
        if (!in_array($pagenow, ['post.php', 'post-new.php', 'edit.php']) || get_post_type() !== 'product') {
            return;
        }

        // Check if user has dismissed the notice
        $dismissed_notices = get_user_meta(get_current_user_id(), 'cranseo_dismissed_notices', true) ?: array();
        $current_notice_key = '';

        $quota_info = $this->ai_writer->get_quota_info();
        $remaining_quota = $quota_info['remaining'];
        $user_tier = $quota_info['license_tier'] ?? 'basic';
        $has_license = $quota_info['has_license'] ?? false;
        
        // Determine which notice to show and set the key
        if (!$has_license) {
            $current_notice_key = 'no_license';
        } elseif ($remaining_quota <= 0) {
            $current_notice_key = 'no_credits';
        } elseif ($remaining_quota <= 1 && $user_tier === 'basic') {
            $current_notice_key = 'low_credits';
        }

        // If notice is dismissed, don't show it
        if ($current_notice_key && in_array($current_notice_key, $dismissed_notices)) {
            return;
        }
        
        // If no license, show notice to get free plan
        if (!$has_license) {
            ?>
            <div class="notice notice-info cranseo-notice is-dismissible" data-notice-key="no_license">
                <p><?php 
                    printf(
                        __('<strong>CranSEO:</strong> Generate AI product descriptions! <a href="%s" target="_blank">Get your free Basic plan</a> with 3 credits to get started.', 'cranseo'),
                        esc_url($this->get_pricing_url())
                    ); 
                ?></p>
            </div>
            <?php
            $this->enqueue_notice_dismissal_script();
            return;
        }

        // Show quota warnings for licensed users
        if ($remaining_quota <= 0) {
            ?>
            <div class="notice notice-error cranseo-notice is-dismissible" data-notice-key="no_credits">
                <p><?php 
                    printf(
                        __('<strong>CranSEO:</strong> You have used all your available credits. <a href="%s" target="_blank">Upgrade your plan</a> to generate more AI content.', 'cranseo'),
                        esc_url($this->get_upgrade_url())
                    ); 
                ?></p>
            </div>
            <?php
        } elseif ($remaining_quota <= 1 && $user_tier === 'basic') {
            ?>
            <div class="notice notice-warning cranseo-notice is-dismissible" data-notice-key="low_credits">
                <p><?php 
                    printf(
                        __('<strong>CranSEO:</strong> You have only %d credit remaining. <a href="%s" target="_blank">Upgrade to Pro</a> for 150 credits.', 'cranseo'),
                        $remaining_quota,
                        esc_url($this->get_upgrade_url())
                    ); 
                ?></p>
            </div>
            <?php
        }

        // Enqueue the dismissal script if any notice is shown
        if ($current_notice_key) {
            $this->enqueue_notice_dismissal_script();
        }
    }

    /**
     * Enqueue notice dismissal script
     */
    private function enqueue_notice_dismissal_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('click', '.cranseo-notice .notice-dismiss', function() {
                var notice = $(this).closest('.cranseo-notice');
                var noticeKey = notice.data('notice-key');
                
                $.post(ajaxurl, {
                    action: 'cranseo_dismiss_notice',
                    notice_key: noticeKey,
                    nonce: '<?php echo wp_create_nonce('cranseo_dismiss_notice'); ?>'
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get user's plan tier
     */
    public function get_user_tier() {
        // Use the AI writer's quota info for accurate tier detection
        $quota_info = $this->ai_writer->get_quota_info();
        return $quota_info['license_tier'] ?? 'basic';
    }

    /**
     * Get upgrade URL for existing users
     */
    public function get_upgrade_url() {
        if (function_exists('cranseo_freemius')) {
            return cranseo_freemius()->get_upgrade_url();
        }
        
        return $this->get_pricing_url();
    }

    /**
     * Get pricing URL for new users
     */
    public function get_pricing_url() {
        return 'https://cranseo.com/pricing/';
    }

    /**
     * Get quota limits for each tier
     */
    public function get_quota_limits() {
        return array(
            'basic' => 3,
            'pro' => 150,
            'agency' => 300
        );
    }
}

// Initialize the plugin
CranSEO::get_instance();