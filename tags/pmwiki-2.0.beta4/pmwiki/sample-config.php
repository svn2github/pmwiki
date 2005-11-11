<?php if (!defined('PmWiki')) exit();
##  This is a sample config.php file.  To use this file, copy it to
##  local/config.php, then edit it for whatever customizations you want.
##  Also, be sure to take a look at http://www.pmichaud.com/wiki/Cookbook
##  for more details on the types of customizations that can be added
##  to PmWiki.

##  $WikiTitle is the name that appears in the browser's title bar.
$WikiTitle = 'PmWiki';

##  $ScriptUrl is your preferred URL for accessing wiki pages
##  $PubDirUrl is the URL for the pub directory.
# $ScriptUrl = 'http://www.mydomain.com/path/to/pmwiki.php';
# $PubDirUrl = 'http://www.mydomain.com/path/to/pub';

##  If your system is able to display the home page but gives
##  "404 Not Found" errors for all others, try setting the following:
# $EnablePathInfo = 0;

## $PageLogoUrl is the URL for a logo image -- you can change this
## to your own logo if you wish.
# $PageLogoUrl = '$PubDirUrl/skins/pmwiki/pmwiki-32.gif';

## If you want to have a custom skin, then set $Skin to the name
## of the directory (in pub/skins/) that contains your skin files.
## See PmWiki.LayoutBasics and Cookbook.Skins.
# $Skin = 'pmwiki';

##  Set $SpaceWikiWords if you want WikiWords to automatically 
##  have spaces before each sequence of capital letters.
# $SpaceWikiWords = 1;                     # turn on WikiWord spacing

##  Set $LinkWikiWords to zero if you don't want WikiWord links (i.e.,
##  all links are made using [[...]].
# $LinkWikiWords = 0;                      # disable WikiWord links

##  If you want uploads enabled on your system, set $EnableUpload=1.
##  You'll also need to set a default upload password, or else set
##  passwords on individual groups and pages.  For more information
##  see PmWiki.UploadsAdmin and PmWiki.PasswordsAdmin.
# $EnableUpload = 1;                       
# $DefaultPasswords['upload'] = crypt('secret');

##  Setting $EnableDiag turns on the ?action=diag and ?action=phpinfo
##  actions, which often helps the PmWiki authors to troubleshoot 
##  various configuration and execution problems.
# $EnableDiag = 1;                         # enable remote diagnostics

##  By default, PmWiki doesn't allow browsers to cache pages.  Setting
##  $EnableIMSCaching=1; will re-enable browser caches in a somewhat
##  smart manner.  Note that you may want to have caching disabled while
##  adjusting configuration files or layout templates.
# $EnableIMSCaching = 1;                   # allow browser caching

##  $DiffKeepDays specifies the minimum number of days to keep a page's
##  revision history.  The default is 3650 (approximately 10 years).
# $DiffKeepDays=30;                        # keep page history at least 30 days

##  The refcount.php script enables ?action=refcount, which helps to
##  find missing and orphaned pages.  See PmWiki.RefCount.
# include_once('scripts/refcount.php');

##  The rss.php script enables ?action=rss and ?action=rdf, which
##  provides RSS feeds for a site based on WikiTrails.  See PmWiki.RSS.
# include_once('scripts/rss.php');

?>
