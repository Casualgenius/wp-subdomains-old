<?php
/*

* Wordpress Info

Plugin Name: WP Subdomains
Plugin URI: http://projects.casualgenius.com/wordpress-subdomains/
Description: Setup your main categories, pages, and authors as subdomains and give them custom themes.
Version: 2.0.0
Author: Alex Stansfield
Author URI: http://casualgenius.com

* LICENSE

    Copyright 2009  Alex Stansfield  (email : alex@casualgenius.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once ('models/Wps/Hooks/Activation.php');
require_once ('plugin/functions.php');

// No Auto Loading Yet
require_once ('models/Wps/Plugin.php');
require_once ('models/Wps/Subdomains.php');
require_once ('models/Wps/Admin.php');
require_once ('models/Wps/Hooks/Abstract.php');
require_once ('models/Wps/Hooks/Actions.php');
require_once ('models/Wps/Hooks/Filters.php');
require_once ('models/Wps/Hooks/RewriteRules.php');
require_once ('models/Wps/Subdomain/Abstract.php');
require_once ('models/Wps/Subdomain/Author.php');
require_once ('models/Wps/Subdomain/Category.php');
require_once ('models/Wps/Subdomain/Page.php');

// Widgets
require_once ('models/Wps/Widgets/Sitelist.php');
require_once ('models/Wps/Widgets/categories.phpegories.php');

$wps_filter_tags_in_loop = false;

//--- Register the Activation Hook
$activation = new Wps_Hooks_Activation();
register_activation_hook(__FILE__, array($activation, 'install'));

//--- Run the Plugin
$WpsPlugin = new Wps_Plugin();