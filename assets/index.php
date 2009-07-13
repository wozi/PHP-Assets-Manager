<?php
/**
 *	Assets Manager index file
 *	Takes care of loading the right files
 */
include('assets-manager/class.assets-manager.php');
$ap = new AssetsManager ('config/config.yml');

/**
 *	For automatic packaging, use getAuto ().
 *	The right .htaccess has to be set in this file's folder in order to use this feature.
 */
$ap->getAuto ();

/**
 *	To call a special file by yourself, use get () instead
 *	$ap->get ('screen');
 */
?>