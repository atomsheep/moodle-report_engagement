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
 * Displays settings for the indicator parameter discovery helper.
 *
 * @package    report_engagement
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die();
}

require_once($CFG->libdir.'/formslib.php');

class report_engagement_indicator_helper_form extends moodleform {

    protected function definition() {

        $mform =& $this->_form;
        
        $mform->addElement('header', 'settings', get_string('indicator_helper_settings', 'report_engagement'));
        
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('select', 'target', get_string('indicator_helper_target', 'report_engagement'), $this->_customdata['target']);
        $mform->addHelpButton('target', 'indicator_helper_target', 'report_engagement');
        $mform->addElement('select', 'discover', get_string('indicator_helper_discover', 'report_engagement'), $this->_customdata['discover']);
        $mform->addHelpButton('discover', 'indicator_helper_discover', 'report_engagement');
        
        $mform->addElement('select', 'indicator', get_string('indicator_helper_indicator', 'report_engagement'), $this->_customdata['indicator']);
        $mform->addHelpButton('indicator', 'indicator_helper_indicator', 'report_engagement');
        $mform->disabledIf('indicator', 'discover', 'neq', 'i');
        
        $indicatorarray = array();
        foreach ($this->_customdata['indicator'] as $indicatorname) {
            $indicatorarray[] =& $mform->createElement('checkbox', "activeindicators_$indicatorname", $indicatorname, $indicatorname);
            $mform->setDefault("activeindicators_$indicatorname", true);
            $mform->disabledIf("activeindicators_$indicatorname", 'discover', 'neq', 'w');
        }
        $mform->addGroup($indicatorarray, 'activeindicatorsgroup', get_string('indicator_helper_activeindicators', 'report_engagement'), array(' '), false);
        $mform->addHelpButton('activeindicatorsgroup', 'indicator_helper_activeindicators', 'report_engagement');
        
        $mform->addElement('select', 'iteri', get_string('indicator_helper_iteri', 'report_engagement'), $this->_customdata['iteri']);
        $mform->addHelpButton('iteri', 'indicator_helper_iteri', 'report_engagement');
        $mform->setDefault('iteri', $this->_customdata['default_iteri']);
        $mform->addElement('select', 'iterj', get_string('indicator_helper_iterj', 'report_engagement'), $this->_customdata['iterj']);
        $mform->addHelpButton('iterj', 'indicator_helper_iterj', 'report_engagement');
        $mform->setDefault('iterj', $this->_customdata['default_iterj']);
        
        $mform->addElement('checkbox', 'datadump', get_string('indicator_helper_datadump', 'report_engagement'));
        
        $mform->addElement('submit', 'submitrundiscovery', get_string('indicator_helper_rundiscovery', 'report_engagement'));
        $mform->addElement('submit', 'submitruncorrelate', get_string('indicator_helper_runcorrelate', 'report_engagement'));
    }

    // Form verification.
    public function validation($data, $files) {
        global $CFG;
        
        $mform =& $this->_form;

        $errors = array();

        return $errors;
    }
}
