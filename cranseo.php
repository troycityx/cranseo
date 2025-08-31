<?php

/*
Plugin Name: CranSEO
Description: Optimize WooCommerce products for Search Engines and LLM, automatic AI content generation and XML sitemap features.
Requires Plugin: WooCommerce 
Version: 1.0.7
Plugin URI: https://cranseo.com
Author: Kijana Omollo
Author URI: https://profiles.wordpress.org/chiqi/ 
License: GPL-2.0+
*/
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Freemius integration with proper basename handling for free/paid version compatibility
if ( function_exists( 'cra_fs' ) ) {
    cra_fs()->set_basename( false, __FILE__ );
} else {
    /**
     * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
     * `function_exists` CALL ABOVE TO PROPERLY WORK.
     */
    if ( !function_exists( 'cra_fs' ) ) {
        // Create a helper function for easy SDK access.
        function cra_fs() {
            global $cra_fs;
            if ( !isset( $cra_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
                $cra_fs = fs_dynamic_init( array(
                    'id'             => '20465',
                    'slug'           => 'cranseo',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_fa39b3d256d341db219be37067ba7',
                    'is_premium'     => false,
                    'premium_suffix' => 'Premium',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => array(
                        'support' => false,
                    ),
                    'is_live'        => true,
                ) );
            }
            return $cra_fs;
        }

        // Init Freemius.
        cra_fs();
        // Signal that SDK was initiated.
        do_action( 'cra_fs_loaded' );
    }
    // Define plugin constants
    define( 'CRANSEO_VERSION', '1.0.7' );
    define( 'CRANSEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    define( 'CRANSEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    define( 'CRANSEO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
    define( 'CRANSEO_AI_TRIAL_LIMIT', 3 );
    // Check if WooCommerce is active
    register_activation_hook( __FILE__, 'cranseo_activation_check' );
    function cranseo_activation_check() {
        if ( !class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( __( 'CranSEO requires WooCommerce to be installed and activated.', 'cranseo' ) );
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
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action( 'plugins_loaded', array($this, 'init') );
        }

        public function init() {
            if ( !class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', array($this, 'woocommerce_missing_notice') );
                return;
            }
            $this->load_dependencies();
            $this->init_components();
            $this->register_hooks();
            // Add contextual upsells for non-paying users
            $this->add_contextual_upsells();
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
            add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_scripts') );
            add_action( 'wp_ajax_cranseo_check_product', array($this, 'ajax_check_product_handler') );
            add_action( 'wp_ajax_cranseo_generate_content', array($this, 'ajax_generate_content_handler') );
        }

        private function add_contextual_upsells() {
            // Add upsells for non-paying users
            if ( cra_fs()->is_not_paying() ) {
                add_filter( 'cranseo_settings_page', array($this, 'add_pro_features_upsell') );
                add_action( 'cranseo_product_metabox', array($this, 'add_ai_upsell') );
            }
        }

        public function add_pro_features_upsell( $settings ) {
            $settings['pro_features'] = array(
                'title' => __( 'Pro Features', 'cranseo' ),
                'type'  => 'section',
                'desc'  => sprintf( __( 'Upgrade to CranSEO Premium to unlock advanced AI content generation, and priority support. %s', 'cranseo' ), '<a href="' . cra_fs()->get_upgrade_url() . '">' . __( 'Upgrade Now!', 'cranseo' ) . '</a>' ),
            );
            return $settings;
        }

        public function add_ai_upsell() {
            if ( cra_fs()->is_not_paying() ) {
                echo '<div class="cranseo-upsell-box">';
                echo '<h4>' . __( 'Want More AI Power?', 'cranseo' ) . '</h4>';
                echo '<p>' . __( 'Upgrade to CranSEO Premium for unlimited AI content generation.', 'cranseo' ) . '</p>';
                echo '<a href="' . cra_fs()->get_upgrade_url() . '" class="button button-primary">' . __( 'Unlock Premium Features', 'cranseo' ) . '</a>';
                echo '</div>';
            }
        }

        public function enqueue_admin_scripts( $hook ) {
            if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
                return;
            }
            global $post;
            if ( !$post || 'product' !== $post->post_type ) {
                return;
            }
            wp_enqueue_style(
                'cranseo-admin',
                CRANSEO_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                CRANSEO_VERSION
            );
            wp_enqueue_script(
                'cranseo-admin',
                CRANSEO_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                CRANSEO_VERSION,
                true
            );
            // Get trial status for localizing
            $trial_data = $this->get_trial_status();
            wp_localize_script( 'cranseo-admin', 'cranseo_ajax', array(
                'ajax_url'    => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'cranseo_nonce' ),
                'post_id'     => $post->ID,
                'ai_trial'    => array(
                    'remaining' => $trial_data['remaining'],
                    'used'      => $trial_data['used'],
                    'limit'     => CRANSEO_AI_TRIAL_LIMIT,
                ),
                'has_premium' => cra_fs()->can_use_premium_code(),
                'upgrade_url' => cra_fs()->get_upgrade_url(),
            ) );
        }

        public function ajax_check_product_handler() {
            check_ajax_referer( 'cranseo_nonce', 'nonce' );
            if ( !current_user_can( 'edit_products' ) ) {
                wp_send_json_error( __( 'Unauthorized', 'cranseo' ) );
            }
            $post_id = intval( $_POST['post_id'] );
            $results = $this->optimizer->check_product( $post_id );
            ob_start();
            ?>
            <div class="cranseo-rules">
                <?php 
            foreach ( $results as $rule => $result ) {
                ?>
                    <div class="cranseo-rule">
                        <span class="cranseo-status <?php 
                echo ( $result['passed'] ? 'passed' : 'failed' );
                ?>">
                            <?php 
                echo ( $result['passed'] ? '✓' : '✗' );
                ?>
                        </span>
                        <span class="cranseo-rule-text"><?php 
                echo $result['message'];
                ?></span>
                        <?php 
                if ( isset( $result['current'] ) ) {
                    ?>
                            <span class="cranseo-current">(<?php 
                    echo $result['current'];
                    ?>)</span>
                        <?php 
                }
                ?>
                    </div>
                <?php 
            }
            ?>
                
                <?php 
            // Premium feature upsell for non-paying users
            if ( cra_fs()->is_not_paying() ) {
                ?>
                <div class="cranseo-rule cranseo-upsell">
                    <span class="cranseo-status pro">★</span>
                    <span class="cranseo-rule-text">
                        <?php 
                _e( 'Advanced SEO recommendations', 'cranseo' );
                ?>
                        <?php 
                echo sprintf( '<a href="%s" target="_blank"><small>%s</small></a>', cra_fs()->get_upgrade_url(), __( '(Pro Feature)', 'cranseo' ) );
                ?>
                    </span>
                </div>
                <?php 
            }
            ?>
            </div>
            <?php 
            $html = ob_get_clean();
            wp_send_json_success( array(
                'html' => $html,
            ) );
        }

        public function ajax_generate_content_handler() {
            check_ajax_referer( 'cranseo_nonce', 'nonce' );
            if ( !current_user_can( 'edit_products' ) ) {
                wp_send_json_error( __( 'Unauthorized', 'cranseo' ) );
            }
            $post_id = intval( $_POST['post_id'] );
            $content_type = sanitize_text_field( $_POST['content_type'] );
            // Check if user has access to AI features using Freemius
            $access_check = $this->check_ai_access();
            if ( !$access_check['has_access'] ) {
                wp_send_json_error( $access_check['message'] );
            }
            // If this is a trial usage, decrement the counter
            if ( $access_check['is_trial'] ) {
                $this->decrement_trial_counter();
            }
            try {
                $content = $this->ai_writer->generate_content( $post_id, $content_type );
                wp_send_json_success( array(
                    'content' => $content,
                ) );
            } catch ( Exception $e ) {
                wp_send_json_error( $e->getMessage() );
            }
        }

        public function woocommerce_missing_notice() {
            ?>
            <div class="error">
                <p><?php 
            _e( 'CranSEO requires WooCommerce to be installed and activated.', 'cranseo' );
            ?></p>
            </div>
            <?php 
        }

        // Trial management methods
        private function get_trial_status() {
            // Check if user has already used trial
            $used = get_option( 'cranseo_ai_trial_used', 0 );
            return array(
                'remaining' => max( 0, CRANSEO_AI_TRIAL_LIMIT - $used ),
                'used'      => $used,
                'limit'     => CRANSEO_AI_TRIAL_LIMIT,
            );
        }

        private function check_ai_access() {
            // If user has premium, always allow access
            if ( cra_fs()->can_use_premium_code() ) {
                return array(
                    'has_access' => true,
                    'is_trial'   => false,
                    'message'    => '',
                );
            }
            // Check trial usage for free users
            $trial_status = $this->get_trial_status();
            if ( $trial_status['remaining'] > 0 ) {
                return array(
                    'has_access' => true,
                    'is_trial'   => true,
                    'message'    => sprintf( __( 'You have %d AI generations remaining in your trial.', 'cranseo' ), $trial_status['remaining'] ),
                );
            }
            return array(
                'has_access' => false,
                'is_trial'   => false,
                'message'    => __( 'Please upgrade to the premium version to access AI content generation.', 'cranseo' ),
            );
        }

        private function decrement_trial_counter() {
            $used = get_option( 'cranseo_ai_trial_used', 0 );
            update_option( 'cranseo_ai_trial_used', $used + 1 );
        }

    }

    // Initialize the plugin
    CranSEO::get_instance();
    // Customize Freemius experience
    cra_fs()->add_filter(
        'connect_message',
        function (
            $message,
            $user_first_name,
            $plugin_title,
            $user_login,
            $site_link,
            $freemius_link
        ) {
            return sprintf(
                __( 'Hey %1$s', 'cranseo' ) . ',<br>' . __( 'Please help us improve %2$s by opting in to share some usage data with %5$s. If you skip this, that\'s okay! %2$s will still work just fine.', 'cranseo' ),
                $user_first_name,
                '<b>' . $plugin_title . '</b>',
                '<b>' . $user_login . '</b>',
                $site_link,
                $freemius_link
            );
        },
        10,
        6
    );
    // Add affiliate program link
    cra_fs()->add_filter( 'affiliate_program_url', function ( $url ) {
        return 'https://cranseo.com/affiliates';
    } );
    // Customize the upgrade message
    cra_fs()->add_filter( 'checkout_url', function ( $url ) {
        return 'https://cranseo.com/pricing';
    } );
}