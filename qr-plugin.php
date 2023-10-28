<?php
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;

/*
Plugin Name: QR Code Plugin
*/


// Hook into the 'plugins_loaded' action to initialize the plugin
add_action('plugins_loaded', 'qr_code_plugin_init');

function qr_code_plugin_init()
{
    // Initialize the plugin
    $qr_code_plugin = new QrCodePlugin();
    $qr_code_plugin->initialize_hooks();
}

class QrCodePlugin
{
    public function initialize_hooks()
    {

        // Hook into the 'admin_menu' action to add the plugin's admin page
        add_action('admin_menu', array($this, 'adminPage'));
        add_shortcode('my_shortcode', array($this, 'myShortcodeFunction'));
        add_filter('wp_insert_post_data', array($this, 'set_unique_profile_slug'), 10, 2);
        add_action('template_redirect', array($this, 'redirect_to_main_url_for_user_profile'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    public function enqueue_styles()
    {
        $base_url = plugins_url('/', __FILE__);
        //Only works if the style is registered first before enqueued
        wp_register_style("qr-plugin-css", $base_url . '/css/qr-plugin.css');
        wp_enqueue_style("qr-plugin-css");

    }
    public function adminPage()
    {
        $mainPageHook = add_menu_page('QR Code Plugin', 'QR Code Plugin', 'manage_options', 'qrcode-plugin-settings', array($this, 'qrPluginAdminPage'), 'dashicons-admin-plugins');
        add_action("load-{$mainPageHook}", array($this, 'addMainPageAssets'));
        // $settingsPageHook = add_submenu_page("qrcode-plugin-settings", 'Qr Code Settings', 'Settings', 'manage_options', 'QR_Code_settings', array($this, 'qrCodeSettings'));
        // Register a section for your plugin settings
        add_settings_section(
            'qr_code_plugin_settings_section',
            'QR Code Plugin Settings',
            array($this, 'qrCodeSettingsSectionCallback'),
            'general'
        );

        // Register a field for the "test display" option
        add_settings_field(
            'test_display_checkbox',
            'Enable Test Display',
            array($this, 'testDisplayCheckboxCallback'),
            'general',
            'qr_code_plugin_settings_section'
        );

        // Register the "test_display" option in the database
        register_setting('general', 'test_display');
    }
    public function addMainPageAssets()
    {

    }

    // Callback function for the settings section
    public function qrCodeSettingsSectionCallback()
    {
        echo '<p>Configure settings for the QR Code Plugin.</p>';
    }

    // Callback function for the checkbox field
    public function testDisplayCheckboxCallback()
    {
        $test_display = get_option('test_display');
        echo '<input type="checkbox" id="test_display" name="test_display" value="1" ' . checked(1, $test_display, false) . ' />';
        echo '<label for="test_display">Enable test display</label>';
    }


    function qrPluginAdminPage()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'deleteQrCode') {
            $this->deleteQRCode();
        } elseif (isset($_GET['action']) && $_GET['action'] === 'showQrCodes') {
            $this->deleteQRCode();
        } else {
            $this->qrCodeTablePage();
        }
    }
    public function qrCodeTablePage()
    {

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'category_name' => 'user_profile',
            'posts_per_page' => -1,
        );
        $query = new WP_Query($args);
        $posts = $query->posts;
        ?>
        <h1>QR Code Plugin</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Post Title</th>
                    <th>QR Code</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                foreach ($posts as $post) {
                    $key = get_post_meta($post->ID, 'qr_key', true);
                    $url_with_key = esc_url(add_query_arg('key', $key, get_permalink($post->ID)));
                    if ($this->categoryCheck($post->ID, 'user_profile')) {
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html($post->post_title); ?>
                            </td>
                            <td>
                                <img src="<?php echo (new QRCode)->render($url_with_key); ?>" alt="QR Code" />
                            </td>
                            <td>
                                <a href="<?php echo esc_url($url_with_key); ?>" target="_blank">View</a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
        <?php
    }
    public function categoryCheck($id, $categoryName)
    {
        $categories = wp_get_post_categories($id);
        foreach ($categories as $category_id) {
            $category = get_category($category_id);
            $category_name = $category->name;
            if ($category_name && $category_name === $categoryName) {
                return true;
            }
        }
        return false;
    }
    public function set_unique_profile_slug($data, $postarr)
    {
        $test_display = get_option('test_display');
        // Check if the post is assigned to a specific custom category (replace 'user_profile' with your category slug)
        $category_check = has_term('user_profile', 'category', $postarr['ID']);

        if ($category_check && !$test_display) {
            if ($postarr['post_status'] === 'draft') {
                // Generate a unique slug
                $unique_slug = uniqid('', false);
                $key = uniqid('', false);
                // Update the post_name (slug) in the $data array
                $data['post_name'] = $unique_slug;

                // Store the unique slug and key in post meta for later retrieval
                update_post_meta($postarr['ID'], 'unique_slug', $unique_slug);
                update_post_meta($postarr['ID'], 'qr_key', $key);
            }
        }

        return $data;
    }

    public function redirect_to_main_url_for_user_profile()
    {
        $test_display = get_option('test_display');

        // Check if the current view is a single post
        if (!is_admin() && !$test_display) {
            // Get the current post's ID
            $post_id = get_the_ID();

            // Check if the post has the "user_profile" category
            if ($this->is_user_profile_post()) {
                if (!get_post_meta($post_id, '_redirected', true)) {
                    // Check if the correct key is provided in the query string
                    $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

                    // Get the author's ID for the current post
                    $author_id = get_post_field('post_author', $post_id);
                    if (!$this->is_key_valid($key, $post_id) && (get_current_user_id() !== $author_id && !current_user_can('administrator'))) {
                        // Redirect to the main URL of the site
                        update_post_meta($post_id, '_redirected', true);
                        wp_redirect(home_url());
                        exit;
                    }
                } else {
                    $this->reset_redirected_flag($post_id);
                    wp_redirect(home_url());
                    exit;
                }
            }
        }
    }
    function reset_redirected_flag($post_id)
    {
        // Check if the post ID is valid
        if ($post_id) {
            // Update the post meta to reset the _redirected flag
            update_post_meta($post_id, '_redirected', false);
        }
    }
    // Define a function to check if a post belongs to the "user_profile" category
    public function is_user_profile_post()
    {
        $categories = get_the_category();

        foreach ($categories as $category) {
            if ($category->slug === 'user_profile') {
                return true;
            }
        }

        return false;
    }

    // Modify the is_key_valid function to retrieve and compare keys
    public function is_key_valid($key, $post_id)
    {
        // Get the saved key value from post meta for the selected post
        $saved_key = get_post_meta($post_id, 'qr_key', true);

        // Compare the submitted key with the saved key
        return $key === $saved_key;
    }

    public function myShortcodeFunction($atts, $content = null)
    {
        // Get the current post's ID
        $post_id = get_the_ID();

        // Check if the post has the "user_profile" category
        $categories = get_the_category($post_id);
        $is_user_profile = false;

        foreach ($categories as $category) {
            if ($category->slug === 'user_profile') {
                $is_user_profile = true;
                break;
            }
        }

        if (!$is_user_profile) {
            return 'This is not a user profile.';
        }

        // Retrieve the key from post meta (replace 'qr_key' with your meta key)
        $key = get_post_meta($post_id, 'qr_key', true);
        if (!$key) {
            return 'No QR Code Found';
        }
        // Get the current post's URL
        $post_url = get_permalink($post_id);

        // Construct the URL with the key
        $url_with_key = esc_url(add_query_arg('key', $key, $post_url));

        // Example QR code generation code
        // You can use $url_with_key as the URL for the QR code
        $generator = new QrGenerator($url_with_key);

        return $generator->generate();
    }

    function deleteQRCode()
    {
        $post_id = intval($_GET['post_id']);
        $key = sanitize_text_field($_GET['key']);
        // Ensure the post ID is valid and the key is provided
        if (!empty($post_id) && !empty($key)) {
            // Use the delete_post_meta function to delete the post meta
            delete_post_meta($post_id, 'qr_key', $key);
        }
        $this->qrCodeTablePage();
    }

}

class QrGenerator
{
    private $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function generate()
    {
        $options = new QROptions;
        $options->bgColor = [255, 255, 255];
        $options->outputType = "png";
        // Generate the QR code image HTML
        $qr_code_image = '<img src="' . (new QRCode)->render($this->data) . '" alt="QR Code" />';

        // Create a download link
        $download_link = '<a id="qr-button" href="' . (new QRCode)->render($this->data) . '" download="qr_code.png">Download QR Code</a>';

        // Combine the QR code image and download link
        $output = $qr_code_image . '<br />' . $download_link;

        return $output;

    }
}
