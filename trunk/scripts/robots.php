<?php if (!defined('PmWiki')) exit();
/*  Copyright 2005 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file provides various features to allow PmWiki to control
    what web crawlers (robots) see when they visit the site.  Of course
    it's still possible to control robots at the webserver level 
    and via robots.txt, but this page provides some finer level
    of control.

    The $MetaRobots variable controls generation of the 
    <meta name='robots' ... /> tag in the head of the HTML document.
    By default $MetaRobots is set so that robots do not index pages in
    the Site and PmWiki groups.

    The $RobotPattern variable is used to determine if the user agent
    accessing the site is a robot, and $IsRobotAgent is set accordingly.  
    By default this pattern identifies Googlebot, Yahoo! Slurp, msnbot, 
    and HTTrack as robots.

    If the agent is deemed a robot, then the $RobotActions array is
    checked to see if robots are allowed to perform the given action,
    and if not the robot is immediately sent an HTTP 403 Forbidden
    response.

    If the robot has made it through all of the above, then a pattern
    is added to $FmtP to hide any "?action=" parameters in page urls
    that robots aren't allowed to access.  This greatly reduces the
    load on the server by not providing the robot with links to pages
    that it will be forbidden to index anyway.
*/

## $MetaRobots provides the value for the <meta name='robots' ...> tag.
SDV($MetaRobots,
  ($action!='browse' || preg_match('#^PmWiki[./](?!PmWiki$)|^Site[./]#',
    $pagename)) ? 'noindex,nofollow' : 'index,follow');
if ($MetaRobots)
  $HTMLHeaderFmt['robots'] =
    "  <meta name='robots' content='\$MetaRobots' />\n";

## $RobotPattern is used to identify robots.
SDV($RobotPattern,'Googlebot|Slurp|msnbot|HTTrack');
SDV($IsRobotAgent, 
  $RobotPattern && preg_match("!$RobotPattern!", @$_SERVER['HTTP_USER_AGENT']));
if (!$IsRobotAgent) return;

## $RobotActions indicates which actions a robot is allowed to perform.
SDVA($RobotActions, array('browse' => 1, 'rss' => 1, 'dc' => 1));
if (!@$RobotActions[$action]) {
  header("HTTP/1.1 403 Forbidden");
  print("<h1>Forbidden</h1>");
  exit();
}

## The following removes any ?action= parameters that robots aren't
## allowed to access.
$p = create_function('$a', 'return (boolean)$a;');
$p = join('|', array_keys(array_filter($RobotActions, $p)));
$FmtP["/(\\\$ScriptUrl[^#\"'\\s<>]+)\?action=(?!$p)\\w+/"] = '$1';

