<?php
/*
Plugin Name: Partyline by Broadstreet
Plugin URI:  https://broadstreetads.com/
Description: Listens for a webhook callback from Twilio, reformats the body via ChatGPT, and saves it as a WordPress draft.
Version:     1.0
Author:      Kenny Katzgrau
Author URI:  https://broadstreetads.com/
License:     No sharing without permission
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: bspl
Domain Path: /languages
*/

define('PARTYLINE_VERSION', '1.0.0');

require dirname(__FILE__) . '/Partyline/Core.php';

# Start the beast
$engine = new Partyline_Core;
$engine->execute();
