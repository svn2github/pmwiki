<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script provides a URL-approval capability.  The URL prefixes
    to be allowed are stored as patterns in $WhiteUrlPatterns.  This
    array can be loaded from config.php, or from the wiki pages given
    by the $ApprovedUrlPagesFmt[] array.  Any URL that isn't in
    WhiteUrlPatterns is rendered using $UnapprovedLinkFmt.

    The script also provides ?action=approveurls and ?action=approvesites, 
    which scan the current page for any new URLs to be automatically added
    the first page of $UrlApprovalPagesFmt.
*/

$LinkFunctions['http:'] = 'LinkHTTP';
$LinkFunctions['https:'] = 'LinkHTTP';
$ApprovedUrlPagesFmt = array('Main.ApprovedUrls');
$UnapprovedLinkFmt = 
  "\$LinkText<a class='apprlink' href='\$PageUrl?action=approveurls'>$[(approve links)]</a>";
$HTMLStylesFmt[] = '.apprlink { font-size:smaller; }';
$ApproveUrlPattern = 
  "\\bhttps?:[^\\s$UrlExcludeChars]*[^\\s.,?!$UrlExcludeChars]";
$WhiteUrlPatterns = array();
$HandleActions['approveurls'] = 'HandleApprove';
$HandleActions['approvesites'] = 'HandleApprove';

function LinkHTTP($pagename,$imap,$path,$title,$txt,$fmt=NULL) {
  global $IMap,$WhiteUrlPatterns,$FmtV,$UnapprovedLinkFmt;
  static $havereadpages;
  if (!$havereadpages) { ReadApprovedUrls($pagename); $havereadpages=true; }
  $p = str_replace(' ','%20',$path);
  $url = str_replace('$1',$p,$IMap[$imap]);
  foreach((array)$WhiteUrlPatterns as $pat) {
    if (preg_match("!^$pat(/|$)!",$url))
      return LinkIMap($pagename,$imap,$path,$title,$txt,$fmt);
  }
  $FmtV['$LinkText'] = $txt;
  return FmtPageName($UnapprovedLinkFmt,$pagename);
}

function ReadApprovedUrls($pagename) {
  global $ApprovedUrlPagesFmt,$ApproveUrlPattern,$WhiteUrlPatterns;
  foreach((array)$ApprovedUrlPagesFmt as $p) {
    $apage = ReadPage(FmtPageName($p,$pagename));
    preg_match_all("/$ApproveUrlPattern/",@$apage['text'],$match);
    foreach($match[0] as $a) {
      $urlp = preg_quote($a,'!');
      if (!in_array($urlp,$WhiteUrlPatterns))
        $WhiteUrlPatterns[] = $urlp;
    }
  }
}

function HandleApprove($pagename) {
  global $ApproveUrlPattern,$WhiteUrlPatterns,$ApprovedUrlPagesFmt,$action;
  Lock(2);
  $page = ReadPage($pagename);
  $text = preg_replace('/[()]/','',$page['text']);
  preg_match_all("/$ApproveUrlPattern/",$text,$match);
  ReadApprovedUrls($pagename);
  $addpat = array();
  foreach($match[0] as $a) {
    foreach((array)$WhiteUrlPatterns as $pat) 
      if (preg_match("!^$pat(/|$)!",$a)) continue 2;
    if ($action=='approvesites') 
      $a=preg_replace("!^([^:]+://[^/]+).*$!",'$1',$a);
    $addpat[] = $a;
  }
  if (count($addpat)>0) {
    $aname = FmtPageName($ApprovedUrlPagesFmt[0],$pagename);
    $apage = ReadPage($aname,'');
    $new = $apage;
    if (substr($new['text'],-1,1)!="\n") $new['text'].="\n";
    foreach($addpat as $pat) $new['text'].="  $pat\n";
    $_REQUEST['post'] = 'y';
    PostPage($aname,$apage,$new);
  }
  Redirect($pagename);
}
    
?>
