<?php  declare(strict_types=1);
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
include_once 'class.ilSEBSessionsTableGUI.php';

/**
 * GUI Class to show a tab to manage Sessions in Test Object
 *
 * @author Stephan Winiker <stephan.winiker@hslu.ch>
 *
 * @ilCtrl_isCalledBy ilSEBSessionsTabGUI: ilRouterGUI, ilUIPluginRouterGUI
 */
class ilSEBSessionsTabGUI extends ilSEBTabGUI
{
    public function executeCommand() : void
    {
        if ($this->rbac_system->checkAccess('write', $this->ref_id) && (in_array(($cmd = $this->ctrl->getCmd()), ['showSessions', 'applyFilter', 'resetFilter', 'confirmDeleteSessions', 'deleteSessions']))) {
            switch ($cmd) {
                case 'showSessions':
                case 'applyFilter':
                    $this->showSessions('show');
                    break;
                case 'resetFilter':
                    $this->showSessions('reset');
                    break;
                case 'confirmDeleteSessions':
                    $this->confirmDeleteSessions();
                    break;
                case 'deleteSessions':
                    $this->deleteSessions();
                    break;
            }
        } else {
            $this->ctrl->returnToParent($this);
        }
    }
    
    /**
     * @param string $mode One of 'show' indicating that the list of sessions should simply be shown applying all filters or 'reset' if the filters need to be reset
     */
    private function showSessions(string $mode) : void
    {
        $this->setupUI();
        
        if (($sessions = $this->getSessionsArrayOfTestParticipants()) &&
                ($users = $this->addUsersInfoToSessionsArray($sessions))) {
            if ($mode == 'reset') {
                unset($_POST['user']);
            } elseif (isset($_POST['user']) && $_POST['user'] != '') {
                foreach ($users as $index => $user) {
                    if (!stristr($user['login'], $_POST['user']) &&
                            !stristr($user['first_name'], $_POST['user']) &&
                            !stristr($user['last_name'], $_POST['user'])) {
                        unset($users[$index]);
                    }
                }
            }
        } else {
            $users = [];
        }
        
        $this->initSessionTable($users, 'confirmDeleteSessions');
    }
    
    private function confirmDeleteSessions() : void
    {
        $this->setupUI();
        
        if (count($_POST['id']) > 0) {
            $sessions = implode("','", $_POST['id']);
            $q = $this->db->query("SELECT session_id, user_id FROM usr_session WHERE session_id IN (" . $this->db->quote($sessions, 'text') . ") AND expires > " . time());
            $sessions = $this->db->fetchAll($q);
            $users = $this->addUsersInfoToSessionsArray($sessions);
            $this->initSessionTable($users, 'deleteSessions');
        } else {
            ilUtil::sendInfo($this->pl->txt('no_sessions_selected'));
            $this->showSessions('show');
        }
    }
    
    private function deleteSessions() : void
    {
        foreach ($_POST['id'] as $session) {
            ilSession::_destroy($session);
        }
        
        ilUtil::sendSuccess($this->pl->txt('sessions_deleted'));
        $this->showSessions('show');
    }
    
    /**
     * @param array $users of all users with sessions to be shown in the table
     * @param string $action One of 'confirmDeleteSessions' if you want to show the participant table or 'deleteSessions' if a list of sessions should be shown for confirmation
     */
    private function initSessionTable(array $users, string $action) : void
    {
        $sessions_table = new ilSEBSessionsTableGUI($this, $action, $this->pl, $this->lang);
        $sessions_table->setData($users);
        
        if (isset($_GET['_table_nav'])) {
            $ordering = explode(':', $_GET['_table_nav']);
            
            if (in_array($ordering[0], ['login', 'first_name', 'last_name']) &&
                    in_array($ordering[1], ['asc', 'desc'])) {
                $sessions_table->setOrderColumn($ordering[0]);
                $sessions_table->setOrderDirection($direction);
            }
        }
        
        $this->tpl->setContent($sessions_table->getHTML());
        $this->tpl->printToStdOut();
    }
    
    /**
     * @return mixed An array of all the sessions or false if none where found
     */
    private function getSessionsArrayOfTestParticipants() : array
    {
        $test = new ilObjTest($this->ref_id);
        $pax_data = new ilTestParticipantData($this->db, $this->lang);
        $pax_data->load($test->getTestId());
        $paxs_array = $pax_data->getUserIds();
        
        $paxs = implode("','", $paxs_array);
        
        $q = $this->db->query("SELECT session_id, user_id FROM usr_session WHERE user_id IN ('$paxs') AND expires > " . time());
        return $this->db->fetchAll($q);
    }
    
    /**
     * @param array An associative array containing user_id and session_id for each record
     * @return array An array of sessions enriched with user information
     */
    private function addUsersInfoToSessionsArray(array $sessions) : array
    {
        $users = [];
        foreach ($sessions as $session) {
            $user_obj = new ilObjUser($session['user_id']);
            
            if ($user_obj->getId() != $this->user->getId()) {
                $users[$session['session_id']]['session_id'] = $session['session_id'];
                $users[$session['session_id']]['login'] = $user_obj->getLogin();
                $users[$session['session_id']]['first_name'] = $user_obj->getFirstname();
                $users[$session['session_id']]['last_name'] = $user_obj->getLastname();
            }
        }
        
        return $users;
    }
}
