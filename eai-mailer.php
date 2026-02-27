<?php
/**
 * Plugin Name: EAI SMTP Mailer
 * Plugin URI:  https://upperlink.ng
 * Description: An overider of the wp_mail)_ function to use SMPT instead.
 * Version: 0.1.0
 * Author: Oluseun Taylor
 * Author URI: https://seuntaylor.co
 * Text Domain: eai-mailer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Settings Menu
add_action('admin_menu', 'eai_mailer_menu');
function eai_mailer_menu() {
    add_options_page('EAI Mailer Settings', 'EAI Mailer', 'manage_options', 'eai-mailer', 'eai_mailer_settings_page');
}

// The Settings Page HTML
function eai_mailer_settings_page() {
    ?>
    <div class="wrap">
        <h1>EAI Mailer Settings</h1>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('eai_mailer_group');
            do_settings_sections('eai-mailer');
            submit_button();
            ?>
        </form>

        <hr style="margin: 40px 0;">

        <div class="eai-test-section">
            <h2>Test Your EAI Mailer</h2>
            <p>Enter an internationalized email address below to test the UTF-8 SMTP override.</p>
            
            <?php 
            // Display notices inside the wrap so they don't float off-screen
            if (isset($_GET['eai_test'])) {
                $msg = ($_GET['eai_test'] == 'success') ? 'Test email sent successfully!' : 'Error: ' . esc_html($_GET['error']);
                $class = ($_GET['eai_test'] == 'success') ? 'notice-success' : 'notice-error';
                echo "<div class='notice $class is-dismissible'><p>$msg</p></div>";
            }
            ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="eai_send_test_email">
                <?php wp_nonce_field('eai_test_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="test_recipient">Recipient Email</label></th>
                        <td>
                            <input type="email" name="test_recipient" id="test_recipient" 
                                   placeholder="こんにちは@élève.com" class="regular-text" required>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Send Test Email', 'secondary'); ?>
            </form>
        </div>
    </div>
    <?php
}

// Settings & Fields Registration
add_action('admin_init', 'eai_mailer_settings_init');
function eai_mailer_settings_init() {
    register_setting('eai_mailer_group', 'eai_mailer_options');

    add_settings_section('smtp_section', 'SMTP Configuration (Outbound)', null, 'eai-mailer');
    add_settings_section('imap_section', 'IMAP Configuration (Inbound/Post-via-Email)', null, 'eai-mailer');

    // Add SMTP Fields
    $fields = [
        'smtp_host' => 'SMTP Host',
        'smtp_port' => 'SMTP Port',
        'smtp_user' => 'SMTP Username',
        'smtp_pass' => 'SMTP Password',
        'imap_host' => 'IMAP Host',
        'imap_port' => 'IMAP Port'
    ];

    foreach ($fields as $id => $title) {
        add_settings_field($id, $title, 'eai_field_render', 'eai-mailer', strpos($id, 'smtp') !== false ? 'smtp_section' : 'imap_section', ['id' => $id]);
    }
}

function eai_field_render($args) {
    $options = get_option('eai_mailer_options');
    $type = (strpos($args['id'], 'pass') !== false) ? 'password' : 'text';
    echo "<input type='$type' name='eai_mailer_options[{$args['id']}]' value='" . esc_attr($options[$args['id']] ?? '') . "'>";
}

// SMTP Override (The magic for EAI)
add_action('phpmailer_init', 'eai_mailer_smtp_override');
function eai_mailer_smtp_override($phpmailer) {
    $options = get_option('eai_mailer_options');
    if (empty($options['smtp_host'])) return;

    $phpmailer->isSMTP();
    $phpmailer->Host       = $options['smtp_host'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = $options['smtp_port'];
    $phpmailer->Username   = $options['smtp_user'];
    $phpmailer->Password   = $options['smtp_pass'];
    $phpmailer->SMTPSecure = 'tls'; // Most modern servers use TLS

    // CRITICAL FOR EAI: Force UTF-8 Encoding
    $phpmailer->CharSet = 'UTF-8';
    $phpmailer->Encoding = 'base64'; // Helps prevent character corruption in transit
}

// Test Form HTML to your existing settings page
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if ($screen->id !== 'settings_page_eai-mailer') return;
    ?>
    <hr>
    <div class="wrap">
        <h2>Test Your EAI Mailer</h2>
        <p>Enter an internationalized email address below to test the UTF-8 SMTP override.</p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="eai_send_test_email">
            <?php wp_nonce_field('eai_test_nonce'); ?>
            <input type="email" name="test_recipient" placeholder="こんにちは@élève.com" required style="width: 300px;">
            <?php submit_button('Send Test Email', 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
    if (isset($_GET['eai_test'])) {
        $msg = ($_GET['eai_test'] == 'success') ? 'Test email sent successfully!' : 'Error: ' . esc_html($_GET['error']);
        $class = ($_GET['eai_test'] == 'success') ? 'updated' : 'error';
        echo "<div class='$class'><p>$msg</p></div>";
    }
});

// The Logic to Send the Test Email
add_action('admin_post_eai_send_test_email', function() {
    check_admin_referer('eai_test_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    $recipient = sanitize_text_field($_POST['test_recipient']);
    
    // We use a Subject and Body with mixed scripts to prove UTF-8 support
    $subject = "EAI Test: 挨拶 from élève site 🚀";
    $message = "This email confirms that your WordPress site can handle:\n\n" .
               "1. Japanese Kanji (挨拶)\n" .
               "2. French Accents (élève)\n" .
               "3. Yoruba Diacritics (ọ̀pọ̀lọpọ̀)\n\n" .
               "Sent via custom SMTP override.";

    $sent = wp_mail($recipient, $subject, $message);

    if ($sent) {
        wp_redirect(admin_url('options-general.php?page=eai-mailer&eai_test=success'));
    } else {
        global $phpmailer;
        $error = $phpmailer->ErrorInfo ?? 'Unknown error';
        wp_redirect(admin_url('options-general.php?page=eai-mailer&eai_test=error&error=' . urlencode($error)));
    }
    exit;
});