<?php
class CranSEO_Sitemap {
    private $sitemap_dir;
    private $sitemap_index_file;
    private $max_urls_per_sitemap = 1000;

    public function __construct() {
        $this->sitemap_dir = ABSPATH . 'cranseo-sitemaps/';
        $this->sitemap_index_file = $this->sitemap_dir . 'sitemap-index.xml';
       
        // WordPress rewrite rules and routing
        add_action('init', array($this, 'add_sitemap_routes'));
        add_action('template_redirect', array($this, 'serve_sitemap'));
        add_action('wp', array($this, 'maybe_generate_sitemaps'));
        
        // Keep content update hooks for regeneration
        add_action('save_post', array($this, 'maybe_update_sitemap'), 10, 3);
        add_action('trashed_post', array($this, 'update_sitemap_on_change'));
        add_action('deleted_post', array($this, 'update_sitemap_on_change'));
        add_action('publish_post', array($this, 'update_sitemap_on_change'));
        add_action('created_term', array($this, 'update_taxonomy_sitemaps'));
        add_action('edited_term', array($this, 'update_taxonomy_sitemaps'));
        add_action('delete_term', array($this, 'update_taxonomy_sitemaps'));
        
        // Register cleanup on deactivation
        register_deactivation_hook(__FILE__, array($this, 'cleanup_sitemaps'));
    }

    public function add_sitemap_routes() {
        // Add rewrite rules for sitemaps
        add_rewrite_rule('^cranseo-sitemaps/sitemap-([a-z0-9-]+)\.xml$', 'index.php?cranseo_sitemap=$1', 'top');
        add_rewrite_rule('^cranseo-sitemaps/sitemap-index\.xml$', 'index.php?cranseo_sitemap=index', 'top');
        
        // Add query var for sitemap detection
        add_rewrite_tag('%cranseo_sitemap%', '([^&]+)');
    }

    public function serve_sitemap() {
        $sitemap_type = get_query_var('cranseo_sitemap');
        
        if (!$sitemap_type) {
            return;
        }

        // Set proper headers
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow', true);

        // Generate and output the sitemap
        if ($sitemap_type === 'index') {
            $this->generate_sitemap_index_output();
        } else {
            $this->generate_sitemap_output($sitemap_type);
        }
        
        exit;
    }

    private function generate_sitemap_index_output() {
        $sitemap_files = $this->get_available_sitemap_types();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($sitemap_files as $sitemap_type) {
            $xml .= '<sitemap>';
            $xml .= '<loc>' . esc_url(home_url("/cranseo-sitemaps/sitemap-{$sitemap_type}.xml")) . '</loc>';
            $xml .= '<lastmod>' . current_time('c') . '</lastmod>';
            $xml .= '</sitemap>';
        }
        
        $xml .= '</sitemapindex>';
        
        echo $xml;
    }

    private function generate_sitemap_output($sitemap_type) {
        $urls = array();
        
        // Handle post type sitemaps
        if (post_type_exists($sitemap_type)) {
            $urls = $this->get_urls_for_post_type($sitemap_type);
        } 
        // Handle taxonomy sitemaps
        elseif (taxonomy_exists($sitemap_type)) {
            $urls = $this->get_urls_for_taxonomy($sitemap_type);
        }
        // Handle paginated sitemaps (e.g., post-1, post-2)
        elseif (preg_match('/([a-z]+)-(\d+)/', $sitemap_type, $matches)) {
            $base_type = $matches[1];
            $page = intval($matches[2]);
            
            if (post_type_exists($base_type)) {
                $urls = $this->get_urls_for_post_type($base_type, $page);
            }
        }

        $this->output_sitemap_xml($urls);
    }

