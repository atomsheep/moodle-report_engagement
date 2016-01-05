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

// Load up js
$PAGE->requires->js(new moodle_url('/report/engagement/mailer.js'));
// Load up jquery
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->jquery_plugin('report_engagement-datatables', 'report_engagement');

echo $OUTPUT->header();

require_capability('report/engagement:view', $context);

add_to_log($course->id, "course", "report engagement", "report/engagement/mailer.php?id=$course->id", $course->id);

// Prepare indicators
$pluginman = core_plugin_manager::instance();
$indicators = get_plugin_list('engagementindicator');
foreach ($indicators as $name => $path) {
    $plugin = $pluginman->get_plugin_info('engagementindicator_'.$name);
    if (!$plugin->is_enabled()) {
        unset($indicators[$name]);
    }
}

// Examine posted data
if (data_submitted() && confirm_sesskey()) {
	$postdata = data_submitted();
	// Get userids that have been checked
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
	// Determine all patterns of checking
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
			// continue - other logic will determine what is shown
			break;
		case 'composing':
			$action = 'previewing';
			// continue - other logic will determine what is shown
			break;
		case 'previewing':
			$action = 'sending';
			// check for capability
			require_capability('report/engagement:send', $context);
			// continue - later logic will send messages
			break;
		case 'sending':
			break;
	}
} else {
	$action = 'viewing';
}

// Retrieve data from databases
$data = array();
$riskdata = array();
$display_data = array();
$weightings = $DB->get_records_menu('report_engagement', array('course' => $id), '', 'indicator, weight');
foreach ($indicators as $name => $path) {
	if (file_exists("$path/indicator.class.php")) {
		require_once("$path/indicator.class.php");
		$classname = "indicator_$name";
		$indicator = new $classname($id);
		// run in order to process data
		$indicatorrisks = $indicator->get_course_risks();
		// work out risk weightings
		$weight = isset($weightings[$name]) ? $weightings[$name] : 0;
		foreach ($indicatorrisks as $_user => $risk) {
			$riskdata[$_user]["indicator_$name"]['raw'] = $risk->risk;
			$riskdata[$_user]["indicator_$name"]['weight'] = $weight;
		}
		// fetch raw data
		$rawdata = $indicator->get_course_rawdata();
		// fetch array of userids
		$users = $indicator->get_course_users();
		if (empty($data)) {
			foreach ($users as $userid) {
				$data[$userid] = array();
			}
		}
		// fetch information to display
		$display_data[$name] = $indicator->get_data_for_mailer();
	}
}

// Parse and store group data
$groups = groups_get_all_groups($course->id);
// - Get groups and membership
$group_memberships = array();
foreach ($groups as $groupid => $group) {
	$group_membership = array();
	$group_membership['members'] = array_keys(groups_get_members($groupid, 'u.id'));
	$group_membership['groupname'] = $group->name;
	$group_membership['groupid'] = $groupid;
	$group_memberships[$groupid] = $group_membership;
}
// - Parse and store by user
$groups_by_user = array();
foreach ($data as $userid => $record) {
	$groups_by_user[$userid] = array();
	foreach ($group_memberships as $groupid => $group_membership) {
		if (array_search($userid, $group_membership['members'])) {
			$groupinfo = array();
			$groupinfo['groupname'] = $group_membership['groupname'];
			$groupinfo['groupid'] = $group_membership['groupid'];
			$groups_by_user[$userid][] = $groupinfo;
		}
	}
}

