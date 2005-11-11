<?php if (!defined('PmWiki')) exit();
/*  Copyright 2005 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script enables simple authentication based on username and 
    password combinations.  At present this script can authenticate
    from passwords held in arrays or in .htpasswd-formatted files,
    but eventually it will support authentication via sources such
    as LDAP and Active Directory.

    To configure a .htpasswd-formatted file for authentication, do
        $AuthUser['htpasswd'] = '/path/to/.htpasswd';
    prior to including this script.  

    Individual username/password combinations can also be placed
    directly in the $AuthUser array, such as:
        $AuthUser['pmichaud'] = crypt('secret');

*/

# Let's set up an authorization prompt that includes usernames.
SDV($AuthPromptFmt, array(&$PageStartFmt,
  "<p><b>Password required</b></p>
    <form name='authform' action='{$_SERVER['REQUEST_URI']}' method='post'>
      Name: <input tabindex='1' type='text' name='authid' value='' /><br />
      Password: <input tabindex='2' type='password' name='authpw' value='' />
      <input type='submit' value='OK' />\$PostVars</form>
      <script language='javascript'><!--
        document.authform.authid.focus() //--></script>", &$PageEndFmt));

# This is a helper function called when someone meets the
# authentication credentials:
function AuthenticateUser($authid) {
  $GLOBALS['AuthId'] = $authid;
  @session_start(); $_SESSION['authid'] = $authid;
}

# If the admin hasn't configured any password entries, just return.
if (!$AuthUser) return;

# Now, let's get the $id and $pw to be checked -- we'll first take them 
# from a submitted form, if any; if not there then we'll check and see
# if they're available from HTTP basic authentication.  If we don't
# have any $id at all, we just exit since there's nothing to 
# authenticate here.
if (@$_POST['authid']) 
  { $id = $_POST['authid']; $pw = $_POST['authpw']; }
else if (@$_SERVER['PHP_AUTH_USER']) 
  { $id = $_SERVER['PHP_AUTH_USER']; $pw = $_SERVER['PHP_AUTH_PW']; }
else return;

# Okay, we have $id and $pw, now let's see if we can find any
# matching entries.  First, let's check the $AuthUser array directly:
if (@$AuthUser[$id]) 
  foreach((array)($AuthUser[$id]) as $c)
    if (crypt($pw, $c) == $c) { AuthenticateUser($id); return; }

# Now lets check any .htpasswd file equivalents
foreach((array)($AuthUser['htpasswd']) as $f) {
  $fp = fopen($f, "r"); if (!$fp) continue;
  while ($x = fgets($fp, 1024)) {
    $x = rtrim($x);
    list($i, $c, $r) = explode(':', $x, 3);
    if ($i == $id && crypt($pw, $c) == $c) 
      { fclose($fp); AuthenticateUser($id); return; }
  }
  fclose($fp);
}


