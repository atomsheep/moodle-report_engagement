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
	
	public $recipient_address;
	public $recipient_name;
	public $sender_address;
	public $sender_name;
	public $replyto_address;
	public $replyto_name;
	public $email_subject;
	public $email_body;

	public function send_email(){
		require('config.php');
		switch ($_CONFIG_MAILER) {
			case 'gmail':
				return $this->send_email_gmail();
				break;
			case 'custom':
				return $this->send_email_custom();
				break;
			case 'mandrill':
				return $this->send_email_mandrill();
				break;
		}
	}
	
	/*private function send_email_phpmail(){
		try {
			$to = $this->recipient_address;
			$subject = $this->email_subject;
			$message = $this->email_body;
			$headers = array();
			$headers[] = 'From: ' . $this->sender_name . "<$this->sender_address>";
			$headers[] = 'Reply-To: ' . $this->replyto_name . "<$this->replyto_address>";
			$headers[] = 'X-Mailer: PHP/' . phpversion();
			return mail($to, $subject, $message, implode("\r\n", $headers));
		} catch (Exception $e) {
			return false;
		}
	}*/
	
	private function send_email_moodle(){
		//$result->result = email_to_user($recipient, $sender, $email_subject, $email_body, $email_body, '', '', true, $replyto->email, fullname($replyto));
	}
	
	private function send_email_gmail(){
		
		require('config.php');
		if (!isset($_CONFIG_MAILER_GMAIL)) return null;
		
		require_once('PHPMailer/PHPMailerAutoload.php');
			
		$mail = new PHPMailer;
		
		$mail->isSMTP();
		$mail->SMTPDebug = 2;
		$mail->Host = $_CONFIG_MAILER_GMAIL['FQDN'];
		$mail->SMTPAuth = $_CONFIG_MAILER_GMAIL['AUTH'];
		$mail->Username = $_CONFIG_MAILER_GMAIL['USER'];
		$mail->Password = $_CONFIG_MAILER_GMAIL['PASS'];
		$mail->SMTPSecure = $_CONFIG_MAILER_GMAIL['ENCR'];
		$mail->Port = $_CONFIG_MAILER_GMAIL['PORT'];

		$mail->From = $this->sender_address;
		$mail->FromName = $this->sender_name;
		$mail->addAddress($this->recipient_address, $this->recipient_name);
		$mail->addReplyTo($this->replyto_address, $this->replyto_name);

		$mail->isHTML(false);

		$mail->Subject = $this->email_subject;
		$mail->Body    = $this->email_body;

		$result = new stdClass();
		if(!$mail->send()) {
			$result->message = $mail->ErrorInfo;
			$result->result = false;
		} else {
			$result->result = true;
		}	
		
		return $result;
			
	}
	
	private function send_email_custom(){
	
		require('config.php');
		if (!isset($_CONFIG_MAILER_CUSTOM)) return null;
		
		require_once('PHPMailer/PHPMailerAutoload.php');
		
		$mail = new PHPMailer;
		
		$mail->isSMTP();
		$mail->SMTPDebug = 2;
		$mail->Host = $_CONFIG_MAILER_CUSTOM['FQDN'];
		$mail->SMTPAuth = $_CONFIG_MAILER_CUSTOM['AUTH'];
		$mail->Username = $_CONFIG_MAILER_CUSTOM['USER'];
		$mail->Password = $_CONFIG_MAILER_CUSTOM['PASS'];
		$mail->SMTPSecure = $_CONFIG_MAILER_CUSTOM['ENCR'];
		$mail->Port = $_CONFIG_MAILER_CUSTOM['PORT'];

		$mail->From = $this->sender_address;
		$mail->FromName = $this->sender_name;
		$mail->addAddress($this->recipient_address, $this->recipient_name);
		$mail->addReplyTo($this->replyto_address, $this->replyto_name);

		$mail->isHTML(false);

		$mail->Subject = $this->email_subject;
		$mail->Body    = $this->email_body;

		$result = new stdClass();
		if(!$mail->send()) {
			$result->message = $mail->ErrorInfo;
			$result->result = false;
		} else {
			$result->result = true;
		}	
		
		return $result;

	}
	
	private function send_email_mandrill(){
	
		require('config.php');
		if (!isset($_CONFIG_MAILER_MANDRILL)) return null;
		
		require_once('mandrill/Mandrill.php');
		
		$res = new stdClass();
		
		try {
			$mandrill = new Mandrill($_CONFIG_MAILER_MANDRILL['APIKEY']);
			$message = array(
				'text' => $this->email_body,
				'subject' => $this->email_subject,
				'from_email' => $this->sender_address,
				'from_name' => $this->sender_name,
				'to' => array(
					array(
						'email' => $this->recipient_address,
						'name' => $this->recipient_name,
						'type' => 'to'
					)
				),
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