// Set up table data
$table_data = array();
// $chk_disabled = $postdata && isset($patterns) ? "disabled='disabled'" : "";
$chk_disabled = "";
foreach ($data as $userid => $record) {
	// Prepare table row
	$table_row = array();
	$table_row['userid'] = $userid;
	$table_row['data'] = array();
	$c = 0;
	// Add checkboxes
	foreach ($indicators as $name => $path) {
		$checked = '';
		// See if checkbox is checked in postdata
		if (isset($postdata) && $postdata && $patterns) {
			if (isset($postdata->{"chk_indicator_".$name."_".$userid})) $checked = "checked='checked'";
		}
		// Show checkbox(es) accordingly
		if (!(isset($postdata) && $postdata && $patterns) || $checked) {
			$table_row['data'][$c] = "<div class='chk_indicator'><input type='checkbox' id='chk_indicator_".$name."_".$userid."' name='chk_indicator_".$name."_".$userid."' $checked $chk_disabled data-userid='$userid' /></div>";
		} else {
			$table_row['data'][$c] = "";
		}
		$c += 1;
	}
	// Show user name
	$studentrecord = $DB->get_record('user', array('id' => $userid));
	$table_row['data'][$c] = "<span title='$userid'>".$studentrecord->firstname." ".$studentrecord->lastname."</span>";
	$c += 1;
	// User email address
	$table_row['data'][$c] = $studentrecord->email;
	$c += 1;
	// Show group membership
	$groups = array();
	foreach ($groups_by_user[$userid] as $group) {
		$groups[] = $group['groupname'];
	}
	$table_row['data'][$c] = join('<br />', $groups);
	$c += 1;
	// Show logic/information for each indicator
	foreach ($indicators as $name => $path) {
		$pluginname = get_string('pluginname', "engagementindicator_$name");
		// Parse display data
		foreach ($display_data[$name] as $display_column) {
			$table_row['data'][$c] = $display_column['display'][$userid];
			$c += 1;
		}
	}
	// Calculate and show total risk
	$totalrisk = 0;
	foreach ($indicators as $name => $path) {
		$totalrisk += $riskdata[$userid]["indicator_$name"]['raw'] * $riskdata[$userid]["indicator_$name"]['weight'];
	}
	$table_row['data'][$c] = '<div><span class="report_engagement_display">'.
		sprintf("%d", $totalrisk * 100).
		'</span></div>';
	$c += 1;
	// Calculate and show how many messages already received
	try {
		$messages_sent = $DB->get_records_sql("SELECT ml.id, sl.timesent FROM {report_engagement_messagelog} ml JOIN {report_engagement_sentlog} sl ON sl.messageid = ml.id WHERE sl.courseid = ? AND sl.recipientid = ? ORDER BY sl.timesent ASC", array($course->id, $userid));
	} catch (Exception $err) {
		$messages_sent = array();
	}
	if (count($messages_sent)) {
		$view_messages_url = new moodle_url('/report/engagement/mailer_log.php', array('id' => $course->id, 'uid' => $userid));
		$most_recent_message = end($messages_sent);
		$days_ago = (time() - $most_recent_message->timesent) / 60 / 60 / 24;
		$table_row['data'][$c] = "<a href='$view_messages_url' target='_blank'>".count($messages_sent)."</a><br />".get_string('report_messagelog_daysago', 'report_engagement', sprintf("%d", $days_ago));
		$c += 1;
	} else {
		$table_row['data'][$c] = count($messages_sent);
		$c += 1;
	}
	// Add row to table
	$table_data[] = $table_row;
}

// Determine column headers and related options
$column_headers = array();
$chk_column_headers = array_keys($indicators);
$heatmappable_columns = array();
$heatmappable_columns_directions = array();
$c = 0;
// First columns are for checkboxes
foreach (array_keys($indicators) as $indicator_name) {
	$column_header = [];
	$column_header['html'] = get_string('mailer_checkbox_column_header', "engagementindicator_{$indicator_name}") . $OUTPUT->help_icon('mailer_checkbox_column_header', "engagementindicator_{$indicator_name}");
	$column_header['chk'] = True;
	$column_header['filter'] = False;
	$column_headers[$c] = $column_header;
	$c += 1;
}
// Next columns are for user info
$column_headers[$c] = array(
	'html'=>get_string('report_username', 'report_engagement'),
	'filterable'=>True
);
$c += 1;
$column_headers[$c] = array(
	'html'=>get_string('report_email', 'report_engagement'),
	'hide'=>True
);
$c += 1;
$column_headers[$c] = array(
	'html'=>get_string('report_groups', 'report_engagement'),
	'filterable'=>True
);
$c += 1;
// Next columns are for individual indicators
foreach (array_keys($indicators) as $indicator_name) {
	foreach ($display_data[$indicator_name] as $display_item) {
		$column_header = [];
		$column_header['html'] = $display_item['header'];
		$column_header['filterable'] = array_key_exists('filterable', $display_item) ? $display_item['filterable'] : False;
		if (array_key_exists('heatmapdirection', $display_item)) {
			$column_header['heatmapdirection'] = $display_item['heatmapdirection'];
			$heatmappable_columns[] = $c;
			$heatmappable_columns_directions[] = $display_item['heatmapdirection'];
		}
		$column_headers[$c] = $column_header;
		$c += 1;
	}
}
// Last columns are for totals etc
$column_headers[$c] = array(
	'html'=>get_string('report_totalrisk', 'report_engagement'),
	'filterable'=>False
);
$heatmappable_columns[] = $c;
$heatmappable_columns_directions[] = 1;
$c += 1;
$column_headers[$c] = array(
	'html'=>get_string('report_messagessent', 'report_engagement'),
	'filterable'=>False
);
$c += 1;

