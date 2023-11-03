<?php
/*
Plugin Name: QR Code Plugin
*/

if (!defined('WPINC')) {
    die;
}

define("AUTOLOADPATH", dirname(__FILE__) . '/vendor/autoload.php');
define("BASE_URL", plugins_url('/', __FILE__));
define("FRONTEND", plugin_dir_path(__FILE__) . '/src/qr-plugin-frontend.php');
define("ADMIN", plugin_dir_path(__FILE__) . '/src/qr-plugin-admin.php');
define("API", plugin_dir_path(__FILE__) . '/src/qr-plugin-api.php');

require_once AUTOLOADPATH;
require_once FRONTEND;
require_once ADMIN;
require_once API;



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
    create_qr_table();
}


// Plugin Deactivation Hook
function my_plugin_deactivation()
{
    // Remove the message from the database upon deactivation
    delete_option('encryption_message');
    delete_option('iv');
    delete_option('api_key');
    delete_passphrase_table();
    delete_qr_table();
}

function qr_code_plugin_init()
{
    // Initialize the plugin
    $qr_code_plugin_frontend = new QrCodePluginFrontend();
    $qr_code_plugin_frontend->initialize_hooks();
    $qr_code_plugin_admin = new QRPluginAdmin();
    $qr_code_plugin_admin->initialize_hooks();
    $qr_code_plugin_api = QRPluginAPI::get_instance();
    $qr_code_plugin_api->initialize_hooks();


}

function create_passphrase_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qr_api'; // Replace 'passphrases' with your preferred table name

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT NOT NULL AUTO_INCREMENT,
        passphrase varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
function create_qr_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qr_code'; // Replace 'custom_table' with your desired table name

    $sql = "CREATE TABLE $table_name (
    id INT NOT NULL AUTO_INCREMENT,
    post_id INT NOT NULL,
    unique_slug VARCHAR(255) NOT NULL,
    qr_key VARCHAR(25) NOT NULL,  
    category VARCHAR(255) NOT NULL,
    PRIMARY KEY (id)
) {$wpdb->get_charset_collate()};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
function delete_passphrase_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qr_api'; // Replace 'passphrases' with your table name

    // Check if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        // Table exists, so we can delete it
        $wpdb->query("DROP TABLE $table_name");
    }
}
function delete_qr_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'qr_code'; // Replace 'custom_table' with your table name

    // Check if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        // Delete the table
        $wpdb->query("DROP TABLE $table_name");
    }
}
