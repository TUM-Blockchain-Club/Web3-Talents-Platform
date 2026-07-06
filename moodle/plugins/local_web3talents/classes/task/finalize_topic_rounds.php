<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\task;

use local_web3talents\local\topic_round_service;

/**
 * Finalizes closed topic-selection rounds.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finalize_topic_rounds extends \core\task\scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_finalize_topic_rounds', 'local_web3talents');
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        $count = topic_round_service::finalize_due_rounds();
        mtrace("Finalized {$count} Web3 Talents topic round(s).");
    }
}
