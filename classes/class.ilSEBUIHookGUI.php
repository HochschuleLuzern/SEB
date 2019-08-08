<?php
/**
 * Copyright (c) 2017 Hochschule Luzern
 *
 * This file is part of the SEB-Plugin for ILIAS.
 
 * SEB-Plugin for ILIAS is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 
 * SEB-Plugin for ILIAS is distributed in the hope that
 * it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 
 * You should have received a copy of the GNU General Public License
 * along with SEB-Plugin for ILIAS.  If not,
 * see <http://www.gnu.org/licenses/>.
 *
 * The SEB-Plugin for ILIAS is a refactoring of a previous Plugin by Stefan
 * Schneider that can be found on Github
 * <https://github.com/hrz-unimr/Ilias.SEBPlugin>
 */

/**
 * All needed includes
 */
include_once './Services/UIComponent/classes/class.ilUIHookPluginGUI.php';
include_once './Services/Object/classes/class.ilObject.php';
include_once './Services/Init/classes/class.ilStartUpGUI.php';
include_once './Services/MainMenu/classes/class.ilMainMenuGUI.php';
include_once './Services/PrivacySecurity/classes/class.ilSecuritySettings.php';
include_once 'class.ilSEBPlugin.php';
include_once 'class.ilSEBConfig.php';
include_once 'class.ilSEBAccessChecker.php';

/**
 * GUI-Plugin Class to add functionality to only allow visitors on a Safe Exam Browser to this ILIAS-Instance and to offer
 * the corresponding setting functionality
 *
 * @author Stephan Winiker <stephan.winiker@hslu.ch>
 *
 */
class ilSEBUIHookGUI extends ilUIHookPluginGUI {
    // Standard classes as constats
    const STANDARD_BASE_CLASS = 'ilUIPluginRouterGUI';
	
	private static $_modifyGUI;
	private static $_ref_id;
	private $plugin;
	private $conf;
	
	public function __construct() {
	    $this->plugin = ilSEBPlugin::getInstance();
	    $this->conf = ilSEBConfig::getInstance();
	}
		
