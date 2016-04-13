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
 * Strings
 *
 * @package    report_engagement
 * @copyright  NetSpot Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['engagement:manage'] = 'Manage engagement analytics report';
$string['engagement:view'] = 'View engagement analytics report';
$string['engagement:send'] = 'Send messages to students from engagement analytics report';
$string['mailer_capability_nopermissions'] = 'Sorry, you do not have permission to send messages to students.';
$string['coursereport'] = 'Engagement for course: {$a->course}';
$string['coursereport_heading'] = 'Engagement for course: {$a->course}';
$string['indicator'] = 'Indicator';
$string['indicator_help'] = 'In the textboxes, enter the percentage contribution (whole number between 0-100) of the risk rating from each indicator to the total risk rating. The totals in this section must add to 100.';
$string['eventreport_viewed'] = "View engagement analytics report";
$string['eventsettings_updated'] = "Update settings for engagement analytics";
$string['eventmessage_sent'] = "Message sent from engagement analytics";

$string['manageindicators'] = 'Manage indicators';
$string['snippetheader'] = 'Manage snippets for {$a}';
$string['snippetnumber'] = 'Snippet {$a}';
$string['snippetnew'] = 'New snippet';
$string['snippetdelete'] = 'Delete snippet';

$string['page-report-engagement-x'] = 'Any engagement analytics report';
$string['page-report-engagement-index'] = 'Course engagement analytics report';
$string['page-report-engagement-user'] = 'User course engagement analytics report';
$string['pluginname'] = 'Engagement analytics';
$string['reportdescription'] = 'Numbers in parentheses are raw risk ratings for each indicator. Numbers outside parentheses are weighted risk ratings for each indicator - these are summed to give the total risk rating.';
$string['updatesettings'] = 'Update settings';
$string['userreport'] = 'Engagement analytics for {$a->user} in course: {$a->course}';
$string['weighting'] = 'Weighting';
$string['weighting_desc'] = 'Test';
$string['weightingmustbenumeric'] = 'Weighting percentages must be a numeric value in the range 0~100';
$string['weightingsumtoonehundred'] = 'The sum of weighting values must be equal to 100%';

$string['queryspecifydatetime'] = 'Limit query dates';
$string['querystartdatetime'] = 'Ignore data before';
$string['queryenddatetime'] = 'Ignore data after';
$string['querylimitset'] = 'Query limit set: ';

$string['mailer'] = 'Mailer';
$string['button_mailer_label_csv'] = 'Download as CSV';
$string['button_mailer_fname_csv'] = 'engagement_report';
$string['button_mailer_log_label_csv'] = 'Download as CSV';
$string['button_mailer_log_fname_csv'] = 'mailer_log';
$string['mailer_message_log'] = 'Mailer message log';
$string['mailer_log_user'] = 'Messages received by ';
$string['mailer_log_message'] = 'Recipients of message';
$string['mailer_log_course'] = 'All messages sent in course';
$string['mailer_log_showmessage'] = 'Message: ';
$string['mailer_log_message_type'] = 'Type: ';
$string['mailer_log_message_from'] = 'From: ';
$string['mailer_log_message_sent'] = 'Sent: ';
$string['mailer_log_message_subject'] = 'Subject: ';
$string['mailer_log_message_body'] = 'Message: ';
$string['mailer_log_message_recipients'] = 'Recipients: ';
$string['mailer_log_message_id'] = 'Message ID: ';
$string['mailer_log_nomessages'] = 'No messages logged';
$string['mailer_log_message_otherrecipients'] = 'other users';
$string['mailer_log_viewbymessage'] = 'View by message';
$string['mailer_log_viewbyuser'] = 'View messages sent to user';

$string['message_will_be_sent_count'] = 'The following message will be sent to:';
$string['message_will_be_sent_count_above'] = 'as listed above';
$string['student_plural'] = 'students';
$string['student_singular'] = 'students';
$string['message_sender'] = 'Sender';
$string['message_sender_help'] = 'The person and email address that the email to the student will appear to come from.';
$string['message_replyto'] = 'Reply to';
$string['message_replyto_help'] = 'The default email address that receives replies when a student replies to a message.';
$string['message_replyto_error_email'] = 'Must be a valid email address';
$string['message_cc'] = 'CC';
$string['message_cc_help'] = 'An email address that will receive a carbon copy of this message.';
$string['message_cc_error_email'] = 'Must be a valid email address';
$string['message_subject'] = 'Message subject';
$string['message_subject_help'] = 'The subject of the email.';
$string['message_body'] = 'Message body';
$string['message_body_help'] = 'The message body of the email. Text between curly braces needs to obey specific conventions - these are available in the snippets below under "variables".';
$string['message_snippets'] = 'Message snippets';
$string['message_snippets_help'] = 'Choose a type of snippet to include, find one that you like, and click it to insert the snippet into the message body at the cursor.

