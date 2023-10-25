<?php
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;

/*
Plugin Name: QR Code Plugin
*/




// Register activation and deactivation hooks (if needed)
// register_activation_hook(__FILE__, 'qr_code_plugin_activate');
// register_deactivation_hook(__FILE__, 'qr_code_plugin_deactivate');

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
        add_action('admin_post_processQRForm', array($this, 'processQRForm'));
        add_action('admin_post_nopriv_processQRForm', array($this, 'processQRForm'));
        add_filter('wp_insert_post_data', array($this, 'set_unique_profile_slug'), 10, 2);
        // Add a filter to modify post content for posts in the "user_profile" category
        add_filter('the_content', array($this, 'modify_post_content_for_user_profile'));
        // Add a filter to modify post title for posts in the "user_profile" category
        add_filter('the_title', array($this, 'modify_post_title_for_user_profile'), 10, 2);
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
        if (isset($_GET['action']) && $_GET['action'] === 'addQrCode') {
            $this->qrCodePage();
        } elseif (isset($_GET['action']) && $_GET['action'] === 'deleteQrCode') {
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
        <a
            href="<?php echo esc_url(add_query_arg('action', 'addQrCode', admin_url('admin.php?page=qrcode-plugin-settings'))); ?>">Add
            QR Code</a>
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
                    if (!$key)
                        echo "<tr><td colspan='3'>No QR Codes Found</td></tr>";
                    else {
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html($post->post_title); ?>
                            </td>
                            <td>
                                <img src="<?php echo (new QRCode)->render($url_with_key); ?>" alt="QR Code" />
                            </td>
                            <td>
                                <a href="<?php echo esc_url($url_with_key); ?>" target="_blank">View</a> |
                                <a
                                    href="<?php echo esc_url(add_query_arg(array('action' => 'deleteQrCode', 'post_id' => $post->ID, 'key' => $key), admin_url('admin.php?page=qrcode-plugin-settings'))); ?>">Delete</a>
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

    public function set_unique_profile_slug($data, $postarr)
    {
        $test_display = get_option('test_display');
        // Check if the post is assigned to a specific custom category (replace 'custom-category' with your category slug)
        $category_check = has_term('user_profile', 'category', $postarr['ID']);

        if ($postarr['post_status'] === 'publish' && $category_check && !$test_display) {
            // Generate a unique slug
            $unique_slug = uniqid('', false);

            // Update the post_name (slug) in the $data array
            $data['post_name'] = $unique_slug;

            // Store the unique slug in post meta for later retrieval
            update_post_meta($postarr['ID'], 'unique_slug', $unique_slug);
        }

        return $data;
    }

    public function modify_post_content_for_user_profile($content)
    {
        $test_display = get_option('test_display');
        // Check if the current view is a single post
        if (is_single() && in_the_loop() && !is_admin() && !$test_display) {
            // Get the current post's ID
            $post_id = get_the_ID();

            // Check if the post has the "user_profile" category
            if ($this->is_user_profile_post()) {
                // Check if the correct key is provided in the query string
                $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

                // Check if the key is valid for the current post
                if (!$this->is_key_valid($key, $post_id)) {
                    return 'Please scan the corresponding QR Code to access this page.'; // Modify the content as needed
                }
            }
        }

        return $content;
    }


    public function modify_post_title_for_user_profile($title, $post_id)
    {
        $test_display = get_option('test_display');
        if (is_single($post_id) && in_the_loop() && !is_admin() && !$test_display) {
            // Check if the post has the "user_profile" category
            if ($this->is_user_profile_post()) {
                // Check if the correct key is provided in the query string
                $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

                // Check if the key is valid for the current post
                if (!$this->is_key_valid($key, $post_id)) {
                    return 'Unauthorized'; // Modify the title as needed
                }
            }
        }

        return $title;
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

        // Get the current post's URL
        $post_url = get_permalink($post_id);

        // Construct the URL with the key
        $url_with_key = esc_url(add_query_arg('key', $key, $post_url));

        // Example QR code generation code
        // You can use $url_with_key as the URL for the QR code
        $generator = new QrGenerator($url_with_key);

        return $generator->generate();
    }

    public function qrCodePage()
    {
        ?>
        <h1>QR Code Plugin</h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="processQRForm">
            <?php wp_nonce_field('my_form_nonce', 'my_form_nonce_field'); ?>

            <label for="post_page_select">Select a Post or Page:</label>
            <select id="post_page_select" name="post_page">
                <?php
                $posts_pages = get_posts(array('post_type' => array('post', 'page')));
                foreach ($posts_pages as $post_page) {
                    echo '<option value="' . $post_page->ID . '">' . esc_html($post_page->post_title) . '</option>';
                }
                ?>
            </select>
            <br>

            <label for="key_input">Enter a Key:</label>
            <input type="text" id="key_input" name="key" required>
            <br>

            <label for="url_display">Full URL with Key:</label>
            <input type="text" id="url_display" name="url_display" value="" readonly>
            <br>

            <input type="submit" name="submit_button" value="Submit">
        </form>

        <script>
            // JavaScript to generate and display the full URL with the key
            document.addEventListener('DOMContentLoaded', function () {
                var postPageSelect = document.getElementById('post_page_select');
                var keyInput = document.getElementById('key_input');
                var urlDisplay = document.getElementById('url_display');

                postPageSelect.addEventListener('change', updateUrl);
                keyInput.addEventListener('input', updateUrl);

                function updateUrl() {
                    var selectedOption = postPageSelect.options[postPageSelect.selectedIndex];
                    var postId = selectedOption.value;
                    var postTitle = selectedOption.text;
                    var key = keyInput.value;
                    var siteUrl = '<?php echo esc_url(home_url('/')); ?>'; // Get the site's URL
                    var url = siteUrl + '?post_id=' + postId + '&key=' + key;
                    urlDisplay.value = url;
                }
            });
        </script>
        <?php
    }


    function processQRForm()
    {
        // Verify the nonce
        if (isset($_POST['my_form_nonce_field']) && wp_verify_nonce($_POST['my_form_nonce_field'], 'my_form_nonce')) {
            // Check if the submitted data is valid
            if (isset($_POST['post_page']) && isset($_POST['key'])) {
                $post_id = intval($_POST['post_page']);
                $key = sanitize_text_field($_POST['key']);

                // Save the key as post meta for the selected post
                update_post_meta($post_id, 'qr_key', $key);

                // Redirect back to the previous page
                wp_redirect(wp_get_referer());
                exit;
            }
        }
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
        $download_link = '<a href="' . (new QRCode)->render($this->data) . '" download="qr_code.png">Download QR Code</a>';

        // Combine the QR code image and download link
        $output = $qr_code_image . '<br />' . $download_link;

        return $output;

    }
}
