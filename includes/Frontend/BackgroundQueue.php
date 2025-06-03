<?php
namespace RemoveUnusedCSS\Frontend;

use WP_Background_Process;
use RemoveUnusedCSS\Database\Queries\UsedCSS;
use voku\helper\HtmlDomParser;
use Sabberworm\CSS\Parser as CSSParser;

class BackgroundQueue extends WP_Background_Process {
    /**
     * Action name
     *
     * @var string
     */
    protected $action = 'rucss_queue';

    /**
     * Process queue item
     *
     * @param mixed $item
     * @return bool
     */
    protected function task($item) {
        if (empty($item['url']) || empty($item['html'])) {
            return false;
        }

        $used_css = $this->process_html($item['html']);
        
        // Save to database
        $used_css_query = new UsedCSS();
        $used_css_query->update_or_insert(
            [
                'url' => $item['url'],
                'is_mobile' => $item['is_mobile'] ?? false
            ],
            [
                'css' => $used_css,
                'status' => 'completed',
                'hash' => md5($used_css),
                'modified' => current_time('mysql')
            ]
        );

        return false;
    }

    /**
     * Process HTML and extract used CSS
     *
     * @param string $html
     * @return string
     */
    private function process_html($html) {
        $dom = HtmlDomParser::str_get_html($html);
        $used_selectors = $this->get_used_selectors($dom);
        $combined_css = '';

        // Process all stylesheets
        foreach ($this->get_enqueued_stylesheets() as $stylesheet) {
            $combined_css .= $this->process_stylesheet($stylesheet, $used_selectors);
        }

        return $combined_css;
    }

    /**
     * Get used selectors from DOM
     *
     * @param HtmlDomParser $dom
     * @return array
     */
    private function get_used_selectors($dom) {
        $selectors = [];

        // Get class selectors
        foreach ($dom->find('[class]') as $element) {
            $classes = explode(' ', $element->class);
            foreach ($classes as $class) {
                $selectors['.' . trim($class)] = true;
            }
        }

        // Get ID selectors
        foreach ($dom->find('[id]') as $element) {
            $selectors['#' . $element->id] = true;
        }

        // Get element selectors
        foreach ($dom->find('*') as $element) {
            $selectors[$element->tag] = true;
        }

        return array_keys($selectors);
    }

    /**
     * Get all enqueued stylesheets
     *
     * @return array
     */
    private function get_enqueued_stylesheets() {
        global $wp_styles;
        $stylesheets = [];

        if (!($wp_styles instanceof \WP_Styles)) {
            return $stylesheets;
        }

        foreach ($wp_styles->queue as $handle) {
            $src = $wp_styles->registered[$handle]->src;
            if ($src) {
                $stylesheets[] = $this->get_local_path($src);
            }
        }

        return array_filter($stylesheets);
    }

    /**
     * Process a single stylesheet
     *
     * @param string $file_path
     * @param array $used_selectors
     * @return string
     */
    private function process_stylesheet($file_path, $used_selectors) {
        if (!file_exists($file_path)) {
            return '';
        }

        $css = file_get_contents($file_path);
        $parser = new CSSParser($css);
        $css_doc = $parser->parse();

        $this->remove_unused_rules($css_doc, $used_selectors);

        return $css_doc->render();
    }

    /**
     * Remove unused CSS rules
     *
     * @param CSSDocument $css_doc
     * @param array $used_selectors
     */
    private function remove_unused_rules($css_doc, $used_selectors) {
        foreach ($css_doc->getAllDeclarationBlocks() as $block) {
            $keep = false;
            
            foreach ($block->getSelectors() as $selector) {
                if ($this->is_selector_used($selector, $used_selectors)) {
                    $keep = true;
                    break;
                }
            }
            
            if (!$keep) {
                $block->removeSelf();
            }
        }
    }

    /**
     * Check if selector is used
     *
     * @param mixed $selector
     * @param array $used_selectors
     * @return bool
     */
    private function is_selector_used($selector, $used_selectors) {
        $selector_str = (string) $selector->getSelector();
        
        // Check simple selectors
        if (in_array($selector_str, $used_selectors)) {
            return true;
        }
        
        // Check complex selectors
        foreach ($used_selectors as $used) {
            if (strpos($selector_str, $used) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Convert URL to local path
     *
     * @param string $url
     * @return string
     */
    private function get_local_path($url) {
        $content_url = content_url();
        $content_dir = WP_CONTENT_DIR;
        
        if (strpos($url, $content_url) === 0) {
            return str_replace($content_url, $content_dir, $url);
        }
        
        $site_url = site_url();
        $abspath = ABSPATH;
        
        if (strpos($url, $site_url) === 0) {
            return str_replace($site_url, $abspath, $url);
        }
        
        return '';
    }
}