<?php
/*
Plugin Name: QR Code Plugin
*/

if (!defined('WPINC')) {
    die;
}

define("AUTOLOADPATH", dirname(__FILE__) . '/vendor/autoload.php');
define("BASE_URL", plugins_url('/', __FILE__));
define("SRC", plugin_dir_path(__FILE__) . '/src/qr-plugin.php');


require_once AUTOLOADPATH;
require_once SRC;


register_deactivation_hook(__FILE__, 'my_plugin_deactivation');
register_activation_hook(__FILE__, 'my_plugin_activation');
add_action('plugins_loaded', 'qr_code_plugin_init');

// Plugin Activation Hook
function my_plugin_activation()
{
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // Specify the directory containing your .env file
    $dotenv->load(); // Load environment variables

    // Check if the message is already stored in the database
    if (!get_option('encryption_message')) {
        // Set the default message to encrypt for your key
        $key = "Da\$Gt48veUdh0!@3463><t2Q";
        $iv = "AO#0fhPad^#f&h29";
        // Add the message to the database
        add_option('encryption_message', $key);
        $a = add_option('iv', $iv);
    }
}


// Plugin Deactivation Hook
function my_plugin_deactivation()
{
    // Remove the message from the database upon deactivation
    delete_option('encryption_message');
    delete_option('iv');
}

function qr_code_plugin_init()
{
    // Initialize the plugin
    $qr_code_plugin = new QrCodePlugin();
    $qr_code_plugin->initialize_hooks();
}

