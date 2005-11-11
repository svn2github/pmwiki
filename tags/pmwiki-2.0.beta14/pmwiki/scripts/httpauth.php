<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file defines an alternate authentication scheme based on the
    HTTP Basic authentication protocol (i.e., the scheme used by default
    in PmWiki 1).
*/

$AuthFunction = 'HTTPBasicAuth';

## HTTPBasicAuth provides password-protection of pages using HTTP Basic
## Authentication.  It is normally called from RetrieveAuthPage.
function HTTPBasicAuth($pagename,$level,$authprompt=true) {
  global $AuthRealmFmt,$AuthDeniedFmt,$DefaultPasswords,
    $AllowPassword,$GroupAttributesFmt;
  SDV($GroupAttributesFmt,'$Group/GroupAttributes');
  SDV($AllowPassword,'nopass');
  SDV($AuthRealmFmt,$GLOBALS['WikiTitle']);
  SDV($AuthDeniedFmt,'A valid password is required to access this feature.');
  $page = ReadPage($pagename);
  if (!$page) { return false; }
  $passwd = @$page["passwd$level"];
  if ($passwd=="") { 
    $grouppg = ReadPage(FmtPageName($GroupAttributesFmt,$pagename));
    $passwd = @$grouppg["passwd$level"];
    if ($passwd=='') $passwd = @$DefaultPasswords[$level];
    if ($passwd=='') $passwd = @$page["passwdread"];
    if ($passwd=='') $passwd = @$grouppg["passwdread"];
    if ($passwd=='') $passwd = @$DefaultPasswords['read'];
  }
  if ($passwd=='') return $page;
  if (crypt($AllowPassword,$passwd)==$passwd) return $page;
  @session_start();
  if (@$_SERVER['PHP_AUTH_PW']) @$_SESSION['authpw'][$_SERVER['PHP_AUTH_PW']]++;
  $authpw = array_keys((array)@$_SESSION['authpw']);
  foreach(array_merge((array)$DefaultPasswords['admin'],(array)$passwd)
      as $pwchal)
    foreach($authpw as $pwresp)
      if (@crypt($pwresp,$pwchal)==$pwchal) return $page;
  if (!$authprompt) return false;
  $realm=FmtPageName($AuthRealmFmt,$pagename);
  header("WWW-Authenticate: Basic realm=\"$realm\"");
  header("Status: 401 Unauthorized");
  header("HTTP-Status: 401 Unauthorized");
  PrintFmt($pagename,$AuthDeniedFmt);
  exit;
}

?>
