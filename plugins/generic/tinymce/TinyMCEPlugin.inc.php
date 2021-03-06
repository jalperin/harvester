<?php

/**
 * @file plugins/generic/tinymce/TinyMCEPlugin.inc.php
 *
 * Copyright (c) 2005-2010 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TinyMCEPlugin
 * @ingroup plugins_generic_tinymce
 *
 * @brief TinyMCE WYSIWYG plugin for textareas - to allow cross-browser HTML editing
 */

// $Id$


import('classes.plugins.GenericPlugin');

define('TINYMCE_INSTALL_PATH', 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'tinymce');
define('TINYMCE_JS_PATH', TINYMCE_INSTALL_PATH . DIRECTORY_SEPARATOR . 'jscripts' . DIRECTORY_SEPARATOR . 'tiny_mce');

class TinyMCEPlugin extends GenericPlugin {
	/**
	 * Register the plugin, if enabled
	 * @param $category string
	 * @param $path string
	 * @return boolean
	 */
	function register($category, $path) {
		if (parent::register($category, $path)) {
			$this->addLocaleData();
			if ($this->isMCEInstalled() && $this->getEnabled()) {
				HookRegistry::register('TemplateManager::display',array(&$this, 'callback'));
			}
			return true;
		}
		return false;
	}

	/**
	 * Get the name of the settings file to be installed site-wide when
	 * Harvester is installed.
	 * @return string
	 */
	function getInstallSitePluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Given a $page and $op, return a list of field names for which
	 * the plugin should be used.
	 * @param $templateMgr object
	 * @param $page string The requested page
	 * @param $op string The requested operation
	 * @return array
	 */
	function getEnableFields(&$templateMgr, $page, $op) {
		$formLocale = $templateMgr->get_template_vars('formLocale');
		$fields = array();
		switch ("$page/$op") {
			case 'admin/settings':
			case 'admin/saveSettings':
				$fields[] = 'intro';
				$fields[] = 'about';
				break;
			case 'admin/createArchive':
			case 'admin/editArchive':
			case 'admin/updateArchive':
				$fields[] = 'description';
				break;
			case 'user/profile':
			case 'user/saveProfile':
			case 'user/register':
			case 'admin/createUser':
			case 'admin/updateUser':
				$fields[] = 'mailingAddress';
				$fields[] = 'biography';
				break;
		}
		HookRegistry::call('TinyMCEPlugin::getEnableFields', array(&$this, &$fields));
		return $fields;
	}

	/**
	 * Hook callback function for TemplateManager::display
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function callback($hookName, $args) {
		$templateManager =& $args[0];

		$page = Request::getRequestedPage();
		$op = Request::getRequestedOp();
		$enableFields = $this->getEnableFields($templateManager, $page, $op);

		if (!empty($enableFields)) {
			$baseUrl = $templateManager->get_template_vars('baseUrl');
			$additionalHeadData = $templateManager->get_template_vars('additionalHeadData');
			$enableFields = join(',', $enableFields);
			$allLocales = Locale::getAllLocales();
			$localeList = array();
			foreach ($allLocales as $key => $locale) {
				$localeList[] = String::substr($key, 0, 2);
			}

			$tinymceScript = '
			<script language="javascript" type="text/javascript" src="'.$baseUrl.'/'.TINYMCE_JS_PATH.'/tiny_mce_gzip.js"></script>
			<script language="javascript" type="text/javascript">
				tinyMCE_GZ.init({
					relative_urls : "false",
					plugins : "paste,ibrowser,fullscreen",
					themes : "advanced",
					languages : "' . join(',', $localeList) . '",
					disk_cache : true
				});
			</script>
			<script language="javascript" type="text/javascript">
				tinyMCE.init({
					plugins : "paste,ibrowser,fullscreen",
					mode : "exact",
					language : "' . String::substr(Locale::getLocale(), 0, 2) . '",
					elements : "' . $enableFields . '",
					relative_urls : false,
					forced_root_block : false,
					apply_source_formatting : false,
					theme : "advanced",
					theme_advanced_buttons1 : "cut,copy,paste,pastetext,pasteword,|,bold,italic,underline,bullist,numlist,|,link,unlink,help,code,fullscreen,ibrowser",
					theme_advanced_buttons2 : "",
					theme_advanced_buttons3 : ""
				});
			</script>';

			$templateManager->assign('additionalHeadData', $additionalHeadData."\n".$tinymceScript);
		}
		return false;
	}

	/**
	 * Get the symbolic name of this plugin
	 * @return string
	 */
	function getName() {
		return 'TinyMCEPlugin';
	}

	/**
	 * Get the display name of this plugin
	 * @return string
	 */
	function getDisplayName() {
		return Locale::translate('plugins.generic.tinymce.name');
	}

	/**
	 * Get the description of this plugin
	 * @return string
	 */
	function getDescription() {
		if ($this->isMCEInstalled()) return Locale::translate('plugins.generic.tinymce.description');
		return Locale::translate('plugins.generic.tinymce.descriptionDisabled', array('tinyMcePath' => TINYMCE_INSTALL_PATH));
	}

	/**
	 * Check whether or not the TinyMCE library is installed
	 * @return boolean
	 */
	function isMCEInstalled() {
		return file_exists(TINYMCE_JS_PATH . '/tiny_mce.js');
	}

	/**
	 * Check whether or not this plugin is enabled
	 * @return boolean
	 */
	function getEnabled() {
		return $this->getSetting('enabled');
	}

	/**
	 * Get a list of available management verbs for this plugin
	 * @return array
	 */
	function getManagementVerbs() {
		$verbs = array();
		if ($this->isMCEInstalled()) $verbs[] = array(
			($this->getEnabled()?'disable':'enable'),
			Locale::translate($this->getEnabled()?'manager.plugins.disable':'manager.plugins.enable')
		);
		return $verbs;
	}

 	/*
 	 * Execute a management verb on this plugin
 	 * @param $verb string
 	 * @param $args array
	 * @param $message string Location for the plugin to put a result msg
 	 * @return boolean
 	 */
	function manage($verb, $args, &$message) {
		switch ($verb) {
			case 'enable':
				$this->updateSetting('enabled', true);
				$message = Locale::translate('plugins.generic.tinymce.enabled');
				break;
			case 'disable':
				$this->updateSetting('enabled', false);
				$message = Locale::translate('plugins.generic.tinymce.disabled');
				break;
		}
		
		return false;
	}
}

?>
