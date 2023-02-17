<?php
/*
Plugin Name: EPOP
Description: Plugin designed to email people upon publication of content.
Version: 1.3
Author: Joshua Campbell
Author URI: https://www.foobarstudios.com
*/

/*
FEATURES INCLUDE: 
1. Plugin is named EPOP. Author is Josh Campbell, author uri is https://www.foobarstudios.com
2. Creates the a table for email templates upon initialization of the plugin. Columns include id (primary key and auto increment), template name varchar(250), subject varchar(500), body varchar(max)
3. Adds a custom meta box to the side of the edit post page with fields for publicist name, publicist email, client name, checkbox for sending email, and a drop-down list of email templates to choose from, populated by a custom table.
4. Sends an email to the specified publicist email using the selected email template when the post is published.
5. Allows the user to create, manage, and maintain email templates with a page similar to a page that other plugins use to manage and maintain forms or other things. When you add or select a template to edit, you get a page that allows you to edit fields for the template name, subject, and body.
6. Stores the email templates in a custom WordPress table.
7. Uses the collected information in the subject and body of email templates, including the publicist name, publicist email, client name, and the post URL.
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function epop_create_email_templates_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'epop_templates';

    $sql = "CREATE TABLE $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,
                template_name VARCHAR(250) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                body LONGTEXT NOT NULL,
                PRIMARY KEY (id)
            ) {$wpdb->get_charset_collate()};";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'epop_create_email_templates_table' );

function epop_add_meta_box() {
    add_meta_box( 'epop_meta_box', 'Email Notification', 'epop_render_meta_box', 'post', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'epop_add_meta_box' );

function epop_render_meta_box( $post ) {
    global $wpdb;

    // Get the email templates from the database
    $email_templates = $wpdb->get_results( "SELECT id, template_name FROM {$wpdb->prefix}epop_templates" );

    // Output the HTML for the meta box
    ?>
    <div>
        <label for="publicist_name">Publicist Name:</label>
        <input type="text" name="publicist_name" id="publicist_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'publicist_name', true ) ); ?>">

        <br>

        <label for="publicist_email">Publicist Email:</label>
        <input type="email" name="publicist_email" id="publicist_email" value="<?php echo esc_attr( get_post_meta( $post->ID, 'publicist_email', true ) ); ?>">

        <br>

        <label for="client_name">Client Name:</label>
        <input type="text" name="client_name" id="client_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'client_name', true ) ); ?>">

        <br>

        <label for="send_email">Send Email:</label>
        <input type="checkbox" name="send_email" id="send_email" value="1" <?php checked( get_post_meta( $post->ID, 'send_email', true ), '1' ); ?>>

        <br>

        <label for="email_template">Email Template:</label>
        <select name="email_template" id="email_template">
            <option value="">
        <?php foreach ( $email_templates as $template ) : ?>
            <option value="<?php echo esc_attr( $template->id ); ?>" <?php selected( get_post_meta( $post->ID, 'email_template', true ), $template->id ); ?>><?php echo esc_html( $template->template_name ); ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php
}

function epop_save_meta_box_data( $post_id ) {
if ( isset( $_POST['publicist_name'] ) ) {
update_post_meta( $post_id, 'publicist_name', sanitize_text_field( $_POST['publicist_name'] ) );
}

if ( isset( $_POST['publicist_email'] ) ) {
    update_post_meta( $post_id, 'publicist_email', sanitize_email( $_POST['publicist_email'] ) );
}

if ( isset( $_POST['client_name'] ) ) {
    update_post_meta( $post_id, 'client_name', sanitize_text_field( $_POST['client_name'] ) );
}

if ( isset( $_POST['send_email'] ) ) {
    update_post_meta( $post_id, 'send_email', '1' );
} else {
    delete_post_meta( $post_id, 'send_email' );
}

if ( isset( $_POST['email_template'] ) ) {
    update_post_meta( $post_id, 'email_template', absint( $_POST['email_template'] ) );
}

}
add_action( 'save_post', 'epop_save_meta_box_data' );

function epop_send_email( $post_id ) {
$publicist_name = get_post_meta( $post_id, 'publicist_name', true );
$publicist_email = get_post_meta( $post_id, 'publicist_email', true );
$client_name = get_post_meta( $post_id, 'client_name', true );
$send_email = get_post_meta( $post_id, 'send_email', true );
$template_id = get_post_meta( $post_id, 'email_template', true );

if ( $send_email && ! empty( $publicist_name ) && ! empty( $publicist_email ) && ! empty( $client_name ) && ! empty( $template_id ) ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'epop_templates';

    $template = $wpdb->get_row( $wpdb->prepare( "SELECT subject, body FROM $table_name WHERE id = %d", $template_id ) );

    if ( $template ) {
        $subject = $template->subject;
        $body = str_replace( '{publicist_name}', $publicist_name, $body );
        $body = str_replace( '{publicist_email}', $publicist_email, $body );
        $body = str_replace( '{client_name}', $client_name, $body );
        $body = str_replace( '{post_url}', get_permalink( $post_id ), $body );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $publicist_name . ' <' . $publicist_email . '>',
        );

        wp_mail( $publicist_email, $subject, $body, $headers );
    }
}

}
add_action( 'publish_post', 'epop_send_email' );

function epop_admin_menu() {
    add_menu_page( 'EPOP', 'EPOP', 'manage_options', 'epop-admin-menu', 'epop_admin_menu_callback' );
    add_submenu_page( 'epop-admin-menu', 'EPOP Templates', 'Templates', 'manage_options', 'epop-admin-menu', 'epop_admin_menu_callback' );
    add_submenu_page( 'epop-admin-menu', 'Add New Template', 'Add New', 'manage_options', 'epop-add-new', 'epop_display_template_form' );
}

add_action( 'admin_menu', 'epop_admin_menu' );

function epop_email_templates_page() {
global $wpdb;

$table_name = $wpdb->prefix . 'epop_templates';

if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_template' ) {
    $id = absint( $_POST['id'] );
    $template_name = sanitize_text_field( $_POST['template_name'] );
    $subject = sanitize_text_field( $_POST['subject'] );
    $body = wp_kses_post( $_POST['body'] );

    if ( $id > 0 ) {
        $wpdb->update(
            $table_name,
            array(
                'template_name' => $template_name,
                'subject' => $subject,
                'body' => $body,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    } else {
        $wpdb->insert(
            $table_name,
            array(
                'template_name' => $template_name,
                'subject' => $subject,
                'body' => $body,
            ),
            array( '%s', '%s', '%s' )
        );
    }
}

if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
    $id = absint( $_GET['id'] );

    $template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
    ?>
    <div class="wrap">
        <h1>Edit Email Template</h1>

        <form method="post">
            <input type="hidden" name="action" value="save_template">
            <input type="hidden" name="id" value="<?php echo $template->id; ?>">
			            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="template_name">Template Name</label>
                        </th>
                        <td>
                            <input type="text" name="template_name" id="template_name" value="<?php echo esc_attr( $template->template_name ); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="subject">Subject</label>
                        </th>
                        <td>
                            <input type="text" name="subject" id="subject" value="<?php echo esc_attr( $template->subject ); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="body">Body</label>
                        </th>
                        <td>
                            <?php wp_editor( $template->body, 'body', array( 'textarea_name' => 'body', 'textarea_rows' => 10 ) ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Save Template</button>
                <a href="<?php menu_page_url( 'epop-email-templates' ); ?>" class="button">Cancel</a>
            </p>
        </form>
    </div>
    <?php
} else {
    ?>
    <div class="wrap">
        <h1>EPOP Email Templates</h1>

        <a href="<?php menu_page_url( 'epop-email-templates', true ); ?>&action=new" class="page-title-action">Add New</a>

        <?php
        $templates = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY template_name" );
        if ( $templates ) {
            ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Template Name</th>
                        <th>Subject</th>
                        <th>Body</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ( $templates as $template ) {
                        ?>
                        <tr>
                            <td><?php echo esc_html( $template->template_name ); ?></td>
                            <td><?php echo esc_html( $template->subject ); ?></td>
                            <td><?php echo wp_trim_words( strip_tags( $template->body ), 10, '...' ); ?></td>
                            <td>
                                <a href="<?php menu_page_url( 'epop-email-templates', true ); ?>&action=edit&id=<?php echo $template->id; ?>">Edit</a>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
        } else {
            ?>
            <p>No email templates found. <a href="<?php menu_page_url( 'epop-email-templates', true ); ?>&action=new">Add New</a></p>
            <?php
        }
        ?>
    </div>
    <?php
}
}

/**
 * Renders the form for adding/editing templates.
 *
 * @param WP_Post|null $template The template being edited. If null, a new template is being added.
 */
