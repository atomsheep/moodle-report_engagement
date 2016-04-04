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
 * @copyright  2015 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot . '/report/engagement/locallib.php');
require_once(dirname(__FILE__).'/mailer_form.php');

$id = required_param('id', PARAM_INT); // Course ID.
$pageparams = array('id' => $id);

$PAGE->set_url('/report/engagement/mailer.php', $pageparams);
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$updateurl = new moodle_url('/report/engagement/edit.php', array('id' => $id));
$reporturl = new moodle_url('/report/engagement/index.php', array('id' => $id));
$mailerurl = new moodle_url('/report/engagement/mailer.php', array('id' => $id));
$mailerlogurl = new moodle_url('/report/engagement/mailer_log.php', array('id' => $id));
$PAGE->navbar->add(get_string('reports'));
$PAGE->navbar->add(get_string('pluginname', 'report_engagement'), $reporturl);
$PAGE->navbar->add(get_string('mailer', 'report_engagement'), $mailerurl);
$PAGE->set_button($OUTPUT->single_button($updateurl, get_string('updatesettings', 'report_engagement'), 'get') . 
                    $OUTPUT->single_button($mailerlogurl, get_string('mailer_message_log', 'report_engagement'), 'get')
                    );
$PAGE->set_heading($course->fullname);

global $DB, $USER;

// Load up js.
$PAGE->requires->js(new moodle_url('/report/engagement/mailer.js'));
// Load up jquery.
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->jquery_plugin('report_engagement-datatables', 'report_engagement');

echo $OUTPUT->header();

require_capability('report/engagement:view', $context);

// Write view to log.
$event = \report_engagement\event\report_viewed::create(array(
    'context' => $context, 
    'other' => array(
        'courseid' => $id,
        'page' => 'mailer'
    )));
$event->trigger();

// Prepare indicators.
$pluginman = core_plugin_manager::instance();
$indicators = get_plugin_list('engagementindicator');
foreach ($indicators as $name => $path) {
    $plugin = $pluginman->get_plugin_info('engagementindicator_'.$name);
    if (!$plugin->is_enabled()) {
        unset($indicators[$name]);
    }
}

// Examine posted data.
if (data_submitted() && confirm_sesskey()) {
    $postdata = data_submitted();
    // Get userids that have been checked.
    $userids = array();
    foreach ($indicators as $name => $path) {
        foreach ($postdata as $key => $value) {
            $prefix = "chk_indicator_$name"."_";
            if (substr($key, 0, strlen($prefix)) === $prefix) {
                $userid = substr($key, strlen($prefix));
                $userids[$userid] = true;
            }
        }
    }
    // Determine all patterns of checking.
    $patterns = array();
    foreach ($userids as $userid => $blah) {
        $pattern = '';
        foreach ($indicators as $name => $path) {
            $prefix = "chk_indicator_$name"."_";
            $inuse = false;
            foreach ($postdata as $key => $value) {
                if ($key == $prefix.$userid) {
                    $inuse = true;
                }
            }
            if ($inuse) {
                $pattern .= '1';
            } else {
                $pattern .= '0';
            }
        }
        if (!isset($patterns[$pattern])) {
            $patterns[$pattern] = array();
        }
        array_push($patterns[$pattern], $userid);
    }
    switch ($postdata->action) {
        case 'viewing':
            $action = 'composing';
            // Continue - other logic will determine what is shown.
            break;
        case 'composing':
            $action = 'previewing';
            // Continue - other logic will determine what is shown.
            break;
        case 'previewing':
            $action = 'sending';
            // Check for capability.
            require_capability('report/engagement:send', $context);
            // Continue - later logic will send messages.
            break;
        case 'sending':
            break;
    }
} else {
    $action = 'viewing';
}

