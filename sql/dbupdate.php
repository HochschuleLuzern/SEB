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
?>
<#1>
<?php
if (!$ilDB->tableExists('ui_uihk_seb_conf')) {
	$data = array(
		'req_header' => 'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH',
		'seb_key' => 0,
		'url_salt' => 0,
		'role_deny' => 1,
		'browser_access' => 1,
		'role_kiosk' => 1,
		'browser_kiosk' => 1
	);
	
	$fields = array(
		'config_json' => array(
			'type' => 'text',
			'length' => '1000',
			'fixed' => false,
			'notnull' => false
		)
	);
	
	$ilDB->createTable("ui_uihk_seb_conf", $fields, true, false);
	$q = 'INSERT INTO ui_uihk_seb_conf (config_json) VALUES (%s)';
	$types = array("text");
	$data = array(json_encode($data));
	$ilDB->manipulateF($q,$types,$data);
}
?>
<#2>
<?php
if (!$ilDB->tableExists('ui_uihk_seb_keys')) {
	$fields = array(
	    'ref_id' => array(
	        'type' => 'integer',
			'length' => 8,
			'notnull' => true
	    ),
	    'seb_key_win' => array(
	        'type' => 'text',
	        'length' => '100',
	        'notnull' => false
	    ),
	    'seb_key_macos' => array(
	        'type' => 'text',
	        'length' => '100',
	        'notnull' => false
	    )
	);
	
	$ilDB->createTable("ui_uihk_seb_keys", $fields);
}
?>
<#3>
<?php
if ($ilDB->tableColumnExists('ui_uihk_seb_conf', 'config_json')) {
	$data = json_decode($ilDB->query('SELECT config_json FROM ui_uihk_seb_conf')->fetchAssoc()['config_json']);
	
	$data->seb_keys = $data->seb_key;
	
	unset($data->seb_key);
	unset($data->url_salt);
	unset($data->req_header);
	unset($data->browser_access);
	unset($data->browser_kiosk);
	
	if ($data->seb_keys === 0) {
	    $data->seb_keys = '';
	}
	
	$data->allow_object_keys = 0;
	
	$ilDB->addTableColumn('ui_uihk_seb_conf', 'name', array(
	    'type' => 'text',
	    'length' => '100',
	    'notnull' => true)
	);
	
	$ilDB->addTableColumn('ui_uihk_seb_conf', 'value', array(
	    'type' => 'text',
	    'length' => '2000',
	    'notnull' => true)
	    );
	
	$s = $ilDB->prepareManip('INSERT INTO ui_uihk_seb_conf(name, value) VALUES (?, ?)', array('text', 'text'));
	
	$ilDB->dropTableColumn('ui_uihk_seb_conf', 'config_json');
	
	foreach ($data as $key => $value) {
	    $s->execute(array($key, $value));
	}
}
?>
<#4>
<?php
if ($ilDB->query("SELECT * FROM ui_uihk_seb_conf WHERE name='activate_session_control'")->numRows() == 0) {
	$data = array(
		'name' => array('text', 'activate_session_control'),
		'value' => array('text', '0')
	);
	$ilDB->insert('ui_uihk_seb_conf', $data);
}
?>
<#5>
<?php
if ($ilDB->query("SELECT * FROM ui_uihk_seb_conf WHERE name='show_pax_pic'")->numRows() == 0) {
	$data = array(
		'name' => array('text', 'show_pax_pic'),
		'value' => array('text', '0')
	);
	$ilDB->insert('ui_uihk_seb_conf', $data);
}
?>
<#6>
<?php
$ilDB->manipulate('ALTER TABLE ui_uihk_seb_keys Modify seb_key_win VARCHAR(2000);');
$ilDB->manipulate('ALTER TABLE ui_uihk_seb_keys Modify seb_key_macos VARCHAR(2000);');
?>