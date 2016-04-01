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
 * @copyright  2015-2016 Macquarie University
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
        
        $tabledata = $this->_customdata['table_data'];
        $columnheaders = $this->_customdata['column_headers'];        
        $chkcolumnheaders = $this->_customdata['chk_column_headers'];
        $heatmappablecolumns = json_encode($this->_customdata['heatmappable_columns']);
        $heatmappablecolumnsdirections = json_encode($this->_customdata['heatmappable_columns_directions']);
        $displaydataraw = $this->_customdata['display_data_raw'];
        $defaultsort = $this->_customdata['defaultsort'];
        $htmlnumfmtcols = $this->_customdata['html_num_fmt_cols'];
        
        $hascapabilitysend = $this->_customdata['has_capability_send'];
        
        $friendlypatterns = $this->_customdata['friendlypatterns'];
        if ($action == 'composing') {
            $defaultmessages = $this->_customdata['defaultmessages'];
            $messagevariables = $this->_customdata['message_variables'];
            $suggestedsnippets = $this->_customdata['suggested_snippets'];
            $othersnippets = $this->_customdata['other_snippets'];
            $mysavedmessages = $this->_customdata['my_saved_messages'];
            $mysavedmessagesdata = $this->_customdata['my_saved_messages_data'];
            $capableusers = $this->_customdata['capable_users'];
        } else if ($action == 'previewing') {
            $messagepreviews = $this->_customdata['message_previews'];
            $senderpreviews = $this->_customdata['sender_previews'];
            $replytopreviews = $this->_customdata['replyto_previews'];
            /* ForFuture: $cc_previews = $this->_customdata['cc_previews']; */
            $messagepreviewsbyuser = $this->_customdata['message_previews_by_user'];
        } else if ($action == 'sending') {
            $messagesendresults = $this->_customdata['message_send_results'];
        }
        
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('html', "<input type='hidden' name='action' value='$action' />"); // mform can't be trusted
        
        foreach ($patterns as $pattern => $userids) {
            $tablehtml = '';
            if ($subsets) {
                $mform->addElement('header', "header_$pattern", get_string('message_header_groupwith', 'report_engagement').
                    $friendlypatterns[$pattern]->human);
                if ($action == 'previewing' || $action == 'sending') {
                    $mform->setExpanded("header_$pattern");
                }
            }
            // Display table data.
            $tablehtml .= html_writer::start_tag('table', array('id' => "data_table_$pattern", 
                                                             'class' => 'row-border display compact'));
                // Table header.
                $tablehtml .= html_writer::start_tag('thead');
                    // First row - summary headers.
                    $tablehtml .= html_writer::start_tag('tr');
                        // Checkboxes.
                        $tablehtml .= html_writer::start_tag('th', array('colspan' => count($chkcolumnheaders)));
                            $tablehtml .= get_string('report_header_selectmessagetypes', 'report_engagement');
                        $tablehtml .= html_writer::end_tag('th');
                        // User info.
                        $tablehtml .= html_writer::start_tag('th', array('colspan' => (2)));
                            $tablehtml .= get_string('report_header_userinfo', 'report_engagement');
                        $tablehtml .= html_writer::end_tag('th');
                        // User data.
                        foreach ($displaydataraw as $name => $raw) {
                            $tablehtml .= html_writer::start_tag('th', array('colspan' => (count($raw))));
                                $tablehtml .= ucfirst($name) . " " . get_string('report_header_data', 'report_engagement');
                            $tablehtml .= html_writer::end_tag('th');
                        }
                        // Totals.
                        $tablehtml .= html_writer::start_tag('th', array('colspan' => (2)));
                            $tablehtml .= get_string('report_header_totals', 'report_engagement');
                        $tablehtml .= html_writer::end_tag('th');
                    $tablehtml .= html_writer::end_tag('tr');
                    // Second row - individual column headers.
                    $tablehtml .= html_writer::start_tag('tr');
                        foreach ($columnheaders as $i => $columnheader) {
                            if (array_key_exists('hide', $columnheader) && $columnheader['hide']) {
                                $tablehtml .= html_writer::start_tag('th', array('style' => 'display:none;'));
                            } else {
                                $tablehtml .= html_writer::start_tag('th');
                            }
                                $tablehtml .= $columnheader['html'];
                                if (!array_key_exists('chk', $columnheader) && array_key_exists('filterable', $columnheader) && $columnheader['filterable']) {
                                    $tablehtml .= '<br /><input type="text" size="6" placeholder="'.
                                        get_string('message_table_filter_column', 'report_engagement') . '" />';
                                }
                            $tablehtml .= html_writer::end_tag('th');
                        }
                    $tablehtml .= html_writer::end_tag('tr');
                $tablehtml .= html_writer::end_tag('thead');
                // Table body.
                $tablehtml .= html_writer::start_tag('tbody');
                    foreach ($tabledata as $row) {
                        if (($subsets && in_array($row['userid'], $userids)) || !$subsets) {
                            $tablehtml .= html_writer::start_tag('tr');
                                foreach ($row['data'] as $c => $cellvalue) {
                                    if (array_key_exists('hide', $columnheaders[$c]) && $columnheaders[$c]['hide']) {
                                        $tablehtml .= html_writer::start_tag('td', array('style' => 'display:none;'));
                                    } else {
                                        $tablehtml .= html_writer::start_tag('td');
                                    }
                                        $tablehtml .= $cellvalue;
                                    $tablehtml .= html_writer::end_tag('td');
                                }
                            $tablehtml .= html_writer::end_tag('tr');
                        }
                    }
                $tablehtml .= html_writer::end_tag('tbody');
            $tablehtml .= html_writer::end_tag('table');
            $mform->addElement('html', $tablehtml);
            // Toggles.
            // Toggles: Details.
            $mform->addElement('checkbox', "toggle_details_$pattern", 
                get_string('message_table_extradetails', 'report_engagement'), 
                get_string('message_table_show_extradetails_checkbox', 'report_engagement'));
            $mform->addHelpButton("toggle_details_$pattern", 'message_table_extradetails', 'report_engagement');
            // Toggles: Heatmap.
            $mform->addElement('checkbox', "toggle_heatmap_$pattern", 
                get_string('message_table_heatmap', 'report_engagement'), 
                get_string('message_table_show_heatmap_checkbox', 'report_engagement'));
            $mform->addHelpButton("toggle_heatmap_$pattern", 'message_table_heatmap', 'report_engagement');
            // Display options for each group.
            if ($subsets && $action == 'composing') {
                // Information.
                $mform->addElement('static', '', 
                    get_string('message_will_be_sent_count', 'report_engagement'), 
                    count($userids)." ".(count($userids) > 1 ? get_string('student_plural', 'report_engagement') : get_string('student_singular', 'report_engagement'))." ".get_string('message_will_be_sent_count_above', 'report_engagement'));
                // Information: Sender.
                $mform->addElement('select', "sender_$pattern", get_string('message_sender', 'report_engagement'), $capableusers);
                $mform->addHelpButton("sender_$pattern", 'message_sender', 'report_engagement');
                // Information: Replyto.
                $mform->addElement('text', "replyto_$pattern", get_string('message_replyto', 'report_engagement'), array('size' => 50));
                $mform->setType("replyto_$pattern", PARAM_TEXT);
                $mform->addRule("replyto_$pattern", get_string('message_replyto_error_email', 'report_engagement'), 'email', null, 'client');
                $mform->addHelpButton("replyto_$pattern", 'message_replyto', 'report_engagement');
                /*// Information: CC.
                $mform->addElement('text', "cc_$pattern", get_string('message_cc', 'report_engagement'), array('size' => 50));
                $mform->addRule("cc_$pattern", get_string('message_cc_error_email', 'report_engagement'), 'email', null, 'client');
                $mform->addHelpButton("cc_$pattern", 'message_cc', 'report_engagement'); */
                // Information: Message subject.
                $mform->addElement('text', "subject_$pattern", get_string('message_subject', 'report_engagement'), array('size' => 50));
                $mform->addHelpButton("subject_$pattern", 'message_subject', 'report_engagement');
                $mform->setType("subject_$pattern", PARAM_TEXT);
                // Information: Message body.
                $mform->addElement('textarea', "message_$pattern", get_string('message_body', 'report_engagement'), array('rows' => 12, 'cols' => 80));
                $mform->addHelpButton("message_$pattern", 'message_body', 'report_engagement');
                $mform->setType("message_$pattern", PARAM_RAW);
                // Information: Message components/snippets.
                $msgcom = html_writer::start_tag('div', array('class' => 'fitem'));
                    $msgcom .= html_writer::start_tag('div', array('class' => 'fitemtitle'));
                        $msgcom .= html_writer::tag('label', get_string('message_snippets', 'report_engagement'));
                        $msgcom .= $OUTPUT->help_icon('message_snippets', 'report_engagement');
                    $msgcom .= html_writer::end_tag('div');
                    $msgcom .= html_writer::start_tag('div', array('class' => 'felement'));
                        $msgcom .= html_writer::select(array('variables' => get_string('message_snippets_variables', 'report_engagement'), 'suggested' => get_string('message_snippets_suggested', 'report_engagement'), 
                                    'other'=>get_string('message_snippets_other', 'report_engagement'), 'my' => get_string('message_snippets_my', 'report_engagement')), 
                                    "snippet_type_select_$pattern", '', array('' => 'choosedots'), array('data-pattern' => $pattern));
                        $msgcom .= html_writer::start_tag('div', array('class' => 'snippet_selector', 'id' => "snippet_selector_$pattern"));
                            // Show variables.
                            $msgcom .= html_writer::start_tag('div', array('id' => "snippet_selection_variables_$pattern"));
                                $msgcom .= html_writer::start_tag('ul', array('class' => 'snippet_list'));
                                    foreach ($messagevariables as $variable => $label) {
                                        $msgcom .= html_writer::tag('li', $label, array('data-content' => json_encode($variable), 'data-pattern' => "$pattern", 'class' => 'snippet_item'));
                                    }
                                $msgcom .= html_writer::end_tag('ul');
                            $msgcom .= html_writer::end_tag('div');
                            // Show suggested snippets.
                            $msgcom .= html_writer::start_tag('div', array('id' => "snippet_selection_suggested_$pattern"));
                                $msgcom .= html_writer::start_tag('ul', array('class' => 'snippet_list'));
                                    foreach ($suggestedsnippets[$pattern] as $suggestedsnippet) {
                                        foreach ($suggestedsnippet as $category => $snippets) {
                                            $msgcom .= html_writer::start_tag('li', array('class' => 'snippet_category'));
                                            $msgcom .= html_writer::tag('div', $category);
                                            $msgcom .= html_writer::start_tag('ul', array('class' => 'snippet_list'));
                                            foreach ($snippets as $variable => $label) {
                                                $msgcom .= html_writer::tag('li', $label, 
                                                    array('data-content' => json_encode($label), 'data-pattern' => "$pattern", 'class' => 'snippet_item'));
                                            }
                                            $msgcom .= html_writer::end_tag('ul');
                                            $msgcom .= html_writer::end_tag('li');
                                        }
                                    }
                                $msgcom .= html_writer::end_tag('ul');
                            $msgcom .= html_writer::end_tag('div');
                            // Show other snippets.
                            $msgcom .= html_writer::start_tag('div', array('id' => "snippet_selection_other_$pattern"));
                                $msgcom .= html_writer::start_tag('ul', array('class' => 'snippet_list'));
                                    foreach ($othersnippets[$pattern] as $othersnippet) {
                                        foreach ($othersnippet as $category => $snippets) {
                                            if (count($snippets) > 0) {
                                                $msgcom .= html_writer::start_tag('li', array('class' => 'snippet_category'));
                                                $msgcom .= html_writer::tag('div', $category);
                                                $msgcom .= html_writer::start_tag('ul', array('class' => 'snippet_list'));
                                                foreach ($snippets as $variable => $label) {
                                                    $msgcom .= html_writer::tag('li', 
                                                        $label, 
                                                        array('data-content' => json_encode($label), 
                                                            'data-pattern' => "$pattern", 'class' => 'snippet_item'));
                                                }
                                                $msgcom .= html_writer::end_tag('ul');
                                                $msgcom .= html_writer::end_tag('li');
                                            }
                                        }
                                    }
                                $msgcom .= html_writer::end_tag('ul');
                            $msgcom .= html_writer::end_tag('div');
                            // Show my saved messages.
                            $mysavedmessagetext = json_decode($mysavedmessagesdata);
                            $msgcom .= html_writer::start_tag('div', array('id' => "snippet_selection_my_$pattern"));
                                $msgcom .= html_writer::start_tag('ul', array('class' => 'snippet_list'));
                                    foreach ($mysavedmessages[$pattern] as $variable => $label) {
                                        $msgcom .= html_writer::start_tag('li', array('class' => 'snippet_category'));
                                        $msgcom .= html_writer::tag('div', $label);
                                        $msgcom .= html_writer::start_tag('ul', array('class' => 'snippet_list'));
                                            $msgcom .= html_writer::tag('li', 
                                                $mysavedmessagetext->{$variable}, 
                                                array('data-content' => json_encode($mysavedmessagetext->{$variable}), 
                                                    'data-pattern' => "$pattern", 'class' => 'snippet_item'));
                                        $msgcom .= html_writer::end_tag('ul');
                                        $msgcom .= html_writer::end_tag('li');
                                    }
                                $msgcom .= html_writer::end_tag('ul');
                            $msgcom .= html_writer::end_tag('div');
                        $msgcom .= html_writer::end_tag('div');
                    $msgcom .= html_writer::end_tag('div');
                $msgcom .= html_writer::end_tag('div');
                $mform->addElement('html', $msgcom);
                // Information: Save my messages options.
                $savemy = array();
                $savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_chk', 'report_engagement'));
                $savemy[] =& $mform->createElement('checkbox', "chk_savemy_$pattern");
                $savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_description', 'report_engagement'));
                $savemy[] =& $mform->createElement('text', "txt_savemy_$pattern", '', array('size' => 30));
                $mform->addGroup($savemy, "savemy_$pattern", get_string('message_savemy', 'report_engagement'), array(' '), false);
                $mform->addHelpButton("savemy_$pattern", 'message_savemy', 'report_engagement');
                $mform->setType("txt_savemy_$pattern", PARAM_TEXT);
                $mform->disabledIf("txt_savemy_$pattern", "chk_savemy_$pattern");
            } else if ($subsets && $action == 'previewing') {
                // Preview nav buttons.
                $previewnav = array();
                $previewnav[] =& $mform->createElement('button', 
                    "button_preview_nav_back_$pattern", 
                    get_string('message_preview_button_back', 'report_engagement'), 
                    array('data-pattern' => "$pattern", 'data-direction' => 'back'));
                $previewnav[] =& $mform->createElement('button', 
                    "button_preview_nav_forward_$pattern", 
                    get_string('message_preview_button_forward', 'report_engagement'), 
                    array('data-pattern' => "$pattern", 'data-direction' => 'forward'));
                $mform->addGroup($previewnav, "preview_nav_$pattern", get_string('message_preview_buttons', 'report_engagement'), array(' '), false);
                $mform->addHelpButton("preview_nav_$pattern", 'message_preview_buttons', 'report_engagement');
                // Sender and replyto and cc.
                $mform->addElement('static', '', get_string('message_sender', 'report_engagement'), reset($senderpreviews[$pattern]));
                $mform->addElement('hidden', "sender_$pattern", key($senderpreviews[$pattern]));
                $mform->setType("sender_$pattern", PARAM_TEXT);
                $mform->addElement('static', '', get_string('message_replyto', 'report_engagement'), reset($replytopreviews[$pattern]));
                $mform->addElement('hidden', "replyto_$pattern", key($replytopreviews[$pattern]));
                $mform->setType("replyto_$pattern", PARAM_TEXT);
                /* ForFuture: $mform->addElement('static', '', get_string('message_cc', 'report_engagement'), reset($cc_previews[$pattern]));
                $mform->addElement('hidden', "cc_$pattern", key($cc_previews[$pattern]));*/
                // Encoded message subject and body.
                $mform->addElement('hidden', "subject_encoded_$pattern", $messagepreviews[$pattern]->subject_encoded);
                $mform->setType("subject_encoded_$pattern", PARAM_TEXT);
                $mform->addElement('hidden', "message_encoded_$pattern", $messagepreviews[$pattern]->message_encoded);
                $mform->setType("message_encoded_$pattern", PARAM_TEXT);
                // Message subject and body and recipient.
                $mform->addElement('html', html_writer::start_tag('div', array('id' => "message_preview_container_$pattern")));
                foreach ($messagepreviewsbyuser[$pattern] as $userid => $messagepreview) {
                    if (array_keys($messagepreviewsbyuser[$pattern])[0] == $userid) {
                        $class = 'first message_preview_current';
                    } else if (array_keys($messagepreviewsbyuser[$pattern])[count($messagepreviewsbyuser[$pattern]) - 1] == $userid) {
                        $class = 'last message_preview_hidden';
                    } else {
                        $class = 'message_preview_hidden';
                    }
                    $mform->addElement('html', html_writer::start_tag('div', array('class' => $class, 'data-userid' => "$userid")));
                        $mform->addElement('static', "recipient_preview_$pattern",
                            get_string('message_recipient_preview', 'report_engagement'),
                            $messagepreview->recipient->email);
                        $mform->addElement('static', "subject_preview_$pattern",
                            get_string('message_subject_preview', 'report_engagement'), 
                            $messagepreview->subject);
                        $mform->addHelpButton("subject_preview_$pattern", 'message_subject_preview', 'report_engagement');
                        $mform->addElement('textarea', "message_preview_$pattern",
                            get_string('message_body_preview', 'report_engagement'), 
                            array('rows' => 12, 'cols' => 80, 'readonly' => 'readonly'))->setValue($messagepreview->message);
                        $mform->addHelpButton("message_preview_$pattern", 'message_body_preview', 'report_engagement');
                    $mform->addElement('html', html_writer::end_tag('div'));
                }
                $mform->addElement('html', html_writer::end_tag('div'));
                // Action button.
                $mform->addElement('button', "button_back_$pattern", 
                    get_string('message_go_back_edit', 'report_engagement'), 
                    array('onclick' => 'go_back_to_composing()'));
                $mform->addHelpButton("button_back_$pattern", 'message_go_back_edit', 'report_engagement');
                // Re-show my messages settings. // TODO: refactor for DRY.
                $savemy = array();
                $savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_chk', 'report_engagement'));
                $savemy[] =& $mform->createElement('checkbox', "chk_savemy_$pattern");
                $savemy[] =& $mform->createElement('static', '', '', get_string('message_savemy_description', 'report_engagement'));
                $savemy[] =& $mform->createElement('text', "txt_savemy_$pattern", '', array('size' => 30));
                $mform->addGroup($savemy, "savemy_$pattern", get_string('message_savemy', 'report_engagement'), array(' '), false);
                $mform->addHelpButton("savemy_$pattern", 'message_savemy', 'report_engagement');
                $mform->setType("txt_savemy_$pattern", PARAM_TEXT);
                $mform->disabledIf("txt_savemy_$pattern", "chk_savemy_$pattern");
            } else if ($subsets && $action == 'sending') {
                $mform->addElement('html', html_writer::tag('div', get_string('message_sent_notification_header', 'report_engagement')));
                // TODO convert to table output format.
                foreach ($messagesendresults[$pattern] as $userid => $result) {
                    $mform->addElement('html', html_writer::tag('div', get_string('message_sent_notification_recipient', 'report_engagement', $result->recipient).
                        ' '.($result->result ? get_string('message_sent_notification_success', 'report_engagement', $result->message) : get_string('message_sent_notification_failed', 'report_engagement', $result->message))));
                }
                $mform->addElement('html', html_writer::tag('br'));
            } else {
                $checkalls = array();
                foreach ($chkcolumnheaders as $name) {
                    $checkalls[] =& $mform->createElement('button', "check_all_$name", ucfirst($name), array('onclick'=>"check_all('$name')"));
                }
                $mform->addGroup($checkalls, 'check_all', get_string('message_check_all', 'report_engagement'), array(' '), false);
                $mform->addHelpButton("check_all", 'message_check_all', 'report_engagement');
            }
            // Script to prepare DataTable.
            $buttonmailerlabelcsv = get_string('button_mailer_label_csv', 'report_engagement');
            $buttonmailerfnamecsv = get_string('button_mailer_fname_csv', 'report_engagement');
            $jssub = "
                <script>
                    $(document).ready(function(){
                        // Set up DataTable
                        datatables[$pattern] = $('#data_table_$pattern').DataTable({
                            'order':$defaultsort,
                            'columnDefs': [
                                { 'type':'num-html', 'targets':$htmlnumfmtcols }
                            ],
                            'lengthMenu':[ [5, 10, 25, 50, 100, -1] , [5, 10, 25, 50, 100, 'All'] ],
                            'dom': 'Blfrtip',
                            'scrollX': true,
                            'buttons': [ {'extend':'csvHtml5', 'text':'$buttonmailerlabelcsv', 'title':'$buttonmailerfnamecsv'} ]
                        }).on('draw', function(){
                            $('input:checkbox[name^=toggle_details_$pattern]').triggerHandler('click');
                        }).columns().every(function(){
                            var that = this;
                            $('input', this.header()).on('keyup change', function(){
                                if (that.search() !== this.value) {
                                    that.search(this.value).draw();
                                }
                            }).on('click', function(event){
                                event.preventDefault();
                                return false;
                            });
                        });
                        // Easy checkbox clicking
                        $('#data_table_$pattern').on('click', 'td', function(event){
                            if (event.target.type != 'checkbox') {                        
                                $(this).find('input:checkbox').trigger('click');
                            }
                        });
                    });
                </script>
            ";
            echo($jssub);
        } // End foreach ($patterns as $pattern => $userids).
        // Overall buttons.
        if (!$subsets) {
            $mform->addElement('header', 'header_compose', get_string('message_header_compose', 'report_engagement'));
            $mform->addElement('submit', 'submit_compose', get_string('message_submit_compose', 'report_engagement'));
        } else if ($subsets && $action == 'composing') {
            $mform->addElement('header', 'header_preview', get_string('message_header_preview', 'report_engagement'));
            $mform->addElement('submit', 'submit_preview', get_string('message_submit_preview', 'report_engagement'));
        } else if ($subsets && $action == 'previewing') {
            $mform->addElement('header', 'header_send', get_string('message_header_send', 'report_engagement'));
            $mform->addElement('button', 'button_back', get_string('message_go_back_edit_plural', 'report_engagement'), array('onclick' => 'go_back_to_composing()'));
            $mform->addHelpButton('button_back', 'message_go_back_edit_plural', 'report_engagement');
            if ($hascapabilitysend) {
                $mform->addElement('submit', 'submit_send', get_string('message_submit_send', 'report_engagement'));
            } else {
                $mform->addElement('static', 'no_permission', '', get_string('mailer_capability_nopermissions', 'report_engagement'));
            }
        }
        // Scripts.
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
                    var my_messages_data = $mysavedmessagesdata;
                    $(document).ready(function(){
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
        // Code for toggles.
        $jsall = "
            <script>
                $(document).ready(function(){
                    // Details show/hide toggle
                    $('input:checkbox[name^=toggle_details_]').on('click', function(event) {
                        var pattern = $(this).prop('name').replace('toggle_details_', '');
                        if (this.checked) {
                            $('table[id=data_table_' + pattern + '] div[class=report_engagement_detail]').show('fast').css('font-size', '0.67em').css('line-height', '100%');
                        } else {
                            $('table[id=data_table_' + pattern + '] div[class=report_engagement_detail]').hide('fast').css('font-size', '0.67em').css('line-height', '100%');
                        }
                    });
                    // Heatmap show/hide toggle
                    // Adapted from http://www.designchemical.com/blog/index.php/jquery/jquery-tutorial-create-a-flexible-data-heat-map/
                    $('input:checkbox[name^=toggle_heatmap_]').on('click', function(event) {
                        var pattern = $(this).prop('name').replace('toggle_heatmap_', '');
                        if (this.checked) {
                            var cols_to_calculate = $heatmappablecolumns;
                            var cols_to_calculate_direction = $heatmappablecolumnsdirections;
                            var col_maxes = {};
                            // Calculate maxes for specified columns
                            for (i = 0; i < cols_to_calculate.length; i++) {
                                var col_data = [];
                                var col_index = cols_to_calculate[i];
                                temp_data = datatables[pattern].columns(col_index).data().eq(0);
                                for (j = 0; j < temp_data.length; j++) {
                                    col_data.push($(temp_data[j]).find('.report_engagement_display').text());
                                }
                                col_maxes[col_index] = Math.max.apply(Math, col_data);
                            }
                            // Define the ending colour
                            xr = 240; // Red value
                            xg = 240; // Green value
                            xb = 255; // Blue value
                            // Define the starting colour
                            yr = 180; // Red value
                            yg = 180; // Green value
                            yb = 255; // Blue value
                            // Declare the number of groups
                            n = 40;
                            // Loop through each data point and calculate its % value
                            datatables[pattern].cells().every(function(rowIndex, colIndex, tlc, clc){
                                //console.log(this.data());
                                var k = cols_to_calculate.indexOf(colIndex);
                                if (k >= 0) {
                                    var val = parseInt($(this.data()).find('.report_engagement_display').text());
                                    var colmax = col_maxes[colIndex];
                                    if (cols_to_calculate_direction[k] == 1) {
                                        var pos = parseInt((Math.round((val/colmax)*100)).toFixed(0));
                                    } else {
                                        var pos = parseInt((Math.round(((colmax - val)/colmax)*100)).toFixed(0));
                                    }
                                    red = parseInt((xr + (( pos * (yr - xr)) / (n-1))).toFixed(0));
                                    green = parseInt((xg + (( pos * (yg - xg)) / (n-1))).toFixed(0));
                                    blue = parseInt((xb + (( pos * (yb - xb)) / (n-1))).toFixed(0));
                                    clr = 'rgb('+red+','+green+','+blue+')';
                                    $(this.node()).css('background-color', clr);
                                }
                            });
                        } else {
                            datatables[pattern].cells().every(function(rowIndex, colIndex, tlc, clc){
                                $(this.node()).css('background-color', '');
                            });
                        }
                    });
                });
            </script>
        ";
        echo($jsall);
    }

    // Form verification.
    public function validation($data, $files) {
        global $CFG;
        
        $mform =& $this->_form;

        $errors = array();

        return $errors;
    }
}
