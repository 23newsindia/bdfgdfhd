<?php

class ClpVarnishCacheManager {

    private $cache_settings = [];
    private $static_asset_lifetime = 604800; // 1 week for static assets
    private $default_lifetime = 7200; // 2 hours default
    private $homepage_lifetime = 3600; // 1 hour for homepage
    private $product_lifetime = 14400; // 4 hours for products

    public function is_enabled() {
        $settings = $this->get_cache_settings();
        return (isset($settings['enabled']) && $settings['enabled']);
    }

    public function get_server() {
        $settings = $this->get_cache_settings();
        return (isset($settings['server']) ? $settings['server'] : '');
    }

    public function get_cache_lifetime() {
        $settings = $this->get_cache_settings();
        return (isset($settings['cacheLifetime']) ? $settings['cacheLifetime'] : $this->default_lifetime);
    }

    public function get_static_asset_lifetime() {
        $settings = $this->get_cache_settings();
        return (isset($settings['staticAssetLifetime']) ? $settings['staticAssetLifetime'] : $this->static_asset_lifetime);
    }

    public function get_homepage_lifetime() {
        $settings = $this->get_cache_settings();
        return (isset($settings['homepageLifetime']) ? $settings['homepageLifetime'] : $this->homepage_lifetime);
    }

    public function get_product_lifetime() {
        $settings = $this->get_cache_settings();
        return (isset($settings['productLifetime']) ? $settings['productLifetime'] : $this->product_lifetime);
    }

    public function get_cache_tag_prefix() {
        $settings = $this->get_cache_settings();
        return (isset($settings['cacheTagPrefix']) ? $settings['cacheTagPrefix'] : '');
    }

    public function get_excluded_params() {
        $settings = $this->get_cache_settings();
        $excluded_params = (isset($settings['excludedParams']) ? (array)$settings['excludedParams'] : []);
        return implode(',', $excluded_params);
    }

    public function get_excludes() {
        $settings = $this->get_cache_settings();
        $excludes = (isset($settings['excludes']) ? (array)$settings['excludes'] : []);
        return implode(PHP_EOL, $excludes);
    }

    public function get_cache_settings() {
        if (empty($this->cache_settings)) {
            $settings_file = sprintf('%s/.varnish-cache/settings.json', rtrim(getenv('HOME'), '/'));
            if (file_exists($settings_file)) {
                $cache_settings = @json_decode(file_get_contents($settings_file), true);
                if (!empty($cache_settings)) {
                    $this->cache_settings = $cache_settings;
                }
            }
        }
        return $this->cache_settings;
    }

    public function write_cache_settings(array $settings) {
        $settings_file = sprintf('%s/.varnish-cache/settings.json', rtrim(getenv('HOME'), '/'));
        $settings = json_encode($settings, JSON_PRETTY_PRINT);
        file_put_contents($settings_file, $settings);
    }

    public function reset_cache_settings() {
        $this->cache_settings = [];
    }

    public function purge_host($host): void {
        $headers = [
            'Host' => $host,
            'X-Cache-Debug' => '1',
            'X-Cache-Control' => 'no-cache'
        ];
        $this->purge($headers);
    }

    public function purge_tag($tag): void {
        $this->purge_tags([$tag]);
    }

    public function purge_tags(array $tags): void {
        $headers = [
            'X-Cache-Tags' => implode(',', $tags),
            'X-Cache-Debug' => '1',
            'X-Cache-Control' => 'no-cache'
        ];
        $this->purge($headers);
    }

