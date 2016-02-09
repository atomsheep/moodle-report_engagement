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
 * Brute force machine learning to help determine optimal parameters
 *
 * @package    report_engagement
 * @author     Danny Liu <danny.liu@mq.edu.au>
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot . '/report/engagement/locallib.php');
require_once(dirname(__FILE__).'/indicator_helper_form.php');
require_once(dirname(__FILE__).'/indicator_helper_report.php');

$id = required_param('id', PARAM_INT); // Course ID.
$targetgradeitemid = optional_param('target', null, PARAM_INT); // Grade item ID.
$indicatortodiscover = optional_param('indicator', '', PARAM_TEXT); // Indicator.
$discovertarget = optional_param('discover', '', PARAM_TEXT); // What to discover: w = overall weightings, i = individual indicator.
$iteri = optional_param('iteri', 2, PARAM_INT); // Iterations of i.
$iterj = optional_param('iterj', 3, PARAM_INT); // Iterations of j.

$pageparams = array('id' => $id);
$PAGE->set_url('/report/engagement/indicator_helper.php', $pageparams);
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$updateurl = new moodle_url('/report/engagement/edit.php', array('id' => $id));
$reporturl = new moodle_url('/report/engagement/index.php', array('id' => $id));
$mailerurl = new moodle_url('/report/engagement/mailer.php', array('id' => $id));
$indicatorhelperurl = new moodle_url('/report/engagement/indicator_helper.php', array('id' => $id));
$PAGE->navbar->add(get_string('reports'));
$PAGE->navbar->add(get_string('pluginname', 'report_engagement'), $reporturl);
$PAGE->navbar->add(get_string('indicator_helper', 'report_engagement'), $indicatorhelperurl);
$PAGE->set_heading($course->fullname);

global $DB;

// Load up js.
$PAGE->requires->js(new moodle_url('/report/engagement/javascript/RGraph.common.core.js'));
$PAGE->requires->js(new moodle_url('/report/engagement/javascript/RGraph.scatter.js'));

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
$gradeitems = $DB->get_records_sql("SELECT * 
                                      FROM {grade_items} 
                                     WHERE courseid = :courseid
                                       AND itemtype IN ('mod','manual')
                                  ORDER BY sortorder ASC",
                                  array('courseid' => $id));
$formtarget = array();
foreach ($gradeitems as $gradeitem) {
    $formtarget[$gradeitem->id] = $gradeitem->itemname;
}
$formdiscover = array('i' => get_string('indicator_helper_discover_indicator', 'report_engagement'),
                  'w' => get_string('indicator_helper_discover_weightings', 'report_engagement'));
$formindicators = array();
foreach ($indicators as $name => $path) {
    $formindicators[$name] = $name;
}
$formiteri = array();
for ($i = 1; $i <= 6; $i++) {
    $formiteri[$i] = $i;
}
$formiterj = array();
for ($j = 1; $j <= 8; $j++) {
    $formiterj[$j] = $j;
}
$mform = new report_engagement_indicator_helper_form(null, array(
                'id' => $id,
            'target' => $formtarget,
          'discover' => $formdiscover,
         'indicator' => $formindicators,
             'iteri' => $formiteri,
             'iterj' => $formiterj,
     'default_iteri' => $iteri,
     'default_iterj' => $iterj
    ));


$formdata = $mform->get_data();
if ($formdata && isset($formdata->submitrundiscovery)) {
    $runmethod = 'discovery';
} else if ($formdata && isset($formdata->submitruncorrelate)) {
    $runmethod = 'correlate';
} else {
    $runmethod = null;
}

