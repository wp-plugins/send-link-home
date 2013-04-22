<?php
/*
Plugin Name: Send Link Home
Plugin URI: http://www.inmote.com
Description: Allow visitors to email the current page URL to themselves, for later viewing.
Version: 1.0.1
Author: Inmote
Author URI: http://www.inmote.com
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/**
 * Plugin to allow users to email themself an URL
 */

// most of the work is done in this class
require_once('class-send-link-home.php');

Send_Link_Home::init();