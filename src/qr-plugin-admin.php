<?php
require_once __DIR__ . '/encryptId.php';

class QRPluginAdmin
{
    public function initialize_hooks()
    {
        // Hook into the 'admin_menu' action to add the plugin's admin page
        add_action('admin_menu', array($this, 'adminPage'));
        add_action('transition_post_status', array($this, 'set_unique_profile_slug'), 10, 3);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
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
    public function enqueue_styles()
    {
        wp_register_style("qr-plugin-css", BASE_URL . '/css/qr-plugin.css');
        wp_enqueue_style("qr-plugin-css");

    }

    function qrPluginAdminPage()
    {
        //Determine what page is being displayed
        if (isset($_GET['action']) && $_GET['action'] === 'deleteQrCode') {
            $this->deleteQRCode();
        } else if (isset($_GET['action']) && $_GET['action'] === 'editQrCode') {
            $this->deleteQRCode();
        } else {
            $this->qrCodeTablePage();
        }
    }
    public function qrCodeTablePage()
    {
        //Get all the user_profile posts
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
                    //Change URL to the api endpoint
                    $encrypt_key = EncryptID::encryptID($key);
                    // Construct the URL with the key
                    $namespace = 'qr-plugin/v1'; // Replace with your plugin's namespace
                    $route = 'qr-endpoint'; // Replace with your endpoint's route
                    // Construct the URL of the registered endpoint with the 'value' query parameter
                    $endpoint_url = rest_url("$namespace/$route?value=$encrypt_key");
                    $url_with_key = esc_url($endpoint_url);
                    $options = new \chillerlan\QRCode\QROptions;
                    $options->returnResource = True;
                    $gdImage = (new \chillerlan\QRCode\QRCode($options))->render($url_with_key);
                    $width = imagesx($gdImage);
                    $height = imagesy($gdImage);
                    //Only display user_profile posts. This is a custom category
                    if ($this->categoryCheck($post->ID, 'user_profile')) {
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html($post->post_title); ?>
                            </td>
                            <td>
                                <img src="<?php echo (new \chillerlan\QRCode\QRCode)->render($url_with_key) ?>" alt="QR Code"
                                    width="<?php echo $width ?>" height="<?php echo $height ?>" />
                            </td>
                            <td>
                                <a href="<?php echo esc_url($url_with_key); ?>" target="_blank">View</a> |
                                <a href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings", "action" => "editQrCode", "id" => "1"), admin_url())); ?>"
                                    target="_blank">Edit</a> |
                                <a href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings", "action" => "deleteQrCode", "id" => "1"), admin_url())); ?>"
                                    target="_blank">Delete</a>
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
        //Checks if the post has a category user_profile
        $categories = wp_get_post_categories($id);
        foreach ($categories as $category_id) {
            $category = get_category($category_id);
            //Makes sure cateogry is an object with the name property
            if (is_object($category) && property_exists($category, 'name')) {
                $category_name = $category->name;
                if ($category_name && $category_name === $categoryName) {
                    return true;
                }
            }
        }
        return false;
    }

    public function set_unique_profile_slug($new_status, $old_status, $post)
    {
        $test_display = get_option('test_display');

        // Check if the post is assigned to a specific custom category (replace 'user_profile' with your category slug)
        $category_check = has_term('user_profile', 'category', $post);

        if ($category_check && !$test_display && $old_status === 'draft' && $new_status === 'publish') {
            // Generate a unique slug
            $unique_slug = uniqid('', False);
            // Key for encryption should be 16 characters long
            $len = openssl_cipher_iv_length('aes-256-cbc');
            $key = uniqid('', True);
            $key = str_replace('.', '', substr($key, 0, $len + 1));

            // Store the unique slug and key in post meta for later retrieval
            update_post_meta($post->ID, 'unique_slug', $unique_slug);
            update_post_meta($post->ID, 'qr_key', $key);
            // Update the post_name (slug)
            wp_update_post(
                array(
                    'ID' => $post->ID,
                    'post_name' => $unique_slug,
                )
            );
        }
    }
    function deleteQRCode()
    {
        $post_id = intval($_GET['post_id']);
        $key = sanitize_text_field($_GET['value']);
        // Ensure the post ID is valid and the key is provided
        if (!empty($post_id) && !empty($key)) {
            // Remove the "user_profile" category from the post
            wp_remove_object_terms($post_id, 'user_profile', 'category');
            // Use the delete_post_meta function to delete the post meta
            delete_post_meta($post_id, 'qr_key', $key);
        }
        $this->qrCodeTablePage();
    }

}