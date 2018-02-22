<#1>
<?php
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
?>
<#2>
<?php
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
?>
<#3>
<?php
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
    'length' => '100',
    'notnull' => true)
    );

$s = $ilDB->prepareManip('INSERT INTO ui_uihk_seb_conf(name, value) VALUES (?, ?)', array('text', 'text'));

$ilDB->dropTableColumn('ui_uihk_seb_conf', 'config_json');

foreach ($data as $key => $value) {
    $s->execute(array($key, $value));
}


?>