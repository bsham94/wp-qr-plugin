<?php
require_once AUTOLOADPATH;
require_once __DIR__ . '/qr-generator.php';

class QrCodePlugin
{
    public function initialize_hooks()
    {

        // Hook into the 'admin_menu' action to add the plugin's admin page
        add_action('admin_menu', array($this, 'adminPage'));
        add_shortcode('my_shortcode', array($this, 'myShortcodeFunction'));
        add_action('transition_post_status', array($this, 'set_unique_profile_slug'), 10, 3);
        add_action('template_redirect', array($this, 'redirect_to_main_url_for_user_profile'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('rest_api_init', array($this, 'register_custom_endpoint'));
    }
    public function enqueue_styles()
    {
        wp_register_style("qr-plugin-css", BASE_URL . '/css/qr-plugin.css');
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

    function register_custom_endpoint()
    {
        $res = register_rest_route(
            'qr-plugin/v1',
            'qr-endpoint',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'handle_custom_endpoint_request'),
                'args' => array(
                    'value' => array(
                        'type' => 'string',
                        // Define the type of parameter (string in this case)
                        'required' => true,
                        // Whether the parameter is required
                    ),
                ),
            )
        );
    }
    function handle_custom_endpoint_request($request)
    {
        // Your code to process the request and generate a response
        // $response_data = array('message' => 'Hello, this is your custom endpoint!');
        // return new WP_REST_Response($response_data, 200);
        $key = $request->get_param('value');
        if ($key) {
            //$post_id = get_the_ID();
            // $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
            $secret = get_option('encryption_message');
            $iv = get_option('iv');
            $decrypted = openssl_decrypt($key, 'AES-256-CBC', $secret, 0, $iv);
            // Get post id associated with key
            // If post id exists, get slug and redirect to post.
            // If post doesnt exist, the key does not correspond to a post, redirect to another page.
            $args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'category_name' => 'user_profile',
                'posts_per_page' => -1,
            );
            $query = new WP_Query($args);
            $posts = $query->posts;
            foreach ($posts as $post) {
                $post_id = $post->ID;
                $post_key = get_post_meta($post_id, 'qr_key', true);
                if ($post_key === $decrypted) {
                    $slug = get_post_field('post_name', $post_id);
                    $url = get_permalink($post_id);
                    $url = $url . $slug;
                    wp_redirect($url);
                    exit;
                }
            }
            wp_redirect(home_url());
            exit;
        }
    }
    function qrPluginAdminPage()
    {
        //Determine what page is being displayed
        if (isset($_GET['action']) && $_GET['action'] === 'deleteQrCode') {
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
                    $encrypt_key = $this->encryptID($key);
                    // Construct the URL with the key
                    $namespace = 'qr-plugin/v1'; // Replace with your plugin's namespace
                    $route = 'qr-endpoint'; // Replace with your endpoint's route
                    // Construct the URL of the registered endpoint with the 'value' query parameter
                    $endpoint_url = rest_url("$namespace/$route?value=$encrypt_key");
                    $url_with_key = esc_url($endpoint_url);
                    //Only display user_profile posts. This is a custom category
                    if ($this->categoryCheck($post->ID, 'user_profile')) {
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html($post->post_title); ?>
                            </td>
                            <td>
                                <img src="<?php echo (new \chillerlan\QRCode\QRCode)->render($url_with_key); ?>" alt="QR Code" />
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
        $encrypt_key = $this->encryptID($key);
        // Construct the URL with the key
        $namespace = 'qr-plugin/v1'; // Replace with your plugin's namespace
        $route = 'qr-endpoint'; // Replace with your endpoint's route

        // Construct the URL of the registered endpoint with the 'value' query parameter
        $endpoint_url = rest_url("$namespace/$route?value=$encrypt_key");

        // Construct the URL of the registered endpoint
        $endpoint_url = rest_url("$namespace/$route");

        $url_with_key = esc_url($endpoint_url);

        // Example QR code generation code
        // You can use $url_with_key as the URL for the QR code
        $generator = new QrGenerator($url_with_key);

        return $generator->generate();
    }
    public function encryptID($key)
    {
        $secret = get_option('encryption_message');
        $iv = get_option('iv');
        $encrypt_key = openssl_encrypt($key, 'AES-256-CBC', $secret, 0, $iv);
        return $encrypt_key;

    }
    function deleteQRCode()
    {
        $post_id = intval($_GET['post_id']);
        $key = sanitize_text_field($_GET['value']);
        // Ensure the post ID is valid and the key is provided
        if (!empty($post_id) && !empty($key)) {
            // Use the delete_post_meta function to delete the post meta
            delete_post_meta($post_id, 'qr_key', $key);
        }
        $this->qrCodeTablePage();
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
        //$message = get_option('encryption_message');
        // Compare the submitted key with the saved key
        return $key === $saved_key;
    }
    public function redirect_to_main_url_for_user_profile()
    {
        $test_display = get_option('test_display');

        // Check if the current view is a single post
        if (!is_admin() && is_single() && !$test_display) {
            // Get the current post's ID
            $post_id = get_the_ID();

            // Check if the post has the "user_profile" category
            if ($this->is_user_profile_post()) {
                $author_id = get_post_field('post_author', $post_id);
                if (get_current_user_id() !== $author_id && !current_user_can('administrator')) {
                    // Check if the correct key is provided in the query string
                    $secret = get_option('encryption_message');
                    $iv = get_option('iv');
                    $key = isset($_GET['value']) ? sanitize_text_field($_GET['value']) : '';
                    //$saved_key = get_post_meta($post_id, 'qr_key', true);
                    $decrypted = openssl_decrypt($key, 'AES-256-CBC', $secret, 0, $iv);
                    // Get the author's ID for the current post

                    if (!$this->is_key_valid($decrypted, $post_id)) {
                        global $wp;
                        // Redirect to the main URL of the site
                        $current_url = home_url(add_query_arg(array(), $wp->request));
                        $home_url = home_url('/');
                        // wp_redirect(home_url());
                        if ($current_url !== $home_url) {
                            // Redirect to the home URL
                            wp_safe_redirect($home_url);
                            exit;
                        }
                    }
                }
            }
        }
    }
}