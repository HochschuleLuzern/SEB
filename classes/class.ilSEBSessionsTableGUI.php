<?php
include_once './Services/Table/classes/class.ilTable2GUI.php';
include_once 'class.ilSEBPlugin.php';

class ilSEBSessionsTableGUI extends ilTable2GUI
{
    /** @var $pl ilSEBUIHookGUI */
    protected $plugin;
    private $ctrl;
    private $lang;
    private $hide_checkbox = false;
    
    function __construct($object_gui, $cmd)
    {
    	global $DIC;
    	
    	parent::__construct($object_gui, $cmd);
    	$this->plugin = ilSEBPlugin::getInstance();
    	$this->ctrl = $DIC->ctrl();
    	$this->lang = $DIC->language();
    	
    	$this->setRowTemplate("tpl.seb_sessions_table_row.html", "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB/");
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
    	} else if ($cmd == 'deleteSessions') {
    		$this->setTitle($this->plugin->txt('title_delete_sessions_table'));
    		$this->hide_checkbox = true;
    		$this->setHeaderRow();
    		$this->addCommandButton('resetFilter', $this->lang->txt('cancel'));
    		$this->addCommandButton('deleteSessions', $this->lang->txt('delete'));
    	}
    }

    public function initFilter()
    {	
    	$user = $this->addFilterItemByMetaType('user', ilTable2GUI::FILTER_TEXT, false, $this->lang->txt('user'));
    	if (isset($_POST['user'])) {
	    	$this->SetFilterValue($user, filter_var($_POST['user'], FILTER_SANITIZE_STRING));
    	} else {
    		$this->SetFilterValue($user, '');
    	}
    }
    
    protected function setHeaderRow()
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
     * @param unknown $row
     */
    function fillRow($row)
    {
    	if ($this->hide_checkbox) {
    		$this->tpl->setCurrentBlock('id_hidden');
    	} else {
    		$this->tpl->setCurrentBlock('id');
    	}
    	$this->tpl->setVariable('ID', $row['session_id']);
    	$this->tpl->parseCurrentBlock();
  		
    	$this->tpl->setCurrentBlock('tbl_row');
        $this->tpl->setVariable('LOGIN', $row['login']);
        $this->tpl->setVariable('FIRST_NAME', $row['first_name']);
        $this->tpl->setVariable('LAST_NAME', $row['last_name']);
 		$this->tpl->parseCurrentBlock();
    }
}