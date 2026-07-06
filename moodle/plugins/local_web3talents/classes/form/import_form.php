<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_web3talents\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Accepted-applicant import form.
 *
 * @package    local_web3talents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Define form fields.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'formtype', 'import');
        $mform->setType('formtype', PARAM_ALPHA);

        $mform->addElement(
            'filepicker',
            'applicantfile',
            get_string('importfile', 'local_web3talents'),
            null,
            ['accepted_types' => ['.csv', '.xlsx', '.xls']]
        );
        $mform->addHelpButton('applicantfile', 'importfile', 'local_web3talents');
        $mform->addRule('applicantfile', null, 'required');

        $choices = \csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        $mform->setDefault('delimiter_name', 'comma');

        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), \core_text::get_encodings());
        $mform->setDefault('encoding', 'UTF-8');

        $this->add_action_buttons(false, get_string('import_applicants', 'local_web3talents'));
    }
}