// Run analyses.
if ($targetgradeitemid != null && isset($runmethod)) {
    
    // Enable the engagement analytics cache; set to 600 seconds.
    $plugincacheconfig = get_config('engagement', 'cachettl');
    set_config('cachettl', '600', 'engagement');
    // Set up a cache variable to hold array of grade data.
    $gradedatacache = null;
    
    if ($discovertarget == 'w') {
        if ($runmethod == 'discovery') {
            // Iterate through all (not just discoverable) user-activated indicators and play with weightings of each.
            // Set initial weightings (evenly split between active indicators).
            $numberactiveindicators = 0;
            $discoveredweightings = array();
            foreach ($indicatorobjects as $name => $indicator) {
                if (isset($formdata->{"activeindicators_$name"})) {
                   $numberactiveindicators++;
                } else {
                   $discoveredweightings[$name] = 0;
                }
            }
            foreach ($indicatorobjects as $name => $indicator) {
                if (isset($formdata->{"activeindicators_$name"})) {
                    $discoveredweightings[$name] = 100.0 / $numberactiveindicators;
                }
            }
            // Loop.
            for ($i = 1; $i <= $iteri; $i++) {
                foreach ($indicatorobjects as $name => $indicator) {
                    if (!isset($formdata->{"activeindicators_$name"})) {
                        continue;
                    }
                    unset($nextweighting);
                    unset($prevcorr);
                    for ($j = $iterj; $j > 0; $j--) {
                        if (isset($nextweighting)) {
                            $weightingvalue = $nextweighting;
                        } else {
                            $weightingvalue = floatval($discoveredweightings[$name]);
                        }
                        // Introduce some stochasticity into the calculations.
                        $randomfactor = mt_rand(12, $j * 15) / 10;
                        // Generate weighting array and normalise weightings to total 100%.
                        $weightslo = $discoveredweightings;
                        $weightslo[$name] = $weightingvalue / $randomfactor;
                        $arraysum = array_sum($weightslo);
                        foreach ($weightslo as $key => $value) {
                            $weightslo[$key] = $value / $arraysum * 100.0;
                        }
                        $weightshi = $discoveredweightings;
                        $weightshi[$name] = $weightingvalue * $randomfactor;
                        $arraysum = array_sum($weightshi);
                        foreach ($weightshi as $key => $value) {
                            $weightshi[$key] = $value / $arraysum * 100.0;
                        }
                        // Calculate the correlation coefficients with varying weighting settings.
                        $corrs = array();
                        foreach (array('lo' => $weightslo, 'md' => $discoveredweightings, 'hi' => $weightshi) as $state => $weightings) {
                            $data = array();
                            if (($state == 'md' && !isset($prevcorr)) || $state != 'md') {
                                // Iterate through each indicator.
                                foreach ($indicatorobjects as $indicatorname => $indicatorobject) {
                                    $temparray = update_config_get_indicator_risks($id, $weightings, array(), $indicatorname);
                                    $data = array_replace_recursive($data, $temparray);
                                }
                                // Calculate total risk.
                                foreach ($data as $userid => $risks) {
                                    foreach ($risks as $riskname => $riskdata) {
                                        $data[$userid]['indicator___total']['raw'] += $riskdata['raw'] * $riskdata['weight'];
                                    }
                                }
                                // Calculate correlation from combined data.
                                $corrs[$state] = correlate_target_with_risks($id, '__total', $targetgradeitemid, $data, $gradedatacache);
                                // Save to prevcorr if necessary.
                                if ($state == 'md') {
                                    $prevcorr = $corrs[$state];
                                }
                            } else if ($state == 'md' && isset($prevcorr)) {
                                $corrs['md'] = $prevcorr;
                            }
                            unset($data);
                        }
                        // Decide which direction has the better correlation.
                        // Important: negative correlation is 'better' because risk rating is inversely related to outcome.
                        if ($corrs['lo'] < $corrs['md']) {
                            $nextweighting = $weightslo[$name];
                        } else if ($corrs['hi'] < $corrs['md']) {
                            $nextweighting = $weightshi[$name];
                        } else {
                            $nextweighting = $weightingvalue;
                        }
                    }
                    // Save.
                    $discoveredweightings[$name] = $nextweighting;
                }
            }
            // Normalise to 100% again.
            $arraysum = array_sum($discoveredweightings);
            foreach ($discoveredweightings as $key => $value) {
                $discoveredweightings[$key] = round($value / $arraysum * 100.0);
            }
            if (array_sum($discoveredweightings) != 100) {
                $discoveredweightings[$key] += 100 - array_sum($discoveredweightings);
            }
            // Save to DB.
            report_engagement_update_indicator($id, $discoveredweightings, array());
            $html = html_writer::start_tag('div');
            $html .= html_writer::tag('span', get_string('indicator_helper_saved', 'report_engagement'));
            $html .= html_writer::link($updateurl, get_string('indicator_helper_viewsettings', 'report_engagement'));
            $html .= html_writer::end_tag('div');
            echo($html);
        } else if ($runmethod == 'correlate') {
            $discoveredweightings = $DB->get_records_menu('report_engagement', array('course' => $id), '', 'indicator, weight');
        }
        // Report.
        $xarray = array();
        $yarray = array();
        $removedusers = array();
        $titlexaxis = '';
        $data = array();
        foreach ($indicatorobjects as $name => $indicator) {
            $temparray = get_indicator_risks($id, $discoveredweightings, $name);
            $data = array_replace_recursive($data, $temparray);
        }
        foreach ($data as $userid => $risks) {
            foreach ($risks as $riskname => $riskdata) {
                $data[$userid]['indicator___total']['raw'] += $riskdata['raw'] * $riskdata['weight'];
            }
        }
        // Calculate correlation and draw representative graph.
        $corrfinal = correlate_target_with_risks($id, '__total', $targetgradeitemid, 
            $data, $gradedatacache, $xarray, $yarray, $titlexaxis, $removedusers);
        $corrfinal = round($corrfinal, 4);
        $html = html_writer::tag('div', get_string('indicator_helper_correlationoutput', 'report_engagement', $corrfinal));
        echo($html);
        $titlexaxis = json_encode($titlexaxis);
        $graphcode = draw_correlation_graph('total', $xarray, $yarray, $titlexaxis, $removedusers);
        echo($graphcode);
        
    } else if ($discovertarget == 'i') {

        $weightings = $DB->get_records_menu('report_engagement', array('course' => $id), '', 'indicator, weight');
        
        // Set up variables.
        $name = $indicatortodiscover;
        $indicator = $indicatorobjects[$name];
        $discoveredsettings = array();
        $weight = isset($weightings[$name]) ? $weightings[$name] : 100 / count($indicators);
        if ($runmethod == 'discovery' && in_array($indicatortodiscover, $discoverableindicators)) {

            // Get and set initial settings.
            $possiblesettings = $indicator->get_helper_initial_settings();
            foreach ($possiblesettings as $key => $value) {
                $discoveredsettings[$key] = floatval($value['start']);
            }

            // Iterate through whole panel of settings.
            for ($i = 1; $i <= $iteri; $i++) {
                $discoveredsettings = shuffle_assoc($discoveredsettings);
                // Iterate through each setting.
                foreach ($discoveredsettings as $discoveredsettingkey => $discoveredsettingvalue) {
                    unset($nextvalue);
                    unset($prevcorr);
                    // Iteratively adjust each setting.
                    for ($j = $iterj; $j > 0; $j--) {
                        if (isset($nextvalue)) {
                            $settingvalue = $nextvalue;
                        } else {
                            $settingvalue = floatval($discoveredsettingvalue);
                        }
                        $settingkey = $discoveredsettingkey;
                        // Introduce some stochasticity into the calculations.
                        $randomfactor = mt_rand(12, $j * 15) / 10;
                        // Calculate new values and ensure within sensible limits.
                        $min = $possiblesettings[$settingkey]['min'];
                        $max = $possiblesettings[$settingkey]['max'];
                        $newvaluelo = $settingvalue / $randomfactor;
                        $newvaluelo = ($newvaluelo < $min ? $min : $newvaluelo);
                        $newvaluehi = $settingvalue * $randomfactor;
                        $newvaluehi = ($newvaluehi > $max ? $max : $newvaluehi);
                        if ($settingvalue < $min) {
                            $settingvalue = $min;
                        } else if ($settingvalue > $max) {
                            $settingvalue = $max;
                        }
                        // Calculate the correlation coefficients with varying settings.
                        $corrlo = try_indicator_setting($id, $indicator, $name, $weight, 
                            $settingkey, $newvaluelo, $targetgradeitemid, 
                            $discoveredsettings, $gradedatacache);
                        if (!isset($prevcorr)) {
                            $prevcorr = try_indicator_setting($id, $indicator, $name, $weight, 
                                $settingkey, $settingvalue, $targetgradeitemid, 
                                $discoveredsettings, $gradedatacache);
                        }
                        $corrhi = try_indicator_setting($id, $indicator, $name, $weight, 
                            $settingkey, $newvaluehi, $targetgradeitemid, 
                            $discoveredsettings, $gradedatacache);
                        // Decide which direction has the better correlation.
                        // Important: negative correlation is 'better' because risk rating is inversely related to outcome.
                        if ($corrlo < $prevcorr) {
                            $nextvalue = $settingvalue / $randomfactor;
                            $prevcorr = $corrlo;
                        } else if ($corrhi < $prevcorr) {
                            $nextvalue = $settingvalue * $randomfactor;
                            $prevcorr = $corrhi;
                        } else {
                            $nextvalue = $settingvalue;
                        }
                    }
                    // Save the value.
                    $discoveredsettings[$settingkey] = $nextvalue;
                }
            }
            // Final transformations and final saving of settings.
            $weights[$name] = $weight;
            $configdata[$name] = $indicator->transform_helper_discovered_settings($discoveredsettings);
            report_engagement_update_indicator($id, $weights, $configdata);
            $html = html_writer::start_tag('div');
            $html .= html_writer::tag('span', get_string('indicator_helper_saved', 'report_engagement'));
            $html .= html_writer::link($updateurl, get_string('indicator_helper_viewsettings', 'report_engagement'));
            $html .= html_writer::end_tag('div');
            echo($html);
        } else if ($runmethod == 'correlate') {
            // Don't need to do anything, since the call get_indicator_risks will load up parameters.
        }
        // Report.
        $xarray = array();
        $yarray = array();
        $titlexaxis = '';
        $removedusers = array();
        $data = get_indicator_risks($id, array("$name" => $weight), $name);
        $corrfinal = correlate_target_with_risks($id, $name, $targetgradeitemid, 
            $data, $gradedatacache, $xarray, $yarray, $titlexaxis, $removedusers);
        $corrfinal = round($corrfinal, 4);
        $html = html_writer::tag('div', get_string('indicator_helper_correlationoutputindicator', 
            'report_engagement', 
            array('corr' => $corrfinal, 'name' => $name)));
        echo($html);
        $titlexaxis = json_encode($titlexaxis);
        // Draw quick graph.
        $graphcode = draw_correlation_graph($name, $xarray, $yarray, $titlexaxis, $removedusers);
        echo($graphcode);
        
    }
    // Return cache settings to original.
    set_config('cachettl', $plugincacheconfig, 'engagement');
    unset($gradedatacache);

}

