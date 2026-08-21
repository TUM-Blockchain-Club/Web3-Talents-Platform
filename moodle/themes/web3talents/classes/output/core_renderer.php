<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_web3talents\output;

/**
 * Web3 Talents core renderer overrides.
 *
 * @package    theme_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Extends Boost's renderer (not core's) — boost/layout/drawers.php calls
// theme_boost-only methods such as firstview_fakeblocks().
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Opt the document into Bootstrap 5.3's dark colour mode.
     *
     * Boost already ships compiled `[data-bs-theme="dark"]` blocks (see
     * theme/boost/style/moodle.css), but nothing ever sets the attribute, so the
     * data-URI SVGs Bootstrap bakes into form-select arrows, switches, close
     * buttons and accordion chevrons stayed in their dark-on-light variants.
     * Setting it here flips those assets; the surface colours themselves still
     * come from scss/moodle-core.scss.
     *
     * @return string
     */
    public function htmlattributes() {
        $attributes = parent::htmlattributes();
        if (strpos($attributes, 'data-bs-theme') === false) {
            $attributes .= ' data-bs-theme="dark"';
        }
        return $attributes;
    }
}
