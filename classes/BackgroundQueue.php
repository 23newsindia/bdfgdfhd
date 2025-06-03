<?php
namespace RemoveUnusedCSS;

use WP_Background_Process;

class BackgroundQueue extends WP_Background_Process {
    protected $action = 'remove_unused_css_queue';

    protected function task($item) {
        if (!isset($item['html']) || !isset($item['url'])) {
            return false;
        }

        $processor = new Processor();
        $processor->processHTML($item['html'], $item['url']);
        return false;
    }

    protected function complete() {
        parent::complete();
        // Optional: Add any completion logging here
    }
}