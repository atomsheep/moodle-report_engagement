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
 * Upgrades for engagement
 *
 * @package    report_engagement
 * @copyright  2012 NetSpot Pty Ltd, 2015 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_report_engagement_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2015052101) {
        // Conditionally create table for report_engagement_generic.
        if (!$dbman->table_exists('report_engagement_generic')) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/report/engagement/db/install.xml',
                'report_engagement_generic');
        }
    }
    
    if ($oldversion < 2015052102) {
        // Conditionally launch create table for report_engagement_sentlog.
        if (!$dbman->table_exists('report_engagement_sentlog')) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/report/engagement/db/install.xml',
                'report_engagement_sentlog');
        }
        
        // Conditionally launch create table for report_engagement_messagelog.
        if (!$dbman->table_exists('report_engagement_messagelog')) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/report/engagement/db/install.xml',
                'report_engagement_messagelog');
        }

        // Conditionally launch create table for report_engagement_mymessages.
        if (!$dbman->table_exists('report_engagement_mymessages')) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/report/engagement/db/install.xml',
                'report_engagement_mymessages');
        }
        
        // Engagement savepoint reached.
        upgrade_plugin_savepoint(true, 2015052102, 'report', 'engagement');
    }
    
    if ($oldversion < 2016012902) {
        // Conditionally launch create table for report_engagement_snippets.
        if (!$dbman->table_exists('report_engagement_snippets')) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/report/engagement/db/install.xml',
                'report_engagement_snippets');
        }
        
        // Populate snippets from lang file to DB.
        require_once(dirname(__FILE__).'/../locallib.php');
        report_engagement_populate_snippets_from_lang('encouragement');
        
        // Engagement savepoint reached.
        upgrade_plugin_savepoint(true, 2016012902, 'report', 'engagement');
    }
    
    return true;
}
