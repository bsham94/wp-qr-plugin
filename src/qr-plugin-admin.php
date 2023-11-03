<?php
require_once __DIR__ . '/encryptId.php';

class QRPluginAdmin
{
    public function initialize_hooks()
    {
        // Hook into the 'admin_menu' action to add the plugin's admin page
        add_action('admin_menu', array($this, 'adminPage'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('set_object_terms', array($this, 'my_category_change_action'), 10, 6);
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
            $this->editQRCode();
        } else if (isset($_GET['action']) && $_GET['action'] === 'addQrCode') {
            $this->addQRCode();
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
        <a
            href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings", "action" => "addQrCode"), admin_url())); ?>">Add
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
                                <a
                                    href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings", "action" => "editQrCode", "id" => $post->ID), admin_url())); ?>">Edit</a>
                                |
                                <a
                                    href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings", "action" => "deleteQrCode", "id" => $post->ID), admin_url())); ?>">Delete</a>
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

    function my_category_change_action($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
    {
        // Check if the object is a post and the taxonomy is 'category'
        if ($taxonomy === 'category' && get_post_status($object_id) === 'publish') {
            // Check if the post is assigned to a specific custom category (replace 'user_profile' with your category slug)
            $category_check = has_term('user_profile', 'category', $object_id);
            if ($category_check) {
                // Generate a unique slug
                // Check if a QR code entry with the same slug or key already exists
                if (!$this->qr_code_entry_exists($object_id)) {
                    if (!get_post_meta($object_id, '_adding_qr_code', true)) {
                        update_post_meta($object_id, '_adding_qr_code', true);
                        $unique_slug = uniqid('', false);
                        // Key for encryption should be 16 characters long
                        $len = openssl_cipher_iv_length('aes-256-cbc');
                        $key = uniqid('', true);
                        $key = str_replace('.', '', substr($key, 0, $len + 1));
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'qr_code';
                        $wpdb->insert(
                            $table_name,
                            array(
                                'post_id' => $object_id,
                                'unique_slug' => $unique_slug,
                                'qr_key' => $key,
                                'category' => 'user_profile',
                            )
                        );
                        // Update the post_name (slug)
                        wp_update_post(
                            array(
                                'ID' => $object_id,
                                'post_name' => $unique_slug,
                            )
                        );
                    }
                } else {
                    update_post_meta($object_id, '_adding_qr_code', false);
                }
            }
        }
    }


    function qr_code_entry_exists($post_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qr_code';

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d",
            $post_id
        );

        return (int) $wpdb->get_var($query) > 0;
    }
    function addQRCode()
    {
        ?>
        <h1>Add QR Code</h1>
        <a href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings"), admin_url())); ?>">Back</a>
        <?php
    }
    function editQRCode()
    {
        ?>
        <h1>Edit QR Code</h1>
        <a href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings"), admin_url())); ?>">Back</a>
        <?php

    }
    function deleteQRCode()
    {
        $post_id = intval($_GET['id']);
        // Ensure the post ID is valid and the key is provided
        if (!empty($post_id)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'qr_code';

            // Delete the QR code entry with the corresponding post_id
            $wpdb->delete(
                $table_name,
                array('post_id' => $post_id),
                array('%d')
            );
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_status' => 'draft',
                    // Set to 'draft' to unpublish the post
                )
            );
            // Remove the "user_profile" category from the post
            wp_remove_object_terms($post_id, 'user_profile', 'category');
        }
        $this->qrCodeTablePage();
    }

}