<?php declare(strict_types=1);
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

include_once 'class.ilSEBPlugin.php';
include_once 'class.ilSEBTabGUI.php';

/**
 * @ilCtrl_isCalledBy ilSEBSettingsTabGUI: ilRouterGUI, ilUIPluginRouterGUI
 */
class ilSEBSettingsTabGUI extends ilSEBTabGUI
{
    public function executeCommand() : void
    {
        if ($this->rbac_system->checkAccess('write', $this->ref_id) && (in_array(($cmd = $this->ctrl->getCmd()), ['seb_settings', 'save']))) {
            switch ($cmd) {
                case 'seb_settings':
                    $this->showSettings();
                    break;
                case 'save':
                    $this->save();
                    break;
            }
        } else {
            $this->ctrl->returnToParent($this);
        }
    }
    
    private function showSettings() : void
    {
        $this->setupUI();
        $form = $this->initConfigurationForm();
        
        $this->tpl->setContent($form->getHTML());
        echo $this->tpl->printToStdOut();
    }
    
    private function save() : void
    {
        $form = $this->initConfigurationForm();
        
        if ($form->checkInput()) {
            $seb_key_win = $form->getInput('seb_key_win');
            $seb_key_macos = $form->getInput('seb_key_macos');
            
            $success = $this->conf->saveObjectKeys($this->ref_id, $seb_key_win, $seb_key_macos);
            
            if ($success < 0) {
                ilUtil::sendFailure($this->pl->txt("save_failure"), true);
            } elseif ($success == 0) {
                ilUtil::sendInfo($this->pl->txt("nothing_changed"), true);
            } else {
                ilUtil::sendSuccess($this->pl->txt("save_success"), true);
            }
            $this->showSettings();
        } else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            $this->tpl->printToStdOut();
        }
    }
    
    private function initConfigurationForm() : ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormActionByClass('ilSEBSettingsTabGUI', 'save'));
        $form->setTitle($this->pl->txt('title_settings_form'));
        $form->setDescription($this->pl->txt('description_settings_form'));
        $form->addCommandButton('save', $this->lang->txt('save'));
        
        $keys = $this->conf->getObjectKeys($this->ref_id);
        
        $key_windows_txt = new ilTextInputGUI($this->pl->txt('key_windows'), 'seb_key_win');
        $key_windows_txt->setInfo($this->pl->txt('key_windows_info'));
        $key_windows_txt->setRequired(false);
        $key_windows_txt->setSize(50);
        $key_windows_txt->setMaxLength(2000);
        $key_windows_txt->setValue($keys['seb_key_win']);
        $form->addItem($key_windows_txt);
        
        $key_macos_txt = new ilTextInputGUI($this->pl->txt('key_macos'), 'seb_key_macos');
        $key_macos_txt->setInfo($this->pl->txt('key_macos_info'));
        $key_macos_txt->setRequired(false);
        $key_macos_txt->setSize(50);
        $key_macos_txt->setMaxLength(2000);
        $key_macos_txt->setValue($keys['seb_key_macos']);
        $form->addItem($key_macos_txt);
        
        return $form;
    }
}
