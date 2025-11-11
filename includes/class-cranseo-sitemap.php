<?php
class CranSEO_Sitemap {
    private $sitemap_file;

    public function __construct() {
        $this->sitemap_file = ABSPATH . 'sitemap-cranseo.xml';
        
        add_action('save_post', array($this, 'maybe_update_sitemap'), 10, 3);
        add_action('trashed_post', array($this, 'update_sitemap'));
        add_action('deleted_post', array($this, 'update_sitemap'));
        add_action('publish_post', array($this, 'update_sitemap'));
        
        add_action('init', array($this, 'maybe_generate_sitemap'));
    }

    public function maybe_update_sitemap($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $included_types = get_option('cranseo_sitemap_post_types', array('product', 'post', 'page'));
        if (in_array($post->post_type, $included_types)) {
            $this->update_sitemap();
        }
    }

    public function update_sitemap() {
        $included_types = get_option('cranseo_sitemap_post_types', array('product', 'post', 'page'));
        $urls = array();

        foreach ($included_types as $post_type) {
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'modified',
                'order' => 'DESC'
            ));

            foreach ($posts as $post) {
                $urls[] = array(
                    'loc' => get_permalink($post->ID),
                    'lastmod' => get_the_modified_date('c', $post->ID),
                    'changefreq' => 'weekly',
                    'priority' => $post_type === 'product' ? '0.8' : '0.6'
                );
            }
        }

        $this->generate_sitemap_xml($urls);
    }

    private function generate_sitemap_xml($urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>' . esc_url($url['loc']) . '</loc>';
            $xml .= '<lastmod>' . $url['lastmod'] . '</lastmod>';
            $xml .= '<changefreq>' . $url['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $url['priority'] . '</priority>';
            $xml .= '</url>';
        }
        
        $xml .= '</urlset>';

        file_put_contents($this->sitemap_file, $xml);
    }

    public function maybe_generate_sitemap() {
        if (!file_exists($this->sitemap_file)) {
            $this->update_sitemap();
        }
    }

    public function regenerate_sitemap() {
        $this->update_sitemap();
        return file_exists($this->sitemap_file);
    }

    public function get_sitemap_url() {
        return home_url('/sitemap-cranseo.xml');
    }
}