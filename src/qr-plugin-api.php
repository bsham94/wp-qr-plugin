<?php
class QRPluginAPI
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance == null) {
            self::$instance = new QRPluginAPI();
        }

        return self::$instance;
    }
    public function initialize_hooks()
    {
        add_action('rest_api_init', array($this, 'register_custom_endpoint'));
        add_action('template_redirect', array($this, 'redirect_to_main_url_for_user_profile'));
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
        $key = $request->get_param('value');
        if ($key) {

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
                    $apikey = get_option('api_key');
                    $slug = get_post_field('post_name', $post_id);
                    $url = get_permalink($post_id);
                    $url = add_query_arg(array('redirect' => 'true', 'value' => urlencode($apikey)), $url . $slug);
                    wp_redirect($url);
                    exit;
                }
            }
        }
        wp_redirect(home_url());
        exit;
    }
    public function redirect_to_main_url_for_user_profile()
    {
        global $wpdb;
        global $wp;
        $home_url = home_url('/');
        $current_url = home_url(add_query_arg(array(), $wp->request));
        $test_display = get_option('test_display');
        // Check if the current view is a single post
        if (!is_admin() && is_single() && !$test_display) {
            // Check if the post has the "user_profile" category
            if ($this->is_user_profile_post()) {
                // Get the current post's ID
                $post_id = get_the_ID();
                $author_id = get_post_field('post_author', $post_id);
                if (get_current_user_id() !== $author_id && !current_user_can('administrator')) {
                    $redirect = isset($_GET['redirect']) ? sanitize_text_field($_GET['redirect']) : False;
                    //True means accessing the page from the QR code
                    //False means accessing the page from the url (direct)
                    if ($redirect === 'true') {
                        // Get the unique identifier from the query string
                        $apiKey = get_option('api_key');
                        $apiValue = isset($_GET['value']) ? urldecode(sanitize_text_field($_GET['value'])) : '';
                        if ($apiKey !== $apiValue) {
                            wp_safe_redirect($home_url);
                            exit;
                        }
                    } else {
                        // Check if the correct key is provided in the query string
                        $secret = get_option('encryption_message');
                        $iv = get_option('iv');
                        $key = isset($_GET['value']) ? sanitize_text_field($_GET['value']) : '';
                        $decrypted = openssl_decrypt($key, 'AES-256-CBC', $secret, 0, $iv);
                        if (!$this->is_key_valid($decrypted, $post_id)) {
                            // Redirect to the main URL of the site
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

    function handle_custom_endpoint_request_with_encryption($request)
    {
        // Your code to process the request and generate a response
        $key = $request->get_param('value');
        if ($key) {

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
                    $apikey = get_option('api_key');
                    $phrase = $this->generateRandomString();
                    $phraseHash = hash('sha256', $phrase);
                    // Save the passphrase and timestamp in the WordPress database
                    $data_to_save = array(
                        'passphrase' => $phraseHash,
                    );
                    // Insert data into a custom table in the database (you need to create the table first)
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'qrapi';
                    $wpdb->insert($table_name, $data_to_save);
                    $unique_identifier = $wpdb->insert_id;
                    $apiValue = openssl_encrypt($apikey, 'AES-256-CBC', $phraseHash, 0, $iv);
                    $slug = get_post_field('post_name', $post_id);
                    $url = get_permalink($post_id);
                    $url = add_query_arg(array('redirect' => 'true', 'uid' => $unique_identifier, 'value' => urlencode($apiValue)), $url . $slug);
                    wp_redirect($url);
                    exit;
                }
            }
            wp_redirect(home_url());
            exit;
        }
    }
    public function redirect_to_main_url_for_user_profile_with_encryption()
    {
        global $wpdb;
        global $wp;
        $home_url = home_url('/');
        $current_url = home_url(add_query_arg(array(), $wp->request));
        $test_display = get_option('test_display');
        // Check if the current view is a single post
        if (!is_admin() && is_single() && !$test_display) {
            // Check if the post has the "user_profile" category
            if ($this->is_user_profile_post()) {
                // Get the current post's ID
                $post_id = get_the_ID();
                $author_id = get_post_field('post_author', $post_id);
                if (get_current_user_id() !== $author_id && !current_user_can('administrator')) {
                    $redirect = isset($_GET['redirect']) ? sanitize_text_field($_GET['redirect']) : False;
                    //True means accessing the page from the QR code
                    //False means accessing the page from the url (direct)
                    if ($redirect === 'true') {
                        // Get the unique identifier from the query string
                        $identifier = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
                        $apiValue = isset($_GET['value']) ? urldecode(sanitize_text_field($_GET['value'])) : '';
                        if ($identifier > 0) {
                            // Retrieve the passphrase from the database using the unique identifier
                            $iv = get_option('iv');
                            $table_name = $wpdb->prefix . 'qrapi';
                            $result = $wpdb->get_row($wpdb->prepare("SELECT passphrase FROM $table_name WHERE ID = %d", $identifier));
                            if ($result) {
                                $passphrase = $result->passphrase;
                                $wpdb->delete($table_name, array('ID' => $identifier));
                                // Decrypt the data using the retrieved passphrase
                                $decrypted_apikey = openssl_decrypt($apiValue, 'AES-256-CBC', $passphrase, 0, $iv);
                                if (!$decrypted_apikey) {
                                    wp_safe_redirect($home_url);
                                    exit;
                                }
                            } else {
                                // Handle the case where the unique identifier is not found in the database
                                // You can redirect or display an error message
                                wp_safe_redirect($home_url);
                                exit;
                            }
                        } else {
                            // Handle the case where the unique identifier is missing or invalid
                            // You can redirect or display an error message
                            wp_safe_redirect($home_url);
                            exit;
                        }
                    } else {
                        // Check if the correct key is provided in the query string
                        $secret = get_option('encryption_message');
                        $iv = get_option('iv');
                        $key = isset($_GET['value']) ? sanitize_text_field($_GET['value']) : '';
                        //$saved_key = get_post_meta($post_id, 'qr_key', true);
                        $decrypted = openssl_decrypt($key, 'AES-256-CBC', $secret, 0, $iv);
                        // Get the author's ID for the current post

                        if (!$this->is_key_valid($decrypted, $post_id)) {

                            // Redirect to the main URL of the site
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
    function generateRandomString($length = 16)
    {
        $randomBytes = random_bytes($length);
        return bin2hex($randomBytes);
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
}