if (isset($formdata->datadump) && isset($xarray) && isset($yarray)) {
    // Show textarea with data.
    $dataoutput = array();
    // Get usernames from user table.
    list($rsql, $rparams) = $DB->get_in_or_equal(array_keys($xarray), SQL_PARAMS_NAMED, 'id');
    $sql = "SELECT id, username
              FROM {user}
             WHERE id $rsql";
    $result = $DB->get_records_sql($sql, $rparams);
    // Put together data output.
    foreach ($xarray as $userid => $x) {
        $dataoutput[] = $result[$userid]->username . "\t" . $x . "\t" . $yarray[$userid];
    }
    $rform = new report_engagement_indicator_helper_report(null, 
        array('output' => implode("\n", $dataoutput)));
    $rform->display();
}

// Show settings form.
$mform->display();

echo $OUTPUT->footer();

function draw_correlation_graph($name, $xarray, $yarray, $titlexaxis, $removedusers) {
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
    $graphhtml = '
        <div id="rgraph-container-'.$name.'">
            <canvas id="rgraph-canvas-'.$name.'" width="600" height="250"></canvas>
        </div>
    ';
    $graphjs = "<script>
        window.onload = (function () {
            console.log('hello');
            var scatter_$name = new RGraph.Scatter({
                id: 'rgraph-canvas-$name',
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
                    titleYaxis: $riskrating
                }
            }).draw();
        });
        </script>";
    return ($graphhtml . $graphjs);
}

