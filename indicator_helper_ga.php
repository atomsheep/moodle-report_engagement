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
 * Page to run genetic algorithm to help determine optimal parameters
 *
 * @package    report_engagement
 * @author     Danny Liu <danny.liu@mq.edu.au>
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot . '/report/engagement/locallib.php');
require_once(dirname(__FILE__).'/indicator_helper_form.php');

$ids = optional_param('ids', null, PARAM_TEXT); // URL encoded comma-delimited string of course IDs.
$urlidparam = "";
if (is_null($ids)) {
    $id = required_param('id', PARAM_INT); // Primary course ID.
    $ids[0] = $id;
    $urlidparam = $id;
} else {
    $ids = explode(',', preg_replace('[^0-9,]', '', urldecode($ids))); // Make into array.
    $id = $ids[0];
    $urlidparam = urlencode(implode(',', $ids));
}
$iteri = optional_param('iteri', 50, PARAM_INT); // Population size.
$iterj = optional_param('iterj', 10, PARAM_INT); // Generations.

$pageparams = array('ids' => $urlidparam);
$PAGE->set_url('/report/engagement/indicator_helper_ga.php', $pageparams);
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$updateurl = new moodle_url('/report/engagement/edit.php', array('id' => $id));
$reporturl = new moodle_url('/report/engagement/index.php', array('id' => $id));
$mailerurl = new moodle_url('/report/engagement/mailer.php', array('id' => $id));
$indicatorhelperurl = new moodle_url('/report/engagement/indicator_helper_ga.php', array('ids' => $urlidparam));
$PAGE->navbar->add(get_string('reports'));
$PAGE->navbar->add(get_string('pluginname', 'report_engagement'), $reporturl);
$PAGE->navbar->add(get_string('indicator_helper', 'report_engagement'), $indicatorhelperurl);
$PAGE->set_heading($course->fullname);

global $DB;

// Load up jquery.
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');

// Load up RGraph Javascript.
$PAGE->requires->js(new moodle_url('/report/engagement/javascript/RGraph.common.core.js'));
$PAGE->requires->js(new moodle_url('/report/engagement/javascript/RGraph.scatter.js'));

// Load up Javascript for running genetic algorithm.
$PAGE->requires->js(new moodle_url('/report/engagement/javascript/indicator_helper.ajax.js'));
$PAGE->requires->js(new moodle_url('/report/engagement/javascript/ajaxq.js'));

echo $OUTPUT->header();

require_capability('report/engagement:manage', $context);

// Prepare indicators.
$pluginman = core_plugin_manager::instance();
$indicators = get_plugin_list('engagementindicator');
$discoverableindicators = array(); // Values are names of indicators capable of having their parameters discovered.
$indicatorobjects = array(); // Keys are names, values are the objects.
foreach ($indicators as $name => $path) {
    $plugin = $pluginman->get_plugin_info('engagementindicator_'.$name);
    if (!$plugin->is_enabled()) {
        unset($indicators[$name]);
        break;
    }
    if (file_exists("$path/indicator.class.php")) {
        require_once("$path/indicator.class.php");
        $classname = "indicator_$name";
        $indicatorobjects[$name] = new $classname($id);
        // Check if this indicator is set up for parameter discovery.
        if (method_exists($indicatorobjects[$name], 'get_helper_initial_settings')) {
            $discoverableindicators[] = $name;
        }
    }
}

// Prepare form.
$formtargets = array();
$formtargetsselected = array();
$courses = array();
foreach ($ids as $courseid) {
    $coursedata = $DB->get_record_sql("SELECT id, shortname, fullname
                                          FROM {course}
                                         WHERE id = :courseid",
                                        array('courseid' => $courseid));
    $gradeitems = $DB->get_records_sql("SELECT * 
                                          FROM {grade_items} 
                                         WHERE courseid = :courseid
                                           AND itemtype IN ('mod','manual')
                                      ORDER BY sortorder ASC",
                                      array('courseid' => $courseid));
    $formtarget = array();
    foreach ($gradeitems as $gradeitem) {
        $formtarget[$gradeitem->id] = $gradeitem->itemname;
    }
    $formtargets[$courseid] = $formtarget;
    $formtargetsselected[$courseid] = $gradeitem->id;
    $courses[$courseid] = $coursedata;
}
$formiteri = array();
for ($i = 25; $i <= 200; $i += 25) {
    $formiteri[$i] = $i;
}
$formiterj = array();
for ($j = 5; $j <= 50; $j += 5) {
    $formiterj[$j] = $j;
}
$mform = new report_engagement_indicator_helper_form(null, array(
                'ids' => $ids,
            'targets' => $formtargets,
            'targetsselected' => $formtargetsselected,
            'courses' => $courses,
             'iteri' => $formiteri,
             'iterj' => $formiterj,
     'default_iteri' => $iteri,
     'default_iterj' => $iterj
    ));