function epop_display_template_form($template_id = null)
{
    global $wpdb;

    // Initialize variables
    $name = '';
    $subject = '';
    $body = '';
    $action = 'epop_add_template';

    // Check if we are updating an existing template
    if (!empty($template_id)) {
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT name, subject, body FROM {$wpdb->prefix}epop_templates WHERE id = %d",
            $template_id
        ));
        if ($template) {
            $name = $template->name;
            $subject = $template->subject;
            $body = $template->body;
            $action = 'epop_update_template';
        }
    }

    // Output the form
    ?>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('epop_save_template', 'epop_template_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo $action; ?>">
        <input type="hidden" name="template_id" value="<?php echo $template_id; ?>">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <label for="name"><?php _e('Template Name:', 'epop'); ?></label>
                </th>
                <td>
                    <input type="text" name="name" id="name" value="<?php echo esc_attr($name); ?>" class="regular-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="subject"><?php _e('Email Subject:', 'epop'); ?></label>
                </th>
                <td>
                    <input type="text" name="subject" id="subject" value="<?php echo esc_attr($subject); ?>" class="regular-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="body"><?php _e('Email Body:', 'epop'); ?></label>
                </th>
                <td>
                    <?php wp_editor(htmlspecialchars_decode($body), 'body'); ?>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Save Template', 'epop'), 'primary', 'submit', false); ?>
    </form>
    <?php
}







