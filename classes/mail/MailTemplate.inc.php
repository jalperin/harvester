<?php

/**
 * MailTemplate.inc.php
 *
 * Copyright (c) 2005-2006 The Public Knowledge Project
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package mail
 *
 * Subclass of Mail for mailing a template email.
 *
 * $Id$
 */

import('mail.Mail');

define('MAIL_ERROR_INVALID_EMAIL', 0x000001);
class MailTemplate extends Mail {
	
	/** @var $emailKey string Key of the email template we are using */
	var $emailKey;
	
	/** @var $locale string locale of this template */
	var $locale;
	
	/** @var $enabled boolean email template is enabled */
	var $enabled;

	/** @var $errorMessages array List of errors to display to the user */
	var $errorMessages;

	/** @var $skip boolean If set to true, this message has been skipped
	    during the editing process by the user. */
	var $skip;

	/** @var $bccSender boolean whether or not to bcc the sender */
	var $bccSender;

	/** @var boolean Whether or not email fields are disabled */
	var $addressFieldsEnabled;
	
	/**
	 * Constructor.
	 * @param $emailKey string unique identifier for the template
	 * @param $locale string locale of the template
	 */
	function MailTemplate($emailKey = null, $locale = null) {
		$this->emailKey = isset($emailKey) ? $emailKey : null;

		// Use current user's locale if none specified
		$this->locale = isset($locale) ? $locale : Locale::getLocale();
		
		if (isset($this->emailKey)) {
			$emailTemplateDao = &DAORegistry::getDAO('EmailTemplateDAO');
			$emailTemplate = &$emailTemplateDao->getEmailTemplate($this->emailKey, $this->locale);
		}

		if (isset($emailTemplate) && Request::getUserVar('subject')==null && Request::getUserVar('body')==null) {
			$this->setSubject($emailTemplate->getSubject());
			$this->setBody($emailTemplate->getBody());
			$this->enabled = $emailTemplate->getEnabled();

			if (Request::getUserVar('usePostedAddresses')) {
				$to = Request::getUserVar('to');
				if (is_array($to)) {
					$this->setRecipients($this->processAddresses ($this->getRecipients(), $to));
				}
				$cc = Request::getUserVar('cc');
				if (is_array($cc)) {
					$this->setCcs($this->processAddresses ($this->getCcs(), $cc));
				}
				$bcc = Request::getUserVar('bcc');
				if (is_array($bcc)) {
					$this->setBccs($this->processAddresses ($this->getBccs(), $bcc));
				}
			}
		} else {
			$this->setSubject(Request::getUserVar('subject'));
			$this->setBody(Request::getUserVar('body'));
			$this->skip = (($tmp = Request::getUserVar('send')) && is_array($tmp) && isset($tmp['skip']));
			$this->enabled = true;

			if (is_array($toEmails = Request::getUserVar('to'))) {
				$this->setRecipients($this->processAddresses ($this->getRecipients(), $toEmails));
			}
			if (is_array($ccEmails = Request::getUserVar('cc'))) {
				$this->setCcs($this->processAddresses ($this->getCcs(), $ccEmails));
			}
			if (is_array($bccEmails = Request::getUserVar('bcc'))) {
				$this->setBccs($this->processAddresses ($this->getBccs(), $bccEmails));
			}
		}

		// Record whether or not to BCC the sender when sending message
		$this->bccSender = Request::getUserVar('bccSender');

		$site = &Request::getSite();
		$this->setFrom($site->getContactEmail(), $site->getContactName());

		$this->addressFieldsEnabled = true;
	}

	/**
	 * Disable or enable the address fields on the email form.
	 * NOTE: This affects the displayed form ONLY; if disabling the address
	 * fields, callers should manually clearAllRecipients and add/set
	 * recipients just prior to sending.
	 * @param $addressFieldsEnabled boolean
	 */
	function setAddressFieldsEnabled($addressFieldsEnabled) {
		$this->addressFieldsEnabled = $addressFieldsEnabled;
	}

	/**
	 * Get the enabled/disabled state of address fields on the email form.
	 * @return boolean
	 */
	function getAddressFieldsEnabled() {
		return $this->addressFieldsEnabled;
	}

	/**
	 * Check whether or not there were errors in the user input for this form.
	 * @return boolean true iff one or more error messages are stored.
	 */
	function hasErrors() {
		return ($this->errorMessages != null);
	}

