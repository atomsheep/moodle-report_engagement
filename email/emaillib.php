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
 * @copyright  2015 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

class report_engagement_email_message {
	
	public $recipient; // user object
	public $recipient_address; // str
	public $recipient_name; // str
	public $sender; // user object
	public $sender_address; // str
	public $sender_name; // str
	public $replyto_address; // str
	//public $replyto_name;
	//public $cc_address; // str
	public $email_subject; // str
	public $email_body; // str
	
	public function send_email(){
		require('config.php'); // dev hack to configure mail engine
		switch ($_CONFIG_MAILER) {
			case 'moodle':
				return $this->send_email_moodle();
				break;
			case 'mandrill':
				return $this->send_email_mandrill();
				break;
		}
	}
	
	private function send_email_moodle(){
		$res = new stdClass();
		if (isset($this->recipient) && isset($this->sender)) {
			$res->result = email_to_user(
				$this->recipient, $this->sender, 
				$this->email_subject, $this->email_body, '', 
				'', '', 
				true, 
				$this->replyto_address, ''
			);
			if ($res->result === true) {
				$res->message = 'OK';
			} else {
				$res->message = '';
			}
		} else {
			$res->result = false;
			$res->message = '';
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
			/*if (isset($this->cc_address)) {
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
}
