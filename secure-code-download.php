<?php
/**
 * Plugin Name: Secure Code Download System
 * Description: One-time download codes with secure file delivery
 * Version: 3.1 - Fixed
 * Author: Custom
 */

if (!defined('ABSPATH')) exit;

class SecureDownloadSystem {
    private $table_name;
    private $secret_key;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'download_codes';
        
        // Get or create secret key
        $this->secret_key = get_option('sds_secret_key');
        if (!$this->secret_key) {
            $this->secret_key = wp_generate_password(64, true, true);
            update_option('sds_secret_key', $this->secret_key);
        }
        
        // Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_shortcode('download_form', [$this, 'render_form']);
        add_action('template_redirect', [$this, 'handle_download'], 1);
        add_action('wp_head', [$this, 'output_custom_css']);
    }
    
    // Create database table
    public function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            download_code varchar(50) NOT NULL,
            is_used tinyint(1) DEFAULT 0,
            used_ip varchar(100) DEFAULT NULL,
            used_date datetime DEFAULT NULL,
            download_attempts int DEFAULT 0,
            last_attempt_date datetime DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY download_code (download_code),
            KEY is_used (is_used)
        ) $charset;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Test table creation
        $test = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        if ($test) {
            update_option('sds_table_created', 'yes');
        }
    }
    
    // Admin menu
    public function add_admin_menu() {
        add_menu_page(
            'Download Codes',
            'Download Codes',
            'manage_options',
            'download-codes',
            [$this, 'admin_page'],
            'dashicons-lock',
            30
        );
    }
    
    // Admin page
    public function admin_page() {
        global $wpdb;
        
        // Handle actions
        if (isset($_POST['action'])) {
            check_admin_referer('sds_admin_action');
            
            if ($_POST['action'] === 'generate' && isset($_POST['count'])) {
                $count = min((int)$_POST['count'], 5000);
                $generated = $this->generate_codes($count);
                echo '<div class="notice notice-success"><p>Generated ' . $generated . ' codes!</p></div>';
            }
            
            if ($_POST['action'] === 'delete_all') {
                $wpdb->query("TRUNCATE TABLE {$this->table_name}");
                echo '<div class="notice notice-success"><p>All codes deleted!</p></div>';
            }
            
            if ($_POST['action'] === 'reset_database') {
                // Drop and recreate table
                $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
                $this->activate(); // Recreate with correct structure
                echo '<div class="notice notice-success"><p><strong>Database reset successfully!</strong> Table recreated with correct structure.</p></div>';
            }
            
            if ($_POST['action'] === 'save_css' && isset($_POST['custom_css'])) {
                update_option('sds_custom_css', wp_strip_all_tags($_POST['custom_css']));
                $disable_css = isset($_POST['disable_plugin_css']) ? 1 : 0;
                update_option('sds_disable_plugin_css', $disable_css);
                echo '<div class="notice notice-success"><p>Custom CSS saved!</p></div>';
            }
        }
        
        // Get stats
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $used = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE is_used = 1");
        $available = $total - $used;
        
        // Check file setup
        $upload_dir = wp_upload_dir();
        $secure_dir = $upload_dir['basedir'] . '/secure-files';
        $test_file = $secure_dir . '/download.zip';  // Changed to .zip
        
        ?>
        <div class="wrap">
            <h1>🔐 Secure Download Codes</h1>
            
            <!-- Setup Status -->
            <div class="notice notice-info">
                <h3>📋 Setup Checklist</h3>
                <ul style="margin-left: 20px;">
                    <li><?php echo get_option('sds_table_created') ? '✅' : '❌'; ?> Database table created</li>
                    <li><?php echo is_dir($secure_dir) ? '✅' : '❌'; ?> Secure directory exists: <code><?php echo $secure_dir; ?></code></li>
                    <li><?php echo file_exists($test_file) ? '✅' : '❌'; ?> Download file exists: <code><?php echo $test_file; ?></code></li>
                    <li><?php echo file_exists($secure_dir . '/.htaccess') ? '✅' : '❌'; ?> Security file (.htaccess) exists</li>
                </ul>
                
                <?php if (!is_dir($secure_dir)): ?>
                    <p><strong>Action needed:</strong> Create directory and upload your file:</p>
                    <pre style="background: #f0f0f0; padding: 10px; border-radius: 4px;">
mkdir -p <?php echo $secure_dir; ?>

echo "Deny from all" > <?php echo $secure_dir; ?>/.htaccess
# Upload your ZIP file as: <?php echo $test_file; ?>

# Set correct permissions:
chmod 755 <?php echo $secure_dir; ?>

chmod 644 <?php echo $secure_dir; ?>/.htaccess
chmod 644 <?php echo $test_file; ?>

                    </pre>
                <?php endif; ?>
                
                <?php if (file_exists($test_file)): ?>
                    <p style="margin-top: 10px;">
                        <strong>📦 File Info:</strong> 
                        <?php echo basename($test_file); ?> 
                        (<?php echo size_format(filesize($test_file)); ?>)
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Statistics -->
            <div class="card" style="max-width: 400px;">
                <h2>📊 Statistics</h2>
                <table class="widefat" style="width: 100%;">
                    <tr>
                        <td><strong>Total Codes:</strong></td>
                        <td><?php echo number_format($total); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Used:</strong></td>
                        <td><?php echo number_format($used); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Available:</strong></td>
                        <td style="color: <?php echo $available > 0 ? 'green' : 'red'; ?>;">
                            <?php echo number_format($available); ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Generate Codes -->
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>⚙️ Generate Codes</h2>
                <form method="post">
                    <?php wp_nonce_field('sds_admin_action'); ?>
                    <input type="hidden" name="action" value="generate">
                    <p>
                        <label>How many codes to generate?</label><br>
                        <input type="number" name="count" value="500" min="1" max="5000" style="width: 150px;">
                        <button type="submit" class="button button-primary">Generate Codes</button>
                    </p>
                </form>
            </div>
            
            <!-- Export Codes -->
            <?php if ($available > 0): ?>
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>📥 Export Codes</h2>
                <p>
                    <a href="<?php echo admin_url('admin-ajax.php?action=export_download_codes&nonce=' . wp_create_nonce('export_codes')); ?>" 
                       class="button button-secondary">
                        Download Unused Codes (CSV)
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Usage Instructions -->
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>📖 Usage Instructions</h2>
                <ol>
                    <li>Complete the setup checklist above</li>
                    <li>Generate codes using the form</li>
                    <li>Export and distribute codes to users</li>
                    <li>Add this shortcode to any page/post: <code>[download_form]</code></li>
                </ol>
                <p><strong>Test it:</strong> Create a test page with the shortcode and try a code!</p>
            </div>
            
            <!-- Custom CSS -->
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>🎨 Custom CSS Styling</h2>
                <p>Customize the appearance of the download form. Use these CSS classes:</p>
                <ul style="font-family: monospace; margin-left: 20px;">
                    <li><code>.sds-download-form</code> - Main form container</li>
                    <li><code>.sds-download-form h3</code> - Form heading</li>
                    <li><code>.sds-download-form input[type="text"]</code> - Code input field</li>
                    <li><code>.sds-download-form button</code> - Submit button</li>
                    <li><code>.sds-message</code> - Message container</li>
                    <li><code>.sds-message.error</code> - Error message</li>
                    <li><code>.sds-message.success</code> - Success message</li>
                </ul>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('sds_admin_action'); ?>
                    <input type="hidden" name="action" value="save_css">
                    
                    <p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" 
                                   name="disable_plugin_css" 
                                   value="1" 
                                   <?php checked(get_option('sds_disable_plugin_css'), 1); ?>
                                   style="margin-right: 10px;">
                            <strong>Disable plugin CSS (use Elementor custom CSS instead)</strong>
                        </label>
                        <small style="display: block; margin-top: 5px; margin-left: 24px;">
                            Check this if you're styling the form with Elementor's custom CSS panel to avoid conflicts.
                        </small>
                    </p>
                    
                    <p>
                        <label><strong>Custom CSS:</strong></label><br>
                        <textarea name="custom_css" 
                                  rows="15" 
                                  style="width: 100%; font-family: monospace; font-size: 13px;"><?php 
                            echo esc_textarea(get_option('sds_custom_css', '')); 
                        ?></textarea>
                    </p>
                    <p>
                        <button type="submit" class="button button-primary">Save Custom CSS</button>
                        <button type="button" class="button" onclick="document.querySelector('textarea[name=custom_css]').value = document.getElementById('default-css').textContent.trim();">Load Default CSS</button>
                    </p>
                </form>
                
                <!-- Default CSS Template (hidden) -->
                <script id="default-css" type="text/plain">
/* Download Form Container */
.sds-download-form {
    max-width: 500px;
    margin: 20px auto;
    padding: 30px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}

/* Form Heading */
.sds-download-form h3 {
    margin-top: 0;
    color: #333;
    font-size: 24px;
}

/* Code Input Field */
.sds-download-form input[type="text"] {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    border: 2px solid #ddd;
    border-radius: 4px;
    margin-bottom: 15px;
    box-sizing: border-box;
}

.sds-download-form input[type="text"]:focus {
    border-color: #0073aa;
    outline: none;
}

/* Submit Button */
.sds-download-form button {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    cursor: pointer;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
}

.sds-download-form button:hover {
    background: #005177;
}

/* Messages */
.sds-message {
    padding: 12px;
    margin: 15px 0;
    border-radius: 4px;
    border-left: 4px solid;
}

.sds-message.error {
    background: #dc354522;
    border-color: #dc3545;
    color: #dc3545;
}

.sds-message.success {
    background: #28a74522;
    border-color: #28a745;
    color: #28a745;
}
</script>
            </div>
            
            <!-- Danger Zone -->
            <div class="card" style="max-width: 600px; margin-top: 20px; border: 2px solid #dc3545;">
                <h2 style="color: #dc3545;">⚠️ Danger Zone</h2>
                
                <div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                    <h3 style="margin-top: 0; font-size: 16px;">Reset Database Table</h3>
                    <p style="margin-bottom: 10px;">Use this if the table structure is outdated or corrupted. This will:</p>
                    <ul style="margin-left: 20px;">
                        <li>Delete the existing table completely</li>
                        <li>Recreate it with the correct structure</li>
                        <li>Remove all existing codes (you'll need to regenerate)</li>
                    </ul>
                    <form method="post" onsubmit="return confirm('⚠️ WARNING: This will delete ALL codes and recreate the table. Continue?');" style="margin-top: 15px;">
                        <?php wp_nonce_field('sds_admin_action'); ?>
                        <input type="hidden" name="action" value="reset_database">
                        <button type="submit" class="button button-secondary" style="background: #ffc107; border-color: #ffc107;">
                            🔄 Reset Database Table
                        </button>
                    </form>
                </div>
                
                <div style="padding: 15px; background: #ffebee; border-left: 4px solid #dc3545;">
                    <h3 style="margin-top: 0; font-size: 16px;">Delete All Codes</h3>
                    <p style="margin-bottom: 10px;">This only deletes the codes, keeps the table structure intact.</p>
                    <form method="post" onsubmit="return confirm('Delete ALL codes? This cannot be undone!');">
                        <?php wp_nonce_field('sds_admin_action'); ?>
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="button button-secondary" style="background: #dc3545; border-color: #dc3545; color: white;">
                            🗑️ Delete All Codes
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Generate codes
    private function generate_codes($count) {
        global $wpdb;
        $generated = 0;
        
        for ($i = 0; $i < $count; $i++) {
            $code = $this->create_unique_code();
            $result = $wpdb->insert(
                $this->table_name,
                ['download_code' => $code],
                ['%s']
            );
            if ($result) $generated++;
        }
        
        return $generated;
    }
    
    // Create unique code (6 characters: letters + numbers only)
    private function create_unique_code() {
        // Generate 6-character alphanumeric code
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing chars: 0,O,1,I
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    // Render download form
    public function render_form() {
        ob_start();
        ?>
        <div class="sds-download-form">
            <h3>Enter Your Download Code</h3>
            
            <form method="post">
                <?php wp_nonce_field('sds_download'); ?>
                <input type="text" 
                       name="download_code" 
                       placeholder="Enter code here" 
                       required
                       autocomplete="off">
                <button type="submit" name="submit_download">
                    Download File
                </button>
            </form>
            
            <?php
            // Show messages
            if (isset($_GET['msg'])) {
                $messages = [
                    'invalid' => ['❌ Invalid code. Please check and try again.', 'error'],
                    'used' => ['❌ This code has already been used by someone else.', 'error'],
                    'already_used' => ['❌ This code has already been used by someone else.', 'error'],
                    'max_attempts' => ['❌ Maximum download attempts (3) reached for this code.', 'error'],
                    'expired' => ['❌ Download link expired.', 'error'],
                ];
                
                if (isset($messages[$_GET['msg']])) {
                    list($text, $type) = $messages[$_GET['msg']];
                    echo '<p class="sds-message ' . esc_attr($type) . '">' . esc_html($text) . '</p>';
                }
                
                // Clear the message from URL using JavaScript
                echo '<script>
                if (window.history.replaceState) {
                    const url = new URL(window.location);
                    url.searchParams.delete("msg");
                    window.history.replaceState({}, document.title, url.toString());
                }
                </script>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Output custom CSS
    public function output_custom_css() {
        // Check if plugin CSS is disabled
        if (get_option('sds_disable_plugin_css')) {
            return; // Don't output any CSS - let Elementor handle it
        }
        
        $custom_css = get_option('sds_custom_css', '');
        
        // Default CSS if none set
        if (empty($custom_css)) {
            $custom_css = '
.sds-download-form {
    max-width: 500px;
    margin: 20px auto;
    padding: 30px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}

.sds-download-form h3 {
    margin-top: 0;
    color: #333;
    font-size: 24px;
}

.sds-download-form input[type="text"] {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    border: 2px solid #ddd;
    border-radius: 4px;
    margin-bottom: 15px;
    box-sizing: border-box;
}

.sds-download-form input[type="text"]:focus {
    border-color: #0073aa;
    outline: none;
}

.sds-download-form button {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    cursor: pointer;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
}

.sds-download-form button:hover {
    background: #005177;
}

.sds-message {
    padding: 12px;
    margin: 15px 0;
    border-radius: 4px;
    border-left: 4px solid;
}

.sds-message.error {
    background: #dc354522;
    border-color: #dc3545;
    color: #dc3545;
}

.sds-message.success {
    background: #28a74522;
    border-color: #28a745;
    color: #28a745;
}';
        }
        
        echo '<style type="text/css">' . $custom_css . '</style>';
    }
    
    // Handle download request
    public function handle_download() {
        // Check if this is a download request
        if (isset($_POST['submit_download']) && isset($_POST['download_code'])) {
            // Verify nonce
            if (!wp_verify_nonce($_POST['_wpnonce'], 'sds_download')) {
                $current_url = remove_query_arg('msg');
                wp_redirect(add_query_arg('msg', 'invalid', $current_url));
                exit;
            }
            
            $code = sanitize_text_field($_POST['download_code']);
            $result = $this->validate_code($code);
            
            if ($result === 'valid') {
                // Generate secure download URL
                $url = $this->create_download_url($code);
                wp_redirect($url);
                exit;
            } else {
                // Redirect back with error, but clean URL first
                $current_url = remove_query_arg('msg');
                wp_redirect(add_query_arg('msg', $result, $current_url));
                exit;
            }
        }
        
        // Handle actual file download
        if (isset($_GET['sds_download']) && isset($_GET['token']) && isset($_GET['expires']) && isset($_GET['sig'])) {
            $this->serve_file();
        }
    }
    
    // Validate code with grace period and attempt limits
    private function validate_code($code) {
        global $wpdb;
        
        $current_ip = $_SERVER['REMOTE_ADDR'];
        
        // Get code from database
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE download_code = %s",
            $code
        ));
        
        if (!$record) {
            return 'invalid';
        }
        
        // Check if code is used
        if ($record->is_used == 1) {
            // Check grace period (15 minutes) and same IP
            $grace_period = 15 * 60; // 15 minutes in seconds
            $time_since_use = time() - strtotime($record->used_date);
            
            if ($record->used_ip === $current_ip && $time_since_use < $grace_period) {
                // Within grace period and same IP - check attempt limit
                if ($record->download_attempts >= 3) {
                    return 'max_attempts';
                }
                
                // Allow retry - increment attempts
                $wpdb->update(
                    $this->table_name,
                    [
                        'download_attempts' => $record->download_attempts + 1,
                        'last_attempt_date' => current_time('mysql')
                    ],
                    ['download_code' => $code],
                    ['%d', '%s'],
                    ['%s']
                );
                
                return 'valid';
            }
            
            return 'already_used';
        }
        
        // First time use - mark as used
        $wpdb->update(
            $this->table_name,
            [
                'is_used' => 1,
                'used_ip' => $current_ip,
                'used_date' => current_time('mysql'),
                'download_attempts' => 1,
                'last_attempt_date' => current_time('mysql')
            ],
            ['download_code' => $code],
            ['%d', '%s', '%s', '%d', '%s'],
            ['%s']
        );
        
        return 'valid';
    }
    
    // Create secure download URL
    private function create_download_url($code) {
        $expires = time() + 1; // 1 second as you wanted
        $token = wp_generate_password(32, false);
        
        // Create signature
        $data = $code . '|' . $expires . '|' . $token;
        $signature = hash_hmac('sha256', $data, $this->secret_key);
        
        return add_query_arg([
            'sds_download' => '1',
            'token' => $token,
            'expires' => $expires,
            'sig' => $signature
        ], home_url('/'));
    }
    
    // Serve the file
    private function serve_file() {
        global $wpdb;
        
        $token = sanitize_text_field($_GET['token']);
        $expires = (int)$_GET['expires'];
        $signature = sanitize_text_field($_GET['sig']);
        $current_ip = $_SERVER['REMOTE_ADDR'];
        
        // Check expiration
        if (time() > $expires) {
            wp_die('Download link expired. Please go back and submit the code again.', 'Link Expired', ['response' => 403]);
        }
        
        // Get the most recent code used by this IP (within last 15 minutes)
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT download_code, used_date, download_attempts FROM {$this->table_name} 
             WHERE is_used = 1 AND used_ip = %s 
             AND used_date > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
             ORDER BY used_date DESC LIMIT 1",
            $current_ip
        ));
        
        if (!$record) {
            wp_die('Invalid download request. Please go back and enter your code again.', 'Access Denied', ['response' => 403]);
        }
        
        $code = $record->download_code;
        
        // Verify signature
        $data = $code . '|' . $expires . '|' . $token;
        $valid_sig = hash_hmac('sha256', $data, $this->secret_key);
        
        if (!hash_equals($valid_sig, $signature)) {
            wp_die('Invalid download link. Please go back and submit your code again.', 'Access Denied', ['response' => 403]);
        }
        
        // Get file path - now looking for .zip
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/secure-files/download.zip';
        
        if (!file_exists($file_path)) {
            wp_die('File not found. Please contact support.', 'File Not Found', ['response' => 404]);
        }
        
        // Disable PHP time limit for large files
        set_time_limit(0);
        
        // Clear output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Serve the file with proper headers for ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="download.zip"');
        header('Content-Length: ' . filesize($file_path));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Accept-Ranges: bytes');
        
        // Use readfile for files under 100MB, chunked reading for larger files
        if (filesize($file_path) < 100 * 1024 * 1024) {
            readfile($file_path);
        } else {
            // Chunked reading for large files (150MB)
            $file = fopen($file_path, 'rb');
            while (!feof($file)) {
                echo fread($file, 8192); // 8KB chunks
                flush(); // Flush output buffer
            }
            fclose($file);
        }
        
        exit;
    }
}

// Initialize plugin
new SecureDownloadSystem();

// Export codes via AJAX
add_action('wp_ajax_export_download_codes', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'export_codes')) {
        wp_die('Invalid request');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'download_codes';
    $codes = $wpdb->get_results("SELECT download_code FROM {$table} WHERE is_used = 0", ARRAY_A);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="codes-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Download Code']);
    
    foreach ($codes as $row) {
        fputcsv($output, [$row['download_code']]);
    }
    
    fclose($output);
    exit;
});