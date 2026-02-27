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

// 1. Settings Menu
add_action('admin_menu', 'eai_mailer_menu');
function eai_mailer_menu() {
    add_options_page('EAI Mailer Settings', 'EAI Mailer', 'manage_options', 'eai-mailer', 'eai_mailer_settings_page');
}

// 2. The Settings Page HTML
function eai_mailer_settings_page() {
    ?>
    <div class="wrap">
        <h1>EAI Mailer Settings</h1>
        <p>Configure your SMTP and IMAP credentials to support Internationalized Email Addresses (EAI).</p>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('eai_mailer_group');
            do_settings_sections('eai-mailer');
            submit_button('Save Settings');
            ?>
        </form>

        <hr style="margin: 40px 0;">

        <div class="eai-test-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h2>Test Your EAI Mailer</h2>
            <p>Send a multi-language test email to verify your UTF-8 SMTP override.</p>
            
            <?php 
            if (isset($_GET['eai_test'])) {
                $msg = ($_GET['eai_test'] == 'success') ? 'Test email sent successfully!' : 'Error: ' . esc_html($_GET['error']);
                $class = ($_GET['eai_test'] == 'success') ? 'notice-success' : 'notice-error';
                echo "<div class='notice $class is-dismissible' style='margin-left:0;'><p>$msg</p></div>";
            }
            ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="eai_send_test_email">
                <?php wp_nonce_field('eai_test_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="test_recipient">Recipient Email</label></th>
                        <td>
                            <input type="text" name="test_recipient" id="test_recipient" 
                                   placeholder="こんにちは@élève.com" class="regular-text" required>
                            <p class="description">Enter an EAI address to test mixed script support.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Send Test Email', 'secondary'); ?>
            </form>
        </div>
    </div>
    <?php
}

// 3. Settings & Fields Registration
add_action('admin_init', 'eai_mailer_settings_init');
function eai_mailer_settings_init() {
    register_setting('eai_mailer_group', 'eai_mailer_options');

    add_settings_section('smtp_section', 'SMTP Configuration (Outbound)', null, 'eai-mailer');
    add_settings_section('imap_section', 'IMAP Configuration (Inbound)', null, 'eai-mailer');

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
    $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
    echo "<input type='$type' name='eai_mailer_options[{$args['id']}]' value='" . esc_attr($value) . "' class='regular-text'>";
}

// 4. SMTP Override (The magic for EAI)
add_action('phpmailer_init', 'eai_mailer_smtp_override');
function eai_mailer_smtp_override($phpmailer) {
    $options = get_option('eai_mailer_options');
    if (empty($options['smtp_host'])) return;

    // Use SMTP
    $phpmailer->isSMTP();
    
    // Connection Settings
    $phpmailer->Host       = $options['smtp_host'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = $options['smtp_port'];
    $phpmailer->Username   = $options['smtp_user'];
    $phpmailer->Password   = $options['smtp_pass'];

    // Intelligent Security Selection
    if ($options['smtp_port'] == 465) {
        $phpmailer->SMTPSecure = 'ssl';
    } elseif ($options['smtp_port'] == 587) {
        $phpmailer->SMTPSecure = 'tls';
    } else {
        $phpmailer->SMTPSecure = ''; // Likely unencrypted or custom
        $phpmailer->SMTPAutoTLS = true; 
    }

    // Timeout Management: Don't let the server hang indefinitely
    $phpmailer->Timeout = 15; // 15 seconds is plenty

    // EAI Support
    $phpmailer->CharSet = 'UTF-8';
    $phpmailer->Encoding = 'base64'; 
}

// 5. The Logic to Send the Test Email
add_action('admin_post_eai_send_test_email', function() {
    check_admin_referer('eai_test_nonce');
    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    // We bypass standard sanitize_email() here to preserve EAI characters for the test
    $recipient = $_POST['test_recipient']; 
    
    $subject = "EAI Test: 挨拶 from élève site 🚀";
    $message = "This email confirms that your WordPress site can successfully route and send:\n\n" .
               "1. Japanese Kanji (挨拶)\n" .
               "2. French Accents (élève)\n" .
               "3. Yoruba Diacritics (ọ̀pọ̀lọpọ̀)\n\n" .
               "Sent via custom SMTP override with UTF-8 encoding.";

    $sent = wp_mail($recipient, $subject, $message);

    if ($sent) {
        wp_redirect(admin_url('options-general.php?page=eai-mailer&eai_test=success'));
    } else {
        global $phpmailer;
        $error = !empty($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : 'Unknown SMTP Error';
        wp_redirect(admin_url('options-general.php?page=eai-mailer&eai_test=error&error=' . urlencode($error)));
    }
    exit;
});