<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\form;

use local_web3talents\local\applicant_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Manual accepted-applicant form.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applicant_form extends \moodleform {
    /**
     * Define form fields.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'formtype', 'manual');
        $mform->setType('formtype', PARAM_ALPHA);

        $mform->addElement('text', 'firstname', get_string('firstname', 'local_web3talents'), ['size' => 32]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required');

        $mform->addElement('text', 'lastname', get_string('lastname', 'local_web3talents'), ['size' => 32]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required');

        // PARAM_EMAIL scrubs a malformed address to an empty string, which would report a
        // missing value instead of an invalid one, so validation() does the checking.
        $mform->addElement('text', 'email', get_string('email', 'local_web3talents'), ['size' => 48]);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        $mform->addRule('email', null, 'required');

        $mform->addElement('text', 'cohortid', get_string('cohortid', 'local_web3talents'), ['size' => 32]);
        $mform->setType('cohortid', PARAM_TEXT);
        $mform->setDefault('cohortid', 'fundamentals');
        $mform->addRule('cohortid', null, 'required');

        $mform->addElement('select', 'status', get_string('status', 'local_web3talents'), applicant_service::statuses());
        $mform->setDefault('status', applicant_service::STATUS_ACCEPTED);

        $mform->addElement('textarea', 'notes', get_string('notes', 'local_web3talents'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('notes', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('add_applicant', 'local_web3talents'));
    }

    /**
     * Validate submitted applicant data.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $email = \core_text::strtolower(trim((string)($data['email'] ?? '')));
        if ($email !== '' && !validate_email($email)) {
            $errors['email'] = get_string('error_invalid_email', 'local_web3talents', $email);
        }

        return $errors;
    }
}
