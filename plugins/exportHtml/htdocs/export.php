<?php
/**
 * Script called by standard Cartoclient GUI to generate export outside of
 * current browser
 * @package Plugins
 * @version $Id$
 */

/**
 * Dir path to Cartoclient home
 */
if (!defined('CARTOCLIENT_HOME'))
    define('CARTOCLIENT_HOME', realpath(dirname(__FILE__) .
                                        '/../../..') . '/');

if (!defined('CARTOCOMMON_HOME'))
    define('CARTOCOMMON_HOME', CARTOCLIENT_HOME);

require_once(CARTOCOMMON_HOME . 'common/Common.php');
Common::preInitializeCartoweb(array('client' => true));

require_once(CARTOCLIENT_HOME . 'client/Cartoclient.php');

$cartoclient = new Cartoclient();
if (!$cartoclient->clientAllowed()) {
    return;
}

$plugin = $cartoclient->getPluginManager()->getCurrentPlugin();

header('Content-Type: text/html');

print $plugin->getExport()->getContents();

?>