$formdata = $mform->get_data();
if ($formdata && isset($formdata->submitruncorrelate)) {
    $runmethod = 'correlate';
} else {
    $runmethod = null;
}

if ($runmethod == 'correlate') {
    foreach ($ids as $courseid) {
        $discoveredweightings = $DB->get_records_menu('report_engagement', array('course' => $courseid), '', 'indicator, weight');
        // Set up variables for report.
        $xarray = array();
        $yarray = array();
        $removedusers = array();
        $titlexaxis = '';
        $dummy = '';
        $data = array();
        // Load indicators for this course.
        $indicatorobjects = array();
        foreach ($indicators as $name => $path) {
            if (file_exists("$path/indicator.class.php")) {
                require_once("$path/indicator.class.php");
                $classname = "indicator_$name";
                $indicatorobjects[$name] = new $classname($courseid);
            }
        }
        // Calculate indicator risks.
        foreach ($indicatorobjects as $name => $indicator) {
            $temparray = get_indicator_risks($courseid, $discoveredweightings, $name);
            $data = array_replace_recursive($data, $temparray);
        }
        foreach ($data as $userid => $risks) {
            foreach ($risks as $riskname => $riskdata) {
                $data[$userid]['indicator___total']['raw'] += $riskdata['raw'] * $riskdata['weight'];
            }
        }
        // Calculate correlation and draw representative graph.
        $targetgradeitemid = required_param("target_$courseid", PARAM_INT); // Target grade item ID.
        $corrfinal = correlate_target_with_risks($courseid, '__total', $targetgradeitemid,
                                                 $data, $xarray, $yarray, $titlexaxis, $removedusers);
        $corrfinal = round($corrfinal, 4);
        $html = html_writer::tag('div', get_string('indicator_helper_correlationoutput', 'report_engagement', $corrfinal));
        $html .= html_writer::start_tag('div');
        $html .= html_writer::start_tag('a', array("href" => new moodle_url('/report/engagement/edit.php', array('id' => $courseid)),
                                                   "target" => "_blank"));
        $html .= get_string('indicator_helper_viewsettings', 'report_engagement');
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_tag('div');
        $html .= html_writer::start_tag('div', array("id" => "rgraph-container-$courseid",
                                                     "style" => "display:none;"));
        $html .= html_writer::start_tag('canvas', array("id" => "rgraph-canvas-$courseid",
                                                        "width" => "600",
                                                        "height" => "250"));
        $html .= html_writer::end_tag('canvas');
        $html .= html_writer::end_tag('div');
        echo($html);
        $titlexaxis = json_encode($titlexaxis);
        $graphtitle = json_encode($courses[$courseid]->shortname);
        $graphcode = draw_correlation_graph('total', $xarray, $yarray, $titlexaxis, $removedusers, $courseid, $graphtitle);
        echo($graphcode);
    }
}

// Show settings form.
$mform->display();

echo $OUTPUT->footer();

function draw_correlation_graph($name, $xarray, $yarray, $titlexaxis, $removedusers, $courseid, $graphtitle) {
    $grapharray = array();
    foreach ($xarray as $userid => $value) {
        if (array_key_exists($userid, $removedusers)) {
            $grapharray[] = array($xarray[$userid], $yarray[$userid], 'red');
        } else {
            $grapharray[] = array($xarray[$userid], $yarray[$userid]);
        }
    }
    $graphxmax = max($xarray);
    $graphxmin = min($xarray);
    $graphymax = max($yarray);
    $graphymin = min($yarray);
    $graphdata = json_encode($grapharray);
    $riskrating = json_encode(get_string('indicator_helper_riskrating', 'report_engagement'));
    $graphjs = "<script>
        $(document).ready(function() {
            document.getElementById('rgraph-container-$courseid').style.display = 'block';
            var scatter_{$name}_{$courseid} = new RGraph.Scatter({
                id: 'rgraph-canvas-$courseid',
                data: $graphdata,
                options: {
                    xmax: $graphxmax,
                    xmin: $graphxmin,
                    ymax: $graphymax,
                    ymin: $graphymin,
                    scaleDecimals: 2,
                    gutterLeft: 75,
                    gutterBottom: 50,
                    titleXaxisPos: 0.20,
                    titleYaxisPos: 0.15,
                    titleXaxis: $titlexaxis,
                    titleYaxis: $riskrating,
                    title: $graphtitle
                }
            }).draw();
        });
        </script>";
    return ($graphjs);
}

function get_indicator_risks($id, $weights, $name) {
    // Calculate indicator's risks.
    $classname = "indicator_$name";
    $currentindicator = new $classname($id);
    $indicatorrisks = $currentindicator->get_course_risks();
    unset($currentindicator);
    $data = array();
    foreach ($indicatorrisks as $user => $risk) {
        $data[$user]["indicator_$name"]['raw'] = $risk->risk;
        $data[$user]["indicator_$name"]['weight'] = $weights[$name];
    }
    return $data;
}

