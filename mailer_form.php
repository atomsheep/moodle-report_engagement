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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

require_once($CFG->libdir.'/formslib.php');

class report_engagement_mailer_form extends moodleform {

    protected function definition() {
        global $CFG, $OUTPUT, $COURSE, $DB, $PAGE, $USER;

        $mform =& $this->_form;
		
		$patterns = $this->_customdata['patterns'];
		$subsets = $this->_customdata['subsets'];
		$action = $this->_customdata['action'];
		
		$jstable = $this->_customdata['jstable'];
		$js_columns = $this->_customdata['js_columns'];		
		$chk_column_headers = $this->_customdata['chk_column_headers'];
		$defaultsort = $this->_customdata['defaultsort'];
		$html_num_fmt_cols = $this->_customdata['html_num_fmt_cols'];
		
		$friendlypatterns = $this->_customdata['friendlypatterns'];
		if ($action == 'composing') {
			$defaultmessages = $this->_customdata['defaultmessages'];
			$message_variables = $this->_customdata['message_variables'];
			$suggested_snippets = $this->_customdata['suggested_snippets'];
			$other_snippets = $this->_customdata['other_snippets'];
			$my_saved_messages = $this->_customdata['my_saved_messages'];
			$my_saved_messages_data = $this->_customdata['my_saved_messages_data'];
			$capable_users = $this->_customdata['capable_users'];
		} else if ($action == 'previewing') {
			$message_previews = $this->_customdata['message_previews'];
			$sender_previews = $this->_customdata['sender_previews'];
			$replyto_previews = $this->_customdata['replyto_previews'];
			$cc_previews = $this->_customdata['cc_previews'];
			$message_previews_by_user = $this->_customdata['message_previews_by_user'];
		} else if ($action == 'sending') {
			$message_send_results = $this->_customdata['message_send_results'];
		}
		
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('html', "<input type='hidden' name='action' value='$action' />"); // mform can't be trusted
		
		foreach ($patterns as $pattern => $userids) {
			$tablehtml = '';
			if ($subsets) {
				$mform->addElement('header', "header_$pattern", get_string('message_header_groupwith', 'report_engagement').$friendlypatterns[$pattern]->human);
				if ($action == 'previewing' || $action == 'sending') {
					$mform->setExpanded("header_$pattern");
				}
			}
			// Display table data
			$tablehtml .= html_writer::start_tag('table', array('id'=>"data_table_$pattern", 'class'=>'row-border display compact'));
				$tablehtml .= html_writer::start_tag('thead');
					$tablehtml .= html_writer::start_tag('tr');
							$tablehtml .= html_writer::start_tag('th', array('colspan'=>count($chk_column_headers)));
								$tablehtml .= get_string('report_header_selectmessagetypes', 'report_engagement');
							$tablehtml .= html_writer::end_tag('th');
							$tablehtml .= html_writer::start_tag('th', array('colspan'=>(count($chk_column_headers) + 3)));
								$tablehtml .= get_string('report_header_data', 'report_engagement');
							$tablehtml .= html_writer::end_tag('th');
					$tablehtml .= html_writer::end_tag('tr');
					$tablehtml .= html_writer::start_tag('tr');
						foreach ($js_columns as $js_column) {
							$tablehtml .= html_writer::start_tag('th');
								$tablehtml .= $js_column;
							$tablehtml .= html_writer::end_tag('th');
						}
					$tablehtml .= html_writer::end_tag('tr');
				$tablehtml .= html_writer::end_tag('thead');
				$tablehtml .= html_writer::start_tag('tbody');
					foreach ($jstable as $row) {
						if (($subsets && in_array($row['_userid'], $userids)) || !$subsets) {
							$tablehtml .= html_writer::start_tag('tr');
								foreach ($row as $cellkey => $cellvalue) {
									if ($cellkey != '_userid') { // do not show moodle userid
										$tablehtml .= html_writer::start_tag('td');
											$tablehtml .= $cellvalue;
										$tablehtml .= html_writer::end_tag('td');
									}
								}
							$tablehtml .= html_writer::end_tag('tr');
						}
					}
				$tablehtml .= html_writer::end_tag('tbody');
			$tablehtml .= html_writer::end_tag('table');
			$mform->addElement('html', $tablehtml);
			$toggles = array();
			$toggles[] =& $mform->createElement('checkbox', "toggle_details_$pattern", '', get_string('message_table_extradetails', 'report_engagement'));
			$mform->addGroup($toggles, "toggles_$pattern", get_string('message_table_showhide', 'report_engagement'), array(' '), false);
			$mform->addHelpButton("toggles_$pattern", 'message_table_showhide', 'report_engagement');
			$mform->addElement('html', "<br />");
			// Display options for each group
			if ($subsets && $action == 'composing') {
				// information
				$mform->addElement('static', '', get_string('message_will_be_sent_count', 'report_engagement'), count($userids)." ".(count($userids) > 1 ? get_string('student_plural', 'report_engagement') : get_string('student_singular', 'report_engagement'))." ".get_string('message_will_be_sent_count_above', 'report_engagement'));
				// message options
				//$mform->addElement('checkbox', "chk_actually_send_$pattern", "Send messages to this group"); 
				// - sender
				$mform->addElement('select', "sender_$pattern", get_string('message_sender', 'report_engagement'), $capable_users);
				$mform->addHelpButton("sender_$pattern", 'message_sender', 'report_engagement');
				// - replyto
				//$mform->addElement('select', "replyto_$pattern", get_string('message_replyto', 'report_engagement'), $capable_users);
				$mform->addElement('text', "replyto_$pattern", get_string('message_replyto', 'report_engagement'), array('size'=>50));
				$mform->addRule("replyto_$pattern", get_string('message_replyto_error_email', 'report_engagement'), 'email', null, 'client');
				$mform->addHelpButton("replyto_$pattern", 'message_replyto', 'report_engagement');
				// - CC
				$mform->addElement('text', "cc_$pattern", get_string('message_cc', 'report_engagement'), array('size'=>50));
				$mform->addRule("cc_$pattern", get_string('message_cc_error_email', 'report_engagement'), 'email', null, 'client');
				$mform->addHelpButton("cc_$pattern", 'message_cc', 'report_engagement');
				// - message subject
				$mform->addElement('text', "subject_$pattern", get_string('message_subject', 'report_engagement'), array('size'=>50));
				$mform->addHelpButton("subject_$pattern", 'message_subject', 'report_engagement');
				$mform->setType("subject_$pattern", PARAM_TEXT);
				// - message body
				//$mform->addElement('editor', "message_$pattern", "Message body"); 
				$mform->addElement('textarea', "message_$pattern", get_string('message_body', 'report_engagement'), array('rows'=>12, 'cols'=>80));
				$mform->addHelpButton("message_$pattern", 'message_body', 'report_engagement');
				$mform->setType("message_$pattern", PARAM_RAW);
				// - message components/snippets
				$msgcom = html_writer::start_tag('div', array('class'=>'fitem'));
					$msgcom .= html_writer::start_tag('div', array('class'=>'fitemtitle'));
						$msgcom .= html_writer::tag('label', get_string('message_snippets', 'report_engagement'));
						$msgcom .= $OUTPUT->help_icon('message_snippets', 'report_engagement');
					$msgcom .= html_writer::end_tag('div');
					$msgcom .= html_writer::start_tag('div', array('class'=>'felement'));
						$msgcom .= html_writer::select(array('variables'=>get_string('message_snippets_variables', 'report_engagement'), 'suggested'=>get_string('message_snippets_suggested', 'report_engagement'), 
									'other'=>get_string('message_snippets_other', 'report_engagement'), 'my'=>get_string('message_snippets_my', 'report_engagement')), 
									"snippet_type_select_$pattern", '', array(''=>'choosedots'), array('data-pattern'=>$pattern));
						//$msgcom .= $OUTPUT->help_icon($helpicon);
						$msgcom .= html_writer::start_tag('div', array('class'=>'snippet_selector', 'id'=>"snippet_selector_$pattern"));
							// Show variables
							$msgcom .= html_writer::start_tag('div', array('id'=>"snippet_selection_variables_$pattern"));
								$msgcom .= html_writer::start_tag('ul', array('class'=>'snippet_list'));
									foreach ($message_variables as $variable => $label) {
										$msgcom .= html_writer::tag('li', $label, array('data-content'=>json_encode($variable), 'data-pattern'=>"$pattern", 'class'=>'snippet_item'));
									}
								$msgcom .= html_writer::end_tag('ul');
							$msgcom .= html_writer::end_tag('div');
							// Show suggested snippets
							$msgcom .= html_writer::start_tag('div', array('id'=>"snippet_selection_suggested_$pattern"));
								$msgcom .= html_writer::start_tag('ul', array('class'=>'snippet_list'));
									foreach ($suggested_snippets[$pattern] as $suggested_snippet) {
										foreach ($suggested_snippet as $category => $snippets) {
											$msgcom .= html_writer::start_tag('li', array('class'=>'snippet_category'));
											$msgcom .= html_writer::tag('div', $category);
											$msgcom .= html_writer::start_tag('ul', array('class'=>'snippet_list'));
											foreach ($snippets as $variable => $label) {
												$msgcom .= html_writer::tag('li', $label, array('data-content'=>json_encode($label), 'data-pattern'=>"$pattern", 'class'=>'snippet_item'));
											}
											$msgcom .= html_writer::end_tag('ul');
											$msgcom .= html_writer::end_tag('li');
										}
									}
								$msgcom .= html_writer::end_tag('ul');
							$msgcom .= html_writer::end_tag('div');
							// Show other snippets
							$msgcom .= html_writer::start_tag('div', array('id'=>"snippet_selection_other_$pattern"));
								$msgcom .= html_writer::start_tag('ul', array('class'=>'snippet_list'));
									foreach ($other_snippets[$pattern] as $other_snippet) {
										foreach ($other_snippet as $category => $snippets) {
											$msgcom .= html_writer::start_tag('li', array('class'=>'snippet_category'));
											$msgcom .= html_writer::tag('div', $category);
											$msgcom .= html_writer::start_tag('ul', array('class'=>'snippet_list'));
											foreach ($snippets as $variable => $label) {
												$msgcom .= html_writer::tag('li', $label, array('data-content'=>json_encode($label), 'data-pattern'=>"$pattern", 'class'=>'snippet_item'));
											}
											$msgcom .= html_writer::end_tag('ul');
											$msgcom .= html_writer::end_tag('li');
										}
									}
								$msgcom .= html_writer::end_tag('ul');
							$msgcom .= html_writer::end_tag('div');
							// Show my saved messages
							$my_saved_message_text = json_decode($my_saved_messages_data);
							$msgcom .= html_writer::start_tag('div', array('id'=>"snippet_selection_my_$pattern"));
								$msgcom .= html_writer::start_tag('ul', array('class'=>'snippet_list'));
									foreach ($my_saved_messages[$pattern] as $variable => $label) {
										$msgcom .= html_writer::start_tag('li', array('class'=>'snippet_category'));
										$msgcom .= html_writer::tag('div', $label);
										$msgcom .= html_writer::start_tag('ul', array('class'=>'snippet_list'));
											$msgcom .= html_writer::tag('li', $my_saved_message_text->{$variable}, array('data-content'=>json_encode($my_saved_message_text->{$variable}), 'data-pattern'=>"$pattern", 'class'=>'snippet_item'));
										$msgcom .= html_writer::end_tag('ul');
										$msgcom .= html_writer::end_tag('li');
									}
								$msgcom .= html_writer::end_tag('ul');
							$msgcom .= html_writer::end_tag('div');
						$msgcom .= html_writer::end_tag('div');
					$msgcom .= html_writer::end_tag('div');
				$msgcom .= html_writer::end_tag('div');
				$mform->addElement('html', $msgcom);
				// - save my messages options
				$savemy = array();
				$savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_chk', 'report_engagement'));
				$savemy[] =& $mform->createElement('checkbox', "chk_savemy_$pattern");
				$savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_description', 'report_engagement'));
				$savemy[] =& $mform->createElement('text', "txt_savemy_$pattern", '', array('size'=>30));
				$mform->addGroup($savemy, "savemy_$pattern", get_string('message_savemy', 'report_engagement'), array(' '), false);
				$mform->addHelpButton("savemy_$pattern", 'message_savemy', 'report_engagement');
				$mform->setType("txt_savemy_$pattern", PARAM_TEXT);
				$mform->disabledIf("txt_savemy_$pattern", "chk_savemy_$pattern");
				/*
				// disable unless actually sending
				$mform->disabledIf("message_$pattern", "chk_actually_send_$pattern"); // Moodle bug - editor does not disable - not much point including this block unless this works
				$mform->disabledIf("select_message_$pattern", "chk_actually_send_$pattern");
				$mform->disabledIf("chk_savemy_$pattern", "chk_actually_send_$pattern");
				*/
			} else if ($subsets && $action == 'previewing') {
				// Preview nav buttons
				$preview_nav = array();
				$preview_nav[] =& $mform->createElement('button', "button_preview_nav_back_$pattern", get_string('message_preview_button_back', 'report_engagement'), array('data-pattern'=>"$pattern", 'data-direction'=>'back'));
				$preview_nav[] =& $mform->createElement('button', "button_preview_nav_forward_$pattern", get_string('message_preview_button_forward', 'report_engagement'), array('data-pattern'=>"$pattern", 'data-direction'=>'forward'));
				$mform->addGroup($preview_nav, "preview_nav_$pattern", get_string('message_preview_buttons', 'report_engagement'), array(' '), false);
				$mform->addHelpButton("preview_nav_$pattern", 'message_preview_buttons', 'report_engagement');
				// Sender and replyto and cc
				$mform->addElement('static', '', get_string('message_sender', 'report_engagement'), reset($sender_previews[$pattern]));
				$mform->addElement('hidden', "sender_$pattern", key($sender_previews[$pattern]));
				$mform->addElement('static', '', get_string('message_replyto', 'report_engagement'), reset($replyto_previews[$pattern]));
				$mform->addElement('hidden', "replyto_$pattern", key($replyto_previews[$pattern]));
				$mform->addElement('static', '', get_string('message_cc', 'report_engagement'), reset($cc_previews[$pattern]));
				$mform->addElement('hidden', "cc_$pattern", key($cc_previews[$pattern]));
				// Encoded message subject and body
				$mform->addElement('hidden', "subject_encoded_$pattern", $message_previews[$pattern]->subject_encoded);
				$mform->addElement('hidden', "message_encoded_$pattern", $message_previews[$pattern]->message_encoded);
				// Message subject and body and recipient
				$mform->addElement('html', html_writer::start_tag('div', array('id'=>"message_preview_container_$pattern")));
				foreach ($message_previews_by_user[$pattern] as $userid => $message_preview) {
					if (array_keys($message_previews_by_user[$pattern])[0] == $userid) {
						$class = 'first message_preview_current';
					} else if (array_keys($message_previews_by_user[$pattern])[count($message_previews_by_user[$pattern]) - 1] == $userid) {
						$class = 'last message_preview_hidden';
					} else {
						$class = 'message_preview_hidden';
					}
					$mform->addElement('html', html_writer::start_tag('div', array('class'=>$class, 'data-userid'=>"$userid")));
						$mform->addElement('static', "recipient_preview_$pattern", get_string('message_recipient_preview', 'report_engagement'), $message_preview->recipient->email);
						$mform->addElement('static', "subject_preview_$pattern", get_string('message_subject_preview', 'report_engagement'), $message_preview->subject);
						$mform->addHelpButton("subject_preview_$pattern", 'message_subject_preview', 'report_engagement');
						$mform->addElement('textarea', "message_preview_$pattern", get_string('message_body_preview', 'report_engagement'), array('rows'=>12, 'cols'=>80, 'readonly'=>'readonly'))->setValue($message_preview->message);
						$mform->addHelpButton("message_preview_$pattern", 'message_body_preview', 'report_engagement');
					$mform->addElement('html', html_writer::end_tag('div'));
				}
				$mform->addElement('html', html_writer::end_tag('div'));
				// Action button
				$mform->addElement('button', "button_back_$pattern", get_string('message_go_back_edit', 'report_engagement'), array('onclick'=>'go_back_to_composing()'));
				$mform->addHelpButton("button_back_$pattern", 'message_go_back_edit', 'report_engagement');
				// Re-show my messages settings // TODO: refactor for DRY
				$savemy = array();
				$savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_chk', 'report_engagement'));
				$savemy[] =& $mform->createElement('checkbox', "chk_savemy_$pattern");
				$savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_description', 'report_engagement'));
				$savemy[] =& $mform->createElement('text', "txt_savemy_$pattern", '', array('size'=>30));
				$mform->addGroup($savemy, "savemy_$pattern", get_string('message_savemy', 'report_engagement'), array(' '), false);
				$mform->addHelpButton("savemy_$pattern", 'message_savemy', 'report_engagement');
				$mform->setType("txt_savemy_$pattern", PARAM_TEXT);
				$mform->disabledIf("txt_savemy_$pattern", "chk_savemy_$pattern");
			} else if ($subsets && $action == 'sending') {
				$send_results = array();
				$mform->addElement('html', html_writer::tag('div', get_string('message_sent_notification_header', 'report_engagement')));
				foreach ($message_send_results[$pattern] as $userid => $result) {
					$mform->addElement('html', html_writer::tag('div', get_string('message_sent_notification_recipient', 'report_engagement', $result->recipient).' '.($result->result ? get_string('message_sent_notification_success', 'report_engagement', $result->message) : get_string('message_sent_notification_failed', 'report_engagement', $result->message))));
				}
				$mform->addElement('html', html_writer::tag('br'));
			} else {
				$check_alls = array();
				foreach ($chk_column_headers as $name) {
					$check_alls[] =& $mform->createElement('button', "check_all_$name", ucfirst($name), array('onclick'=>"check_all('$name')"));
				}
				$mform->addGroup($check_alls, 'check_all', get_string('message_check_all', 'report_engagement'), array(' '), false);
				$mform->addHelpButton("check_all", 'message_check_all', 'report_engagement');
			}
			// Script to prepare DataTable
			$button_mailer_label_csv = get_string('button_mailer_label_csv', 'report_engagement');
			$button_mailer_fname_csv = get_string('button_mailer_fname_csv', 'report_engagement');
			$js_sub = "
				<script>
					$(document).ready(function(){
						$('#data_table_$pattern').DataTable({
							'order':$defaultsort,
							'columnDefs': [
								{ 'type':'num-html', 'targets':$html_num_fmt_cols }
							],
							'lengthMenu':[ [5, 10, 25, 50, 100, -1] , [5, 10, 25, 50, 100, 'All'] ],
							'dom': 'Blfrtip',
							'buttons': [ {'extend':'csvHtml5', 'text':'$button_mailer_label_csv', 'title':'$button_mailer_fname_csv'} ]
						}).on('draw', function(){
							$('input:checkbox[name^=toggle_details_$pattern]').triggerHandler('click');
						});
					});
				</script>
			";
			echo($js_sub);
		}
		// overall buttons
		if (!$subsets) {
			$mform->addElement('header', 'header_compose', get_string('message_header_compose', 'report_engagement'));
			$mform->addElement('submit', 'submit_compose', get_string('message_submit_compose', 'report_engagement'));
		} else if ($subsets && $action == 'composing') {
			$mform->addElement('header', 'header_preview', get_string('message_header_preview', 'report_engagement'));
			$mform->addElement('submit', 'submit_preview', get_string('message_submit_preview', 'report_engagement'));
		} else if ($subsets && $action == 'previewing') {
			$mform->addElement('header', 'header_send', get_string('message_header_send', 'report_engagement'));
			$mform->addElement('button', 'button_back', get_string('message_go_back_edit_plural', 'report_engagement'), array('onclick'=>'go_back_to_composing()'));
			$mform->addHelpButton('button_back', 'message_go_back_edit_plural', 'report_engagement');
			$mform->addElement('submit', 'submit_send', get_string('message_submit_send', 'report_engagement'));
		}
		// scripts
		if (!$subsets) {
			$js = "
				<script>
					function check_all(name) {	
						$('#data_table_$pattern').DataTable().cells( {page:'current'} ).nodes().to$().find('input[type=checkbox][name^=chk_indicator_' + name + ']').each(function(){
							$(this).prop('checked',function(i,val){return !val;});
						});
					};
					$(function(){
						$('form[class*=mform]').on('submit', function (event) {	
							$('#data_table_$pattern').DataTable().cells().nodes().to$().find('input:checked').each(function(){
								$(this).hide().detach().appendTo('form[class*=mform]');
							});
						})
					});
				</script>
			";
			echo($js);
		} else if ($subsets && $action == 'composing') {
			$js = "
				<script>
					function insertTextAtCursor(text) {
						// http://stackoverflow.com/questions/2920150/insert-text-at-cursor-in-a-content-editable-div
						var sel, range, html;
						console.log(window.getSelection());
						if (window.getSelection) {
							sel = window.getSelection();
							try {
								editable = $(sel)[0].focusNode.parentNode.hasAttribute('contenteditable') || $(sel)[0].baseNode.hasAttribute('contenteditable');
								if (sel.getRangeAt && sel.rangeCount && editable) {
									range = sel.getRangeAt(0);
									range.deleteContents();
									range.insertNode( document.createTextNode(text) );
								}
							} catch(err) {
								console.log(err);
							}
						} else if (document.selection && document.selection.createRange) {
							document.selection.createRange().text = text;
						}
					}				
					var snippet_data;
					var my_messages_data = $my_saved_messages_data;
					$(document).ready(function(){
						$.getJSON('lang/en/data.json.txt')
							.done(function (data) {
								console.log(data);
								snippet_data = data;
						});
						$('select[name^=snippet_type_select_]').on('change', function(){
							var pattern = $(this).prop('name').replace('snippet_type_select_', '');
							var snippet_type = $(this).val();
							if (snippet_type) {
								$('div[id=snippet_selector_' + pattern + ']').show();
								$('div[id^=snippet_selection_][id$=_' + pattern + ']').hide();
								$('div[id=snippet_selection_' + snippet_type + '_' + pattern + ']').show();
							} else {
								$('div[id=snippet_selector_' + pattern + ']').hide();
							}
						});
						$('textarea[name^=message_]').each(function(){
							$(this).setSelection($(this).val().length);
						});
						$('li[data-content][data-pattern]').on('click', function(){
							var pattern = $(this).attr('data-pattern');
							var ta = $('textarea[name=message_' + pattern + ']');
							var content = JSON.parse($(this).attr('data-content'));
							ta.insertText('\\n' + content + '\\n', ta[0].selectionStart);
						});
					});
				</script>
			";
			echo($js);
		}
		if ($subsets) {
			$js = "
				<script>
					$(document).ready(function(){
						$('form[class*=mform]').on('submit', function (event) {	
							$('table[id^=data_table_]').each(function(){
								$(this).DataTable().cells().nodes().to$().find('input:checked').each(function(){
									$(this).removeAttr('disabled').hide().detach().appendTo('form[class*=mform]');
								});
							});
						})
						$('input:button[name^=button_preview_nav_]').on('click', function(){
							var pattern = $(this).attr('data-pattern');
							var direction = $(this).attr('data-direction');
							var current_preview = $('div[id=message_preview_container_' + pattern + '] .message_preview_current');
							if (direction == 'back' && !current_preview.hasClass('first')) {
								current_preview.removeClass('message_preview_current').hide().prev().show().addClass('message_preview_current');
							} else if (direction == 'forward' && !current_preview.hasClass('last')) {
								current_preview.removeClass('message_preview_current').hide().next().show().addClass('message_preview_current');
							}
						});
					});
					function go_back_to_composing(){
						$('input[type=hidden][name=action]').val('viewing'); // pretend to come from viewing phase (first stage)
						$('form[class*=mform]').trigger('submit');
					}
				</script>
			";
			echo($js);
		}
		if ($subsets && ($action == 'composing' || $action == 'previewing')) {
			$js = "
				<script>
					$(document).ready(function(){
						$('input:checkbox[name^=chk_indicator_]').on('click', function(){
							checked = this.checked;
							console.log(checked);
							$('input:checkbox[name^=chk_indicator_][name$=_' + $(this).attr('data-userid') + ']').each(function(){
								$(this).prop('checked', checked);
							});
						});
					});
				</script>
			";
			echo($js);
		}
		$js_all = "
			<script>
				$(document).ready(function(){
					$('input:checkbox[name^=toggle_details_]').on('click', function(event) {
						var pattern = $(this).prop('name').replace('toggle_details_', '');
						if (this.checked) {
							$('table[id=data_table_' + pattern + '] div[class=report_engagement_detail]').show('fast').css('font-size', '0.67em').css('line-height', '100%');
						} else {
							$('table[id=data_table_' + pattern + '] div[class=report_engagement_detail]').hide('fast').css('font-size', '0.67em').css('line-height', '100%');
						}
					});
				});
			</script>
		";
		echo($js_all);
    }

    // Form verification.
    public function validation($data, $files) {
		global $CFG;
		
        $mform =& $this->_form;

        $errors = array();

        return $errors;
    }
}