// Retrieve data from databases.
$data = array();
$riskdata = array();
$displaydata = array();
$weightings = $DB->get_records_menu('report_engagement', array('course' => $id), '', 'indicator, weight');
foreach ($indicators as $name => $path) {
    if (file_exists("$path/indicator.class.php")) {
        require_once("$path/indicator.class.php");
        $classname = "indicator_$name";
        $indicator = new $classname($id);
        // Run in order to process data.
        $indicatorrisks = $indicator->get_course_risks();
        // Work out risk weightings.
        $weight = isset($weightings[$name]) ? $weightings[$name] : 0;
        foreach ($indicatorrisks as $curuser => $risk) {
            $riskdata[$curuser]["indicator_$name"]['raw'] = $risk->risk;
            $riskdata[$curuser]["indicator_$name"]['weight'] = $weight;
        }
        // Fetch raw data.
        $rawdata = $indicator->get_course_rawdata();
        // Fetch array of userids.
        $users = $indicator->get_course_users();
        if (empty($data)) {
            foreach ($users as $userid) {
                $data[$userid] = array();
            }
        }
        // Fetch information to display.
        $displaydata[$name] = $indicator->get_data_for_mailer();
    }
}

// Parse and store group data.
$groups = groups_get_all_groups($course->id);
// Get groups and membership.
$groupmemberships = array();
foreach ($groups as $groupid => $group) {
    $groupmembership = array();
    $groupmembership['members'] = array_keys(groups_get_members($groupid, 'u.id'));
    $groupmembership['groupname'] = $group->name;
    $groupmembership['groupid'] = $groupid;
    $groupmemberships[$groupid] = $groupmembership;
}
// Parse and store by user.
$groupsbyuser = array();
foreach ($data as $userid => $record) {
    $groupsbyuser[$userid] = array();
    foreach ($groupmemberships as $groupid => $groupmembership) {
        if (array_search($userid, $groupmembership['members'])) {
            $groupinfo = array();
            $groupinfo['groupname'] = $groupmembership['groupname'];
            $groupinfo['groupid'] = $groupmembership['groupid'];
            $groupsbyuser[$userid][] = $groupinfo;
        }
    }
}

