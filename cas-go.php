<?php
// Load the settings from the central config file
require_once '../../config.php';
// Load the CAS lib
require_once '../../CAS.php';
// Enable debugging
phpCAS::setDebug();
// Enable verbose error messages. Disable in production!
phpCAS::setVerbose(true);

// Initialize phpCAS
phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
phpCAS::setNoCasServerValidation();
if (isset($_REQUEST['logout'])) {
  if ($_SERVER['SERVER_NAME'] == 'localhost') {
    $redirect = $_SERVER['SERVER_NAME']."/~Ben/curriculum/editors/";
  } else {$redirect = $_SERVER['SERVER_NAME']."/curriculum/editors/";}
    phpCAS::logout();
}
if (isset($_REQUEST['logout'])) {
  phpCAS::logout();
}

$auth = phpCAS::checkAuthentication();
if (isset($prompt_id)) {
  $additional ="&prompt_id=$prompt_id";
} else {
  $additional="";
}

if ($auth) {$net_id = phpCAS::getUser();
  $netid = phpCAS::getUser();
  $name = phpCAS::getAttributes()['name'];
  $login = $name." | <a href='?logout='>Logout</a>";
} else {    
  phpCAS::forceAuthentication();
  $id = '';
}

$name = phpCAS::getAttributes()['name'];
?>