    private function get_urls_for_post_type($post_type, $page = 1) {
        $posts_per_page = 1000; // Match max_urls_per_sitemap
        $offset = ($page - 1) * $posts_per_page;
        
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => $posts_per_page,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC'
        ));

        $urls = array();
        foreach ($posts as $post) {
            $urls[] = array(
                'loc' => get_permalink($post->ID),
                'lastmod' => get_the_modified_date('c', $post->ID),
                'changefreq' => $this->get_change_frequency($post_type, $post),
                'priority' => $this->get_priority($post_type, $post)
            );
        }

        return $urls;
    }

    private function get_urls_for_taxonomy($taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $urls = array();
        foreach ($terms as $term) {
            $urls[] = array(
                'loc' => get_term_link($term),
                'lastmod' => $this->get_taxonomy_lastmod($term, $taxonomy),
                'changefreq' => 'weekly',
                'priority' => '0.4'
            );
        }

        return $urls;
    }

    private function output_sitemap_xml($urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>' . esc_url($url['loc']) . '</loc>';
            $xml .= '<lastmod>' . esc_xml($url['lastmod']) . '</lastmod>';
            $xml .= '<changefreq>' . esc_xml($url['changefreq']) . '</changefreq>';
            $xml .= '<priority>' . esc_xml($url['priority']) . '</priority>';
            $xml .= '</url>';
        }
        
        $xml .= '</urlset>';
        
        echo $xml;
    }

    private function get_available_sitemap_types() {
        $sitemap_types = array();
        
        // Add post types
        $included_post_types = get_option('cranseo_sitemap_post_types', array('product', 'post', 'page'));
        foreach ($included_post_types as $post_type) {
            if (post_type_exists($post_type)) {
                $post_count = wp_count_posts($post_type)->publish;
                if ($post_count > 0) {
                    // Add paginated sitemaps if needed
                    $pages = ceil($post_count / $this->max_urls_per_sitemap);
                    for ($i = 1; $i <= $pages; $i++) {
                        $sitemap_types[] = $pages > 1 ? "{$post_type}-{$i}" : $post_type;
                    }
                }
            }
        }
        
        // Add taxonomies
        $included_taxonomies = get_option('cranseo_sitemap_taxonomies', array());
        foreach ($included_taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $term_count = wp_count_terms($taxonomy, array('hide_empty' => true));
                if ($term_count > 0) {
                    $sitemap_types[] = $taxonomy;
                }
            }
        }
        
        return $sitemap_types;
    }

    // Keep existing helper methods for lastmod, changefreq, priority
    private function get_taxonomy_lastmod($term, $taxonomy) {
        $latest_post = get_posts(array(
            'post_type' => 'any',
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                )
            ),
            'orderby' => 'modified',
            'order' => 'DESC',
            'numberposts' => 1
        ));

        if (!empty($latest_post)) {
            return get_the_modified_date('c', $latest_post[0]->ID);
        }

        return current_time('c');
    }

    private function get_change_frequency($post_type, $post) {
        $frequencies = array(
            'post' => 'weekly',
            'page' => 'monthly',
            'product' => 'weekly'
        );

        return isset($frequencies[$post_type]) ? $frequencies[$post_type] : 'weekly';
    }

    private function get_priority($post_type, $post) {
        $priorities = array(
            'page' => '0.8',
            'post' => '0.6',
            'product' => '0.6'
        );

        if ($post->ID == get_option('page_on_front')) {
            return '1.0';
        }

        return isset($priorities[$post_type]) ? $priorities[$post_type] : '0.5';
    }

    // Update existing methods to work with dynamic generation
    public function maybe_update_sitemap($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $included_types = get_option('cranseo_sitemap_post_types', array('product', 'post', 'page'));
        if (in_array($post->post_type, $included_types)) {
            //clear cache if using caching plugins
            $this->clear_sitemap_cache();
        }
    }

    public function update_sitemap_on_change($post_id = null) {
        // Clear any sitemap caches
        $this->clear_sitemap_cache();
    }

    public function update_taxonomy_sitemaps() {
        // Clear any sitemap caches
        $this->clear_sitemap_cache();
    }

    private function clear_sitemap_cache() {
        // Clear WordPress rewrite rules cache
        flush_rewrite_rules(false);
        
        // If using caching plugins, clear their cache too
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // Clear any transients related to sitemaps
        delete_transient('cranseo_sitemap_data');
    }

    public function maybe_generate_sitemaps() {
        // With dynamic generation, we don't need to pre-generate files
        // But we can ensure rewrite rules are set
        $this->add_sitemap_routes();
    }

    public function regenerate_all_sitemaps() {
        // With dynamic generation, regeneration just means clearing caches
        $this->clear_sitemap_cache();
        return true;
    }

    public function get_sitemap_url() {
        return home_url('/cranseo-sitemaps/sitemap-index.xml');
    }

    public function cleanup_sitemaps() {
        // Remove rewrite rules on deactivation
        flush_rewrite_rules();
        
        // Clean up any options or transients
        delete_transient('cranseo_sitemap_data');
    }
}