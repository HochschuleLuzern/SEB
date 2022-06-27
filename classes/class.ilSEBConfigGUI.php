<?php  declare(strict_types = 1);
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

use ILIAS\DI\UIServices;

class ilSEBConfigGUI extends ilPluginConfigGUI
{
    private $pl;
    private $config;
    private $ui;
    private $lang;
    private $ctrl;
    private $rbac_review;
    
    public function performCommand($cmd)
    {
        switch ($cmd) {
            case "configure":
            case "save":
                global $DIC;
                $this->pl = $this->getPluginObject();
                $this->config = new ilSEBConfig($DIC->database());
                $this->ui = $DIC->ui();
                $this->lang = $DIC->language();
                $this->ctrl = $DIC->ctrl();
                $this->rbac_review = $DIC->rbac()->review();
                $this->$cmd();
                break;

        }
    }
    
    public function configure() : void
    {
        $form = $this->initConfigurationForm();
        $this->ui->mainTemplate()->setContent($form->getHTML());
    }
    
    public function initConfigurationForm() : ilPropertyFormGUI
    {
        $roles = array(0 => $this->pl->txt("role_none"),1 => $this->pl->txt("role_all_except_admin"));
        
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
        $gr = $this->rbac_review->getGlobalRoles();
        
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
        
        $show_pax_pic_cb = new ilCheckboxInputGUI($this->pl->txt('show_pax_pic'), 'show_pax_pic');
        $show_pax_pic_cb->setInfo($this->pl->txt('show_pax_pic_info'));
        $show_pax_pic_cb->setChecked($this->config->getShowPaxPic());
        $form->addItem($show_pax_pic_cb);
        
        $show_pax_matriculation_cb = new ilCheckboxInputGUI($this->pl->txt('show_pax_matriculation'), 'show_pax_matriculation');
        $show_pax_matriculation_cb->setInfo($this->pl->txt('show_pax_matriculation_info'));
        $show_pax_matriculation_cb->setChecked($this->config->getShowPaxMatriculation());
        $form->addItem($show_pax_matriculation_cb);
        
        $show_pax_username_cb = new ilCheckboxInputGUI($this->pl->txt('show_pax_username'), 'show_pax_username');
        $show_pax_username_cb->setInfo($this->pl->txt('show_pax_username_info'));
        $show_pax_username_cb->setChecked($this->config->getShowPaxUsername());
        $form->addItem($show_pax_username_cb);
        
        $form->addCommandButton('save', $this->lang->txt('save'));
                    
        $form->setTitle($this->pl->txt('config'));
        $form->setFormAction($this->ctrl->getFormAction($this));
        
        return $form;
    }
    
    public function save() : void
    {
        $form = $this->initConfigurationForm();
        if ($form->checkInput()) {
            // ToDo validate
            
            $form_input['seb_keys'] = $form->getInput('seb_keys');
            $form_input['allow_object_keys'] = $form->getInput('allow_object_keys');
            $form_input['role_deny'] = $form->getInput('role_deny');
            $form_input['role_kiosk'] = $form->getInput('role_kiosk');
            $form_input['activate_session_control'] = $form->getInput('activate_session_control');
            $form_input['show_pax_pic'] = $form->getInput('show_pax_pic');
            $form_input['show_pax_matriculation'] = $form->getInput('show_pax_matriculation');
            $form_input['show_pax_username'] = $form->getInput('show_pax_username');
            
            $success = $this->config->saveSEBConf($form_input);

            if ($success < 0) {
                ilUtil::sendFailure($this->pl->txt("save_failure"), true);
            } elseif ($success == 0) {
                ilUtil::sendInfo($this->pl->txt("nothing_changed"), true);
            } else {
                ilUtil::sendSuccess($this->pl->txt("save_success"), true);
            }
            $this->configure();
        } else {
            $form->setValuesByPost();
            $this->ui->mainTemplate()->setContent($form->getHtml());
        }
    }
}
