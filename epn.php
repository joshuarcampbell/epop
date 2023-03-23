<?php
/**
 * Plugin Name: 01A Email Templates
 * Description: Allows users to manage email templates and send email notifications upon post publication.
 * Version: 1.0
 * Author: Your Name
 */

// Register custom page in WordPress admin dashboard
function my_email_templates_menu() {
    add_submenu_page(
        'options-general.php',
        'Email Templates',
        'Email Templates',
        'manage_options',
        'my-email-templates',
        'my_email_templates_page'
        );
}
add_action( 'admin_menu', 'my_email_templates_menu' );

// Display custom page with email template management form
function my_email_templates_page() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Process form submissions
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_email_template' ) {
        $template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
        $template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( $_POST['template_name'] ) : '';
        $template_subject = isset( $_POST['template_subject'] ) ? sanitize_text_field( $_POST['template_subject'] ) : '';
        $template_body = isset( $_POST['template_body'] ) ? wp_kses_post( $_POST['template_body'] ) : '';
        
        // Save email template to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'my_email_templates';
        if ( $template_id ) {
            $wpdb->update(
                $table_name,
                array(
                    'template_name' => $template_name,
                    'template_subject' => $template_subject,
                    'template_body' => $template_body,
                ),
                array( 'id' => $template_id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
                );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'template_name' => $template_name,
                    'template_subject' => $template_subject,
                    'template_body' => $template_body,
                ),
                array( '%s', '%s', '%s' )
                );
        }
    } elseif ( isset( $_POST['action'] ) && $_POST['action'] === 'delete_email_template' ) {
        $template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
        
        // Delete email template from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'my_email_templates';
        $wpdb->delete(
            $table_name,
            array( 'id' => $template_id ),
            array( '%d' )
            );
    }
    
    // Display email template management form
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_email_templates';
    $templates = $wpdb->get_results( "SELECT * FROM $table_name" );
    ?>
    <div class="wrap">
        <h1>Email Templates</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'my_email_templates', 'my_email_templates_nonce' ); ?>
            <input type="hidden" name="action" value="save_email_template">
            <table class="form-table">
                                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="template_name">Template Name</label>
                        </th>
                        <td>
                            <input type="text" id="template_name" name="template_name" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="template_subject">Template Subject</label>
                        </th>
                        <td>
                            <input type="text" id="template_subject" name="template_subject" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="template_body">Template Body</label>
                        </th>
                        <td>
                            <?php
                            $content = '';
                            $editor_id = 'template_body';
                            $settings = array(
                                'textarea_name' => 'template_body',
                                'textarea_rows' => 10,
                            );
                            wp_editor( $content, $editor_id, $settings );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <input type="submit" class="button button-primary" value="Save Template">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
        <hr>
        <h2>Existing Templates</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Template Name</th>
                    <th>Template Subject</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $templates as $template ) : ?>
                    <tr>
                        <td><?php echo esc_html( $template->template_name ); ?></td>
                        <td><?php echo esc_html( $template->template_subject ); ?></td>
                        <td>
                            <form method="post" action="">
                                <?php wp_nonce_field( 'my_email_templates', 'my_email_templates_nonce' ); ?>
                                <input type="hidden" name="action" value="delete_email_template">
                                <input type="hidden" name="template_id" value="<?php echo intval( $template->id ); ?>">
                                <input type="submit" class="button button-link-delete" value="Delete">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Enqueue styles and scripts
//function my_email_templates_scripts() {
//    wp_enqueue_style( 'my-email-templates-style', plugins_url( 'style.css', __FILE__ ) );
//}
//add_action( 'admin_enqueue_scripts', 'my_email_templates_scripts' );

// Create custom database table on plugin activation
function my_email_templates_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_email_templates';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        template_name varchar(255) NOT NULL,
        template_subject varchar(255) NOT NULL,
        template_body longtext NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'my_email_templates_activate' );

// Send email notification upon post publication
function my_email_notification( $post_id ) {
    // Get email template from database
    $template_id = get_post_meta( $post_id, 'my_email_template', true );
    $template = get_email_template( $template_id );
    if ( ! $template ) {
        return;
    }
    
    // Get publicist name and email from post meta
    $publicist_name = get_post_meta( $post_id, 'my_publicist_name', true );
    $publicist_email = get_post_meta( $post_id, 'my_publicist_email', true );
    
    // Get client name from post meta or site name
    $client_name = get_post_meta( $post_id, 'my_client_name', true );
    if ( ! $client_name ) {
        $client_name = get_bloginfo( 'name' );
    }
    
    // Get email subject and body
    $email_subject = apply_shortcodes( $template->template_subject, $post_id, $publicist_name, $publicist_email, $client_name );
    $email_body = apply_shortcodes( $template->template_body, $post_id, $publicist_name, $publicist_email, $client_name );
    
    // Send email
    $headers = array(
    'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>',
    'Content-Type: text/html; charset=UTF-8',
    );
    wp_mail( $publicist_email, $email_subject, $email_body, $headers );
}
add_action( 'publish_post', 'my_email_notification' );

// Define the shortcode function for post URL
function my_post_url_shortcode( $atts ) {
    $post_id = get_the_ID();
    return get_permalink( $post_id );
}
add_shortcode( 'post_url', 'my_post_url_shortcode' );

// Define the shortcode function for publicist name
function my_publicist_name_shortcode( $atts ) {
    $post_id = get_the_ID();
    return get_post_meta( $post_id, 'my_publicist_name', true );
}
add_shortcode( 'publicist_name', 'my_publicist_name_shortcode' );

