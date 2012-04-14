<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'groups';
$app['version'] = '1.1.0';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('groups_app_description');
$app['tooltip'] = lang('groups_tooltip');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('groups_app_name');
$app['category'] = lang('base_category_system');
$app['subcategory'] = lang('base_subcategory_accounts');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['requires'] = array(
    'app-accounts',
    'app-users',
);

$app['core_requires'] = array(
    'app-accounts-core',
    'app-base-core >= 1:1.0.6',
    'system-groups-driver', 
);