*Variables*: A variable represents information that that system will automatically add to your message.

*Suggested snippets*: Text for messages relating to the indicator or indicators that you have selected.

*Other snippets*: Text for messages not relating to the indicator or indicators that you have selected. You might want to have a look at these if you want some more ideas about what to write.

*My saved messages*: Text for messages that you have saved to your message bank.';
$string['message_snippets_variables'] = 'Variables';
$string['message_snippets_suggested'] = 'Suggested snippets';
$string['message_snippets_other'] = 'Other snippets';
$string['message_snippets_my'] = 'My saved messages';
$string['message_savemy'] = 'Save message';
$string['message_savemy_chk'] = 'Save to my message bank';
$string['message_savemy_description'] = 'Short description:';
$string['message_savemy_help'] = 'If you wish to save your message for future use, tick the checkbox and enter a short phrase to help you identify this message in the future. Your saved messages will be available for your own use in other courses.';
$string['message_recipient_preview'] = 'Recipient';
$string['message_subject_preview'] = 'Subject preview (read-only)';
$string['message_subject_preview_help'] = 'Preview of the subject of the email. Since this is a preview, this field is read-only. To edit, click the edit button to go back.';
$string['message_body_preview'] = 'Message preview (read-only)';
$string['message_body_preview_help'] = 'Preview of the actual message body of the email which will be sent. Since this is a preview, this field is read-only. To edit, click the edit button to go back.';
$string['message_go_back_edit'] = 'Go back and edit message';
$string['message_go_back_edit_help'] = 'Navigates to the previous screen, which allows you to edit this message.';
$string['message_go_back_edit_plural'] = 'Go back and edit messages';
$string['message_go_back_edit_plural_help'] = 'Navigates to the previous screen, which allows you to edit messages.';
$string['message_check_all'] = 'Toggle all visible checkboxes for';
$string['message_check_all_help'] = 'Click each button to toggle the checked status of all the checkboxes for that indicator that are visible in the table.';
$string['message_header_compose'] = 'Compose messages';
$string['message_submit_compose'] = 'Compose messages for selected people';
$string['message_header_preview'] = 'Preview messages';
$string['message_submit_preview'] = 'Preview messages before sending';
$string['message_preview_buttons'] = 'Navigate message previews';
$string['message_preview_buttons_help'] = 'Select either previous message or next message to scroll through each student email for that group. ';
$string['message_preview_button_back'] = '<< Previous message';
$string['message_preview_button_forward'] = 'Next message >>';
$string['message_header_send'] = 'Send messages';
$string['message_submit_send'] = 'Send messages now';
$string['message_table_extradetails'] = 'Extra details in table';
$string['message_table_extradetails_help'] = 'Toggle to show or hide extra details in the table.';
$string['message_table_heatmap'] = 'Heatmap';
$string['message_table_heatmap_help'] = 'Toggle to show or hide heatmap - darker coloration tends to indicate more negative metrics. This visualisation is processed live so may take a few moments to display.';
$string['message_table_filter_column'] = 'Filter';
$string['message_table_show_extradetails_checkbox'] = 'Show';
$string['message_table_show_heatmap_checkbox'] = 'Show (may take a short time to process)';
$string['message_header_groupwith'] = 'Group with: ';
$string['message_sent_notification_header'] = 'Message sent to:';
$string['message_sent_notification_recipient'] = '{$a->email}';
$string['message_sent_notification_success'] = '[{$a}]';
$string['message_sent_notification_failed'] = '[FAILED {$a}]';

$string['report_messagelog_daysago'] = '{$a} days ago';
$string['report_username'] = 'Name';
$string['report_email'] = 'Email';
$string['report_groups'] = 'Groups';
$string['report_totalrisk'] = 'Total risk';
$string['report_messagessent'] = 'Msgs <br />sent';
$string['report_header_selectmessagetypes'] = 'Select message type(s)';
$string['report_header_userinfo'] = 'User information';
$string['report_header_data'] = 'Data';
$string['report_header_totals'] = 'Totals';

$string['message_default_greeting'] = 'Dear {#FIRSTNAME#},'.chr(13).chr(13);
$string['message_default_closing'] = 'Kind regards';

$string['message_variables_firstname'] = 'First name';
$string['message_variables_lastname'] = 'Last name';
$string['message_variables_fullname'] = 'Full name';

