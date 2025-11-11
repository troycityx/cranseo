<?php
/**
* Plugin Name: CranSEO
* Description: Optimize WooCommerce products for Search Engines and LLM, automatic AI content generation and XML sitemap features.
* Requires Plugin: WooCommerce 
* Version: 2.0.4
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
define('CRANSEO_VERSION', '2.0.4');
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
        
        // Get user's remaining quota
        $remaining_quota = $this->ai_writer->get_remaining_quota();
        $user_tier = $this->get_user_tier();
        $quota_limits = $this->get_quota_limits();
        
        wp_localize_script('cranseo-admin', 'cranseo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cranseo_nonce'),
            'post_id' => $post->ID,
            'remaining_quota' => $remaining_quota,
            'user_tier' => $user_tier,
            'quota_limit' => $quota_limits[$user_tier] ?? 3,
            'quota_exceeded_message' => __('You have used all your available credits. Please upgrade your plan to generate more content.', 'cranseo'),
            'upgrade_url' => $this->get_upgrade_url()
        ));
    }

    public function ajax_check_product_handler() {
        check_ajax_referer('cranseo_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }

        $post_id = intval($_POST['post_id']);
        $results = $this->optimizer->check_product($post_id);

        ob_start();
        ?>
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
        <?php
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_generate_content_handler() {
        check_ajax_referer('cranseo_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(__('Unauthorized', 'cranseo'));
        }

        $post_id = intval($_POST['post_id']);
        $content_type = sanitize_text_field($_POST['content_type']);
        
        try {
            // Check quota before generating content
            $remaining_quota = $this->ai_writer->get_remaining_quota();
            if ($remaining_quota <= 0) {
                wp_send_json_error(__('You have used all your available credits. Please upgrade your plan to generate more content.', 'cranseo'));
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

    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
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

        $remaining_quota = $this->ai_writer->get_remaining_quota();
        $user_tier = $this->get_user_tier();
        
        if ($remaining_quota <= 0) {
            ?>
            <div class="notice notice-error">
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
            <div class="notice notice-warning">
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
    }

    /**
     * Get user's plan tier from Freemius
     */
    public function get_user_tier() {
        // Check if Freemius is available
        if (function_exists('cranseo_freemius')) {
            $fs = cranseo_freemius();
            
            if ($fs->is_premium()) {
                if ($fs->is_plan('agency', true)) {
                    return 'agency';
                } elseif ($fs->is_plan('pro', true)) {
                    return 'pro';
                }
            }
        }
        
        // Default to basic plan
        return 'basic';
    }

    /**
     * Get upgrade URL for Freemius
     */
    public function get_upgrade_url() {
        if (function_exists('cranseo_freemius')) {
            return cranseo_freemius()->get_upgrade_url();
        }
        
        // Fallback upgrade URL
        return 'https://cranseo.com/pricing/';
    }

    /**
     * Get quota limits for each tier - UPDATED TO MATCH REQUIREMENTS
     */
    public function get_quota_limits() {
        return array(
            'basic' => 3,    // Changed from 10 to 3
            'pro' => 150,    // Changed from 500 to 150
            'agency' => 300  // Changed from 1000 to 300
        );
    }
}

// Initialize the plugin
CranSEO::get_instance();