// Make friendly patterns and compose message boilerplate
$default_message_greeting = get_string('message_default_greeting', 'report_engagement');
$default_message_closing = get_string('message_default_closing', 'report_engagement');
if (!isset($patterns) && !$postdata) {
	$patterns = array(false);
	$subsets = false;
} else {
	$subsets = true;
	$friendlypatterns = array();
	foreach ($patterns as $pattern => $userids){
		// Form default message
		$defaultmessages[$pattern] = $default_message_greeting;
		$defaultmessages[$pattern] .= $default_message_closing;
		// Form friendly patterns
		$friendlypatterns[$pattern] = new stdClass();
		$friendlypatterns[$pattern]->names = array();
		$indicator_names = array_keys($indicators);
		foreach (str_split($pattern) as $char) {
			$indicator_name = array_shift($indicator_names);
			if ($char == '1') {
				$friendlypatterns[$pattern]->human .= get_string('pluginname', "engagementindicator_$indicator_name") . ", ";
				$friendlypatterns[$pattern]->names[] = $indicator_name;
			}
		}
		if (substr($friendlypatterns[$pattern]->human, strlen($friendlypatterns[$pattern]->human) - 2) === ', ') {
			$friendlypatterns[$pattern]->human = substr($friendlypatterns[$pattern]->human, 0, strlen($friendlypatterns[$pattern]->human) - 2);
		}
	}
}

