<?php

include_once './Services/Component/classes/class.ilPluginConfigGUI.php';
include_once './Services/PrivacySecurity/classes/class.ilSecuritySettings.php';
include_once 'class.ilSEBPlugin.php';
include_once 'class.ilSEBConfig.php';
/**
 * Example configuration user interface class
 *
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 *
 */
class ilSEBConfigGUI extends ilPluginConfigGUI {
	private $pl;
	private $config;
	private $DIC;

    /**
	* Handles all commmands, default is "configure"
	*/
	function performCommand($cmd) {
		switch ($cmd)
		{
			case "configure":
			case "save":
			    global $DIC;
			    $this->DIC = &$DIC;
			    $this->pl = $this->getPluginObject();
			    $this->config = ilSEBConfig::getInstance();
				$this->$cmd();
				break;

		}
	}

	/**
	 * Configure screen
	 */
	function configure() {
		$form = $this->initConfigurationForm();
		$this->DIC->ui()->mainTemplate()->setContent($form->getHTML());
	}
	
	//
	// From here on, this is just an example implementation using
	// a standard form (without saving anything)
	//
	
	/**
	 * Init configuration form.
	 *
	 * @return object form object
	 */
	public function initConfigurationForm() {
		$roles = array(0=>$this->pl->txt("role_none"),1=>$this->pl->txt("role_all_except_admin"));
		
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		
		// allow object keys
		$allow_object_keys_cb = new ilCheckboxInputGUI($this->pl->txt("allow_object_keys"), "allow_object_keys");
		$allow_object_keys_cb->setInfo($this->pl->txt("allow_object_keys_info"));
		$allow_object_keys_cb->setChecked($this->config->getAllowObjectKeys());
		$form->addItem($allow_object_keys_cb);
		
		// SEB key
		$seb_keys_txt = new ilTextInputGUI($this->pl->txt("seb_keys"), "seb_keys");
		$seb_keys_txt->setInfo($this->pl->txt("seb_keys_info"));
		$seb_keys_txt->setMaxLength(2000);
		$seb_keys_txt->setValue($this->config->getSEBKeysString());
		$form->addItem($seb_keys_txt);
		
		// global role access deny
		$gr = $this->DIC->rbac()->review()->getGlobalRoles();
		
		foreach ($gr as $rid) {
			//if ($role_id != 2 && $role_id != 5 && $role_id != 14) {
			if ($rid != 2 && $rid != 14) { // no admin no anomymous
				$roles[$rid] = ilObject::_lookupTitle($rid);
			}
		}
		$role_deny_sel = new ilSelectInputGUI($this->pl->txt("role_deny"), "role_deny");
		$role_deny_sel->setInfo($this->pl->txt("role_deny_info"));
		$role_deny_sel->setRequired(false);
		$role_deny_sel->setOptions($roles);
		$role_deny_sel->setValue($this->config->getRoleDeny());
		$form->addItem($role_deny_sel);
		
		$role_kiosk_sel = new ilSelectInputGUI($this->pl->txt("role_kiosk"), "role_kiosk");
		$role_kiosk_sel->setInfo($this->pl->txt("role_kiosk_info"));
		$role_kiosk_sel->setRequired(false);
		$role_kiosk_sel->setOptions($roles);
		$role_kiosk_sel->setValue($this->config->getRoleKiosk());
		$form->addItem($role_kiosk_sel);
		
		// activate session control
		$activate_session_control_cb = new ilCheckboxInputGUI($this->pl->txt('activate_session_control'), 'activate_session_control');
		$security = ilSecuritySettings::_getInstance();
		if ($security->isPreventionOfSimultaneousLoginsEnabled()) {
			$activate_session_control_cb->setInfo($this->pl->txt('activate_session_control_info'));
		} else {
			$activate_session_control_cb->setInfo($this->pl->txt('activate_session_control_info_disabled'));
			$activate_session_control_cb->setDisabled(true);
		}
		$activate_session_control_cb->setChecked($this->config->getActivateSessionControl());
		$form->addItem($activate_session_control_cb);
		
		// show pax picture
		$show_pax_pic_cb = new ilCheckboxInputGUI($this->pl->txt('show_pax_pic'), 'show_pax_pic');
		$show_pax_pic_cb->setInfo($this->pl->txt('show_pax_pic_info'));			
		$show_pax_pic_cb->setChecked($this->config->getShowPaxPic());
		$form->addItem($show_pax_pic_cb);
		
		$form->addCommandButton('save', $this->DIC->language()->txt('save'));
	                
		$form->setTitle($this->pl->txt('config'));
		$form->setFormAction($this->DIC->ctrl()->getFormAction($this));
		
		return $form;
	}
	
	/**
	 * Save form input (currently does not save anything to db)
	 *
	 */
	public function save() {
		$this->pl = $this->getPluginObject();
		$form = $this->initConfigurationForm();
		if ($form->checkInput()) {
			// ToDo validate
			
		    $form_input['seb_keys'] = $form->getInput('seb_keys');
		    $form_input['allow_object_keys'] = $form->getInput('allow_object_keys');
		    $form_input['role_deny'] = $form->getInput('role_deny');
		    $form_input['role_kiosk'] = $form->getInput('role_kiosk');
		    $form_input['activate_session_control'] = $form->getInput('activate_session_control');
		    $form_input['show_pax_pic'] = $form->getInput('show_pax_pic');
		    
		    $success = $this->config->saveSEBConf($form_input);

			if ($success < 0) {
				ilUtil::sendFailure($this->pl->txt("save_failure"), true);
			} else if ($success == 0) {
				ilUtil::sendInfo($this->pl->txt("nothing_changed"), true);
			}else {	
				ilUtil::sendSuccess($this->pl->txt("save_success"), true);							
			}
			$this->configure();
		} else {
			$form->setValuesByPost();
			$this->DIC->ui()->mainTemplate()->setContent($form->getHtml());
		}
	}
}
?>
