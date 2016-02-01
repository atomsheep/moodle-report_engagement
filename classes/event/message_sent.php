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
 * The message_sent event.
 *
 * @package    report_engagement
 * @author       Danny Liu <danny.liu@mq.edu.au>
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_engagement\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The message_sent event class.
 *
 * @property-read array $other {
 *      - int recipientid: user id of recipient
 *      - int courseid: course id
 *      - int messageid: message id
 *      - bool success: whether the send was successful or not
 *      - string result: message send status message
 * }
 *
 * @since    Moodle 2.7
 * @copyright 2016 Macquarie University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class message_sent extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }
 
    public static function get_name() {
        return get_string('eventmessage_sent', 'report_engagement');
    }
 
    public function get_description() {
        if ($this->other['success']) {
            return "The user with id {$this->userid} sent a message of id {$this->other['messageid']} with sent id {$this->data['objectid']} to user with id {$this->other['recipientid']} for course with id {$this->other['courseid']}.";
        } else {
            return "The user with id {$this->userid} attempted to send a message of id {$this->other['messageid']} to user with id {$this->other['recipientid']} for course with id {$this->other['courseid']} resulting in error message of {$this->other['result']}.";
        }
    }
 
    public function get_url() {
        return null;
    }
 
    public function get_legacy_logdata() {
        return array(
            $this->other['courseid'], 
            'report engagement message send', 
            $this->get_url()
        );
    }
    
    /*
    public static function get_legacy_eventname() {
        // Override ONLY if you are migrating events_trigger() call.
        return 'MYPLUGIN_OLD_EVENT_NAME';
    }
 
    protected function get_legacy_eventdata() {
        // Override if you migrating events_trigger() call.
        $data = new \stdClass();
        $data->id = $this->objectid;
        $data->userid = $this->relateduserid;
        return $data;
    }
    */
}