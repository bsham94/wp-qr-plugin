<?php
require_once AUTOLOADPATH;
require_once __DIR__ . '/qr-generator.php';
require_once __DIR__ . '/qr-plugin-api.php';
require_once __DIR__ . '/encryptId.php';

class QrCodePluginFrontend
{
    public function initialize_hooks()
    {
        add_shortcode('qr_shortcode', array($this, 'shortcodeFunction'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_jquery_script'));
    }
    function enqueue_jquery_script()
    {
        // Enqueue jQuery from the WordPress core
        wp_enqueue_script('jquery');
        // Enqueue your custom jQuery file
        wp_enqueue_script('custom-jquery', BASE_URL . 'js/hideurl.js', array('jquery'), '1.0.0', true);

    }
    public function shortcodeFunction($atts, $content = null)
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
            return '<div>This is not a user profile.</div>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'qr_code';
        $query = $wpdb->prepare(
            "SELECT qr_key FROM $table_name WHERE post_id = %d",
            $post_id
        );
        $key = $wpdb->get_var($query);

        if (!$key) {
            return '<div>No QR Code Found</div>';
        }
        $encrypt_key = EncryptID::encryptID($key);
        // Construct the URL with the key
        $namespace = 'qr-plugin/v1'; // Replace with your plugin's namespace
        $route = 'qr-endpoint'; // Replace with your endpoint's route

        // Construct the URL of the registered endpoint with the 'value' query parameter
        $endpoint_url = rest_url("$namespace/$route?value=" . urlencode($encrypt_key));

        $url_with_key = esc_url($endpoint_url);

        // Example QR code generation code
        // You can use $url_with_key as the URL for the QR code
        $generator = new QrGenerator($url_with_key);
        $res = $generator->generate();
        return $res;
    }

}