// Indicator parameter discovery helper.
$string['indicator_helper'] = 'Indicator parameter discovery helper';
$string['indicator_helper_report'] = 'Data dump';
$string['indicator_helper_report_textarea'] = 'Data dump';
$string['indicator_helper_datadump'] = 'Show data dump';
$string['indicator_helper_report_textarea_help'] = 'Data used for calculating correlation. Username, x-values, y-values. This can be copied and pasted directly into a spreadsheet.';
$string['indicator_helper_settings'] = 'Genetic algorithm settings';
$string['indicator_helper_correlation'] = 'Correlation';
$string['indicator_helper_target'] = 'Target variable for {$a}';
$string['indicator_helper_target_help'] = 'The numerical grade item against which to correlate risk ratings. Usually a course outcome such as final grade.';
$string['indicator_helper_discover'] = 'Discovery mode';
$string['indicator_helper_discover_help'] = 'Choose to run discovery for indicators individually, or for the overall weightings. Typically you would run discovery for individual indicators first, and then run the discovery for overall weightings.';
$string['indicator_helper_discover_indicator'] = 'By individual selected indicator';
$string['indicator_helper_discover_weightings'] = 'Overall weightings between indicators';
$string['indicator_helper_indicator'] = 'Indicator to discover parameters';
$string['indicator_helper_indicator_help'] = 'Select indicator to run discovery for. Only applies if running discovery for indicators individually.';
$string['indicator_helper_activeindicators'] = 'Indicator(s) to discover weightings';
$string['indicator_helper_activeindicators_help'] = 'Select indicator(s) to run discovery for. Only applies if running discovery for overall weightings between indicators. You can exclude whole indicators from discovery by unchecking them.';
$string['indicator_helper_population_size'] = 'Population size';
$string['indicator_helper_population_size_help'] = 'Number of "individuals" in the genetic algorithm population. Each individual represents a complete set of settings, so set this number high enough so that the population contains a good variety of individuals.';
$string['indicator_helper_generations'] = 'Generations';
$string['indicator_helper_generations_help'] = 'Number of generations that the genetic algorithm will work through. The more generations, the more time that "evolution" has to improve the population. However, too many generations may drive evolution the wrong way.';
$string['indicator_helper_rundiscovery'] = 'Run parameter discovery';
$string['indicator_helper_rundiscovery_help'] = 'Runs the genetic algorithm asynchronously on this page. A progress and status panel will appear.';
$string['indicator_helper_runcorrelate'] = 'Draw correlation graph';
$string['indicator_helper_correlationoutputindicator'] = 'Correlation coefficient {$a->corr} (closer to -1 is better) for indicator {$a->name}.';
$string['indicator_helper_correlationoutput'] = 'Correlation coefficient {$a} (closer to -1 is better).';
$string['indicator_helper_saved'] = 'Discovered settings have been saved.';
$string['indicator_helper_viewsettings'] = 'View settings.';
$string['indicator_helper_riskrating'] = 'Risk rating';

// Default snippet strings.
$string['defaultsnippetencouragement0'] = "I can see that you are trying hard but still struggling with the unit material and may need extra help.";
$string['defaultsnippetencouragement1'] = "We provide specialist assistance within this department that is designed to help you with your studies.";
$string['defaultsnippetencouragement2'] = "It seems that you are making good progress in this unit. We hope that you are enjoying learning new things. Keep up the good work.";
$string['defaultsnippetencouragement3'] = "There is an important assessment coming up, so I recommend you spend some time reviewing the unit materials in preparation.";
$string['defaultsnippetencouragement4'] = "If you need help, please get in touch with me to arrange a time where we can meet and work to help you with this unit.";
$string['defaultsnippetencouragement5'] = "Your academic success is important, and I would like to help you succeed in your studies. Please contact me for guidance.";
$string['defaultsnippetencouragement6'] = "Please get in touch with your tutor to organise a consultation so that you can work together on the unit materials.";
$string['defaultsnippetencouragement7'] = "Creating a student study group can be a great way to study for this unit.";
$string['defaultsnippetencouragement8'] = "Taking notes and reviewing them right after class will help you remember information presented.";
$string['defaultsnippetencouragement9'] = "Remember to keep accessing and reviewing the resources available for this unit and continue to make your own notes.";
$string['defaultsnippetencouragement10'] = "Please don’t hesitate to ask for additional help with this unit. If you are struggling with fully understanding the unit materials or concepts, it is crucial that you seek help as soon as possible.";
$string['defaultsnippetencouragement11'] = "We are here to help with your studies and make sure that you succeed. Please get in contact with your teaching staff for guidance.";

