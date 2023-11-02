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

    if (!get_option('encryption_message')) {
        $secret = "Da\$Gt48veUdh0!@3463><t2Q";
        add_option('encryption_message', $secret);
    }
    if (!get_option('iv')) {
        $iv = "AO#0fhPad^#f&h29";
        add_option('iv', $iv);
    }
    if (!get_option('api_key')) {
        $apiKey = "38Diehf301$38Dh#2@!#d2";
        add_option('api_key', $apiKey);
    }
    create_passphrase_table();
}


// Plugin Deactivation Hook
function my_plugin_deactivation()
{
    // Remove the message from the database upon deactivation
    delete_option('encryption_message');
    delete_option('iv');
    delete_option('api_key');
    delete_passphrase_table();
}

function qr_code_plugin_init()
{
    // Initialize the plugin
    $qr_code_plugin = new QrCodePlugin();
    $qr_code_plugin->initialize_hooks();

}

function create_passphrase_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qrapi'; // Replace 'passphrases' with your preferred table name

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT NOT NULL AUTO_INCREMENT,
        passphrase varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
function delete_passphrase_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qrapi'; // Replace 'passphrases' with your table name

    // Check if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        // Table exists, so we can delete it
        $wpdb->query("DROP TABLE $table_name");
    }
}