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

// Load up jquery
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->jquery_plugin('report_engagement-datatables', 'report_engagement');

echo $OUTPUT->header();

require_capability('report/engagement:manage', $context);

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
		if (!$patterns[$pattern]) {
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
		foreach ($users as $userid) {
			$data[$userid]["indicator_$name"] = [];
		}
		// Populate data // TODO: refactor this into individual indicator classes
		switch ($name) {
			case 'forum':
				foreach ($rawdata->posts as $userid => $record) {
					$data[$userid]["indicator_$name"]['total'] = $record['total']; // total postings (not readings)
					$data[$userid]["indicator_$name"]['new'] = $record['new'];
					$data[$userid]["indicator_$name"]['replies'] = $record['replies'];
					$data[$userid]["indicator_$name"]['read'] = $record['read'];
				}
				break;
			case 'login':
				foreach ($rawdata as $userid => $record) {
					$data[$userid]["indicator_$name"]['totaltimes'] = count($record['lengths']);
					$data[$userid]["indicator_$name"]['lastlogin'] = $record['lastlogin'];
					if ($record['total'] > 0) {
						$data[$userid]["indicator_$name"]['averagesessionlength'] = array_sum($record['lengths']) / count($record['lengths']);
						$data[$userid]["indicator_$name"]['averageperweek'] = array_sum($record['weeks']) / count($record['weeks']);
					} else {
						$data[$userid]["indicator_$name"]['averagesessionlength'] = "";
						$data[$userid]["indicator_$name"]['averageperweek'] = "";					
					}
				}
				break;
			case 'assessment':
				foreach ($rawdata->assessments as $assessment) {
					/*echo("<pre>");
					var_dump($rawdata->assessments);
					die();
					echo("</pre>");*/
					foreach ($users as $userid) {
						$submittime = isset($assessment->submissions[$userid]['submitted']) ? $assessment->submissions[$userid]['submitted'] : PHP_INT_MAX;
						$timedue = isset($assessment->submissions[$userid]['due']) ? $assessment->submissions[$userid]['due'] : 1;
						$interval = $submittime - $timedue;
						if (isset($assessment->submissions[$userid]['submitted'])) {
							$data[$userid]["indicator_$name"]['numbersubmissions'] += 1;
							if ($interval > 0) {
								$data[$userid]["indicator_$name"]['numberoverduesubmitted'] += 1;
								$data[$userid]["indicator_$name"]['totallateinterval'] += $interval;
							}
						} else if ($assessment->due > time()) {
							// Not due yet
							
						} else {
							$data[$userid]["indicator_$name"]['numberoverduenotsubmitted'] += 1;
							$data[$userid]["indicator_$name"]['overdueassessments'][] = $assessment->description;
						}
					}
				}
				break;
			case 'gradebook':
				foreach ($users as $userid) {
					$obj = $indicatorrisks[$userid];
					$data[$userid]["indicator_$name"]['risk'] = $obj->risk;
					foreach ($obj->info as $info) {
						if ($info->riskcontribution == '0%') {
							$data[$userid]["indicator_$name"]['nottriggeredby'][] = $info->title . " " . $info->logic;
						} else {
							$data[$userid]["indicator_$name"]['triggeredby'][] = $info->title . " " . $info->logic;
						}
					}
				}
				/*echo("<pre>");
				var_dump($indicatorrisks);
				echo("</pre>");
				die();*/
				break;
		}
	}
}

