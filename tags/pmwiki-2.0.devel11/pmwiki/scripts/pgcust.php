<?php if (!defined('PmWiki')) exit();
/*  Copyright 2002-2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script enables per-page and per-group customizations in the
    local/ subdirectory.  For example, to create customizations for
    the 'Demo' group, place them in a file called local/Demo.php.
    To customize a single page, use the full page name (e.g., 
    local/Demo.MyPage.php).  If neither a per-page nor a per-group
    customization file exists, then the file 'default.php' is
    included if it exists.
   
    Per-group customizations can be handled at any time by adding
	include_once("scripts/pgcust.php");
    to config.php.  It is automatically included by scripts/stdconfig.php
    unless $EnablePerGroupCust is set to zero in local.php.
*/

SDV($DefaultPage,"$DefaultGroup.$DefaultName");
if ($pagename=='') $pagename=$DefaultPage;

for($p=$pagename;$p;$p=preg_replace('/\\.*[^.]*$/','',$p)) {
  if (file_exists("local/$p.php")) {
    include_once("local/$p.php");
    return;
  }
}
if (file_exists('local/default.php'))
  include_once('local/default.php');

?>
