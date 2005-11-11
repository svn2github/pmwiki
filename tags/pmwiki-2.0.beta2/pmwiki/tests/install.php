<?php if (!defined('PmWiki')) exit();
/*  
    This file sets up a basic configuration for testing that common
    functions work upon a first install.  The purpose of this
    file is to provide Pm with a standard installation config
    that can be tested on a variety of combinations of operating
    systems, PHP versions, webservers, and PHP configurations.

    To participate in the test, simply install a fresh copy of
    PmWiki somewhere, and place the following in local/config.php:

            include_once('tests/install.php');

    Then run through the steps described at 
    http://www.pmwiki.org/pmwiki2/pmwiki.php/Development/InstallTest.
    If you run into any errors while performing the steps, report them
    to PITS or the pmwiki-users mailing list.
*/

$EnableUpload = 1;
$DefaultPasswords['upload'] = crypt('upload');
$EnableDiag = 1;
?>
