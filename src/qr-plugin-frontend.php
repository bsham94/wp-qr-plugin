<?php
require_once AUTOLOADPATH;
require_once __DIR__ . '/qr-generator.php';
require_once __DIR__ . '/qr-plugin-api.php';
require_once __DIR__ . '/encryptId.php';

class QrCodePluginFrontend
{
    public function initialize_hooks()
    {
        add_shortcode('qr_shortcode', array($this, 'myShortcodeFunction'));
    }

    public function set_unique_profile_slug($new_status, $old_status, $post)
    {
        $test_display = get_option('test_display');

        // Check if the post is assigned to a specific custom category (replace 'user_profile' with your category slug)
        $category_check = has_term('user_profile', 'category', $post);

        if ($category_check && !$test_display && $old_status === 'draft' && $new_status === 'publish') {
            // Generate a unique slug
            $unique_slug = uniqid('', false);
            // Key for encryption should be 16 characters long
            $len = openssl_cipher_iv_length('aes-256-cbc');
            $key = uniqid('', true);
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
        $script = "
            <script>
                if (window.location.search) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            </script>";
        foreach ($categories as $category) {
            if ($category->slug === 'user_profile') {
                $is_user_profile = true;
                break;
            }
        }

        if (!$is_user_profile) {
            return '<div>This is not a user profile.</div>' . $script;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'qr_code';
        $query = $wpdb->prepare(
            "SELECT qr_key FROM $table_name WHERE post_id = %d",
            $post_id
        );
        $key = $wpdb->get_var($query);

        if (!$key) {
            return '<div>No QR Code Found</div>' . $script;
        }
        $encrypt_key = EncryptID::encryptID($key);
        // Construct the URL with the key
        $namespace = 'qr-plugin/v1'; // Replace with your plugin's namespace
        $route = 'qr-endpoint'; // Replace with your endpoint's route

        // Construct the URL of the registered endpoint with the 'value' query parameter
        $endpoint_url = rest_url("$namespace/$route?value=$encrypt_key");

        $url_with_key = esc_url($endpoint_url);

        // Example QR code generation code
        // You can use $url_with_key as the URL for the QR code
        $generator = new QrGenerator($url_with_key);
        $res = $generator->generate() . $script;
        return $res;
    }

}
