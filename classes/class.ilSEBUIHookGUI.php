<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/UIComponent/classes/class.ilUIHookPluginGUI.php';
include_once './Services/Object/classes/class.ilObject.php';
include_once './Services/Init/classes/class.ilStartUpGUI.php';
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
	
	private static $_modifyGUI = 0;
	private $plugin;
	private $conf;
	private $tpl;
	
	public function __construct() {
	    $this->plugin = ilSEBPlugin::getInstance();
	    $this->conf = ilSEBConfig::getInstance();
	}
	
	function getSebObject() { // obsolet?
	    global $DIC;
	    $user = $DIC->user();
		$pl = $this->getPluginObject();
		$login = ($user->getLogin()) ? $user->getLogin() : "";
		$firstname = ($user->getFirstname()) ? $user->getFirstname() : "";
		$lastname = ($user->getLastname()) ? $user->getLastname() : "";
		$matriculation = ($user->getMatriculation()) ? $user->getMatriculation() : "";
		
		$seb_user = array(
					"login" => $login,
					"firstname" => $firstname,
					"lastname" => $lastname,
					"matriculation" => $matriculation
				);
		$seb_object = array("user" => $seb_user);
		$ret = json_encode($seb_object); 
		return $ret;
	}
	
	function setUserGUI ($styleDefinition) {
	    global $DIC;

		$this->_modifyGUI = 0;
		$styleDefinition::setCurrentSkin($DIC->user()->getPref("skin"));
		$styleDefinition::setCurrentStyle($DIC->user()->getPref("style"));
	}
	
	function setSebGUI ($styleDefinition) {
	    include_once 'Services/MainMenu/classes/class.ilMainMenuGUI.php';
	    global $DIC;
	    $lng =  $DIC->language();
	    
		$this->_modifyGUI = 1;
		$styleDefinition::setCurrentSkin("seb");
		$styleDefinition::setCurrentStyle("seb");
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
	    
        // We don't have to run through the whole thing if we don't need to modify anything. Let's get out.
		if (!self::$_modifyGUI ) {
			return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
		}
		
		// JavaScript Injection of seb_object on TA kioskmode
	
		if ($a_part == "template_load" && $a_par["tpl_id"] == "Modules/Test/tpl.il_as_tst_kiosk_head.html") {		
			$pl = $this->getPluginObject();
			$DIC->ui()->mainTemplate()->addJavaScript($pl->getDirectory() . "/ressources/seb.js");
			$seb_object = $this->getSebObject(); 
			return array("mode" => ilUIHookPluginGUI::PREPEND, "html" => "<script type=\"text/javascript\">var seb_object = " . $seb_object . ";</script>");
		}
	
		// JavaScript Injection of seb_object on PD kioskmode
		
		if ($a_comp == "Services/MainMenu" && $a_part == "main_menu_list_entries") {			
			$pl = $this->getPluginObject();
			$DIC->ui()->mainTemplate()->addJavaScript($pl->getDirectory() . "/ressources/seb.js");
			$seb_object = $this->getSebObject(); 
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "<script type=\"text/javascript\">var seb_object = " . $seb_object . ";</script>");
		} 
		
		if ($a_comp == "Services/MainMenu" && $a_part == "main_menu_search") {		
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");			
		}
		
		if ($a_comp == "Services/Locator" && $a_part == "main_locator") {
		    return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		}
		
		if ($a_comp == "Services/PersonalDesktop" && $a_part == "right_column") {
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		}
		
		if ($a_comp == "Services/Container" && $a_part == "right_column") {
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		}
		
		if ($a_comp == "Services/PersonalDesktop" && $a_part == "left_column") {			
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
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

		if (!$this->_modifyGUI && $a_part == 'tabs') {
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
		
		//This is the first GUI Event triggered when building the GUI. We check if we need to redirect to seb or to block access.
		if ($a_comp == "Services/Init" && $a_part == "init_style") {
		    if (isset($_GET['ref_id'])) {
		        $access_checker = new ilSEBAccessChecker((int)$_GET['ref_id']);
		    } else {
		        $access_checker = new ilSEBAccessChecker(explode("_", $_GET["target"])[1]);
		    }
		    $styleDefinition = $a_par["styleDefinition"];

		    $ctrl = $DIC->ctrl();
		    $cmd = $ctrl->getCmd();
		    $cmdclass = $ctrl->getCmdClass();
		    
		    //We need to figure out if it is a call from the access checker. Not beautiful, but the best I found.
		    $calling_class = debug_backtrace();
		    $calling_class = $calling_class[count($calling_class)-1]['class'];
		    
			// don't modify anything after an initial installation with an empty key and without object keys
			if ($this->conf->getSebKeysString() == ''  && !$this->conf->getAllowObjectKeys()) {
				$this->setUserGUI($styleDefinition);
			// don't modify anything in public ilias or if we are on the page with the Agreement or the completion of profile Data
			} else if ($access_checker->isAnonymusUser() ||
			    $cmd == 'showPersonalData' && $cmdclass == 'ilpersonalprofilegui'||
			    $cmd == 'savePersonalData' && $cmdclass == 'ilpersonalprofilegui' ||
			    $cmd == 'getAcceptance' && $cmdclass == 'ilstartupgui' ||
			    $cmd == '' && $cmdclass == 'ilstartupgui' ||
			    $cmd == 'getOSDNotifications' ||
			    $calling_class == 'ilWebAccessCheckerDelivery') {
			        if ($access_checker->detectSeb()) {
        				$this->setUserGUI($styleDefinition);
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
				$this->setSebGUI($styleDefinition);
			} else {
				$this->setUserGUI($styleDefinition);
			}
		}
	}
}
?>
