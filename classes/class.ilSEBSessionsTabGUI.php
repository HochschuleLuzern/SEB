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

include_once './Modules/Test/classes/class.ilTestParticipantData.php';
include_once './Modules/Test/classes/class.ilObjTest.php';
include_once './Services/User/classes/class.ilObjUser.php';
include_once './Services/Authentication/classes/class.ilSession.php';
include_once 'class.ilSEBPlugin.php';
include_once 'class.ilSEBSessionsTableGUI.php';

class ilSEBSessionsTabGUI {
    /** @var $object ilObjComponentSettings */
    protected $object;
    
    private $tpl;
    private $pl;
    private $conf;
    private $ctrl;
    private $user;
    private $tabs;
    private $db;
    private $lang;
    private $obj_def;
    private $ref_id;
    
    function __construct()
    {
        /**
         * @var $ilCtrl ilCtrl
         * @var $ilUser ilObjUser
         * @var $ilTabs ilTabsGUI
         */
        global $DIC;
        
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->user = $DIC->user();
        $this->tabs = $DIC->tabs();
        $this->db = $DIC->database();
        $this->lang = $DIC->language();
        $this->obj_def = $DIC['objDefinition'];
        
        $this->pl = ilSEBPlugin::getInstance();
        $this->conf = ilSEBConfig::getInstance();
        
        $this->ref_id = $_GET['ref_id'];
        $this->object = ilObjectFactory::getInstanceByRefId($this->ref_id);
        
        $this->ctrl->setParameter($this, 'ref_id', $this->ref_id);
    }
  
    function executeCommand() {
        // Fill content
        $cmd = $this->ctrl->getCmd();

        switch($cmd)
        {
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
        
        
    }
    
    private function showSessions($mode) {
        $this->initHeader();
        
        if (($sessions = $this->getSessionsForTestParticipants()) &&
        		($users = $this->getUsersForSessions($sessions)) !== false) {
        			
        	if ($mode == 'reset') {
        		unset($_POST['user']);
	        } else if (isset($_POST['user']) && $_POST['user'] != '') {
        		foreach ($users as $index => $user) {
        			if (!stristr($user['login'], $_POST['user']) && !stristr($user['first_name'], $_POST['user'])) {
        				unset($users[$index]);
        			}
        		}
        	}
        } else {
        	$users = [];			
        }
        
        $this->initSessionTable($users, 'show');
    }
    
    private function initSessionTable($users, $action) {
    	if ($action == 'show') {
	    	$sessions_table = new ilSEBSessionsTableGUI($this, 'confirmDeleteSessions');
    	} else if ($action == 'confirm') {
    		$sessions_table = new ilSEBSessionsTableGUI($this, 'deleteSessions');
    	}
    	$sessions_table->setData($users);
    	
    	if (isset($_GET['_table_nav'])) {
    		$ordering = explode(':', $_GET['_table_nav']);
    		
    		if (in_array($ordering[0], ['login', 'first_name', 'last_name']) &&
    				in_array($ordering[1], ['asc', 'desc'])	) {
    					$sessions_table->setOrderColumn($ordering[0]);
    					$sessions_table->setOrderDirection($direction);
    				}
    	}
    	
    	$this->tpl->setContent($sessions_table->getHTML());
    	$this->tpl->getStandardTemplate();
    	$this->tpl->show();
    }
    
    private function confirmDeleteSessions() {
    	$this->initHeader();
    	
    	if (count($_POST['id']) > 0) {
	    	$sessions = implode("','", $_POST['id']);
	    	$q = $this->db->query("SELECT session_id, user_id FROM usr_session WHERE session_id IN (".$this->db->quote($sessions, 'text').") AND expires > ".time());
	    	$sessions = $this->db->fetchAll($q);
	    	$users = $this->getUsersForSessions($sessions);
	    	$this->initSessionTable($users, 'confirm');
    	} else {
    		ilUtil::sendInfo($this->pl->txt('no_sessions_selected'));
    		$this->showSessions('show');
    	}
    }
    
    private function deleteSessions() {
    	foreach ($_POST['id'] as $session) {
    		ilSession::_destroy($session);
    	}
    	
    	ilUtil::sendSuccess($this->pl->txt('sessions_deleted'));
    	$this->showSessions('show');
    }
    
    private function initHeader() {
        global $DIC;
        
        /* Add breadcrumbs */
        $DIC['ilLocator']->addRepositoryItems($this->ref_id);
        $DIC['ilLocator']->addItem($this->object->getTitle(), 
           $this->ctrl->getLinkTargetByClass(array(
                'ilRepositoryGUI',
                'ilObj' . $this->obj_def->getClassName($this->object->getType()) . 'GUI'
                ),
            "",
            $this->ref_id));
        $this->tpl->setLocator();
        
        /* Add title, description and icon of the current repositoryobject */
        $this->tpl->setTitle($this->object->getTitle());
        $this->tpl->setDescription($this->object->getDescription());
        $this->tpl->setTitleIcon(ilUtil::getTypeIconPath($this->object->getType(), $this->object->getId(), 'big'));
        
        /* Create and add backlink */
        $back_link = $this->ctrl->getLinkTargetByClass(array(
            'ilRepositoryGUI',
            'ilObj' . $this->obj_def->getClassName($this->object->getType()) . 'GUI'
        ));
        
        $class_name = $this->obj_def->getClassName($this->object->getType());
        $this->ctrl->setParameterByClass('ilObj' . $class_name . 'GUI', 'ref_id', $this->ref_id);
        $this->tabs->setBackTarget($DIC->language()->txt('back'), $back_link);
    }
    
    private function getSessionsForTestParticipants($filter = []) {
    	$test = new ilObjTest($this->ref_id);
    	$pax_data = new ilTestParticipantData($this->db, $this->lang);
    	$pax_data->load($test->getTestId());
    	$paxs_array = $pax_data->getUserIds();
    	if (count($filter) > 0) {
    		array_intersect($paxs_array, $filter);
    	}
    	
    	$paxs = implode("','", $paxs_array);
    	
    	$q = $this->db->query("SELECT session_id, user_id FROM usr_session WHERE user_id IN ('$paxs') AND expires > ".time());
    	return $this->db->fetchAll($q);
    }
    
    private function getUsersForSessions($sessions) {
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