    public function purge_url($url): void {
        $parsed_url = parse_url($url);
        if (isset($parsed_url['host'])) {
            $server = $this->get_server();
            $host = $parsed_url['host'];
            $request_url = $server;
            
            if (isset($parsed_url['path'])) {
                $path = $parsed_url['path'];
                $request_url = sprintf('%s/%s', $request_url, ('/' == $path ? '' : ltrim($path, '/')));
            }

            $query_string = parse_url($url, PHP_URL_QUERY);
            if (!empty($query_string)) {
                parse_str($query_string, $query_params);
                if (!empty($query_params)) {
                    $query_string = http_build_query($query_params);
                    $request_url = sprintf('%s?%s', $request_url, $query_string);
                }
            }

            // Set cache times based on URL pattern
            $headers = [
                'Host' => $host,
                'X-Cache-Debug' => '1',
                'X-Cache-Status' => 'MISS'
            ];

            // Homepage
            if ($path == '/' || $path == '/index.php') {
                $ttl = $this->get_homepage_lifetime();
                $headers['Cache-Control'] = 'public, max-age=' . $ttl;
                $headers['X-Cache-TTL'] = $ttl;
            }
            // Product pages
            elseif (strpos($path, '/product/') === 0) {
                $ttl = $this->get_product_lifetime();
                $headers['Cache-Control'] = 'public, max-age=' . $ttl;
                $headers['X-Cache-TTL'] = $ttl;
            }
            // Static assets
            elseif (preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|pdf|doc|docx|ppt|pptx|woff|woff2)$/', $path)) {
                $ttl = $this->get_static_asset_lifetime();
                $headers['Cache-Control'] = 'public, max-age=' . $ttl;
                $headers['X-Cache-TTL'] = $ttl;
            }
            // Default cache time
            else {
                $ttl = $this->get_cache_lifetime();
                $headers['Cache-Control'] = 'public, max-age=' . $ttl;
                $headers['X-Cache-TTL'] = $ttl;
            }

            $this->purge($headers, $request_url);
        } else {
            throw new \Exception(sprintf('Not a valid url: %s', $url));
        }
    }

    private function purge(array $headers, $request_url = null): void {
        try {
            if (is_null($request_url)) {
                $request_url = $this->get_server();
            }
            $request_url = sprintf('http://%s', $request_url);

            $headers['X-Varnish-Debug'] = '1';
            $headers['X-Cache-Debug'] = '1';
            
            $response = wp_remote_request(
                $request_url,
                [
                    'sslverify' => false,
                    'method'    => 'PURGE',
                    'headers'   => $headers,
                    'timeout'   => 30
                ]
            );

            $http_status_code = 0;
            if (isset($response['response']['code'])) {
                $http_status_code = $response['response']['code'];
            }

            if ($http_status_code != 200) {
                throw new \Exception(sprintf('HTTP Status Code: %d', $http_status_code));
            }

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            echo esc_html(sprintf('Varnish Cache Purge Failed, Error Message: %s', $error_message));
            exit();
        }
    }

    public function add_cache_headers() {
        if (!$this->is_enabled()) {
            return;
        }

        // Check if WooCommerce functions exist before using them
        $is_woo_page = false;
        if (function_exists('is_cart') && function_exists('is_checkout') && function_exists('is_account_page')) {
            $is_woo_page = is_cart() || is_checkout() || is_account_page();
        }

        // Don't cache admin, login, or WooCommerce pages
        if (
            is_admin() ||
            is_user_logged_in() ||
            $is_woo_page ||
            (function_exists('is_product') && is_product())
        ) {
            nocache_headers();
            return;
        }

        $ttl = $this->get_cache_lifetime();

        // Set different cache times based on content type
        if (is_front_page() || is_home()) {
            $ttl = $this->get_homepage_lifetime();
        } elseif (function_exists('is_product') && is_product() || is_single()) {
            $ttl = $this->get_product_lifetime();
        } elseif (preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|pdf|doc|docx|ppt|pptx|woff|woff2)$/', $_SERVER['REQUEST_URI'])) {
            $ttl = $this->get_static_asset_lifetime();
        }

        header('Cache-Control: public, max-age=' . $ttl);
        header('X-Cache-TTL: ' . $ttl);
        header('X-Varnish-Cache: 1');
        header('X-Cache-Debug: 1');
        header('X-Varnish-Debug: 1');
    }
}