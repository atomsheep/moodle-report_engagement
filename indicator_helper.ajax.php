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
 * Output rendering of engagement report
 *
 * @package    report_engagement
 * @copyright  2016 Macquarie University
 * @author     Danny Liu <danny.liu@mq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot . '/report/engagement/locallib.php');

//define('AJAX_SCRIPT', true);
/*if (!defined('MOODLE_INTERNAL')) {
    die();
}*/

global $DB, $CFG;

$id = required_param('id', PARAM_INT); // Course ID.
$method = required_param('method', PARAM_TEXT); // Ajax method.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
require_capability('report/engagement:manage', $context);

switch ($method) {
    case 'initialise':
        // Enable the engagement analytics cache; set to 600 seconds.
        $plugincacheconfig = get_config('engagement', 'cachettl');
        set_config('cachettl', '600', 'engagement');
        echo($plugincacheconfig);
        break;
    case 'finalise':
        $plugincacheconfig = required_param('plugincacheconfig', PARAM_INT);
        // Return cache settings to original.
        set_config('cachettl', $plugincacheconfig, 'engagement');
        echo(true);
        break;
    case 'get_possible_settings':
        $discoverableindicators = report_engagement_indicator_helper_get_indicator_objects($id, "", true);
        $allpossiblesettings = [];
        foreach ($discoverableindicators as $name => $indicator) {
            // Populate the discoverable settings.
            $possiblesettings = $indicator->get_helper_initial_settings();
            foreach ($possiblesettings as $key => $value) {
                $allpossiblesettings["{$name}_{$key}"] = $value;
            }
            // Add on the indicator itself. Note the double underscore leader. This is for the indicator weighting.
            $allpossiblesettings["__{$name}"] = ["min" => 0, "max" => 100];
        }
        $return = [];
        $return["settings"] = $allpossiblesettings;
        $return["indicators"] = array_keys($discoverableindicators);
        echo(json_encode($return));
        break;
    case 'try_settings':
        // Parse AJAX inputs.
        $targetgradeitemid = required_param('targetgradeitemid', PARAM_INT);
        $settings = (array) json_decode(required_param('settings', PARAM_TEXT)); // Settings as JSON string.
        $returndata = json_decode(optional_param('returndata', '', PARAM_TEXT));
        // Get indicators.
        $discoverableindicators = report_engagement_indicator_helper_get_indicator_objects($id, "", true);
        // Calculate correlation.
        $corr = try_settings($id, $targetgradeitemid, $settings, $discoverableindicators);
        // Return fitness and other data as necessary.
        $fitness = -1 * $corr;
        $output = ["fitness" => $fitness, "returndata" => $returndata];
        echo(json_encode($output));
        break;
}

function try_settings($id, $targetgradeitemid, $discoveredsettings, $indicators) {
    // Programmatically set parameters.
    $name = $indicatorname;
    $configdata = [];
    // Normalise and set overall indicator weights.
    $weights = [];
    foreach ($indicators as $name => $indicator) {
        $weights[$name] = $discoveredsettings["__{$name}"];
    }
    $arraysum = array_sum($weights);
    foreach ($weights as $key => $value) {
        $weights[$key] = round($value / $arraysum * 100.0);
    }
    if (array_sum($weights) != 100) {
        $weights[$key] += 100 - array_sum($weights);
    }
    // Process settings for each indicator.
    foreach ($indicators as $name => $indicator) {
        //$defaults = $indicator->get_defaults();
        //$config = array();
        $settings = [];
        foreach ($discoveredsettings as $key => $value) {
            if (starts_with($key, $name)) {
                $settings[substr($key, strlen($name) + 1)] = $discoveredsettings[$key];
            }
        }
        // Beautify.
        $configdata[$name] = $indicator->transform_helper_discovered_settings($settings);
    }
    // Update config.
    report_engagement_update_indicator($id, $weights, $configdata);
    // Calculate risks.
    $data = [];
    foreach ($indicators as $name => $indicator) {
        $temparray = report_engagement_indicator_helper_get_indicator_risks($id, $weights, $name);
        $data = array_replace_recursive($data, $temparray);
    }
    foreach ($data as $userid => $risks) {
        foreach ($risks as $riskname => $riskdata) {
            $data[$userid]['indicator___total']['raw'] += $riskdata['raw'] * $riskdata['weight'];
        }
    }
    // Calculate correlation.
    $corrfinal = report_engagement_indicator_helper_correlate_target_with_risks($id, '__total', $targetgradeitemid, $data);    
    // Return.
    return $corrfinal;
}

// http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
function starts_with($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}