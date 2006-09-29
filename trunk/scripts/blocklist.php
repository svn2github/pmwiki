<?php if (!defined('PmWiki')) exit();
/*  Copyright 2006 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.
*/


##   Some recipes do page updates outside of the built-in posting
##   cycle, so $EnableBlocklistImmediate is used to determine if
##   we need to catch these.  Currently this defaults to enabled,
##   but at some point we may change the default to disabled.
if (IsEnabled($EnableBlocklistImmediate, 1)) {
  SDVA($BlocklistActions, array('comment' => 1));
  if ($BlocklistActions[$action]) {
    Blocklist($pagename, $_POST['text']);
    if (!$EnablePost) {
      unset($_POST['post']);
      unset($_POST['postattr']);
      unset($_POST['postedit']);
    }
  }
}


##   If $EnableBlocklist is set to 10 or higher, then arrange to 
##   periodically download the "chongqed" and "moinmaster" blacklists.
if ($EnableBlocklist >= 10) {
  SDVA($BlocklistDownload['Site.Blocklist-Chongqed'], array(
    'url' => 'http://blacklist.chongqed.org/',
    'format' => 'regex'));
  SDVA($BlocklistDownload['Site.Blocklist-MoinMaster'], array(
    'url' => 'http://moinmaster.wikiwikiweb.de/BadContent?action=raw',
    'format' => 'regex'));
}


##   CheckBlocklist is inserted into $EditFunctions, to automatically
##   check for blocks on anything being posted through the normal
##   "update a page cycle"
array_unshift($EditFunctions, 'CheckBlocklist');
function CheckBlocklist($pagename, &$page, &$new) 
  { Blocklist($pagename, $new['text']); }


##   Blocklist is the function that does all of the work of
##   checking for reasons to block a posting.  It reads
##   the available blocklist pages ($BlocklistPages) and
##   builds an array of strings and regular expressiongs to
##   be checked against the page; if any are found, then
##   posting is blocked (via $EnablePost=0).  The function
##   also checks the REMOTE_ADDR against any blocked IP addresses.
function Blocklist($pagename, $text) {
  global $BlocklistPages, $BlockedMessagesFmt, $BlocklistDownload,
    $BlocklistDownloadRefresh, $Now, $EnablePost, $WhyBlockedFmt,
    $MessagesFmt, $BlocklistMessageFmt, $EnableWhyBlocked;

  $BlocklistDownload = (array)@$BlocklistDownload;
  SDV($BlocklistPages, 
    array_merge(array('{$SiteGroup}.Blocklist', '{$SiteGroup}.Blocklist-Farm'),
                array_keys($BlocklistDownload)));
  SDV($BlocklistMessageFmt, "<h3 class='wikimessage'>$[This post has been blocked by the administrator]</h3>");
  SDVA($BlockedMessagesFmt, array(
    'ip' => '$[Address blocked from posting]: ',
    'text' => '$[Text blocked from posting]: '));
  SDV($BlocklistDownloadRefresh, 86400);

  ##  Loop over all blocklist pages
  foreach((array)$BlocklistPages as $b) {

    ##  load the current blocklist page
    $pn = FmtPageName($b, $pagename);
    $page = ReadPage($pn, READPAGE_CURRENT);
    if (!$page) continue;

    ##  if the page being checked is a blocklist page, stop blocking
    if ($pagename == $pn) return;

    ##  If the blocklist page is managed by automatic download,
    ##  schedule any new downloads here
    if (@$BlocklistDownload[$pn]) {
      $bd = &$BlocklistDownload[$pn];
      SDVA($bd, array(
        'refresh' => $BlocklistDownloadRefresh,
        'url' => "http://www.pmwiki.org/blocklists/$pn" ));
      if (!@$page['text'] || $page['time'] < $Now - $bd['refresh'])
        register_shutdown_function('BlocklistDownload', $pn, getcwd());
    }

    ##  If the blocklist is simply a list of regexes to be matched, load 
    ##  them into $terms['block'] and continue to the next blocklist page.
    ##  Some regexes from remote sites aren't well-formed, so we have
    ##  to escape any slashes that aren't already escaped.
    if (strpos($page['text'], '#blocklist-format: regex') !==false) {
      if (preg_match_all('/^([^\\s#].+)/m', $page['text'], $match)) 
        foreach($match[0] as $m) {
          $m = preg_replace('#(?<!\\\\)/#', '\\/', trim($m));
          $terms['block'][] = "/$m/";
        }
      continue;
    }

    ##  Treat the page as a pmwiki-format blocklist page, with
    ##  IP addresses and "block:"-style declarations.  First, see
    ##  if we need to block the author based on a.b.c.d or a.b.c.*
    ##  IP addresses.
    $ip = preg_quote($_SERVER['REMOTE_ADDR']);
    $ip = preg_replace('/\\d+$/', '($0\\b|\\*)', $ip);
    if (preg_match("/\\b$ip/", $page['text'], $match)) {
      $EnablePost = 0;
      $WhyBlockedFmt[] = $BlockedMessagesFmt['ip'] . $match[0];
    }

    ##  Now we'll load any "block:" or "unblock:" specifications
    ##  from the page text.
    if (preg_match_all('/(un)?(?:block|regex):(.*)/', $page['text'], 
                       $match, PREG_SET_ORDER)) 
      foreach($match as $m) $terms[$m[1].'block'][] = trim($m[2]);
  }

  ##  okay, we've loaded all of the terms, now subtract any 'unblock'
  ##  terms from the block set.
  $blockterms = array_diff((array)@$terms['block'], (array)@$terms['unblock']);

  ##  go through each of the remaining blockterms and see if it matches the
  ##  text -- if so, disable posting and add a message to $WhyBlockedFmt.
  $itext = strtolower($text);
  foreach($blockterms as $b) {
    if ($b{0} == '/') {
      if (!preg_match($b, $text)) continue;
    } else if (strpos($itext, strtolower($b)) === false) continue;
    $EnablePost = 0;
    $WhyBlockedFmt[] = $BlockedMessagesFmt['text'] . $b;
  }

  ##  If we came across any reasons to block, let's provide a message
  ##  to the author that it was blocked.  If $EnableWhyBlocked is set,
  ##  we'll even tell the author why.  :-)
  if (@$WhyBlockedFmt) {
    $MessagesFmt[] = $BlocklistMessageFmt;
    if (IsEnabled($EnableWhyBlocked, 0)) 
      foreach((array)$WhyBlockedFmt as $why) 
        $MessagesFmt[] = "<pre class='blocklistmessage'>$why</pre>\n";
  }
}


