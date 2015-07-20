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
$string['coursereport'] = 'Engagement for course: {$a->course}';
$string['coursereport_heading'] = 'Engagement for course: {$a->course}';
$string['indicator'] = 'Indicator';
$string['indicator_help'] = 'In the textboxes, enter the percentage contribution (whole number between 0-100) of the risk rating from each indicator to the total risk rating. The totals in this section must add to 100.';

$string['manageindicators'] = 'Manage indicators';
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
$string['message_replyto_help'] = 'The default person and email address that receives replies when a student replies to a message.';
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
$string['message_table_showhide'] = 'Show/hide';
$string['message_table_showhide_help'] = 'Toggle this checkbox to show or hide extra details in the table.';
$string['message_header_groupwith'] = 'Group with: ';
$string['message_sent_notification_header'] = 'Message sent to:';
$string['message_sent_notification_recipient'] = '{$a->email}';
$string['message_sent_notification_success'] = '[success {$a}]';
$string['message_sent_notification_failed'] = '[FAILED {$a}]';

$string['report_readposts'] = '{$a} read posts';
$string['report_posted'] = '{$a} posted';
$string['report_login_dayssince'] = ' days since last login';
$string['report_login_perweek'] = ' logins per week';
$string['report_assessment_overdue'] = '{$a} overdue';
$string['report_assessment_submitted'] = '{$a} submitted';
$string['report_assessment_overduelate'] = '{$a->o} overdue, average {$a->v} days late';
$string['report_gradebook_triggered'] = '{$a} triggered';
$string['report_gradebook_nottriggered'] = '{$a} not triggered';
$string['report_gradebook_percentrisk'] = '{$a}% risk';
$string['report_messagelog_daysago'] = '{$a} days ago';
$string['report_totalrisk'] = 'Total risk';
$string['report_messagessent'] = 'Msgs<br />sent';
$string['report_header_selectmessagetypes'] = 'Select message type(s)';
$string['report_header_data'] = 'Data';

$string['message_default_greeting'] = 'Dear {#FIRSTNAME#},'.chr(13).chr(13);
$string['message_default_closing'] = 'Kind regards';

$string['message_variables_firstname'] = 'First name';
$string['message_variables_lastname'] = 'Last name';
$string['message_variables_fullname'] = 'Full name';
