<?php
/*
Plugin Name: Partyline: Reader-Submitted Stories for Local News and Magazines
Plugin URI:  https://github.com/broadstreetads/partyline
Description: Let your community submit local news, a photo and a story, through an installable web app or by text message (Twilio), optionally polished with AI and saved as WordPress drafts.
Version:     1.3.0
Author:      Kenny Katzgrau
Author URI:  https://broadstreetads.com/
License:     GPL v2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: partyline
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('PARTYLINE_VERSION', '1.3.0');

require dirname(__FILE__) . '/Partyline/Core.php';

# Start the beast
$partyline_engine = new Partyline_Core;
$partyline_engine->execute();
