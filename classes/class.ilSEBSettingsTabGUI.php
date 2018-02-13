<?php
include_once("class.ilSEBPlugin.php");

/**
 * GUI-Class ilSEBSettingsTabGUI
 *
 * @author      Raphael Heer <raphael.heer@hslu.ch>
 * @version     $Id:
 *
 * @ilCtrl_isCalledBy ilSEBSettingsTabGUI: ilRouterGUI, ilUIPluginRouterGUI
 */
class ilSEBSettingsTabGUI {
    /** @var $object ilObjComponentSettings */
    protected $object;
    
    private $tpl;
    private $pl;
    private $conf;
    private $ctrl;
    private $user;
    private $tabs;
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
        $this->obj_def = $DIC['objDefinition'];
        
        $this->pl = ilSEBPlugin::getInstance();
        $this->conf = ilSEBConfig::getInstance();
        
        $this->ref_id = $_GET['ref_id'];
        $this->object = ilObjectFactory::getInstanceByRefId($this->ref_id);
        
        $this->ctrl->setParameter($this, 'ref_id', $this->ref_id);
    }
  
    function executeCommand() {
        global $ilCtrl;
        // Fill content
        $cmd = $ilCtrl->getCmd();
        
        switch($cmd)
        {
            case 'seb_settings':
                $this->showSettings();
                break;
            case 'save';
                $this->save();
                break;
            default:
                $this->defaultcmd();
        }
        
        
    }
    
    private function showSettings() {
        $this->initHeader();
        $form =  $this->initConfigurationForm();
        
        $this->tpl->setContent($form->getHTML());
        $this->tpl->getStandardTemplate();
        $this->tpl->show();
    }
    
    private function save() {
        $form = $this->initConfigurationForm();
        
        if ($form->checkInput()) {
            $seb_key_win = $form->getInput('seb_key_win');
            $seb_key_macos = $form->getInput('seb_key_macos');
            
            $success = $this->conf->saveObjectKeys($this->ref_id, $seb_key_win, $seb_key_macos);
            
            if ($success < 0) {
                ilUtil::sendFailure($this->pl->txt("save_failure"), true);
            } else if ($success == 0) {
                ilUtil::sendInfo($this->pl->txt("nothing_changed"), true);
            }else {
                ilUtil::sendSuccess($this->pl->txt("save_success"), true);
            }
            $this->showSettings();
        } else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            $this->tpl->show();
        }
    }
    
    function defaultcmd() {
        $tpl->setContent(print_r("123", true));
    }
    
    private function initConfigurationForm() {
        include_once('Services/Form/classes/class.ilPropertyFormGUI.php');
        
        global $DIC;
        
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormActionByClass('ilSEBSettingsTabGUI', 'save'));
        $form->setTitle($this->pl->txt('title_settings_form'));
        $form->setDescription($this->pl->txt('description_settings_form'));
        $form->addCommandButton('save', $DIC->language()->txt('save'));
        
        $keys = $this->conf->getObjectKeys($this->ref_id);
        
        $key_windows_txt = new ilTextInputGUI($this->pl->txt('key_windows'), 'seb_key_win');
        $key_windows_txt->setInfo($this->pl->txt('key_windows_info'));
        $key_windows_txt->setRequired(false);
        $key_windows_txt->setSize(50);
        $key_windows_txt->setValue($keys['seb_key_win']);
        $form->addItem($key_windows_txt);
        
        $key_macos_txt = new ilTextInputGUI($this->pl->txt('key_macos'), 'seb_key_macos');
        $key_macos_txt->setInfo($this->pl->txt('key_macos_info'));
        $key_macos_txt->setRequired(false);
        $key_macos_txt->setSize(50);
        $key_macos_txt->setValue($keys['seb_key_macos']);
        $form->addItem($key_macos_txt);
        
        return $form;
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
}