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
 * Displays indicator reports for a chosen course
 *
 * @package    report_engagement
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

require_once($CFG->libdir.'/formslib.php');

class report_engagement_manage_indicators_form extends moodleform {

    public function definition_after_data() {
        
        parent::definition_after_data();
        
        $mform =& $this->_form;
        
        $defaultvalues = $this->_form->_defaultValues;
        
        $mform->addElement('hidden', 'contextid', $defaultvalues['contextid']);

        $indicators = $defaultvalues['indicators'];
        foreach ($indicators as $name) {
            $mform->addElement('header', "snippet_header_$name", get_string('snippetheader', 'report_engagement', $name));
            $counter = 1;
            foreach ($defaultvalues['snippets'][$name] as $id => $snippet) {
                $snippetgroup = array();
                $snippetgroup[] =& $mform->createElement('textarea', 
                    "snippet_$name"."_$id",
                    '',
                    array('rows' => 3, 'cols' => 50));
                $snippetgroup[] =& $mform->createElement('checkbox', 
                    "snippet_delete_{$name}_{$id}",
                    '',
                    get_string('snippetdelete', 'report_engagement'));
                $mform->setDefault("snippet_{$name}_{$id}", $snippet);
                $mform->addGroup($snippetgroup,
                    "snippet_group_{$name}_{$id}",
                    get_string('snippetnumber', 'report_engagement', $counter),
                    array(' '),
                    false);
                $counter += 1;
            }
            $mform->addElement('textarea',
                "snippet_".$name."_new",
                get_string('snippetnew', 'report_engagement'),
                array('rows' => 3, 'cols' => 50));
        }
        
        $this->add_action_buttons(false);
    }

    protected function definition() {
        
    }

    // Form verification.
    public function validation($data, $files) {
        $mform =& $this->_form;

        $errors = array();
        
        return $errors;
    }
}
