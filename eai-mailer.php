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

// 1. Create the Settings Menu
add_action('admin_menu', 'eai_mailer_menu');
function eai_mailer_menu() {
    add_options_page('EAI Mailer Settings', 'EAI Mailer', 'manage_options', 'eai-mailer', 'eai_mailer_settings_page');
}

// 2. The Settings Page HTML
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
    </div>
    <?php
}

// 3. Register Settings & Fields
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

// 4. The SMTP Override (The magic for EAI)
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