	/**
	 * Assigns values to e-mail parameters.
	 * @param $paramArray array
	 * @return void
	 */
	function assignParams($paramArray = array()) {
		$subject = $this->getSubject();
		$body = $this->getBody();

		// Add commonly-used variables to the list
		$site = &Request::getSite();
		$paramArray['principalContactSignature'] = $site->getContactName();

		// Replace variables in message with values
		foreach ($paramArray as $key => $value) {
			if (!is_object($value)) {
				$subject = str_replace('{$' . $key . '}', $value, $subject);
				$body = str_replace('{$' . $key . '}', $value, $body);
			}
		}
		
		$this->setSubject($subject);
		$this->setBody($body);
	}
	
	/**
	 * Returns true if the email template is enabled; false otherwise.
	 * @return boolean
	 */
	function isEnabled() {
		return $this->enabled;
	}

	/**
	 * Processes form-submitted addresses for inclusion in
	 * the recipient list
	 * @param $currentList array Current recipient/cc/bcc list
	 * @param $newAddresses array "Raw" form parameter for additional addresses
	 */
	function &processAddresses($currentList, &$newAddresses) {
		foreach ($newAddresses as $newAddress) {
			$regs = array();
			// Match the form "My Name <my_email@my.domain.com>"
			if (ereg('^([^<>' . "\n" . ']*[^<> ' . "\n" . '])[ ]*<([-A-Za-z0-9]+([-_\+\.][A-Za-z0-9]+)*@[A-Za-z0-9]+([-_\.][A-Za-z0-9]+)*\.[A-Za-z]{2,})>$', $newAddress, $regs)) {
				$currentList[] = array('name' => $regs[1], 'email' => $regs[2]);
			} elseif (ereg('^[A-Za-z0-9]+([-_\+\.][A-Za-z0-9]+)*@[A-Za-z0-9]+([-_\.][A-Za-z0-9]+)*\.[A-Za-z]{2,}$', $newAddress)) {
				$currentList[] = array('name' => '', 'email' => $newAddress);
			} else if ($newAddress != '') {
				$this->errorMessages[] = array('type' => MAIL_ERROR_INVALID_EMAIL, 'address' => $newAddress);
			}
		}
		return $currentList;
	}

	/**
	 * Displays an edit form to customize the email.
	 * @param $formActionUrl string
	 * @param $hiddenFormParams array
	 * @return void
	 */
	function displayEditForm($formActionUrl, $hiddenFormParams = null, $alternateTemplate = null, $additionalParameters = array()) {
		import('form.Form');
		$form = &new Form($alternateTemplate!=null?$alternateTemplate:'email/email.tpl');

		$form->setData('formActionUrl', $formActionUrl);
		$form->setData('subject', $this->getSubject());
		$form->setData('body', $this->getBody());

		$form->setData('to', $this->getRecipients());
		$form->setData('cc', $this->getCcs());
		$form->setData('bcc', $this->getBccs());
		$form->setData('blankTo', Request::getUserVar('blankTo'));
		$form->setData('blankCc', Request::getUserVar('blankCc'));
		$form->setData('blankBcc', Request::getUserVar('blankBcc'));
		$form->setData('from', $this->getFromString());

		$form->setData('addressFieldsEnabled', $this->getAddressFieldsEnabled());

		$user = &Request::getUser();
		if ($user) {
			$form->setData('senderEmail', $user->getEmail());
			$form->setData('bccSender', $this->bccSender);
		}

		$form->setData('errorMessages', $this->errorMessages);

		if ($hiddenFormParams != null) {
			$form->setData('hiddenFormParams', $hiddenFormParams);
		}

		foreach ($additionalParameters as $key => $value) {
			$form->setData($key, $value);
		}

		$templateMgr = &TemplateManager::getManager();

		$form->display();
	}

	/**
	 * Send the email.
	 */
	function send() {
		if (isset($this->skip) && $this->skip) {
			$result = true;
		} else {
			$result = parent::send();
		}

		return $result;
	}

	/**
	 * Assigns user-specific values to email parameters, sends
	 * the email, then clears those values.
	 * @param $paramArray array
	 * @return void
	 */
	function sendWithParams($paramArray) {
		$savedHeaders = $this->getHeaders();
		$savedSubject = $this->getSubject();
		$savedBody = $this->getBody();
		
		$this->assignParams($paramArray);
		
		$ret = $this->send();
		
		$this->setHeaders($savedHeaders);
		$this->setSubject($savedSubject);
		$this->setBody($savedBody);
		
		return $ret;
	}
	
	/**
	 * Clears the recipient, cc, and bcc lists.
	 * @param $clearHeaders boolean if true, also clear headers
	 * @return void
	 */
	function clearRecipients($clearHeaders = true) {
		$this->setData('recipients', null);
		$this->setData('ccs', null);
		$this->setData('bccs', null);
		if ($clearHeaders) {
			$this->setData('headers', null);
		}
	}
}

?>