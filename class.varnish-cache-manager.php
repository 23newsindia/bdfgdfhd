<?php

class ClpVarnishCacheManager {

    private $cache_settings = [];
    private $static_asset_lifetime = 2592000; // 30 days for static assets
    private $default_lifetime = 3600; // 1 hour default

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

            // Add cache control headers based on file type
            $headers = [
                'Host' => $host,
                'X-Cache-Debug' => '1',
                'X-Cache-Control' => 'public',
                'X-Cache-Status' => 'MISS'
            ];

            // Set cache times based on file type
            $ext = pathinfo($parsed_url['path'], PATHINFO_EXTENSION);
            $static_assets = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'woff', 'woff2'];
            
            if (in_array($ext, $static_assets)) {
                $ttl = $this->get_static_asset_lifetime();
                $headers['Cache-Control'] = 'public, max-age=' . $ttl;
                $headers['X-Cache-TTL'] = $ttl;
            } else {
                $ttl = $this->get_cache_lifetime();
                $headers['Cache-Control'] = 'public, max-age=' . $ttl;
                $headers['X-Cache-TTL'] = $ttl;
            }

            // Add Varnish specific headers
            $headers['X-Varnish-Cache'] = '1';
            $headers['X-Varnish-Debug'] = '1';
            
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

            // Add standard Varnish debug headers
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

            // Get cache status headers
            $cache_status = wp_remote_retrieve_header($response, 'X-Cache');
            $cache_hits = wp_remote_retrieve_header($response, 'X-Cache-Hits');
            $age = wp_remote_retrieve_header($response, 'Age');
            
            if ($http_status_code != 200) {
                throw new \Exception(sprintf(
                    'HTTP Status: %s, Cache Status: %s, Cache Hits: %s, Age: %s', 
                    $http_status_code, 
                    $cache_status ?: 'unknown', 
                    $cache_hits ?: '0',
                    $age ?: '0'
                ));
            }

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            echo esc_html(sprintf('Varnish Cache Purge Failed, Error Message: %s', $error_message));
            exit();
        }
    }

    // Add this function to handle response headers
    public function add_cache_headers() {
        if (!$this->is_enabled()) {
            return;
        }

        // Get current URL path
        $path = $_SERVER['REQUEST_URI'];
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        
        // Set appropriate cache headers based on file type
        $static_assets = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'woff', 'woff2'];
        
        if (in_array($ext, $static_assets)) {
            $ttl = $this->get_static_asset_lifetime();
            header('Cache-Control: public, max-age=' . $ttl);
            header('X-Cache-TTL: ' . $ttl);
        } else {
            $ttl = $this->get_cache_lifetime();
            header('Cache-Control: public, max-age=' . $ttl);
            header('X-Cache-TTL: ' . $ttl);
        }

        // Add debug headers
        header('X-Varnish-Cache: 1');
        header('X-Cache-Debug: 1');
        header('X-Varnish-Debug: 1');
    }
}