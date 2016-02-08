<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Displays a textarea to report on results of the indicator parameter discovery helper.
 *
 * @package    report_engagement
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die();
}

require_once($CFG->libdir.'/formslib.php');

class report_engagement_indicator_helper_report extends moodleform {

    protected function definition() {

        $mform =& $this->_form;
        
        $mform->addElement('header', 'settings', get_string('indicator_helper_report', 'report_engagement'));
        
        $mform->addElement('textarea', 'output', get_string('indicator_helper_report_textarea', 'report_engagement'), array('rows' => 5));
        $mform->addHelpButton('output', 'indicator_helper_report_textarea', 'report_engagement');
        $mform->setDefault('output', $this->_customdata['output']);
        
    }

    // Form verification.
    public function validation($data, $files) {
        global $CFG;
        
        $mform =& $this->_form;

        $errors = array();

        return $errors;
    }
}