	/**
	 * Remove breadcrumb, left and right column on personal desktop, right column on all objects, footer and tabs and apply additional changes in
	 * switchToKiosk (dependent on the object type we are in)
	 *
	 * @param string $a_comp Component
	 * @param string $a_part String that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 *
	 * @return array Array defining the changes to be applied to the template of the current part ("mode" => modification mode, "html" => your html)
	 */
	function getHTML($a_comp, $a_part, $a_par = array()) {
		if (!isset(self::$_modifyGUI) && $a_par['tpl_id'] != 'tpl.main.html') {
			$this->setModifyGUI();
			
			if (self::$_modifyGUI) {
				global $DIC;
				$user = $DIC->user();
				
				if ($user->getLanguage() != $user->getCurrentLanguage()) {
					$user->setLanguage($user->getCurrentLanguage());
					$user->update();
				}
			}
		}
		
		if (!self::$_modifyGUI) {
			return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
		} else if ($a_comp == "Services/MainMenu" ||
				$a_comp == "Services/Locator" && $a_part == "main_locator" ||
				$a_comp == "Services/PersonalDesktop" && $a_part == "left_column" ||
				$a_comp == "Services/PersonalDesktop" && $a_part == "right_column" ||
				$a_comp == "Services/Container" && $a_part == "right_column" ||
				$a_part == "template_get" && $a_par['tpl_id'] == "Services/UICore/tpl.footer.html" ||
				$a_part == "template_get" && $a_par['tpl_id'] == "Services/UIComponent/Tabs/tpl.tabs.html") {
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		} else if ($a_part == "template_get" && $a_par['tpl_id'] == 'Services/MainMenu/tpl.main_menu.html') {
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => $this->switchToKiosk());
		} else if ($a_part == "template_get" && $a_par['tpl_id'] == 'Modules/Test/tpl.il_as_tst_kiosk_head.html') {
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => $this->switchToKiosk(true));
		}
				
		return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
	}
	
	/**
	 * Add a tab for session control and a settings tab where object-specific keys can be added.
	 *
	 * @param string $a_comp Component
	 * @param string $a_part String that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 * 
	 * @return array Array defining the changes to be applied to the template of the current part ("mode" => modification mode, "html" => your html)
	 */
	function modifyGUI($a_comp, $a_part, $a_par = array()) {
		global $DIC;
		
		if (!self::$_modifyGUI && $a_part == 'tabs') {
    	    $ctrl = $DIC->ctrl();
    	    
    	    $ref_id = (int)$_GET['ref_id'];
    	    $obj_id = ilObject::_lookupObjectId($ref_id);
    	    $obj_type= ilObject::_lookupType($obj_id);
    	    $has_write_access = $DIC->rbac()->system()->checkAccessOfUser($DIC->user()->getId(), 'write', $ref_id);
    
    	    if ($obj_type == 'tst' && $has_write_access && $_GET['cmd'] != 'showQuestion' && $_GET['cmd'] != 'outUserResultsOverview') {
    	        /*
    	         * Add Sessioncontrol Tab for SEB
    	         **/
    	    	if ($this->conf->getActivateSessionControl() && 
    	    		!in_array($_GET['cmdClass'], ['ilsebsettingstabgui', 'iltestevaluationgui', 'ilobjectactivationgui']) &&
    	    		!($_GET['cmdClass'] == 'iltestcorrectionsgui' && $_GET['cmd'] != 'showQuestionList')) {
    	    		$security = ilSecuritySettings::_getInstance();
    	    		if ($security->isPreventionOfSimultaneousLoginsEnabled()) {
	    	    		$ctrl->setParameterByClass('ilSEBSessionsTabGUI', 'ref_id', $ref_id);
	    	    		$link = $ctrl->getLinkTargetByClass(array(
	    	    				self::STANDARD_BASE_CLASS,
	    	    				'ilSEBSessionsTabGUI'
	    	    		),'showSessions');
	    	    		$a_par['tabs']->addTab('sessions', $this->plugin->txt('sessions_tab_title'), $link);
    	    		}
    	    	}
    	        
    	        /*
    	         * Add Settings Tab for SEB
    	         **/
    	    	if ($this->conf->getAllowObjectKeys() && 
    	    			!in_array($_GET['cmdClass'], ['ilsebsessionstabgui', 'iltestevaluationgui', 'ilobjectactivationgui']) &&
    	    			!($_GET['cmdClass'] == 'iltestcorrectionsgui' && $_GET['cmd'] != 'showQuestionList')) {
	    	        $ctrl->setParameterByClass('ilSEBSettingsTabGUI', 'ref_id', $ref_id);
	    	        $link = $ctrl->getLinkTargetByClass(array(
	    	            self::STANDARD_BASE_CLASS,
	    	            'ilSEBSettingsTabGUI'
	    	        ),'seb_settings');
	    	        $a_par['tabs']->addTab("seb_settings", $this->plugin->txt('settings_tab_title'), $link);
    	    	}
    	    }
		}
	}
	
	/**
	 * Define if a switch to the SEB-GUI is needed and deny access to users with other browsers
	 */
	
	private function setModifyGUI() {
		global $DIC;
		
		if (isset($_GET['ref_id'])) {
			self::$_ref_id = (int)$_GET['ref_id'];
		} else {
			self::$_ref_id = (int)(explode("_", $_GET["target"])[1]);
		}
	
		$access_checker = new ilSEBAccessChecker(self::$_ref_id);
		
		$ctrl = $DIC->ctrl();
		$cmd = $ctrl->getCmd();
		$cmdclass = $ctrl->getCmdClass();
	
		/*
		 * Don't modify anything in public ilias, when sending OSDNotifications, when asking the web
		 * access checker for a file, if we are on the page with the Agreement or the completion of profile Data.
		 * Only in those places unspecific object keys are allowed.
		 */
		if ($access_checker->getNeedsSeb() &&
			($cmd == 'showPersonalData' && $cmdclass == 'ilpersonalprofilegui'||
			$cmd == 'savePersonalData' && $cmdclass == 'ilpersonalprofilegui' ||
			$cmd == 'getAcceptance' && $cmdclass == 'ilstartupgui' ||
			$cmd == 'getOSDNotifications' ||
			ilContext::getType() == ilContext::CONTEXT_WAC)) {
			if ($access_checker->detectSeb(0)) {
				self::$_modifyGUI = 1;
			} else {
				self::$_modifyGUI = 1;
				$access_checker->exitIlias();
				return;
			}
		/*
		 * Check browser access for all other objects. Unspecific object keys are not accepted anymore
		 */ 
		} else if ($access_checker->getNeedsSeb() && $access_checker->isSeb() < 2) {
			self::$_modifyGUI = 1;
			$access_checker->exitIlias();
			return;
		/*
		 * If you got this far you are either a User using SEB or an admin. Check whichone it is.
		 */
		} else if ($access_checker->getSwitchToSebSkin()) {
			self::$_modifyGUI = 1;
		} else {
			self::$_modifyGUI = 0;
		}
	}
	
	/**
	 * Returns the image to be shown in the modified header
	 * 
	 * @return string The url of the user's image file
	 */
	private function getUserImage() {
		global $DIC;
		$webspace_dir = ilUtil::getWebspaceDir();

		$imagefile = $webspace_dir."/usr_images/".$DIC->user()->getPref("profile_image");
		
		if (!is_file($imagefile))
		{
			$imagefile = $DIC->user()->getPersonalPicturePath("small", false, true);
		}
		
		return $imagefile;
	}
	
	/**
	 * Populates the template if we are dealing with a user needing SEB
	 * 
	 * @param boolean $is_tst_kiosk Defines if we are in a test in kosk mode and we might need to add the title and/or the test pass id.
	 * @return string The html of the template with all replacements applied.
	 */
	private function switchToKiosk($is_tst_kiosk = false) {
		global $DIC;
		$user = $DIC->user();
		$lang = $DIC->language();

		$DIC->ui()->mainTemplate()->addCss($this->plugin->getStyleSheetLocation('default/seb.css'));
		
		$lang_select = ilMainMenuGUI::getLanguageSelection(true);
		
		$lang_select = str_replace("&", "&amp;", $lang_select);
		
		$tpl = $this->plugin->getTemplate('default/tpl.seb_header.html');
		if ($this->conf->getShowPaxPic()) {
			$tpl->setCurrentBlock('seb_usr_img');
			$tpl->setVariable('SEB_USR_IMG_URL', $this->getUserImage());
			$tpl->setVariable('SEB_USR_IMG_ALT', $user->getFullname());
		}
		
		$tpl->setCurrentBlock("kiosk_show_participant");
		$tpl->setVariable("PARTICIPANT_NAME", $user->getFullname());
		$tpl->setVariable("PARTICIPANT_LOGIN", $user->getLogin());
		
		$link_dir = (defined("ILIAS_MODULE")) ? "../" : "";
		
		$tpl->setVariable("SEB_LOGOUT_TEXT",$lang->txt("logout"));
		$tpl->setVariable("SEB_LOGOUT_LINK", $link_dir."logout.php?lang=".$user->getCurrentLanguage());
		$tpl->parseCurrentBlock();
		
		if ($is_tst_kiosk) {
			$object = new ilObjTest(self::$_ref_id);
			
			if ($object->getShowKioskModeTitle()) {
				$tpl->setCurrentBlock("kiosk_show_title");
				$tpl->setVariable("TEST_TITLE", $object->getTitle());
				$tpl->parseCurrentBlock();
			}
			if ($object->isShowExamIdInTestPassEnabled()) {
				$testSession = new ilTestSession();
				$testSession->loadTestSession($object->getTestId(), $user->getId());
				$exam_id = ilObjTest::buildExamId(
						$testSession->getActiveId() , $testSession->getPass(), self::$_ref_id
						);
				
				$tpl->setCurrentBlock("kiosk_show_exam_id");
				$tpl->setVariable("EXAM_ID_TXT", $lang->txt("exam_id"));
				$tpl->setVariable("EXAM_ID", $exam_id);
				$tpl->parseCurrentBlock();
			}
		}
		
		$tpl->setCurrentBlock('seb_lang_select');
		$tpl->setVariable("TXT_LANGSELECT", $lang->txt("language"));
		$tpl->setVariable('SEB_LANG_SELECT', $lang_select);
		$tpl->parseCurrentBlock();
		
		$tpl->parseCurrentBlock();
		
		return $tpl->get();
	}
}
?>