if ($action == 'composing') {
	// Set up message components/snippets
	// - variables
	$message_variables = message_variables_get_array();
	// - load snippets from file
	$snippets_file = file_get_contents('lang/en/data.json.txt');
	$snippets = json_decode($snippets_file);
	$snippet_category_map = array();
	$snippet_category_map_names = array();
	$snippet_category_names = array();
	foreach ($snippets->categories as $category) {
		if (isset($category->engagement_mapping)) {
			$snippet_category_map[$category->engagement_mapping] = $category->id;
			$snippet_category_map_names[$category->engagement_mapping] = $category->name;
		}
		$snippet_category_names[$category->id] = $category->name;
	}
	// - suggested snippets
	$suggested_snippets = array();
	$desired_categories = array();
	foreach ($patterns as $pattern => $userids) {
		$desired_categories[$pattern] = array();
		foreach ($friendlypatterns[$pattern]->names as $indicator_name) {
			$desired_categories[$pattern][] = $snippet_category_map[$indicator_name];
		}
		$c = 0;
		foreach ($friendlypatterns[$pattern]->names as $indicator_name) {
			$desired_category = $snippet_category_map[$indicator_name];
			$suggested_snippets[$pattern][$c][$snippet_category_map_names[$indicator_name]] = array();
			foreach ($snippets->snippets as $snippet) {
				if (in_array($desired_category, $snippet->categories)){
					$suggested_snippets[$pattern][$c][$snippet_category_map_names[$indicator_name]][$snippet->id] = $snippet->text;
				}
			}
			$c += 1;
		}
	}
	// - other snippets
	$other_snippets = array();
	$undesired_categories = array();
	foreach ($patterns as $pattern => $userids) {
		$undesired_categories[$pattern] = array_diff(array_keys($snippet_category_names), $desired_categories[$pattern]);
		$c = 0;
		foreach ($undesired_categories[$pattern] as $undesired_category) {
			$other_snippets[$pattern][$c][$snippet_category_names[$undesired_category]] = array();
			foreach ($snippets->snippets as $snippet) {
				if (in_array($undesired_category, $snippet->categories)) {
					$other_snippets[$pattern][$c][$snippet_category_names[$undesired_category]][$snippet->id] = $snippet->text;
				}
			}
			$c += 1;
		}
	}
	// - load my messages
	$saved_messages = my_messages_get();
	$my_saved_messages = array();
	$my_saved_messages_data = array();
	foreach ($patterns as $pattern => $userid) {
		$my_saved_messages[$pattern] = array();
		$c = 0;
		foreach ($saved_messages as $message) {
			$my_saved_messages[$pattern]["m$c"] = base64_decode($message->messagesummary); // the short description
			$my_saved_messages_data["m$c"] = base64_decode($message->messagetext); // stores the actual message text
			$c += 1;
		}
	}
	// - Load up userlist of those with appropriate capabilities
	$users = get_users_by_capability($context, 'report/engagement:manage');
	if (!in_array($USER->id, array_keys($users))) {
		// Add current user to userlist
		$users[$USER->id] = $USER;
	}
	$capable_users = array();
	foreach ($users as $userid => $user) {
		$capable_users[$userid] = fullname($user) . " &lt;$user->email&gt;";
	}
	asort($capable_users);
} else if ($action == 'previewing') {
	$message_previews = array();
	$message_previews_by_user = array();
	foreach ($patterns as $pattern => $userids) {
		$message_previews[$pattern] = new stdClass();
		$message_previews[$pattern]->subject = $postdata->{"subject_$pattern"};
		$message_previews[$pattern]->subject_encoded = base64_encode($postdata->{"subject_$pattern"});
		$message_previews[$pattern]->message = $postdata->{"message_$pattern"};
		$message_previews[$pattern]->message_encoded = base64_encode($postdata->{"message_$pattern"});
		$message_previews_by_user[$pattern] = array();
		foreach ($userids as $userid) {
			$message_previews_by_user[$pattern][$userid] = new stdClass();
			$recipient = $DB->get_record('user', array('id'=>$userid));
			$message_previews_by_user[$pattern][$userid]->recipient = $recipient;
			$message_previews_by_user[$pattern][$userid]->subject = message_variables_replace($postdata->{"subject_$pattern"}, $userid);
			$message_previews_by_user[$pattern][$userid]->message = message_variables_replace($postdata->{"message_$pattern"}, $userid);
		}
	}
	$sender_previews = array();
	$replyto_previews = array();
	//$cc_previews = array();
	foreach ($patterns as $pattern => $userids) {
		$sender = $DB->get_record('user', array('id'=>$postdata->{"sender_$pattern"}));
		$sender_previews[$pattern][$postdata->{"sender_$pattern"}] = fullname($sender) . " &lt;$sender->email&gt;";
		//$replyto = $DB->get_record('user', array('id'=>$postdata->{"replyto_$pattern"}));
		//$replyto_previews[$pattern][$postdata->{"replyto_$pattern"}] = fullname($replyto) . " &lt;$replyto->email&gt;";
		$replyto_previews[$pattern][$postdata->{"replyto_$pattern"}] = $postdata->{"replyto_$pattern"};
		//$cc_previews[$pattern][$postdata->{"cc_$pattern"}] = $postdata->{"cc_$pattern"};
	}
} else if ($action == 'sending') {
	$messages = array();
	// Create messages
	$message_decoded = array();
	$subject_decoded = array();
	$senderids = array();
	//$replytoids = array();
	$replytoaddresses = array();
	//$ccaddresses = array();
	foreach ($patterns as $pattern => $userids) {
		$message_decoded[$pattern] = base64_decode($postdata->{"message_encoded_$pattern"});
		$subject_decoded[$pattern] = base64_decode($postdata->{"subject_encoded_$pattern"});
		$messages[$pattern] = array();
		foreach ($userids as $userid) {
			$messages[$pattern][$userid] = array();
			$messages[$pattern][$userid]['message'] = message_variables_replace($message_decoded[$pattern], $userid);
			$messages[$pattern][$userid]['subject'] = message_variables_replace($subject_decoded[$pattern], $userid);
		}
		$senderids[$pattern] = $postdata->{"sender_$pattern"};
		//$replytoids[$pattern] = $postdata->{"replyto_$pattern"};
		$replytoaddresses[$pattern] = $postdata->{"replyto_$pattern"};
		//$ccaddresses[$pattern] = $postdata->{"cc_$pattern"};
	}
	// Send messages
	$message_send_results = array();
	$messageid = 0;
	foreach ($patterns as $pattern => $userids) {
		$message_send_results[$pattern] = array();
		$n = 0;
		foreach ($messages[$pattern] as $userid => $message) {
			// Log message, just once per message
			if ($n == 0) {
				$messageid = message_send_log_message($subject_decoded[$pattern], $message_decoded[$pattern], 'email');
				$n += 1;
			}
			// Send messages
			//$message_send_results[$pattern][$userid] = message_send_customised_email($message, $userid, $senderids[$pattern], $replytoids[$pattern]);
			$message_send_results[$pattern][$userid] = message_send_customised_email($message, $userid, $senderids[$pattern], $replytoaddresses[$pattern], $ccaddresses[$pattern]);
			if ($message_send_results[$pattern][$userid]->result == true) {
				// Log send event to database
				$user = $DB->get_record('user', array('id'=>$userid));
				message_send_log_send($messageid, $user->email, $user->id, $senderids[$pattern]);					
			}
		}
	}
	// Save my messages if necessary
	foreach ($patterns as $pattern => $userids) {
		if (isset($postdata->{"chk_savemy_$pattern"})) {
			my_message_save($postdata->{"txt_savemy_$pattern"}, $postdata->{"message_encoded_$pattern"});
		}
	}
}

