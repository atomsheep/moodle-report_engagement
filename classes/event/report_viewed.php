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
 * The report_viewed event. User views any engagement analytics plugin report.
 *
 * @package    report_engagement
 * @author       Danny Liu <danny.liu@mq.edu.au>
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_engagement\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The report_viewed event class.
 *
 * @property-read array $other {
 *      - int userid: user id requested, if any
 *      - int courseid: course id requested
 *      - int messageid: message id requested
 *      - string page: filename of page visited, either index or mailer
 * }
 *
 * @since    Moodle 2.7
 * @copyright 2016 Macquarie University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class report_viewed extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'r'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }
 
    public static function get_name() {
        return get_string('eventreport_viewed', 'report_engagement');
    }
 
    public function get_description() {
        if (isset($this->other['userid'])) {
            return "The user with id {$this->userid} viewed an engagement analytics report at page {$this->other['page']} for user with id {$this->other['userid']} in course with id {$this->other['courseid']}.";
        } else {
            return "The user with id {$this->userid} viewed an engagement analytics report at page {$this->other['page']} for course with id {$this->other['courseid']}.";
        }
    }
 
    public function get_url() {
        if (isset($this->other['userid'])) {
            return new \moodle_url('/report/engagement/'.$this->other['page'].'.php', array(
                'id' => $this->other['courseid'],
                'userid' => $this->other['userid']
            ));
        } else {
            return new \moodle_url('/report/engagement/'.$this->other['page'].'.php', array(
                'id' => $this->other['courseid']
            ));
        }
    }
 
    public function get_legacy_logdata() {
        return array(
            $this->other['courseid'], 
            'report engagement', 
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