##   BlocklistDownload() handles retrieving blocklists from
##   external sources into PmWiki pages.  If it's able to
##   download an updated list, it uses that; otherwise it leaves
##   any existing list alone.
function BlocklistDownload($pagename, $dir = '') {
  global $BlocklistDownloadFmt, $BlocklistDownload, $FmtV;

  if ($dir) { flush(); chdir($dir); }
  SDV($BlocklistDownloadFmt, "
  [@
#blocklist-note:   NOTE: This page is automatically generated by blocklist.php
#blocklist-note:   NOTE: Any edits to this page may be lost!
#blocklist-url:    \$BlocklistDownloadUrl
#blocklist-when:   \$CurrentTime
#blocklist-format: \$BlocklistFormat
\$BlocklistData
  @]
");

  ##  get the existing blocklist page
  $bd = &$BlocklistDownload[$pagename];
  $page = ReadPage($pagename, READPAGE_CURRENT);

  ##  try to retrieve the remote data
  $blocklistdata = @file($bd['url']);

  ##  if we didn't get it, and we don't already have text, save a
  ##  note in the page so we know what happened
  if (!$blocklistdata && !@$page['text']) 
    $blocklistdata = '#### Unable to download blocklist';

  ##  if we have some new text to save, let's format it and save it
  if ($blocklistdata) {
    $blocklistdata = implode('', (array)$blocklistdata);
    $blocklistdata = preg_replace('/^#blocklist.*/m', '', $blocklistdata);
    $FmtV['$BlocklistData'] = $blocklistdata;
    $FmtV['$BlocklistDownloadUrl'] = $bd['url'];
    $FmtV['$BlocklistFormat'] = $bd['format'];
    $page['text'] = FmtPageName($BlocklistDownloadFmt, $pagename);
    SDV($page['passwdread'], '@lock');
  }

  ##  save our updated(?) blocklist page
  WritePage($pagename, $page);
}
