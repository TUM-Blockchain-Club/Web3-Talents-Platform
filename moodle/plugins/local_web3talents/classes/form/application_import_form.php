<?php
namespace local_web3talents\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class application_import_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'csv', get_string('review_import_file', 'local_web3talents'), null,
            ['accepted_types' => ['.csv'], 'maxbytes' => 5 * 1024 * 1024]);
        $mform->addRule('csv', null, 'required');
        $mform->addElement('text', 'cohort', get_string('cohortid', 'local_web3talents'));
        $mform->setType('cohort', PARAM_TEXT);
        $mform->setDefault('cohort', 'fundamentals-2026-27');
        $mform->addRule('cohort', null, 'required');
        $mform->addRule('cohort', null, 'maxlength', 100);
        $this->add_action_buttons(true, get_string('review_preview', 'local_web3talents'));
    }
}
