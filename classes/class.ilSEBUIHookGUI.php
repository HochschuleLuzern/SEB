<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/UIComponent/classes/class.ilUIHookPluginGUI.php';
include_once './Services/Object/classes/class.ilObject.php';
include_once './Services/Init/classes/class.ilStartUpGUI.php';
include_once './Services/MainMenu/classes/class.ilMainMenuGUI.php';
include_once 'class.ilSEBPlugin.php';
include_once 'class.ilSEBConfig.php';
include_once 'class.ilSEBAccessChecker.php';

/**
 * User interface hook class
 *
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 * @ingroup ServicesUIComponent
 */
class ilSEBUIHookGUI extends ilUIHookPluginGUI {
    // Standard classes as constats
    const STANDARD_BASE_CLASS = 'ilUIPluginRouterGUI';
    const STANDARD_CMD_CLASS = 'ilSEBSettingsTabGUI';
	
	private static $_modifyGUI;
	private static $_ref_id;
	private $plugin;
	private $conf;
	
	public function __construct() {
	    $this->plugin = ilSEBPlugin::getInstance();
	    $this->conf = ilSEBConfig::getInstance();
	}
	
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
	    
	    // don't modify anything after an initial installation with an empty key and without object keys
	    if ($this->conf->getSebKeysString() == ''  && !$this->conf->getAllowObjectKeys()) {
	        self::$_modifyGUI = 0;
	        // don't modify anything in public ilias or if we are on the page with the Agreement or the completion of profile Data
	    } else if ($access_checker->isAnonymusUser() ||
	        $cmd == 'showPersonalData' && $cmdclass == 'ilpersonalprofilegui'||
	        $cmd == 'savePersonalData' && $cmdclass == 'ilpersonalprofilegui' || 
	        $cmd == 'getAcceptance' && $cmdclass == 'ilstartupgui' ||
	        $cmd == '' && $cmdclass == 'ilstartupgui') {
	            if ($access_checker->detectSeb(0)) {
	                self::$_modifyGUI = 1;   
	            } else {
	                $access_checker->exitIlias();
	                return;
	            }	            
	    // check browser access
	    } else if ($access_checker->getNeedsSeb() && !$access_checker->isSeb()) {
	        $access_checker->exitIlias();
	        return;
	    //Should we switch to Kiosk-Skin?
	    } else if ($access_checker->getSwitchToSebSkin()) {
	        self::$_modifyGUI = 1;
	    } else {
	        self::$_modifyGUI = 0;
	    }
	}
	
	private function getUserImage() {
	    global $DIC;
	    $webspace_dir = ilUtil::getWebspaceDir("user");
	    $check_dir = ilUtil::getWebspaceDir();
	    $imagefile = $webspace_dir."/usr_images/".$DIC->user()->getPref("profile_image")."?dummy=".rand(1,999999);
	    $check_file = $check_dir."/usr_images/".$DIC->user()->getPref("profile_image");
	    
	    if (!@is_file($check_file))
	    {
	        $imagefile = $check_file =
	        $DIC->user()->getPersonalPicturePath("small", false, true);
	    }
	    
	    return $imagefile;
	}
	
	private function switchToKiosk($page, $is_tst_kiosk = false) {
	    global $DIC;
	    $user = $DIC->user();
	    $lang = $DIC->language();
	    
	    $DIC->ui()->mainTemplate()->addCss('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB/templates/default/seb.css');
	    
        $lang_select = ilMainMenuGUI::getLanguageSelection(true);
        
        $lang_select = str_replace("&", "&amp;", $lang_select);
	    
	    $tpl = new ilTemplate('tpl.seb_header.html', true, true, 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB');
	    $tpl->setCurrentBlock('seb_usr_img');
	    $tpl->setVariable('SEB_USR_IMG_URL', $this->getUserImage());
	    $tpl->setVariable('SEB_USR_IMG_ALT', $user->getFullname());
	    
	    $tpl->setCurrentBlock("kiosk_show_participant");
	    $tpl->setVariable("PARTICIPANT_NAME", $user->getFullname());
	    
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
    	        $tpl->setVariable(	"EXAM_ID", $exam_id);
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
	
	/**
	 * Modify HTML output of GUI elements. Modifications modes are:
	 * - ilUIHookPluginGUI::KEEP (No modification)
	 * - ilUIHookPluginGUI::REPLACE (Replace default HTML with your HTML)
	 * - ilUIHookPluginGUI::APPEND (Append your HTML to the default HTML)
	 * - ilUIHookPluginGUI::PREPEND (Prepend your HTML to the default HTML)
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 *
	 * @return array array with entries "mode" => modification mode, "html" => your html
	 */
	function getHTML($a_comp, $a_part, $a_par = array()) {
	    global $DIC;
	    $rbacreview =  $DIC->rbac()->review();
	    $user = $DIC->user();
	    $log = $DIC->logger();
	    
	    if (!isset(self::$_modifyGUI)) {
	        $this->setModifyGUI();
	        
	        if (self::$_modifyGUI) {
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
	        return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => $this->switchToKiosk($a_par['html']));
	    } else if ($a_part == "template_get" && $a_par['tpl_id'] == 'Modules/Test/tpl.il_as_tst_kiosk_head.html') {
	        return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => $this->switchToKiosk($a_par['html'], true));
	    }
	    
	    
		
		return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
	}
	
	/**
	 * Modify GUI objects, before they generate ouput
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 */
	function modifyGUI($a_comp, $a_part, $a_par = array()) {
		global $DIC;
		
		if (!self::$_modifyGUI && $a_part == 'tabs') {
    	    $ctrl = $DIC->ctrl();
    	    
    	    $ref_id = (int)$_GET['ref_id'];
    	    $obj_id = ilObject::_lookupObjectId($ref_id);
    	    $obj_type= ilObject::_lookupType($obj_id);
    	    $has_write_access = $DIC->rbac()->system()->checkAccessOfUser($DIC->user()->getId(), 'write', $ref_id);
    
    	    if ($obj_type == 'tst' && $has_write_access && $this->conf->getAllowObjectKeys() && $_GET['cmd'] != 'showQuestion' && $_GET['cmd'] != 'outUserResultsOverview') {
    	        $ctrl->setParameterByClass(self::STANDARD_CMD_CLASS, 'ref_id', $ref_id);
    	        $link = $ctrl->getLinkTargetByClass(array(
    	            self::STANDARD_BASE_CLASS,
    	            self::STANDARD_CMD_CLASS
    	        ),'seb_settings');
    	        $a_par['tabs']->addTab("SEB Settings", $this->plugin->txt('tab_title'), $link);
    	    }
		}
	}
}
?>