function correlate_target_with_risks($id, $name, $targetgradeitemid, $data, &$xarray = null, &$yarray = null, &$titlexaxis = null, &$removedusers = null, &$gradedatacache = null) {
    global $CFG, $DB;
    // Gather grade data in preparation for calculating correlation.
    $userarray = array_keys($data);
    $gradeitems = $DB->get_records_sql("SELECT id, itemname, itemtype, itemmodule, iteminstance 
                                          FROM {grade_items} 
                                         WHERE courseid = :courseid
                                           AND id = :gradeitemid
                                      ORDER BY sortorder",
                                      array('courseid' => $id, 'gradeitemid' => $targetgradeitemid));
    if (!isset($gradedatacache)) {
        require_once($CFG->libdir.'/gradelib.php');
        $gradedata = array();
        foreach ($gradeitems as $gradeitem) {
            switch ($gradeitem->itemtype) {
                case 'manual':
                    $grades = $DB->get_records_sql("SELECT * 
                                                      FROM {grade_grades} 
                                                     WHERE itemid = :itemid",
                                                     array('itemid' => $gradeitem->id));
                    foreach ($grades as $grade) {
                        $gradedata[$grade->userid] = $grade->finalgrade;
                    }
                    break;
                default:
                    $grades = grade_get_grades($id, 
                                               $gradeitem->itemtype, 
                                               $gradeitem->itemmodule, 
                                               $gradeitem->iteminstance, 
                                               $userarray);
                    foreach ($grades->items[0]->grades as $userid => $grade) {
                        $gradedata[$userid] = $grade;
                    }
            }
        }
        $gradedatacache = $gradedata;
    } else {
        $gradedata = $gradedatacache;
    }
    // Calculate correlation between selected grade item target and raw risk.
    $array1 = array();
    $array2 = array();
    foreach ($userarray as $userid) {
        $grade = $gradedata[$userid];
        $risk = $data[$userid]["indicator_$name"]['raw'];
        if ($grade !== null && $risk !== null) {
            $array1[$userid] = floatval($grade);
            $array2[$userid] = $risk;
        }
    }
    // Remove outliers before calculating correlation coefficient.
    $intersectusers = array_intersect_key(remove_outliers($array1, 2), remove_outliers($array2, 2));
    $array1b = array();
    $array2b = array();
    foreach ($intersectusers as $userid => $value) {
        $array1b[] = $array1[$userid];
        $array2b[] = $array2[$userid];
    }
    // Save to output by reference variables.
    if (is_array($xarray) && is_array($yarray)) {
        $xarray = $array1;
        $yarray = $array2;
    }
    if (isset($titlexaxis)) {
        $titlexaxis = array_values($gradeitems)[0]->itemname;
    }
    if (isset($removedusers)) {
        $removedusers = array_diff_key($array1, $intersectusers);
    }
    // Calculate and return Pearson correlation coefficient.
    return pearson_correlation_coefficient($array1b, $array2b);
}

function pearson_correlation_coefficient($x, $y){
    $length = count($x);
    $mean1 = array_sum($x) / $length;
    $mean2 = array_sum($y) / $length;
    $a = 0;
    $b = 0;
    $axb = 0;
    $a2 = 0;
    $b2 = 0;
    for($i = 0; $i < $length; $i++) {
        $a = $x[$i] - $mean1;
        $b = $y[$i] - $mean2;
        $axb = $axb + ($a * $b);
        $a2 = $a2 + pow($a, 2);
        $b2 = $b2 + pow($b, 2);
    }
    $corr = $axb / sqrt($a2 * $b2);
    return $corr;
}

function shuffle_assoc($list) { 
    if (!is_array($list)) {
        return $list;
    }
    $keys = array_keys($list); 
    shuffle($keys); 
    $random = array(); 
    foreach ($keys as $key) { 
        $random[$key] = $list[$key]; 
    }
    return $random;
}

// Courtesy of http://stackoverflow.com/questions/15174952/finding-and-removing-outliers-in-php .
function remove_outliers($dataset, $magnitude = 1) {
    $count = count($dataset);
    $mean = array_sum($dataset) / $count; // Calculate the mean
    $deviation = sqrt(array_sum(array_map("sd_square", $dataset, array_fill(0, $count, $mean))) / $count) * $magnitude; // Calculate standard deviation and times by magnitude
    return array_filter($dataset, function($x) use ($mean, $deviation) { return ($x <= $mean + $deviation && $x >= $mean - $deviation); }); // Return filtered array of values that lie within $mean +- $deviation.
}
function sd_square($x, $mean) {
    return pow($x - $mean, 2);
} 