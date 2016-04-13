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
 *
 * @package    report_engagement
 * @copyright  2015-2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

class report_engagement_email_message {
    
    public $recipient; // User object.
    public $recipient_address; // Str.
    public $recipient_name; // Str.
    public $sender; // User object.
    public $sender_address; // Str.
    public $sender_name; // Str.
    public $replyto_address; // Str.
    /* ForFuture: public $cc_address; // Str. */
    public $email_subject; // Str.
    public $email_body; // Str.
    
    public function send_email(){
        require('config.php'); // Dev hack to configure mail engine.
        switch ($_CONFIG_MAILER) {
            case 'mandrill':
                return $this->send_email_mandrill();
                break;
            case 'mailgun':
                return $this->send_email_mailgun();
                break;
            case 'moodle':
            default:
                return $this->send_email_moodle();
                break;
        }
    }
    
    private function send_email_moodle(){
        $res = new stdClass();
        if (isset($this->recipient) && isset($this->sender)) {
            $res->result = email_to_user(
                $this->recipient, // User object.
                $this->sender, // User object.
                $this->email_subject, // String.
                $this->email_body, // Plain text message.
                '', // HTML message.
                '', // Attachment.
                '', // Attachment name.
                true, // Use true from address.
                $this->replyto_address, // Reply to address.
                '' // Reply to name.
            );
            if ($res->result === true) {
                $res->message = 'OK';
            } else {
                $res->message = 'Error';
            }
        } else {
            $res->result = false;
            $res->message = 'Error';
        }
        return $res;
    }
    
    private function send_email_mandrill(){
    
        require('config.php');
        if (!isset($_CONFIG_MAILER_MANDRILL)) return null;
        
        require_once('mandrill/Mandrill.php');
        
        $res = new stdClass();
        
        try {
            $mandrill = new Mandrill($_CONFIG_MAILER_MANDRILL['APIKEY']);
            $to_array = array();
            $to_array[] = array(
                'email' => $this->recipient_address,
                'name' => $this->recipient_name,
                'type' => 'to');
            /* ForFuture: if (isset($this->cc_address)) {
                $to_array[] = array(
                    'email' => $this->cc_address,
                    'type' => 'cc');
            };*/
            $message = array(
                'text' => $this->email_body,
                'subject' => $this->email_subject,
                'from_email' => $this->sender_address,
                'from_name' => $this->sender_name,
                'to' => $to_array,
                'headers' => array('Reply-To' => $this->replyto_address),
            );
            $async = true;
            $result = $mandrill->messages->send($message, $async);
            $res->message = $result[0]['status'];
            $res->result = true;
        } catch (Exception $e) {
            $res->message = $e->getMessage();
            $res->result = false;
        }
        
        return $res;
    }
    
    private function send_email_mailgun(){

        require('config.php');
        if (!isset($_CONFIG_MAILER_MAILGUN)) return null;
        
        $res = new stdClass();
        
        try {
            $url = "https://api:{$_CONFIG_MAILER_MAILGUN['APIKEY']}@{$_CONFIG_MAILER_MAILGUN['APIBASE']}/messages";
            $data = array(
                'from'    => "{$this->sender_name} <{$this->sender_address}>",
                'to'      => "{$this->recipient_name} <{$this->recipient_address}>",
                'subject' => $this->email_subject,
                'text'    => $this->email_body,
                'html'    => nl2br($this->email_body),
                'h:Reply-To' => $this->replyto_address
            );
            
            // Call API via cURL.
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = json_decode(curl_exec($ch));
            curl_close($ch);
            
            // Set some output.
            $res->result = true;
            $res->message = $result->message;
            
        } catch (Exception $e) {
        
            $res->message = $e->getMessage();
            $res->result = false;
            
        }
        
        return $res;
    }
}