// Load up data to send to form
$mformdata = array();
$mformdata['id'] = $id;
$mformdata['action'] = $action;
$mformdata['patterns'] = $patterns;
$mformdata['subsets'] = $subsets;
$mformdata['table_data'] = $table_data;
$mformdata['column_headers'] = $column_headers;
$mformdata['chk_column_headers'] = $chk_column_headers;
$mformdata['heatmappable_columns'] = $heatmappable_columns;
$mformdata['heatmappable_columns_directions'] = $heatmappable_columns_directions;
$mformdata['display_data_raw'] = $display_data;
$mformdata['defaultsort'] = json_encode(array(array(count($column_headers) - 2, 'desc'))); // default sort by total descending
$mformdata['html_num_fmt_cols'] = json_encode(range(count($chk_column_headers) + 1, count($column_headers) - 3));
$mformdata['friendlypatterns'] = $friendlypatterns;
$mformdata['has_capability_send'] = has_capability('report/engagement:send', $context);
if ($action == 'composing') {
	$mformdata['defaultmessages'] = $defaultmessages;
	$mformdata['message_variables'] = $message_variables;
	$mformdata['suggested_snippets'] = $suggested_snippets;
	$mformdata['other_snippets'] = $other_snippets;
	$mformdata['my_saved_messages'] = $my_saved_messages;
	$mformdata['my_saved_messages_data'] = json_encode($my_saved_messages_data);
	$mformdata['capable_users'] = $capable_users;
} else if ($action == 'previewing') {
	$mformdata['message_previews'] = $message_previews;
	$mformdata['sender_previews'] = $sender_previews;
	$mformdata['replyto_previews'] = $replyto_previews;
	//$mformdata['cc_previews'] = $cc_previews;
	$mformdata['message_previews_by_user'] = $message_previews_by_user;
} else if ($action == 'sending') {
	$mformdata['message_send_results'] = $message_send_results;
}

// Instantiate form
$mform = new report_engagement_mailer_form(null, $mformdata);

// Load up form data e.g. if pressing 'back'
if ($postdata) {
	// put back postdata
	$mform->set_data($postdata);
	// hack to put back data
	foreach ($patterns as $pattern => $userids) {
		// Set message
		$data = array();
		if (isset($postdata->{"message_encoded_$pattern"})) {
			// If a message exists already (e.g. returning from previewing screen back to composing screen for further editing)
			//$data["message_$pattern"] = array('text' => base64_decode($postdata->{"message_encoded_$pattern"}));
			$data["message_$pattern"] = base64_decode($postdata->{"message_encoded_$pattern"});
		} else {
			// Message doesn't exist yet, so populate with default
			//$data["message_$pattern"] = array('text' => $defaultmessages[$pattern]);
			$data["message_$pattern"] = $defaultmessages[$pattern];
		}
		$mform->set_data($data);
		// Set subject
		if (isset($postdata->{"subject_encoded_$pattern"})) {
			$data = array();
			$data["subject_$pattern"] = base64_decode($postdata->{"subject_encoded_$pattern"});
			$mform->set_data($data);
		}
	}
}
// Defaults for composing
if ($action == 'composing') {
	foreach ($patterns as $pattern => $userids) {
		//$mform->set_data(array("sender_$pattern"=>$USER->id,"replyto_$pattern"=>$USER->id));
		$mform->set_data(array("sender_$pattern"=>$USER->id,"replyto_$pattern"=>$USER->email));
	}
}

// Display form
$mform->display();

echo $OUTPUT->footer();