// Set up table
$jstable = array();
// $chk_disabled = $postdata && isset($patterns) ? "disabled='disabled'" : "";
$chk_disabled = "";
foreach ($data as $userid => $record) {
	$jsrow = array();
	$jsrow['_userid'] = $userid;
	// Add checkboxes
	$c = 0;
	foreach ($indicators as $name => $path) {
		/*
		$r = dechex((255 * ($riskdata[$userid]["indicator_$name"]['raw'] * 100)) / 100);
		$g = dechex((255 * (100 - ($riskdata[$userid]["indicator_$name"]['raw'] * 100))) / 100);
		*/
		$c += 1;
		$checked = '';
		if ($postdata && $patterns) {
			if (isset($postdata->{"chk_indicator_".$name."_".$userid})) $checked = "checked='checked'";
		}
		if (!($postdata && $patterns) || $checked) {
			$jsrow["$c"] = "<div class='chk_indicator'><input type='checkbox' id='chk_indicator_".$name."_".$userid."' name='chk_indicator_".$name."_".$userid."' $checked $chk_disabled data-userid='$userid' /></div>";
		} else {
			$jsrow["$c"] = "";
		}
	}
	// Show username
	$studentrecord = $DB->get_record('user', array('id' => $userid));
	$jsrow['Username'] = "<span title='$userid'>".$studentrecord->firstname." ".$studentrecord->lastname."</span>";
	// Show logic/information
	$c = 0;
	foreach ($indicators as $name => $path) {
		$c += 1;
		$pluginname = get_string('pluginname', "engagementindicator_$name");
		// TODO: best to eventually refactor this into individual classes
		switch ($name) {
			case 'forum':
				$r = $record["indicator_$name"]['read'];
				$r = $r ? $r : 0;
				$p = $record["indicator_$name"]['total'];
				$p = $p ? $p : 0;
				$jsrow["$pluginname"] = get_string('report_readposts', 'report_engagement', $r)."<br />".get_string('report_posted', 'report_engagement', $p);
				break;
			case 'login':
				$n = $record["indicator_$name"]['lastlogin'];
				$a = $record["indicator_$name"]['averageperweek'];
				if ($n) {
					$d = (time() - $n) / 60 / 60 / 24.0;
					$jsrow["$pluginname"] = sprintf("%.1d", $d).get_string('report_login_dayssince', 'report_engagement')."<br />".sprintf("%.1f", $a).get_string('report_login_perweek', 'report_engagement');
				} else {
					$jsrow["$pluginname"] = "";
				}
				break;
			case 'assessment':
				$n = $record["indicator_$name"]['numberoverduenotsubmitted'];
				$n = $n ? $n : 0;
				$s = $record["indicator_$name"]['numbersubmissions'];
				$s = $s ? $s : 0;
				$o = $record["indicator_$name"]['numberoverduesubmitted'];
				$o = $o ? $o : 0;
				$l = $record["indicator_$name"]['totallateinterval'] / 60 / 60 / 24;
				$v = $l / $s;
				$ov = new stdClass();
				$ov->o = $o;
				$ov->v = $v;
				$a = implode('<br />', $record["indicator_$name"]['overdueassessments']);
				$jsrow["$pluginname"] = get_string('report_assessment_overdue', 'report_engagement', $n)."<div class='report_engagement_detail'>$a</div><br />".get_string('report_assessment_submitted', 'report_engagement', $s)."<div class='report_engagement_detail'>".get_string('report_assessment_overduelate', 'report_engagement', $ov)."</div>";
				break;
			case 'gradebook':
				$r = $record["indicator_$name"]['risk'];
				$r = $r ? $r : 0;
				$t = $record["indicator_$name"]['triggeredby'];
				$ts = implode('&#10;', $t);
				$t = $t ? count($t) : 0;
				$n = $record["indicator_$name"]['nottriggeredby'];
				$ns = implode('&#10;', $n);
				$n = $n ? count($n) : 0;
				$jsrow["$pluginname"] = get_string('report_gradebook_percentrisk', 'report_engagement', ($r * 100))."<br />".get_string('report_gradebook_triggered', 'report_engagement', $t)."<div class='report_engagement_detail'>$ts</div><br />".get_string('report_gradebook_nottriggered', 'report_engagement', $n)."<div class='report_engagement_detail'>$ns</div>";
				break;
		}
	}
	// Calculate and show total risk
	$totalrisk = 0;
	foreach ($indicators as $name => $path) {
		$totalrisk += $riskdata[$userid]["indicator_$name"]['raw'] * $riskdata[$userid]["indicator_$name"]['weight'];
	}
	$jsrow[get_string('report_totalrisk', 'report_engagement')] = sprintf("%d%%", $totalrisk * 100);
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
		$jsrow[get_string('report_messagessent', 'report_engagement')] = "<a href='$view_messages_url' target='_blank'>".count($messages_sent)."</a><br />".get_string('report_messagelog_daysago', 'report_engagement', sprintf("%d", $days_ago));
	} else {
		$jsrow[get_string('report_messagessent', 'report_engagement')] = count($messages_sent);
	}
	// Add row to table
	$jstable[] = $jsrow;
}

$c = 0;
$js_columns = array();
$chk_column_headers = array_keys($indicators);
foreach (array_keys($jstable[0]) as $key) {
	$c += 1;
	if ($key == '_userid') {
		$c -= 1;
		// otherwise do nothing
	} elseif ($c <= count($indicators)) {
		$js_columns[] = ucfirst(substr($chk_column_headers[$c - 1], 0, 5)) . (strlen($chk_column_headers[$c - 1]) > 5 ? "." : "") . $OUTPUT->help_icon('mailer_column_header', "engagementindicator_{$chk_column_headers[$c - 1]}");
	} else {
		$js_columns[] = $key;
	}
}

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
	foreach ($patterns as $pattern => $userids) {
		$sender = $DB->get_record('user', array('id'=>$postdata->{"sender_$pattern"}));
		$sender_previews[$pattern][$postdata->{"sender_$pattern"}] = fullname($sender) . " &lt;$sender->email&gt;";
		$replyto = $DB->get_record('user', array('id'=>$postdata->{"replyto_$pattern"}));
		$replyto_previews[$pattern][$postdata->{"replyto_$pattern"}] = fullname($replyto) . " &lt;$replyto->email&gt;";
	}
} else if ($action == 'sending') {
	$messages = array();
	// Create messages
	$message_decoded = array();
	$subject_decoded = array();
	$senderids = array();
	$replytoids = array();
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
		$replytoids[$pattern] = $postdata->{"replyto_$pattern"};
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
			$message_send_results[$pattern][$userid] = message_send_customised_email($message, $userid, $senderids[$pattern], $replytoids[$pattern]);
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
$mformdata['jstable'] = $jstable;
$mformdata['js_columns'] = $js_columns;
$mformdata['chk_column_headers'] = $chk_column_headers;
$mformdata['defaultsort'] = json_encode(array(array(count($indicators)*2+1, 'desc'))); // default sort by total descending
$mformdata['html_num_fmt_cols'] = json_encode(array(5,6,7,8));
$mformdata['friendlypatterns'] = $friendlypatterns;
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
		$mform->set_data(array("sender_$pattern"=>$USER->id,"replyto_$pattern"=>$USER->id));
	}
}

// Display form
$mform->display();

echo $OUTPUT->footer();
