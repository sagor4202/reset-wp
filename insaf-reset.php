<?php
/**
 * Plugin Name: Insaf Full Reset
 * Plugin URI: #
 * Description: Full WordPress reset plugin. Drops all database tables, reinstalls WordPress from scratch, creates admin user (admin/pass), and cleans all content.
 * Version: 2.0.0
 * Author: Rabbi Hasan Emon
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================
// HANDLE RESET EARLY (before any output)
// =============================================
add_action('admin_init', 'insaf_handle_reset_action');

function insaf_handle_reset_action() {
    if (!isset($_POST['insaf_do_reset'])) {
        return;
    }

    // Verify nonce
    if (!isset($_POST['insaf_reset_nonce']) || !wp_verify_nonce($_POST['insaf_reset_nonce'], 'insaf_full_reset')) {
        wp_die('Security check failed.');
    }

    // Verify admin
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.');
    }

    // Verify confirmation text
    if (!isset($_POST['insaf_confirm']) || trim($_POST['insaf_confirm']) !== 'RESET') {
        // Redirect back with error
        wp_safe_redirect(admin_url('tools.php?page=insaf-full-reset&error=confirm'));
        exit;
    }

    // ---- STORE CONFIG BEFORE RESET ----
    $site_url = get_option('siteurl');
    $home_url = get_option('home');
    $table_prefix = $GLOBALS['table_prefix'];

    // Increase limits
    @set_time_limit(600);
    @ini_set('memory_limit', '512M');

    global $wpdb;

    // ============================================
    // STEP 1: Drop ALL database tables
    // ============================================
    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    if (!empty($tables)) {
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table[0]}`");
        }
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
    }

    // ============================================
    // STEP 2: Reinstall WordPress
    // ============================================
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Reconnect
    $wpdb->db_connect(false);

    // Fresh install
    wp_install(
        'WordPress Site',    // Blog title
        'admin',             // Username
        'admin@localhost.com', // Email
        true,                // Public
        '',                  // Deprecated
        'pass',              // Password
        false                // Language
    );

    // Restore URLs
    update_option('siteurl', $site_url);
    update_option('home', $home_url);

    // Set defaults
    update_option('blogdescription', 'Just another WordPress site');
    update_option('permalink_structure', '/%postname%/');
    update_option('timezone_string', 'Asia/Dhaka');
    update_option('date_format', 'F j, Y');
    update_option('time_format', 'g:i a');
    update_option('start_of_week', 6);
    update_option('default_comment_status', 'closed');
    update_option('default_ping_status', 'closed');

    // ============================================
    // STEP 3: Clean file system
    // ============================================

    // Clean uploads
    $upload_dir = wp_upload_dir();
    if (is_dir($upload_dir['basedir'])) {
        insaf_recursive_delete($upload_dir['basedir'], true);
    }

    // Clean plugins (keep only this one)
    $plugins_dir = WP_CONTENT_DIR . '/plugins';
    if (is_dir($plugins_dir)) {
        $items = scandir($plugins_dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'insaf-reset' || $item === 'index.php') {
                continue;
            }
            $path = $plugins_dir . '/' . $item;
            if (is_dir($path)) {
                insaf_recursive_delete($path);
            } else {
                @unlink($path);
            }
        }
    }

    // Clean themes (keep latest twenty* theme)
    $themes_dir = WP_CONTENT_DIR . '/themes';
    if (is_dir($themes_dir)) {
        $items = scandir($themes_dir);
        $twenty_themes = array();
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (preg_match('/^twenty/', $item)) {
                $twenty_themes[] = $item;
            }
        }
        sort($twenty_themes);
        $keep_theme = !empty($twenty_themes) ? end($twenty_themes) : '';

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'index.php' || $item === $keep_theme) {
                continue;
            }
            $path = $themes_dir . '/' . $item;
            if (is_dir($path)) {
                insaf_recursive_delete($path);
            } else {
                @unlink($path);
            }
        }

        if ($keep_theme) {
            update_option('template', $keep_theme);
            update_option('stylesheet', $keep_theme);
        }
    }

    // Clean cache, mu-plugins, backups
    $clean_dirs = array(
        WP_CONTENT_DIR . '/cache',
        WP_CONTENT_DIR . '/mu-plugins',
        WP_CONTENT_DIR . '/ai1wm-backups',
    );
    foreach ($clean_dirs as $dir) {
        if (is_dir($dir)) {
            insaf_recursive_delete($dir, true);
        }
    }
    // Clean extra files
    $extra_files = array(WP_CONTENT_DIR . '/advanced-headers.php');
    foreach ($extra_files as $file) {
        if (file_exists($file)) @unlink($file);
    }

    // Activate this plugin
    update_option('active_plugins', array('insaf-reset/insaf-reset.php'));

    // Flush rewrite
    flush_rewrite_rules(true);

    // Redirect to login page with success message
    wp_redirect(wp_login_url() . '?insaf_reset=success');
    exit;
}

// =============================================
// RECURSIVE DELETE HELPER
// =============================================
function insaf_recursive_delete($dir, $keep_dir = false) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            insaf_recursive_delete($path);
        } else {
            @unlink($path);
        }
    }
    if (!$keep_dir) @rmdir($dir);
}

// =============================================
// ADD MENU
// =============================================
add_action('admin_menu', 'insaf_reset_add_menu');

function insaf_reset_add_menu() {
    add_management_page(
        'Insaf Full Reset',
        '🔴 Full Reset',
        'manage_options',
        'insaf-full-reset',
        'insaf_reset_render_page'
    );
}

// =============================================
// RENDER PAGE (pure HTML, no JavaScript)
// =============================================
function insaf_reset_render_page() {
    $error = isset($_GET['error']) && $_GET['error'] === 'confirm';
    ?>
    <style>
        .insaf-wrap { max-width: 650px; margin: 30px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .insaf-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,0.08); padding: 35px; }
        .insaf-card h1 { color: #d63031; font-size: 26px; margin: 0 0 8px; }
        .insaf-card .sub { color: #636e72; font-size: 14px; margin-bottom: 25px; }
        .insaf-warn { background: #d63031; color: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
        .insaf-info { background: #f1f2f6; border-left: 4px solid #0984e3; padding: 14px 18px; border-radius: 0 8px 8px 0; margin-bottom: 20px; font-size: 14px; line-height: 1.8; }
        .insaf-info code { background: #dfe6e9; padding: 2px 6px; border-radius: 3px; font-weight: 700; }
        .insaf-list { list-style: none; padding: 0; margin: 0 0 20px; }
        .insaf-list li { padding: 6px 0; font-size: 13px; color: #2d3436; border-bottom: 1px solid #f1f2f6; }
        .insaf-form-group { background: #ffeaa7; padding: 18px; border-radius: 8px; margin-bottom: 18px; }
        .insaf-form-group label { font-weight: 700; color: #2d3436; font-size: 14px; display: block; margin-bottom: 8px; }
        .insaf-form-group input[type="text"] { width: 100%; padding: 10px 14px; border: 2px solid #ddd; border-radius: 6px; font-size: 18px; font-weight: 700; letter-spacing: 2px; box-sizing: border-box; }
        .insaf-btn { background: #d63031; color: #fff; border: none; padding: 14px 36px; font-size: 16px; font-weight: 700; border-radius: 8px; cursor: pointer; letter-spacing: 1px; }
        .insaf-btn:hover { background: #c0392b; }
        .insaf-error { background: #ff7675; color: #fff; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-weight: 600; }
    </style>

    <div class="insaf-wrap">
        <div class="insaf-card">
            <h1>⚠️ Insaf Full Reset</h1>
            <p class="sub">Complete WordPress factory reset tool</p>

            <?php if ($error): ?>
                <div class="insaf-error">❌ You must type RESET exactly to confirm!</div>
            <?php endif; ?>

            <div class="insaf-warn">
                <strong>🚨 WARNING:</strong> This will PERMANENTLY delete ALL data — posts, pages, media, users, plugins, themes, and the entire database. This cannot be undone!
            </div>

            <div class="insaf-info">
                <strong>After reset:</strong><br>
                👤 Username: <code>admin</code><br>
                🔑 Password: <code>pass</code><br>
                🌐 URL: <code><?php echo esc_html(home_url()); ?></code>
            </div>

            <h4 style="margin: 0 0 10px; color: #2d3436;">Will be deleted:</h4>
            <ul class="insaf-list">
                <li>❌ All database tables</li>
                <li>❌ All posts, pages, media, comments</li>
                <li>❌ All plugins (except this one)</li>
                <li>❌ All themes (except default)</li>
                <li>❌ All uploads & cache</li>
                <li>❌ All users</li>
            </ul>

            <form method="post" action="">
                <?php wp_nonce_field('insaf_full_reset', 'insaf_reset_nonce'); ?>
                <input type="hidden" name="insaf_do_reset" value="1">

                <div class="insaf-form-group">
                    <label>Type RESET to confirm:</label>
                    <input type="text" name="insaf_confirm" placeholder="Type RESET here..." autocomplete="off" required>
                </div>

                <button type="submit" class="insaf-btn">🔄 EXECUTE FULL RESET</button>
            </form>
        </div>
    </div>
    <?php
}

// =============================================
// SUCCESS MESSAGE ON LOGIN PAGE
// =============================================
add_action('login_message', 'insaf_reset_login_message');

function insaf_reset_login_message($message) {
    if (isset($_GET['insaf_reset']) && $_GET['insaf_reset'] === 'success') {
        $message .= '<div style="background:#00b894;color:#fff;padding:16px;border-radius:8px;margin-bottom:16px;text-align:center;font-weight:600;">';
        $message .= '✅ Reset Complete!<br>Username: <code>admin</code> | Password: <code>pass</code>';
        $message .= '</div>';
    }
    return $message;
}