// Set up table data.
$tabledata = array();
foreach ($data as $userid => $record) {
    // Prepare table row.
    $tablerow = array();
    $tablerow['userid'] = $userid;
    $tablerow['data'] = array();
    $c = 0;
    // Add checkboxes.
    foreach ($indicators as $name => $path) {
        $checked = '';
        // See if checkbox is checked in postdata.
        if (isset($postdata) && $postdata && $patterns) {
            if (isset($postdata->{"chk_indicator_".$name."_".$userid})) {
                $checked = "checked='checked'";
            }
        }
        // Show checkbox(es) accordingly.
        if (!(isset($postdata) && $postdata && $patterns) || $checked) {
            $tablerow['data'][$c] = "<div class='chk_indicator'>".
                "<input type='checkbox' id='chk_indicator_".$name."_".$userid."' ".
                "name='chk_indicator_".$name."_".$userid."' $checked data-userid='$userid' /></div>";
        } else {
            $tablerow['data'][$c] = "";
        }
        $c += 1;
    }
    // Show user name.
    $studentrecord = $DB->get_record('user', array('id' => $userid));
    $tablerow['data'][$c] = "<span title='$userid'>".$studentrecord->firstname." ".$studentrecord->lastname."</span>";
    $c += 1;
    // User email address.
    $tablerow['data'][$c] = $studentrecord->email;
    $c += 1;
    // Show group membership.
    $groups = array();
    foreach ($groupsbyuser[$userid] as $group) {
        $groups[] = $group['groupname'];
    }
    $tablerow['data'][$c] = join('<br />', $groups);
    $c += 1;
    // Show logic/information for each indicator.
    foreach ($indicators as $name => $path) {
        $pluginname = get_string('pluginname', "engagementindicator_$name");
        // Parse display data.
        foreach ($displaydata[$name] as $displaycolumn) {
            $tablerow['data'][$c] = $displaycolumn['display'][$userid];
            $c += 1;
        }
    }
    // Calculate and show total risk.
    $totalrisk = 0;
    foreach ($indicators as $name => $path) {
        $totalrisk += $riskdata[$userid]["indicator_$name"]['raw'] * $riskdata[$userid]["indicator_$name"]['weight'];
    }
    $tablerow['data'][$c] = '<div><span class="report_engagement_display">'.
        sprintf("%d", $totalrisk * 100).
        '</span></div>';
    $c += 1;
    // Calculate and show how many messages already received.
    try {
        $messagessent = $DB->get_records_sql("SELECT ml.id, sl.timesent
                                                FROM {report_engagement_messagelog} ml
                                                JOIN {report_engagement_sentlog} sl ON sl.messageid = ml.id
                                               WHERE sl.courseid = ? AND sl.recipientid = ?
                                            ORDER BY sl.timesent ASC", array($course->id, $userid));
    } catch (Exception $err) {
        $messagessent = array();
    }
    if (count($messagessent)) {
        $viewmessagesurl = new moodle_url('/report/engagement/mailer_log.php', array('id' => $course->id, 'uid' => $userid));
        $mostrecentmessage = end($messagessent);
        $daysago = (time() - $mostrecentmessage->timesent) / 60 / 60 / 24;
        $tablerow['data'][$c] = "<a href='$viewmessagesurl' target='_blank'>".
            count($messagessent)."</a><br />".
            get_string('report_messagelog_daysago', 'report_engagement', sprintf("%d", $daysago));
        $c += 1;
    } else {
        $tablerow['data'][$c] = count($messagessent);
        $c += 1;
    }
    // Add row to table.
    $tabledata[] = $tablerow;
}

// Determine column headers and related options.
$columnheaders = array();
$chkcolumnheaders = array_keys($indicators);
$heatmappablecolumns = array();
$heatmappablecolumnsdirections = array();
$c = 0;
// First columns are for checkboxes.
foreach (array_keys($indicators) as $indicatorname) {
    $columnheader = [];
    $columnheader['html'] = get_string('mailer_checkbox_column_header', 
        "engagementindicator_{$indicatorname}") . $OUTPUT->help_icon('mailer_checkbox_column_header', "engagementindicator_{$indicatorname}");
    $columnheader['chk'] = true;
    $columnheader['filter'] = false;
    $columnheaders[$c] = $columnheader;
    $c += 1;
}
// Next columns are for user info.
$columnheaders[$c] = array(
    'html' => get_string('report_username', 'report_engagement'),
    'filterable' => true
);
$c += 1;
$columnheaders[$c] = array(
    'html' => get_string('report_email', 'report_engagement'),
    'hide' => true
);
$c += 1;
$columnheaders[$c] = array(
    'html' => get_string('report_groups', 'report_engagement'),
    'filterable' => true
);
$c += 1;
// Next columns are for individual indicators.
foreach (array_keys($indicators) as $indicatorname) {
    foreach ($displaydata[$indicatorname] as $displayitem) {
        $columnheader = [];
        $columnheader['html'] = $displayitem['header'];
        $columnheader['filterable'] = array_key_exists('filterable', $displayitem) ? $displayitem['filterable'] : false;
        if (array_key_exists('heatmapdirection', $displayitem)) {
            $columnheader['heatmapdirection'] = $displayitem['heatmapdirection'];
            $heatmappablecolumns[] = $c;
            $heatmappablecolumnsdirections[] = $displayitem['heatmapdirection'];
        }
        $columnheaders[$c] = $columnheader;
        $c += 1;
    }
}
// Last columns are for totals etc.
$columnheaders[$c] = array(
    'html' => get_string('report_totalrisk', 'report_engagement'),
    'filterable' => false
);
$heatmappablecolumns[] = $c;
$heatmappablecolumnsdirections[] = 1;
$c += 1;
$columnheaders[$c] = array(
    'html' => get_string('report_messagessent', 'report_engagement'),
    'filterable' => false
);
$c += 1;

// Make friendly patterns and compose message boilerplate.
if (!isset($patterns) && !isset($postdata)) {
    $patterns = array(false);
    $subsets = false;
    $friendlypatterns = array();
} else {
    $subsets = true;
    $friendlypatterns = array();
    foreach ($patterns as $pattern => $userids) {
        // Form default message.
        $defaultmessages[$pattern] = get_string('message_default_greeting', 'report_engagement');
        $defaultmessages[$pattern] .= get_string('message_default_closing', 'report_engagement');
        // Form friendly patterns.
        $friendlypatterns[$pattern] = new stdClass();
        $friendlypatterns[$pattern]->names = array();
        $indicatornames = array_keys($indicators);
        foreach (str_split($pattern) as $char) {
            $indicatorname = array_shift($indicatornames);
            if ($char == '1') {
                $friendlypatterns[$pattern]->human .= get_string('pluginname', "engagementindicator_$indicatorname") . ", ";
                $friendlypatterns[$pattern]->names[] = $indicatorname;
            }
        }
        if (substr($friendlypatterns[$pattern]->human, strlen($friendlypatterns[$pattern]->human) - 2) === ', ') {
            $friendlypatterns[$pattern]->human = substr($friendlypatterns[$pattern]->human, 0, strlen($friendlypatterns[$pattern]->human) - 2);
        }
    }
}

if ($action == 'composing') {
    // Set up message components/snippets.
    // Variables.
    $messagevariables = message_variables_get_array();
    // Load snippets from database.
    $snippets = $DB->get_records('report_engagement_snippets');
    // Snippets.
    $suggestedsnippets = array();
    $othersnippets = array();
    foreach ($patterns as $pattern => $userids) {
        $c = 0;
        foreach ($friendlypatterns[$pattern]->names as $indicatorname) {
            $suggestedsnippets[$pattern][$c][$indicatorname] = array();
            $othersnippets[$pattern][$c][$indicatorname] = array();
            foreach ($snippets as $id => $snippet) {
                if ($snippet->category == $indicatorname) {
                    $suggestedsnippets[$pattern][$c][$indicatorname][$snippet->id] = $snippet->snippet_text;
                } else {
                    $othersnippets[$pattern][$c][$snippet->category][$snippet->id] = $snippet->snippet_text;
                }
            }
            $c += 1;
        }
    }
    // Load 'my messages'.
    $savedmessages = my_messages_get();
    $mysavedmessages = array();
    $mysavedmessagesdata = array();
    foreach ($patterns as $pattern => $userid) {
        $mysavedmessages[$pattern] = array();
        $c = 0;
        foreach ($savedmessages as $message) {
            $mysavedmessages[$pattern]["m$c"] = base64_decode($message->messagesummary); // The short description.
            $mysavedmessagesdata["m$c"] = base64_decode($message->messagetext); // Stores the actual message text.
            $c += 1;
        }
    }
    // Load up userlist of those with appropriate capabilities.
    $users = get_users_by_capability($context, 'report/engagement:manage');
    if (!in_array($USER->id, array_keys($users))) {
        // Add current user to userlist.
        $users[$USER->id] = $USER;
    }
    $capableusers = array();
    foreach ($users as $userid => $user) {
        $capableusers[$userid] = fullname($user) . " &lt;$user->email&gt;";
    }
    asort($capableusers);
} else if ($action == 'previewing') {
    $messagepreviews = array();
    $messagepreviewsbyuser = array();
    foreach ($patterns as $pattern => $userids) {
        $messagepreviews[$pattern] = new stdClass();
        $messagepreviews[$pattern]->subject = $postdata->{"subject_$pattern"};
        $messagepreviews[$pattern]->subject_encoded = base64_encode($postdata->{"subject_$pattern"});
        $messagepreviews[$pattern]->message = $postdata->{"message_$pattern"};
        $messagepreviews[$pattern]->message_encoded = base64_encode($postdata->{"message_$pattern"});
        $messagepreviewsbyuser[$pattern] = array();
        foreach ($userids as $userid) {
            $messagepreviewsbyuser[$pattern][$userid] = new stdClass();
            $recipient = $DB->get_record('user', array('id' => $userid));
            $messagepreviewsbyuser[$pattern][$userid]->recipient = $recipient;
            $messagepreviewsbyuser[$pattern][$userid]->subject = message_variables_replace($postdata->{"subject_$pattern"}, $userid);
            $messagepreviewsbyuser[$pattern][$userid]->message = message_variables_replace($postdata->{"message_$pattern"}, $userid);
        }
    }
    $senderpreviews = array();
    $replytopreviews = array();
    /* ForFuture: $cc_previews = array(); */
    foreach ($patterns as $pattern => $userids) {
        $sender = $DB->get_record('user', array('id' => $postdata->{"sender_$pattern"}));
        $senderpreviews[$pattern][$postdata->{"sender_$pattern"}] = fullname($sender) . " &lt;$sender->email&gt;";
        $replytopreviews[$pattern][$postdata->{"replyto_$pattern"}] = $postdata->{"replyto_$pattern"};
        /* ForFuture: $cc_previews[$pattern][$postdata->{"cc_$pattern"}] = $postdata->{"cc_$pattern"}; */
    }
} else if ($action == 'sending') {
    $messages = array();
    // Create messages.
    $messagedecoded = array();
    $subjectdecoded = array();
    $senderids = array();
    $replytoaddresses = array();
    /* ForFuture: $ccaddresses = array(); */
    foreach ($patterns as $pattern => $userids) {
        $messagedecoded[$pattern] = base64_decode($postdata->{"message_encoded_$pattern"});
        $subjectdecoded[$pattern] = base64_decode($postdata->{"subject_encoded_$pattern"});
        $messages[$pattern] = array();
        foreach ($userids as $userid) {
            $messages[$pattern][$userid] = array();
            $messages[$pattern][$userid]['message'] = message_variables_replace($messagedecoded[$pattern], $userid);
            $messages[$pattern][$userid]['subject'] = message_variables_replace($subjectdecoded[$pattern], $userid);
        }
        $senderids[$pattern] = $postdata->{"sender_$pattern"};
        $replytoaddresses[$pattern] = $postdata->{"replyto_$pattern"};
        /* ForFuture: $ccaddresses[$pattern] = $postdata->{"cc_$pattern"}; */
    }
    // Send messages.
    $messagesendresults = array();
    $messageid = 0;
    foreach ($patterns as $pattern => $userids) {
        $messagesendresults[$pattern] = array();
        $n = 0;
        foreach ($messages[$pattern] as $userid => $message) {
            // Log message, just once per message.
            if ($n == 0) {
                $messageid = message_send_log_message($subjectdecoded[$pattern], $messagedecoded[$pattern], 'email');
                $n += 1;
            }
            // Send messages.
            $messagesendresults[$pattern][$userid] = message_send_customised_email($message, $userid, $senderids[$pattern], $replytoaddresses[$pattern]);
            // ForFuture: $messagesendresults[$pattern][$userid] = message_send_customised_email($message, $userid, $senderids[$pattern], $replytoaddresses[$pattern], $ccaddresses[$pattern]);.
            if ($messagesendresults[$pattern][$userid]->result == true) {
                // Log send event to database.
                $user = $DB->get_record('user', array('id' => $userid));
                $newrecord = message_send_log_send($messageid, $user->email, $user->id, $senderids[$pattern]);                    
                // Log to log.
                $event = \report_engagement\event\message_sent::create(array(
                    'context' => $context, 
                    'relateduserid' => $userid,
                    'objectid' => $newrecord,
                    'other' => array(
                        'courseid' => $id,
                        'recipientid' => $userid,
                        'messageid' => $messageid,
                        'success' => true,
                        'result' => $messagesendresults[$pattern][$userid]->message
                    )));
                $event->trigger();
            } else {
                // Log to log.
                $event = \report_engagement\event\message_sent::create(array(
                    'context' => $context, 
                    'relateduserid' => $userid,
                    'objectid' => null,
                    'other' => array(
                        'courseid' => $id,
                        'recipientid' => $userid,
                        'messageid' => $messageid,
                        'success' => false,
                        'result' => $messagesendresults[$pattern][$userid]->message
                    )));
                $event->trigger();
            }
        }
    }
    // Save my messages if necessary.
    foreach ($patterns as $pattern => $userids) {
        if (isset($postdata->{"chk_savemy_$pattern"})) {
            my_message_save($postdata->{"txt_savemy_$pattern"}, $postdata->{"message_encoded_$pattern"});
        }
    }
}

// Load up data to send to form.
$mformdata = array();
$mformdata['id'] = $id;
$mformdata['action'] = $action;
$mformdata['patterns'] = $patterns;
$mformdata['subsets'] = $subsets;
$mformdata['table_data'] = $tabledata;
$mformdata['column_headers'] = $columnheaders;
$mformdata['chk_column_headers'] = $chkcolumnheaders;
$mformdata['heatmappable_columns'] = $heatmappablecolumns;
$mformdata['heatmappable_columns_directions'] = $heatmappablecolumnsdirections;
$mformdata['display_data_raw'] = $displaydata;
$mformdata['defaultsort'] = json_encode(array(array(count($columnheaders) - 2, 'desc'))); // Default sort by total descending.
$mformdata['html_num_fmt_cols'] = json_encode(range(count($chkcolumnheaders) + 1, count($columnheaders) - 3));
$mformdata['friendlypatterns'] = $friendlypatterns;
$mformdata['has_capability_send'] = has_capability('report/engagement:send', $context);
if ($action == 'composing') {
    $mformdata['defaultmessages'] = $defaultmessages;
    $mformdata['message_variables'] = $messagevariables;
    $mformdata['suggested_snippets'] = $suggestedsnippets;
    $mformdata['other_snippets'] = $othersnippets;
    $mformdata['my_saved_messages'] = $mysavedmessages;
    $mformdata['my_saved_messages_data'] = json_encode($mysavedmessagesdata);
    $mformdata['capable_users'] = $capableusers;
} else if ($action == 'previewing') {
    $mformdata['message_previews'] = $messagepreviews;
    $mformdata['sender_previews'] = $senderpreviews;
    $mformdata['replyto_previews'] = $replytopreviews;
    /* ForFutureL $mformdata['cc_previews'] = $cc_previews; */
    $mformdata['message_previews_by_user'] = $messagepreviewsbyuser;
} else if ($action == 'sending') {
    $mformdata['message_send_results'] = $messagesendresults;
}

// Instantiate form.
$mform = new report_engagement_mailer_form(null, $mformdata);

// Load up form data e.g. if pressing 'back'.
if (isset($postdata)) {
    // Put back postdata.
    $mform->set_data($postdata);
    // Hack to put back data.
    foreach ($patterns as $pattern => $userids) {
        // Set message.
        $data = array();
        if (isset($postdata->{"message_encoded_$pattern"})) {
            // If a message exists already (e.g. returning from previewing screen back to composing screen for further editing).
            $data["message_$pattern"] = base64_decode($postdata->{"message_encoded_$pattern"});
        } else {
            // Message doesn't exist yet, so populate with default.
            $data["message_$pattern"] = $defaultmessages[$pattern];
        }
        $mform->set_data($data);
        // Set subject.
        if (isset($postdata->{"subject_encoded_$pattern"})) {
            $data = array();
            $data["subject_$pattern"] = base64_decode($postdata->{"subject_encoded_$pattern"});
            $mform->set_data($data);
        }
    }
}
// Defaults for composing.
if ($action == 'composing') {
    foreach ($patterns as $pattern => $userids) {
        $mform->set_data(array("sender_$pattern" => $USER->id,"replyto_$pattern" => $USER->email));
    }
}

// Display form.
$mform->display();

echo $OUTPUT->footer();
