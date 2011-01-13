<?php if (!defined('PmWiki')) exit();
/*  Copyright 2002-2011 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file allows features to be easily enabled/disabled in config.php.
    Simply set variables for the features to be enabled/disabled in config.php
    before including this file.  For example:
        $EnableQAMarkup=0;                      #disable Q: and A: tags
        $EnableWikiStyles=1;                    #include default wikistyles
    Each feature has a default setting, if the corresponding $Enable
    variable is not set then you get the default.

    To avoid processing any of the features of this file, set 
        $EnableStdConfig = 0;
    in config.php.
*/

$pagename = ResolvePageName($pagename);

if (!IsEnabled($EnableStdConfig,1)) return;

if (!function_exists('session_start') && IsEnabled($EnableRequireSession, 1))
  Abort('PHP is lacking session support', 'session');


if (IsEnabled($EnablePGCust,1))
  SDVA($PmConfig, array("$FarmD/scripts/pgcust.php" => 10));

if (IsEnabled($EnableRobotControl,1))
  SDVA($PmConfig, array("$FarmD/scripts/robots.php" => 20));

if (IsEnabled($EnableCaches, 1))
  SDVA($PmConfig, array("$FarmD/scripts/caches.php" => 30));

## Scripts that are part of a standard PmWiki distribution.
if (IsEnabled($EnableAuthorTracking,1)) 
  SDVA($PmConfig, array("$FarmD/scripts/author.php" => 40));
if (IsEnabled($EnablePrefs, 1))
  SDVA($PmConfig, array("$FarmD/scripts/prefs.php" => 50));
if (IsEnabled($EnableSimulEdit, 1))
  SDVA($PmConfig, array("$FarmD/scripts/simuledit.php" => 60));
if (IsEnabled($EnableDrafts, 0))
  SDVA($PmConfig, array("$FarmD/scripts/draft.php" => 70));        # after simuledit + prefs
if (IsEnabled($EnableSkinLayout,1))
  SDVA($PmConfig, array("$FarmD/scripts/skins.php" => 80));        # must come after prefs
if (@$Transition || IsEnabled($EnableTransitions, 0))
  SDVA($PmConfig, array("$FarmD/scripts/transition.php" => 90));   # must come after skins
if (@$LinkWikiWords || IsEnabled($EnableWikiWords, 0))
  SDVA($PmConfig, array("$FarmD/scripts/wikiwords.php" => 100));   # must come before stdmarkup
if (IsEnabled($EnableStdMarkup,1))
  SDVA($PmConfig, array("$FarmD/scripts/stdmarkup.php" => 110));   # must come after transition
if ($action=='diff' && @!$HandleActions['diff'])
  SDVA($PmConfig, array("$FarmD/scripts/pagerev.php" => 120));
if (IsEnabled($EnableWikiTrails,1))
  SDVA($PmConfig, array("$FarmD/scripts/trails.php" => 130));
if (IsEnabled($EnableWikiStyles,1))
  SDVA($PmConfig, array("$FarmD/scripts/wikistyles.php" => 140));
if (IsEnabled($EnableMarkupExpressions, 1) 
    && !function_exists('MarkupExpression'))
  SDVA($PmConfig, array("$FarmD/scripts/markupexpr.php" => 150));
if (IsEnabled($EnablePageList,1))
  SDVA($PmConfig, array("$FarmD/scripts/pagelist.php" => 160));
if (IsEnabled($EnableVarMarkup,1))
  SDVA($PmConfig, array("$FarmD/scripts/vardoc.php" => 170));
if (!function_exists(@$DiffFunction)) 
  SDVA($PmConfig, array("$FarmD/scripts/phpdiff.php" => 180));
if ($action=='crypt')
  SDVA($PmConfig, array("$FarmD/scripts/crypt.php" => 190));
if ($action=='edit' && IsEnabled($EnableGUIButtons,0))
  SDVA($PmConfig, array("$FarmD/scripts/guiedit.php" => 200));
if (IsEnabled($EnableForms,1))                     
  SDVA($PmConfig, array("$FarmD/scripts/forms.php" => 210));       # must come after prefs
if (IsEnabled($EnableUpload,0))
  SDVA($PmConfig, array("$FarmD/scripts/upload.php" => 220));      # must come after forms
if (IsEnabled($EnableBlocklist, 0))
  SDVA($PmConfig, array("$FarmD/scripts/blocklist.php" => 230));
if (IsEnabled($EnableNotify,0))
  SDVA($PmConfig, array("$FarmD/scripts/notify.php" => 240));
if (IsEnabled($EnableDiag,0)) 
  SDVA($PmConfig, array("$FarmD/scripts/diag.php" => 250));

if (IsEnabled($EnableUpgradeCheck,1))
  SDVA($PmConfig, array("UpgradeCheck" => 260));

SDVA($PmConfig, array(
  "LoadInterMaps" => 270, 
  "CascadeAuth"   => 280
  ));

function UpgradeCheck() {
  global $StatusPageName, $SiteAdminGroup, $VersionNum, $action;
  SDV($StatusPageName, "$SiteAdminGroup.Status");
  $page = ReadPage($StatusPageName, READPAGE_CURRENT);
  if (@$page['updatedto'] != $VersionNum) 
    { $action = 'upgrade'; include_once("$FarmD/scripts/upgrades.php"); }
}