function try_indicator_setting($id, $indicator, $indicatorname, $weight, $settingkey, $settingvalue, $targetgradeitemid, $discoveredsettings, &$gradedatacache = null, &$xarray = null, &$yarray = null, &$titlexaxis = null, &$removedusers = null) {
    // Programmatically set indicator parameters.
    $name = $indicatorname;
    $weights = array();
    $configdata = array();
    $weights[$name] = $weight;
    $defaults = $indicator->get_defaults();
    $config = array();
    foreach ($defaults as $key => $value) {
        if ($key == $settingkey) {
            $config["{$name}_{$key}"] = $settingvalue;
        } else if (array_key_exists($key, $discoveredsettings)) {
            $config["{$name}_{$key}"] = $discoveredsettings[$key];
        } else {
            $config["{$name}_{$key}"] = $value;
        }
    }
    $configdata[$name] = $config;
    // Update config and get indicator's risks.
    $data = update_config_get_indicator_risks($id, $weights, $configdata, $name);
    // Calculate and return correlation.
    return correlate_target_with_risks($id, $name, $targetgradeitemid, $data, $gradedatacache, $xarray, $yarray, $titlexaxis, $removedusers);
}

function update_config_get_indicator_risks($id, $weights, $configdata, $name) {
    // Update config.
    report_engagement_update_indicator($id, $weights, $configdata);
    // Get indicator's risks.
    return get_indicator_risks($id, $weights, $name);
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

function correlate_target_with_risks($id, $name, $targetgradeitemid, $data, &$gradedatacache = null, &$xarray = null, &$yarray = null, &$titlexaxis = null, &$removedusers = null) {
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