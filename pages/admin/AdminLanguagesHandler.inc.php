<?php

/**
 * @file pages/admin/AdminLanguagesHandler.inc.php
 *
 * Copyright (c) 2005-2010 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package pages.admin
 * @class AdminLanguagesHandler
 *
 * Handle requests for changing site language settings. 
 *
 */

// $Id$

import('pages.admin.AdminHandler');

class AdminLanguagesHandler extends AdminHandler {

	/**
	 * Display form to modify site language settings.
	 */
	function languages() {
		$this->validate();
		$this->setupTemplate(true);

		$site =& Request::getSite();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('localeNames', Locale::getAllLocales());
		$templateMgr->assign('primaryLocale', $site->getPrimaryLocale());
		$templateMgr->assign('supportedLocales', $site->getSupportedLocales());
		$localesComplete = array();
		foreach (Locale::getAllLocales() as $key => $name) {
			$localesComplete[$key] = Locale::isLocaleComplete($key);
		}
		$templateMgr->assign('localesComplete', $localesComplete);

		$templateMgr->assign('installedLocales', $site->getInstalledLocales());
		$templateMgr->assign('uninstalledLocales', array_diff(array_keys(Locale::getAllLocales()), $site->getInstalledLocales()));
		$templateMgr->display('admin/languages.tpl');
	}

	/**
	 * Update language settings.
	 */
	function saveLanguageSettings() {
		$this->validate();
		$this->setupTemplate(true);

		$site =& Request::getSite();

		$primaryLocale = Request::getUserVar('primaryLocale');
		$supportedLocales = Request::getUserVar('supportedLocales');

		if (Locale::isLocaleValid($primaryLocale)) {
			$site->setPrimaryLocale($primaryLocale);
		}

		$newSupportedLocales = array();
		if (isset($supportedLocales) && is_array($supportedLocales)) {
			foreach ($supportedLocales as $locale) {
				 if (Locale::isLocaleValid($locale)) {
				 	array_push($newSupportedLocales, $locale);
				 }
			}
		}
		if (!in_array($primaryLocale, $newSupportedLocales)) {
			array_push($newSupportedLocales, $primaryLocale);
		}
		$site->setSupportedLocales($newSupportedLocales);

		$siteDao =& DAORegistry::getDAO('SiteDAO');
		$siteDao->updateObject($site);

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign(array(
			'currentUrl' => Request::url('admin', 'languages'),
			'pageTitle' => 'common.languages',
			'message' => 'common.changesSaved',
			'backLink' => Request::url('admin'),
			'backLinkLabel' => 'admin.siteAdmin'
		));
		$templateMgr->display('common/message.tpl');
	}

	/**
	 * Install a new locale.
	 */
	function installLocale() {
		$this->validate();

		$site =& Request::getSite();
		$installLocale = Request::getUserVar('installLocale');

		if (isset($installLocale) && is_array($installLocale)) {
			$installedLocales = $site->getInstalledLocales();

			foreach ($installLocale as $locale) {
				if (Locale::isLocaleValid($locale) && !in_array($locale, $installedLocales)) {
					array_push($installedLocales, $locale);
					Locale::installLocale($locale);
				}
			}

			$site->setInstalledLocales($installedLocales);
			$siteDao =& DAORegistry::getDAO('SiteDAO');
			$siteDao->updateObject($site);
		}

		Request::redirect('admin', 'languages');
	}

	/**
	 * Uninstall a locale
	 */
	function uninstallLocale() {
		$this->validate();

		$site =& Request::getSite();
		$locale = Request::getUserVar('locale');

		if (isset($locale) && !empty($locale) && $locale != $site->getPrimaryLocale()) {
			$installedLocales = $site->getInstalledLocales();

			if (in_array($locale, $installedLocales)) {
				$installedLocales = array_diff($installedLocales, array($locale));
				$site->setInstalledLocales($installedLocales);
				$supportedLocales = $site->getSupportedLocales();
				$supportedLocales = array_diff($supportedLocales, array($locale));
				$site->setSupportedLocales($supportedLocales);

				$siteDao =& DAORegistry::getDAO('SiteDAO');
				$siteDao->updateObject($site);

				Locale::uninstallLocale($locale);
			}
		}

		Request::redirect('admin', 'languages');
	}

	/*
	 * Reload data for an installed locale.
	 */
	function reloadLocale() {
		$this->validate();

		$site =& Request::getSite();
		$locale = Request::getUserVar('locale');

		if (in_array($locale, $site->getInstalledLocales())) {
			Locale::reloadLocale($locale);
		}

		Request::redirect('admin', 'languages');
	}
}

?>
