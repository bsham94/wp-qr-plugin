<?php
/*
Plugin Name: QR Code Plugin
Description: This plugin generates QR codes for your posts and provides QR code management.
Version: 1.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: qr-code-plugin
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

    if (!get_option('ENCRYPTION_KEY')) {
        $secret = $_ENV['ENCRYPTION_KEY'];
        add_option('ENCRYPTION_KEY', $secret);
    }
    if (!get_option('IV')) {
        $iv = $_ENV['IV'];
        add_option('IV', $iv);
    }
    if (!get_option('API_KEY')) {
        $apiKey = $_ENV['API_KEY'];
        add_option('API_KEY', $apiKey);
    }
    create_passphrase_table();
    create_qr_table();
}


// Plugin Deactivation Hook
function my_plugin_deactivation()
{
    // Remove the message from the database upon deactivation
    delete_option('ENCRYPTION_KEY');
    delete_option('IV');
    delete_option('API_KEY');
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
