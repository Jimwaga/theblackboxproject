<?php

/**
 * CONFIG
 * Module: Midnite Classic 
 * 
 * Revision: $Rev$
 *
 **/
 
 
 
//====================================================================================//

//STD 
$set['module_name']=         'Midnite Classic';
$set['module_order']=        1;

$set['sample_device']=       true;  //unused?
$set['sample_during_hours']= '06-19'; //unused?
$set['sample_interval']=     $GLOBALS['SETTINGS']['sample_interval'] * 60;

$set['store_in_db']=         true;
$set['store_interval']=      $GLOBALS['SETTINGS']['sample_interval'] * 60; 
$set['store_db_table']=      'classiclogs';
$set['store_db_table_day']=  'classicdaylogs';


//SET IP ADDRESS
//this is the static ip address of the classic
$set['ip_address']=         '192.168.0.223';
$set['modbus_port']=        '502';

//SET NEWMODBUS BINARY
//choose which architecture and version from one of the files in the module folder
//its assumed that the path is the relative to this module folder
//the file must be chmod executable
$set['newmodbus_mode']=   'normal'; // normal|daemon
$set['newmodbus_ver']=    'newmodbus-1.0.19-ARM';
$set['newmodbusd_log']=   '/home/tasks/blackbox/data.txt';// required only for daemon mode


//===================================================================================//



?>