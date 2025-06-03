<?php
namespace RemoveUnusedCSS;

use voku\helper\HtmlDomParser;
use Sabberworm\CSS\Parser as CSSParser;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Rule\Rule;

class Processor {
    private $queue;

    public function __construct() {
        $this->queue = new BackgroundQueue();
    }

    public function capturePageHTML() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        ob_start(function($buffer) {
            $this->queuePageForCleanup($buffer, home_url($_SERVER['REQUEST_URI']));
            return $buffer;
        });
    }

    public function queuePageForCleanup($html, $url) {
        if (!$html || !$url) {
            return false;
        }

        $this->queue->push_to_queue([
            'html' => $html,
            'url' => $url
        ]);
        $this->queue->save()->dispatch();
        return true;
    }

    public function processHTML($html, $url) {
        if (empty($html)) {
            return false;
        }

        $usedSelectors = $this->extractUsedSelectors($html);
        $cssFiles = $this->getEnqueuedStylesheets();

        foreach ($cssFiles as $cssFile) {
            $this->processCSSFile($cssFile, $usedSelectors);
        }

        return true;
    }

    private function extractUsedSelectors($html) {
        $dom = HtmlDomParser::str_get_html($html);
        $usedSelectors = [];

        // Extract class selectors
        foreach ($dom->find('*[class]') as $node) {
            $classes = explode(' ', $node->getAttribute('class'));
            foreach ($classes as $cls) {
                $cls = trim($cls);
                if ($cls) {
                    $usedSelectors['.' . $cls] = true;
                }
            }
        }

        // Extract ID selectors
        foreach ($dom->find('*[id]') as $node) {
            $id = trim($node->getAttribute('id'));
            if ($id) {
                $usedSelectors['#' . $id] = true;
            }
        }

        // Extract element selectors
        foreach ($dom->find('*') as $node) {
            $tag = strtolower($node->tag);
            if ($tag && !isset($usedSelectors[$tag])) {
                $usedSelectors[$tag] = true;
            }
        }

        return array_keys($usedSelectors);
    }

    private function getEnqueuedStylesheets() {
        global $wp_styles;
        $cssFiles = [];

        if (!($wp_styles instanceof \WP_Styles)) {
            $wp_styles = new \WP_Styles();
        }

        foreach ($wp_styles->queue as $handle) {
            $src = $wp_styles->registered[$handle]->src;
            if ($src) {
                $localPath = $this->getLocalPathFromUrl($src);
                if ($localPath) {
                    $cssFiles[] = $localPath;
                }
            }
        }

        return array_filter($cssFiles);
    }

    private function processCSSFile($filePath, array $usedSelectors) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $cssContent = file_get_contents($filePath);
        $parser = new CSSParser($cssContent);
        $cssDocument = $parser->parse();

        $this->removeUnusedRules($cssDocument, $usedSelectors);
        $this->saveOptimizedCSS($filePath, $cssDocument);

        return true;
    }

    private function removeUnusedRules(Document $cssDocument, array $usedSelectors) {
        foreach ($cssDocument->getAllDeclarationBlocks() as $block) {
            $matched = false;
            $selectors = $block->getSelectors();

            foreach ($selectors as $selector) {
                if ($this->isSelectorUsed($selector, $usedSelectors)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $block->removeSelf();
            }
        }
    }

    private function isSelectorUsed($selector, array $usedSelectors) {
        $selectorString = (string) $selector->getSelector();
        $individualSelectors = array_map('trim', explode(',', $selectorString));

        foreach ($individualSelectors as $singleSelector) {
            if (in_array($singleSelector, $usedSelectors)) {
                return true;
            }

            foreach ($usedSelectors as $usedSelector) {
                if (strpos($singleSelector, $usedSelector) !== false) {
                    return true;
                }
            }

            if (preg_match('/^[a-z0-9\-_]+(:[a-z]+)?$/i', $singleSelector)) {
                $baseSelector = preg_replace('/:[a-z]+$/i', '', $singleSelector);
                if (in_array($baseSelector, $usedSelectors)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function saveOptimizedCSS($originalPath, Document $cssDocument) {
        $optimizedDir = WP_CONTENT_DIR . '/cache/optimized-css/';
        if (!file_exists($optimizedDir)) {
            wp_mkdir_p($optimizedDir);
        }

        $filename = 'optimized-' . basename($originalPath);
        $optimizedPath = $optimizedDir . $filename;

        file_put_contents($optimizedPath, $cssDocument->render());
        $this->updateEnqueue($originalPath, $optimizedPath);
    }

    private function updateEnqueue($originalPath, $optimizedPath) {
        $originalUrl = $this->getUrlFromPath($originalPath);
        $optimizedUrl = content_url('/cache/optimized-css/' . basename($optimizedPath));

        add_filter('style_loader_src', function($src) use ($originalUrl, $optimizedUrl) {
            return str_replace($originalUrl, $optimizedUrl, $src);
        }, 100);
    }

    private function getLocalPathFromUrl($url) {
        $contentUrl = content_url();
        $contentPath = WP_CONTENT_DIR;

        if (strpos($url, $contentUrl) === 0) {
            return str_replace($contentUrl, $contentPath, $url);
        }

        $siteUrl = site_url();
        $abspath = ABSPATH;

        if (strpos($url, $siteUrl) === 0) {
            return str_replace($siteUrl, $abspath, $url);
        }

        return false;
    }

    private function getUrlFromPath($path) {
        $contentPath = WP_CONTENT_DIR;
        $contentUrl = content_url();

        if (strpos($path, $contentPath) === 0) {
            return str_replace($contentPath, $contentUrl, $path);
        }

        $abspath = ABSPATH;
        $siteUrl = site_url();

        if (strpos($path, $abspath) === 0) {
            return str_replace($abspath, $siteUrl, $path);
        }

        return false;
    }
}