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

class ilSEBSessionsTableGUI extends ilTable2GUI
{
    private $plugin;
    private $lang;
    private $hide_checkbox = false;
    
    public function __construct(ilSEBSessionsTabGUI $object_gui, String $cmd, ilSEBPlugin $plugin, ilLanguage $lang)
    {
        parent::__construct($object_gui, $cmd);
        $this->plugin = $plugin;
        $this->lang = $lang;
        
        $this->setRowTemplate('tpl.seb_sessions_table_row.html', 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB/');
        $this->setFormAction($this->ctrl->getFormAction($object_gui, $cmd));
        $this->setEnableHeader(true);
        $this->setExternalSorting(false);
        $this->setDefaultOrderField("login");
        $this->setDefaultOrderDirection("asc");
        
        if ($cmd == 'confirmDeleteSessions') {
            $this->setTitle($this->plugin->txt('title_sessions_table'));
            $this->setHeaderRow();
            $this->initFilter();
            $this->setSelectAllCheckbox("id[]");
            $this->addMultiCommand($cmd, $this->plugin->txt('delete_session_of_users'));
        } elseif ($cmd == 'deleteSessions') {
            $this->setTitle($this->plugin->txt('title_delete_sessions_table'));
            $this->hide_checkbox = true;
            $this->setHeaderRow();
            $this->addCommandButton('deleteSessions', $this->lang->txt('delete'));
            $this->addCommandButton('resetFilter', $this->lang->txt('cancel'));
        }
    }
    
    
    public function initFilter() : void
    {
        $user = $this->addFilterItemByMetaType('user', ilTable2GUI::FILTER_TEXT, false, $this->lang->txt('user'));
        if (isset($_POST['user'])) {
            $this->SetFilterValue($user, filter_var($_POST['user'], FILTER_SANITIZE_STRING));
        } else {
            $this->SetFilterValue($user, '');
        }
    }
    
    protected function setHeaderRow() : void
    {
        if (!$this->hide_checkbox) {
            $this->addColumn('', 'delete_session_checkbox');
        }
        $this->addColumn($this->lang->txt('login'), 'login');
        $this->addColumn($this->lang->txt('firstname'), 'first_name');
        $this->addColumn($this->lang->txt('lastname'), 'last_name');
    }
    
    /**
     *
     * @param array
     */
    public function fillRow($row_data) : void
    {
        if ($this->hide_checkbox) {
            $this->tpl->setCurrentBlock('id_hidden');
        } else {
            $this->tpl->setCurrentBlock('id');
        }
        $this->tpl->setVariable('ID', $row_data['session_id']);
        $this->tpl->parseCurrentBlock();
        
        $this->tpl->setCurrentBlock('tbl_row');
        $this->tpl->setVariable('LOGIN', $row_data['login']);
        $this->tpl->setVariable('FIRST_NAME', $row_data['first_name']);
        $this->tpl->setVariable('LAST_NAME', $row_data['last_name']);
        $this->tpl->parseCurrentBlock();
    }
}
