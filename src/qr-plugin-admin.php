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
        add_action('admin_post_handle_qr_code_edit', array($this, 'handle_qr_code_edit'));
        add_action('admin_post_handle_qr_code_with_auto_key', array($this, 'handle_qr_code_with_auto_key'));

    }

    public function adminPage()
    {
        $mainPageHook = add_menu_page('QR Code Plugin', 'QR Code Plugin', 'manage_options', 'qrcode-plugin-settings', array($this, 'qrPluginAdminPage'), 'dashicons-admin-plugins');
        add_action("load-{$mainPageHook}", array($this, 'addMainPageAssets'));
        $editPageHook = add_submenu_page(NULL, 'Edit QR Code', 'Edit QR Code', 'manage_options', 'edit-qr-code', array($this, 'editQRCode'));
        add_action("load-{$editPageHook}", array($this, 'addEditPageAssets'));
        $addPageHook = add_submenu_page(NULL, 'Add QR Code', 'Add QR Code', 'manage_options', 'add-qr-code', array($this, 'addQRCode'));
        add_action("load-{$addPageHook}", array($this, 'addAddPageAssets'));
    }
    public function addMainPageAssets()
    {

    }
    public function addEditPageAssets()
    {
        wp_register_style('editqr-css', BASE_URL . '/css/qr-plugin-settings.css');
        wp_enqueue_style('editqr-css');
    }
    public function addAddPageAssets()
    {
        wp_register_style('addqr-css', BASE_URL . '/css/qr-plugin-settings.css');
        wp_enqueue_style('addqr-css');
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
        <!-- <a href="<?php echo esc_url(add_query_arg(array("page" => "add-qr-code", "action" => "addQrCode"), admin_url())); ?>">Add
            QR Code</a> -->
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
                //Display all the user_profile posts
                //If no posts are found, display a message
                if (!$posts) {
                    echo '<tr><td colspan="3">No QR Codes Found</td></tr>';
                } {
                    foreach ($posts as $post) {
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'qr_code';
                        $key = $wpdb->get_var($wpdb->prepare("SELECT qr_key FROM $table_name WHERE post_id = %d", $post->ID));
                        if (!$key) {
                            continue;
                        }
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
                                        href="<?php echo esc_url(add_query_arg(array("page" => "edit-qr-code", "action" => "editQrCode", "id" => $post->ID), admin_url())); ?>">Edit</a>
                                    |
                                    <a
                                        href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings", "action" => "deleteQrCode", "id" => $post->ID), admin_url())); ?>">Delete</a>
                                </td>
                            </tr>
                            <?php
                        }
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
                // If not, create a new QR code entry
                // If it does,  skip creating a new slug. The existing QR code entry will be used.
                if (!$this->qr_code_entry_exists($object_id)) {
                    //Check if the post has the meta key _adding_qr_code
                    //This is to prevent the post from being updated infinitely
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
        // Check if a QR code entry with the same post_id already exists
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d",
            $post_id
        );

        return (int) $wpdb->get_var($query) > 0;
    }
    function addQRCode()
    {
        // Get the selected post's data (if provided in the URL)
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

        // Retrieve user_profile posts for the dropdown
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'category_name' => 'user_profile',
            'posts_per_page' => -1,
        );
        $query = new WP_Query($args);
        $user_profile_posts = $query->posts;
        $len = openssl_cipher_iv_length('aes-256-cbc');
        $key = uniqid('', true);
        $key = str_replace('.', '', substr($key, 0, $len + 1));
        $encrypt_key = EncryptID::encryptID($key);
        // Construct the URL with the key
        $namespace = 'qr-plugin/v1'; // Replace with your plugin's namespace
        $route = 'qr-endpoint'; // Replace with your endpoint's route
        // Construct the URL of the registered endpoint with the 'value' query parameter
        $endpoint_url = rest_url("$namespace/$route?value=$encrypt_key");
        $qr_code_url = esc_url($endpoint_url);
        // HTML for the dropdown options
        $dropdown_html = '<select name="post_id">';
        foreach ($user_profile_posts as $post) {
            $selected = '';
            if ($post->ID === $post_id) {
                $selected = 'selected';
            }
            $dropdown_html .= '<option value="' . esc_attr($post->ID) . '" ' . $selected . '>' . esc_html($post->post_title) . '</option>';
        }
        $dropdown_html .= '</select>';

        // HTML for the form?>
        <div class="wrap">
            <h2>Create New QR Code Entry</h2>
            <a class="back-link"
                href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings"), admin_url())); ?>">Back</a>
            <form class="qr-code-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="handle_qr_code_with_auto_key">
                <label for="post_id">Select Post:</label>
                <?php echo $dropdown_html; ?><br>
                <label for="qr_key">QR Code Key:</label>
                <input type="text" name="qr_key" value="<?php echo esc_attr($key); ?>" readonly><br>
                <label for="qr_url">QR Code URL:</label>
                <input class="wide" type="text" name="qr_url" value="<?php echo esc_url($qr_code_url); ?>" readonly><br>
                <input type="submit" name="submit" value="Submit">
            </form>
        </div>
        <?php
    }

    function handle_qr_code_with_auto_key()
    {
        if (isset($_POST['submit'])) {
            // Handle form submission here
            // Retrieve and sanitize form data
            $post_id = intval($_POST['post_id']);
            $post = get_post($post_id); // Get the post by post_id
            $unique_slug = $post->post_name; // Get the post's slug
            $key = sanitize_text_field($_POST['qr_key']); // Get the key from the form

            // You can modify this based on your data source
            $category_name = 'user_profile';

            // Add the entry to your QR code table
            global $wpdb;
            $table_name = $wpdb->prefix . 'qr_code';

            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'unique_slug' => $unique_slug,
                    'qr_key' => $key,
                    'category' => $category_name,
                )
            );
            wp_safe_redirect(add_query_arg(array("page" => "qrcode-plugin-settings", "message" => "True"), admin_url()));
            exit;
        }
        wp_safe_redirect(add_query_arg(array("page" => "qrcode-plugin-settings", "message" => "False"), admin_url()));
        exit;
    }


    function editQRCode()
    {
        // Check if the user is logged in and has the necessary capability to access this page
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to access this page.'));
        }

        // Check if the 'id' parameter is present in the URL
        $post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Get the list of posts with the 'user_profile' category
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'category_name' => 'user_profile',
            'posts_per_page' => -1,
        );
        $query = new WP_Query($args);
        $posts = $query->posts;

        // Create a dropdown list of posts
        $dropdown_html = '<select name="post_id">';
        foreach ($posts as $post) {
            $selected = ($post_id === $post->ID) ? 'selected' : '';
            $dropdown_html .= '<option value="' . esc_attr($post->ID) . '" ' . $selected . '>' . esc_html($post->post_title) . '</option>';
        }
        $dropdown_html .= '</select>';

        // Get the QR code key value based on the post_id from the qr_code table
        $key = '';
        if ($post_id > 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'qr_code';
            $key = $wpdb->get_var($wpdb->prepare("SELECT qr_key FROM $table_name WHERE post_id = %d", $post_id));
        }

        // Generate the QR code URL based on the key
        $qr_code_url = add_query_arg(array('value' => $key), rest_url('qr-plugin/v1/qr-endpoint'));

        // Output the HTML for the edit page
        ?>
        <div class="wrap">
            <h2>Edit QR Code</h2>
            <a class="back-link"
                href="<?php echo esc_url(add_query_arg(array("page" => "qrcode-plugin-settings"), admin_url())); ?>">Back</a>
            <form class="qr-code-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <!-- Add a hidden field to specify the action -->
                <input type="hidden" name="action" value="handle_qr_code_edit">

                <div class="form-group">
                    <label for="post_id">Select Post:</label>
                    <?php echo $dropdown_html; ?>
                </div>

                <div class="form-group">
                    <label for="qr_key">QR Code Key:</label>
                    <input type="text" name="qr_key" value="<?php echo esc_attr($key); ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="qr_url">QR Code URL:</label>
                    <input class="wide" type="text" name="qr_url" value="<?php echo esc_url($qr_code_url); ?>" readonly>
                </div>

                <div class="form-group">
                    <input type="submit" name="submit" value="Submit">
                </div>
            </form>
        </div>

        <?php
    }
    function handle_qr_code_edit()
    {
        // Check if the form was submitted
        if (isset($_POST['submit'])) {
            // Retrieve the selected post ID, QR key, and new slug from the form
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $qr_key = isset($_POST['qr_key']) ? sanitize_text_field($_POST['qr_key']) : '';

            // Get the slug associated with the selected post ID
            $new_slug = get_post_field('post_name', $post_id);

            // Update the row in the qr_code table
            global $wpdb;
            $table_name = $wpdb->prefix . 'qr_code';

            $wpdb->update(
                $table_name,
                array(
                    'unique_slug' => $new_slug,
                    'post_id' => $post_id,
                ),
                array(
                    'qr_key' => $qr_key,
                )
            );
            // Redirect back to the edit page
            wp_redirect(add_query_arg(array("page" => "qrcode-plugin-settings", "message" => "True"), admin_url()));
            exit;
        }
        wp_redirect(add_query_arg(array("page" => "qrcode-plugin-settings", "message" => "False"), admin_url()));
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
            // Get the post title
            $post_title = get_the_title($post_id);
            // Remove the 'user_profile' category from the post
            wp_remove_object_terms($post_id, 'user_profile', 'category');
            // Update the post status to 'draft' and change the slug back to its original value
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_name' => sanitize_title($post_title),
                    'post_status' => 'draft',
                )
            );

        }
        // Redirect back to the QR code table page, the settings main page.
        $this->qrCodeTablePage();
    }

}