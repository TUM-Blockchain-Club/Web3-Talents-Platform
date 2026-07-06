<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * First-login agreement form.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class agreement_form extends \moodleform {
    /**
     * Define form fields.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'accepted', '', get_string('agreement_checkbox', 'local_web3talents'));
        $mform->setType('accepted', PARAM_BOOL);
        $mform->addRule('accepted', get_string('required'), 'required');

        $this->add_action_buttons(false, get_string('agreement_accept', 'local_web3talents'));
    }

    /**
     * Validate checkbox acceptance.
     *
     * @param array $data Submitted data.
     * @param array $files Uploaded files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['accepted'])) {
            $errors['accepted'] = get_string('required');
        }
        return $errors;
    }
}