function epop_admin_init() {
    add_submenu_page(
        'options-general.php',
        'Email Templates',
        'Email Templates',
        'manage_options',
        'epop',
        'epop_admin_menu_callback'
    );
}
add_action('admin_menu', 'epop_admin_init');


/**
 * Callback function for the EPOP menu page.
 */
// Callback function for the submenu page
function epop_admin_menu_callback() {
    if (isset($_GET['action']) && $_GET['action'] == 'edit') {
        epop_display_template_form();
    } else {
        epop_display_template_list();
    }
}


function epop_display_template_list() {
    global $wpdb;

    // Retrieve the templates from the database
    $table_name = $wpdb->prefix . 'epop_templates';
    $templates = $wpdb->get_results("SELECT * FROM $table_name");

    // If there are no templates, display a message
    if (empty($templates)) {
        echo '<p>No templates found.</p>';
        return;
    }

    // Display the list of templates
    echo '<table class="widefat">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Template Name</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($templates as $template) {
        echo '<tr>';
        echo '<td>' . esc_html($template->template_name) . '</td>';
        echo '<td>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=epop_templates&action=edit&id=' . $template->id)) . '">Edit</a>';
        echo '&nbsp;|&nbsp;';
        echo '<a href="' . esc_url(admin_url('admin.php?page=epop_templates&action=delete&id=' . $template->id)) . '">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
}

function epop_nonce_verification($action = 'epop_save_template') {
    $nonce = $_REQUEST['_wpnonce'] ?? '';
    if (!wp_verify_nonce($nonce, $action)) {
        wp_die('Invalid nonce');
    }
}

// Save template data to database
/**
 * Save the template data when a template is added or updated
 */
function epop_save_template($template_id, $template_name, $template_subject, $template_body) {
  global $wpdb;
  
  // Verify nonce
  if ( !isset($_POST['epop_nonce']) || !wp_verify_nonce($_POST['epop_nonce'], 'epop_template_nonce') ) {
    wp_die('Unauthorized access');
  }
  
 $table_name = $wpdb->prefix . 'epop_templates';

    $name = esc_sql($data['name']);
    $subject = esc_sql($data['subject']);
    $body = esc_sql($data['body']);
    $id = absint($data['id']);

    if ($id) {
        $wpdb->update(
            $table_name,
            array(
                'name' => $name,
                'subject' => $subject,
                'body' => $body
            ),
            array('id' => $id)
        );
    } else {
        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'subject' => $subject,
                'body' => $body
            )
        );
    }
}



add_action('admin_post_epop_save_template', 'epop_save_template');



function epop_add_template() {
    global $wpdb;

    $template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( $_POST['template_name'] ) : '';
    $subject = isset( $_POST['template_subject'] ) ? sanitize_text_field( $_POST['template_subject'] ) : '';
    $body = isset( $_POST['template_body'] ) ? wp_kses_post( $_POST['template_body'] ) : '';
	echo $body;
    // Insert the template data into the database
    $wpdb->insert(
        "{$wpdb->prefix}epop_templates",
        array(
            'template_name' => $template_name,
            'subject' => $subject,
            'body' => $body,
        ),
        array(
            '%s',
            '%s',
            '%s',
        )
    );

    // Redirect to the template list page
    //wp_redirect( admin_url( 'admin.php?page=epop-admin-menu' ) );
    exit();
}

function epop_get_templates() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'epop_templates';

    $query = "SELECT * FROM $table_name ORDER BY id DESC";
    $results = $wpdb->get_results($query);

    return $results;
}
