<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\task;

use local_web3talents\local\retention_service;

/**
 * Cleans up expired Web3 Talents operational data.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_retention extends \core\task\scheduled_task {
    /**
     * Task display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_retention', 'local_web3talents');
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        $counts = retention_service::cleanup();
        mtrace("Deleted {$counts['exportfiles']} Web3 Talents temporary export file(s).");
        mtrace("Marked {$counts['applicants']} expired Web3 Talents applicant(s) as removed.");
    }
}
