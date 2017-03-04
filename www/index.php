<?php

/*
	Copyright (c) 2015-2017, Maximilian Doerr

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	IABot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with IABot.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once( 'loader.php' );

$oauthObject = new OAuth();
$dbObject = new DB2();
$userObject = new User( $dbObject, $oauthObject );
$userCache = [];
if( !is_null( $userObject->getDefaultWiki() ) && $userObject->getDefaultWiki() !== WIKIPEDIA &&
    isset( $_GET['returnedfrom'] )
) {
	header( "Location: " . $_SERVER['REQUEST_URI'] . "&wiki=" . $userObject->getDefaultWiki() );
	exit( 0 );
}

use Wikimedia\DeadlinkChecker\CheckIfDead;

$checkIfDead = new CheckIfDead();

$_POST = []; //workaround for broken PHPstorm
parse_str( file_get_contents( 'php://input' ), $_POST );

if( empty( $_GET ) && empty( $_POST ) ) {
	$oauthObject->storeArguments();
	$loadedArguments = [];
} elseif( isset( $_GET['returnedfrom'] ) ) {
	$oauthObject->recallArguments();
	$loadedArguments = array_replace( $_GET, $_POST );
} else {
	$oauthObject->storeArguments();
	$loadedArguments = array_replace( $_GET, $_POST );
}

if( file_exists( "gui.maintenance.json" ) || $disableInterface === true ) {
	$mainHTML = new HTMLLoader( "maindisabled", $userObject->getLanguage() );
	if( isset( $loadedArguments['action'] ) ) {
		switch( $loadedArguments['action'] ) {
			case "loadmaintenancejson":
		}
	}
	if( file_exists( "gui.maintenance.json" ) ) {
		if( isset( $loadedArguments['action'] ) ) {
			switch( $loadedArguments['action'] ) {
				case "loadmaintenancejson":
					$json = json_decode( file_get_contents( "gui.maintenance.json" ), true );
					die( json_encode( $json ) );
			}
		}
		loadMaintenanceProgress();
	}
	else loadDisabledInterface();
	goto finishloading;
}
else $mainHTML = new HTMLLoader( "main", $userObject->getLanguage() );

if( isset( $loadedArguments['action'] ) ) {
	if( $oauthObject->isLoggedOn() === true ) {
		if( $userObject->getLastAction() <= 0 ) {
			if( loadToSPage() === true ) goto quickreload;
			else exit( 0 );
		} else {
			switch( $loadedArguments['action'] ) {
				case "changepermissions":
					if( changeUserPermissions() ) goto quickreload;
					break;
				case "toggleblock":
					if( toggleBlockStatus() ) goto quickreload;
					break;
				case "togglefpstatus":
					if( toggleFPStatus() ) goto quickreload;
					break;
				case "reviewreportedurls":
					if( runCheckIfDead() ) goto quickreload;
					break;
				case "massbqchange":
					if( massChangeBQJobs() ) goto quickreload;
					break;
				case "togglebqstatus":
					if( toggleBQStatus() ) goto quickreload;
					break;
				case "killjob":
					if( toggleBQStatus( true ) ) goto quickreload;
					break;
				case "submitfpreport":
					if( reportFalsePositive() ) goto quickreload;
					break;
				case "changepreferences":
					if( changePreferences() ) goto quickreload;
					break;
				case "submiturldata":
					if( changeURLData() ) goto quickreload;
					break;
				case "submitdomaindata":
					if( changeDomainData() ) goto quickreload;
					break;
			}
		}
	} else {
		loadLoginNeededPage();
	}
}
quickreload:
if( isset( $loadedArguments['page'] ) ) {
	if( $oauthObject->isLoggedOn() === true ) {
		if( $userObject->getLastAction() <= 0 ) {
			if( loadToSPage() === true ) goto quickreload;
		} else {
			switch( $loadedArguments['page'] ) {
				case "viewjob":
				case "runbotsingle":
				case "runbotqueue":
					loadConstructionPage();
					break;
				case "manageurlsingle":
					loadURLInterface();
					break;
				case "manageurldomain":
					loadDomainInterface();
					break;
				case "reportfalsepositive":
					loadFPReporter();
					break;
				case "reportbug":
					loadBugReporter();
					break;
				case "metalogs":
					loadConstructionPage();
					break;
				case "metausers":
					loadUserSearch();
					break;
				case "metainfo":
					loadInterfaceInfo();
					break;
				case "metafpreview":
					loadFPReportMeta();
					break;
				case "metabotqueue":
					loadBotQueue();
					break;
				case "user":
					loadUserPage();
					break;
				case "userpreferences":
					loadUserPreferences();
					break;
				default:
					load404Page();
					break;
			}
		}
	} else {
		loadLoginNeededPage();
	}
} else {
	loadHomePage();
}

finishloading:
$sql =
	"SELECT COUNT(*) AS count FROM externallinks_user WHERE `last_action` >= '" . date( 'Y-m-d H:i:s', time() - 300 ) .
	"' OR `last_login` >= '" . date( 'Y-m-d H:i:s', time() - 300 ) . "';";
$res = $dbObject->queryDB( $sql );
if( $result = mysqli_fetch_assoc( $res ) ) {
	$mainHTML->assignAfterElement( "activeusers5", $result['count'] );
	mysqli_free_result( $res );
}

$mainHTML->setUserMenuElement( $oauthObject->getUsername(), $oauthObject->getUserID() );
$mainHTML->assignAfterElement( "csrftoken", $oauthObject->getCSRFToken() );
$mainHTML->assignAfterElement( "checksum", $oauthObject->getChecksumToken() );
$mainHTML->finalize();
echo $mainHTML->getLoadedTemplate();