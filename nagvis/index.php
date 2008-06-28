<?PHP
/*****************************************************************************
 *
 * index.php - Main page of NagVis
 *
 * Copyright (c) 2004-2008 NagVis Project (Contact: lars@vertical-visions.de
 *                                                  , michael_luebben@web.de)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/

// Start the user session (This is needed by some caching mechanism)
@session_start();

// Set PHP error handling to standard level
error_reporting(E_ALL ^ E_STRICT);

// Include defines
require('./includes/defines/global.php');
require('./includes/defines/matches.php');

// Include functions
require('./includes/functions/debug.php');
require('./includes/functions/oldPhpVersionFixes.php');

/**
 * Load required files
 *
 * @param	string	$class
 * @author  Michael Luebben <michael_luebben@web.de>
 */
function __autoload($class) {
	require($class.'.php');
}

/**
 * Sets the path where we will look for files when they
 * are requested.
 *
 * @author  Michael Luebben <michael_luebben@web.de>
 */
set_include_path(
	get_include_path()
	.PATH_SEPARATOR.'./includes/classes/'
	.PATH_SEPARATOR.'./includes/classes/objects/'
	.PATH_SEPARATOR.'./includes/classes/controller/'
	.PATH_SEPARATOR.'./includes/classes/validator/'
	.PATH_SEPARATOR.'./includes/classes/httpRequest/'
);

$controller = new GlobalController();

// ONLY FOR DEBUGGING
//-----------------------------------------------------------------------------------------------
$state = ($controller->isValid()) ? 'TRUE' : 'FALSE';
print "Status        : <b>${state}</b><br />";
print "Action        : ".$controller->getAction()."<br />";
print "Parametername : ".$controller->getParameterName()."<br />";
print "Message       : ".$controller->getMessage();
//-----------------------------------------------------------------------------------------------
?>