// Define the shortcode function for publicist email
function my_publicist_email_shortcode( $atts ) {
    $post_id = get_the_ID();
    return get_post_meta( $post_id, 'my_publicist_email', true );
}
add_shortcode( 'publicist_email', 'my_publicist_email_shortcode' );

// Define the shortcode function for client name
function my_client_name_shortcode( $atts ) {
    $post_id = get_the_ID();
    return get_post_meta( $post_id, 'my_client_name', true );
}
add_shortcode( 'client_name', 'my_client_name_shortcode' );


// Retrieve email templates from the database
function get_email_templates() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_email_templates';
    $query = "SELECT id, template_name FROM $table_name";
    $results = $wpdb->get_results($query, ARRAY_A);
    $templates = array();
    
    if ($results) {
        foreach ($results as $result) {
            $templates[$result['id']] = $result['template_name'];
        }
    }
    
    return $templates;
}



// Add a meta box to the post editor page
function email_notification_meta_box() {
    add_meta_box(
        'email_notification_meta_box',
        'Email Notification',
        'email_notification_meta_box_callback',
        'post',
        'side'
        );
}
add_action( 'add_meta_boxes', 'email_notification_meta_box' );
function email_notification_meta_box_callback($post) {
    wp_nonce_field('save_email_notification_meta_box', 'email_notification_nonce');
    
    $publicist_name = get_post_meta($post->ID, 'publicist_name', true);
    $publicist_email = get_post_meta($post->ID, 'publicist_email', true);
    $client_name = get_post_meta($post->ID, 'client_name', true);
    $email_notification_enabled = get_post_meta($post->ID, 'email_notification_enabled', true);
    $selected_template_id = get_post_meta($post->ID, 'email_template_id', true);
    $templates = get_email_templates();
    ?>
    <p>
        <label for="publicist_name">Publicist Name:</label><br>
        <input type="text" id="publicist_name" name="publicist_name" value="<?php echo esc_attr($publicist_name); ?>">
    </p>
    <p>
        <label for="publicist_email">Publicist Email:</label><br>
        <input type="email" id="publicist_email" name="publicist_email" value="<?php echo esc_attr($publicist_email); ?>">
    </p>
    <p>
        <label for="client_name">Client Name:</label><br>
        <input type="text" id="client_name" name="client_name" value="<?php echo esc_attr($client_name); ?>">
    </p>
    <p>
        <input type="checkbox" id="email_notification_enabled" name="email_notification_enabled" value="1" <?php checked($email_notification_enabled, '1'); ?>>
        <label for="email_notification_enabled">Send email notification when published</label>
    </p>
    <p>
        <label for="email_template_id">Email Template:</label><br>
        <select id="email_template_id" name="email_template_id">
            <?php foreach ($templates as $id => $name) : ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_template_id, $id); ?>><?php echo esc_html($name); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

function save_email_notification_meta_box($post_id) {
    $is_autosave = wp_is_post_autosave($post_id);
    $is_revision = wp_is_post_revision($post_id);
    
    if ($is_autosave || $is_revision) {
        return;
    }
    
    $publicist_name = isset($_POST['publicist_name']) ? sanitize_text_field($_POST['publicist_name']) : '';
    $publicist_email = isset($_POST['publicist_email']) ? sanitize_email($_POST['publicist_email']) : '';
    $client_name = isset($_POST['client_name']) ? sanitize_text_field($_POST['client_name']) : '';
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $send_email_notification = isset($_POST['send_email_notification']) ? true : false;
    
    if ($send_email_notification) {
        update_post_meta($post_id, '_send_email_notification', 'yes');
    } else {
        delete_post_meta($post_id, '_send_email_notification');
    }
    
    update_post_meta($post_id, '_publicist_name', $publicist_name);
    update_post_meta($post_id, '_publicist_email', $publicist_email);
    update_post_meta($post_id, '_client_name', $client_name);
    update_post_meta($post_id, '_template_id', $template_id);
}
add_action('save_post', 'save_email_notification_meta_box');


// Save the email notification settings when the post is saved
function save_email_notification( $post_id ) {
    // Check if the user has permissions to save the post
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Check if the email notification nonce is set
    if ( ! isset( $_POST['email_notification_nonce'] ) || ! wp_verify_nonce( $_POST['email_notification_nonce'], basename( __FILE__ ) ) ) {
        return;
    }

    // Save the email notification settings
    if ( isset( $_POST['send_email'] ) ) {
        update_post_meta( $post_id, 'send_email', 1 );
    } else {
        delete_post_meta( $post_id, 'send_email' );
    }
    
    if ( isset( $_POST['client_name'] ) ) {
        update_post_meta( $post_id, 'client_name', sanitize_text_field( $_POST['client_name'] ) );
    }
    
    if ( isset( $_POST['publicist_name'] ) ) {
        update_post_meta( $post_id, 'publicist_name', sanitize_text_field( $_POST['publicist_name'] ) );
    }
    
    if ( isset( $_POST['publicist_email'] ) ) {
        update_post_meta( $post_id, 'publicist_email', sanitize_email( $_POST['publicist_email'] ) );
    }
    
    if ( isset( $_POST['template_id'] ) ) {
        update_post_meta( $post_id, 'template_id', absint( $_POST['template_id'] ) );
    }
}
add_action( 'save_post', 'save_email_notification' );

    
