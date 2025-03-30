<?php
/**
 * Plugin Name: Procore Integration for WordPress
 * Plugin URI: https://example.com/procore-integration
 * Description: Connect WordPress to Procore API and display project information using shortcodes.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: procore-integration
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('PROCORE_INTEGRATION_VERSION', '1.0.0');
define('PROCORE_INTEGRATION_PATH', plugin_dir_path(__FILE__));
define('PROCORE_INTEGRATION_URL', plugin_dir_url(__FILE__));

class Procore_Integration {

    // Singleton instance
    private static $instance = null;

    // Plugin settings
    private $settings = [];

    /**
     * Constructor
     */
    private function __construct() {
        // Load settings
        $this->settings = get_option('procore_integration_settings', [
            'client_id' => '',
            'client_secret' => '',
            'api_url' => 'https://api.procore.com',
            'token' => '',
            'token_expires' => 0,
            'refresh_token' => '',
        ]);

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // Register shortcodes
        add_shortcode('procore_project', [$this, 'project_shortcode']);
        add_shortcode('procore_team', [$this, 'team_shortcode']);
        add_shortcode('procore_featured_image', [$this, 'featured_image_shortcode']);
        add_shortcode('procore_drawings', [$this, 'drawings_shortcode']);
        add_shortcode('procore_specifications', [$this, 'specifications_shortcode']);
        add_shortcode('procore_project_data', [$this, 'project_data_shortcode']);
    }

    /**
     * Add settings page to WordPress admin
     */
    public function add_settings_page() {
        add_options_page(
            'Procore Integration Settings',
            'Procore Integration',
            'manage_options',
            'procore-integration',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('procore_integration_settings_group', 'procore_integration_settings');

        add_settings_section(
            'procore_integration_main_section',
            'API Configuration',
            [$this, 'render_main_section'],
            'procore-integration'
        );

        add_settings_field(
            'procore_client_id',
            'Client ID',
            [$this, 'render_client_id_field'],
            'procore-integration',
            'procore_integration_main_section'
        );

        add_settings_field(
            'procore_client_secret',
            'Client Secret',
            [$this, 'render_client_secret_field'],
            'procore-integration',
            'procore_integration_main_section'
        );

        add_settings_field(
            'procore_api_url',
            'API URL',
            [$this, 'render_api_url_field'],
            'procore-integration',
            'procore_integration_main_section'
        );
    }

    /**
     * Render main settings section
     */
    public function render_main_section() {
        echo '<p>Enter your Procore API credentials below. <a href="https://developers.procore.com/documentation/oauth-flow" target="_blank">Learn more about Procore API authentication</a>.</p>';
    }

    /**
     * Render client ID field
     */
    public function render_client_id_field() {
        $value = isset($this->settings['client_id']) ? $this->settings['client_id'] : '';
        echo '<input type="text" name="procore_integration_settings[client_id]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Render client secret field
     */
    public function render_client_secret_field() {
        $value = isset($this->settings['client_secret']) ? $this->settings['client_secret'] : '';
        echo '<input type="password" name="procore_integration_settings[client_secret]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Render API URL field
     */
    public function render_api_url_field() {
        $value = isset($this->settings['api_url']) ? $this->settings['api_url'] : 'https://api.procore.com';
        echo '<input type="text" name="procore_integration_settings[api_url]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if we need to test the connection
        if (isset($_POST['test_connection'])) {
            $this->test_connection();
        }

        // Display settings form
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('procore_integration_settings_group');
                do_settings_sections('procore-integration');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>Test Connection</h2>
            <p>Click the button below to test your Procore API connection.</p>
            <form method="post">
                <?php submit_button('Test Connection', 'secondary', 'test_connection'); ?>
            </form>
            
            <hr>
            
            <h2>Shortcode Reference</h2>
            <p>Use these shortcodes to display Procore information on your WordPress site:</p>
            <ul style="margin-left: 20px; list-style-type: disc;">
                <li><code>[procore_project id="123"]</code> - Display project information</li>
                <li><code>[procore_team id="123"]</code> - Display project team members</li>
                <li><code>[procore_featured_image id="123"]</code> - Display project featured image</li>
                <li><code>[procore_drawings id="123"]</code> - Display project drawings</li>
                <li><code>[procore_specifications id="123"]</code> - Display project specifications</li>
                <li><code>[procore_project_data id="123" field="field_name"]</code> - Display specific project data</li>
            </ul>
        </div>
        <?php
    }

    /**
     * Test the Procore API connection
     */
    private function test_connection() {
        $result = $this->get_token();

        if (is_wp_error($result)) {
            add_settings_error(
                'procore_integration',
                'connection_error',
                'Connection failed: ' . $result->get_error_message(),
                'error'
            );
        } else {
            add_settings_error(
                'procore_integration',
                'connection_success',
                'Connection successful! Your authentication token has been updated.',
                'success'
            );
        }
    }

    /**
     * Get OAuth token from Procore
     */
    private function get_token() {
        // Check if we have a valid token
        if (!empty($this->settings['token']) && $this->settings['token_expires'] > time()) {
            return true;
        }

        // Check if we have a refresh token
        if (!empty($this->settings['refresh_token'])) {
            return $this->refresh_token();
        }

        // Otherwise get a new token
        $client_id = $this->settings['client_id'];
        $client_secret = $this->settings['client_secret'];
        $api_url = $this->settings['api_url'];

        if (empty($client_id) || empty($client_secret)) {
            return new WP_Error('missing_credentials', 'Client ID and Client Secret are required');
        }

        $response = wp_remote_post($api_url . '/oauth/token', [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('auth_error', $body['error_description'] ?? $body['error']);
        }

        if (empty($body['access_token'])) {
            return new WP_Error('invalid_response', 'Invalid response from Procore API');
        }

        // Update settings with new token
        $this->settings['token'] = $body['access_token'];
        $this->settings['token_expires'] = time() + ($body['expires_in'] ?? 7200);
        $this->settings['refresh_token'] = $body['refresh_token'] ?? '';
        update_option('procore_integration_settings', $this->settings);

        return true;
    }

    /**
     * Refresh OAuth token
     */
    private function refresh_token() {
        $client_id = $this->settings['client_id'];
        $client_secret = $this->settings['client_secret'];
        $api_url = $this->settings['api_url'];
        $refresh_token = $this->settings['refresh_token'];

        $response = wp_remote_post($api_url . '/oauth/token', [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            // If refresh token fails, clear it and try to get a new token
            $this->settings['refresh_token'] = '';
            update_option('procore_integration_settings', $this->settings);
            return $this->get_token();
        }

        if (empty($body['access_token'])) {
            return new WP_Error('invalid_response', 'Invalid response from Procore API');
        }

        // Update settings with new token
        $this->settings['token'] = $body['access_token'];
        $this->settings['token_expires'] = time() + ($body['expires_in'] ?? 7200);
        $this->settings['refresh_token'] = $body['refresh_token'] ?? '';
        update_option('procore_integration_settings', $this->settings);

        return true;
    }

    /**
     * Make a request to the Procore API
     */
    private function api_request($endpoint, $method = 'GET', $data = null) {
        // Get token
        $token_result = $this->get_token();
        if (is_wp_error($token_result)) {
            return $token_result;
        }

        $api_url = $this->settings['api_url'];
        $token = $this->settings['token'];

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($api_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new WP_Error(
                'api_error',
                'API Error (' . $code . '): ' . ($body['message'] ?? 'Unknown error')
            );
        }

        return $body;
    }

    /**
     * Get project details
     */
    private function get_project($project_id) {
        return $this->api_request('/rest/v1.0/projects/' . $project_id);
    }

    /**
     * Get project team members
     */
    private function get_project_team($project_id) {
        return $this->api_request('/rest/v1.0/projects/' . $project_id . '/users');
    }

    /**
     * Get project drawings
     */
    private function get_project_drawings($project_id) {
        return $this->api_request('/rest/v1.0/projects/' . $project_id . '/drawing_areas');
    }

    /**
     * Get project specifications
     */
    private function get_project_specifications($project_id) {
        return $this->api_request('/rest/v1.0/projects/' . $project_id . '/specification_sections');
    }

    /**
     * Get project image
     */
    private function get_project_image($project_id) {
        $project = $this->get_project($project_id);
        if (is_wp_error($project)) {
            return $project;
        }
        
        // Check if project has a logo
        if (!empty($project['logo_url'])) {
            return $project['logo_url'];
        }
        
        return '';
    }

    /**
     * Project information shortcode
     */
    public function project_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
        ], $atts, 'procore_project');

        if (empty($atts['id'])) {
            return '<p class="error">Error: Project ID is required</p>';
        }

        $project = $this->get_project($atts['id']);
        if (is_wp_error($project)) {
            return '<p class="error">Error: ' . esc_html($project->get_error_message()) . '</p>';
        }

        ob_start();
        ?>
        <div class="procore-project">
            <h2><?php echo esc_html($project['name']); ?></h2>
            <div class="procore-project-details">
                <p><strong>Address:</strong> <?php echo esc_html($project['address']); ?></p>
                <p><strong>City:</strong> <?php echo esc_html($project['city']); ?></p>
                <p><strong>State:</strong> <?php echo esc_html($project['state_code']); ?></p>
                <p><strong>Zip:</strong> <?php echo esc_html($project['zip']); ?></p>
                <p><strong>Start Date:</strong> <?php echo esc_html($project['start_date'] ?? 'N/A'); ?></p>
                <p><strong>Completion Date:</strong> <?php echo esc_html($project['completion_date'] ?? 'N/A'); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html($project['active'] ? 'Active' : 'Inactive'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Project team shortcode
     */
    public function team_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
        ], $atts, 'procore_team');

        if (empty($atts['id'])) {
            return '<p class="error">Error: Project ID is required</p>';
        }

        $team = $this->get_project_team($atts['id']);
        if (is_wp_error($team)) {
            return '<p class="error">Error: ' . esc_html($team->get_error_message()) . '</p>';
        }

        if (empty($team)) {
            return '<p>No team members found for this project.</p>';
        }

        ob_start();
        ?>
        <div class="procore-team">
            <h3>Project Team</h3>
            <ul class="procore-team-list">
                <?php foreach ($team as $member) : ?>
                    <li class="procore-team-member">
                        <div class="procore-member-name"><?php echo esc_html($member['name']); ?></div>
                        <div class="procore-member-email"><?php echo esc_html($member['email']); ?></div>
                        <div class="procore-member-role"><?php echo esc_html($member['role']); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Project featured image shortcode
     */
    public function featured_image_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'width' => '300',
            'height' => 'auto',
        ], $atts, 'procore_featured_image');

        if (empty($atts['id'])) {
            return '<p class="error">Error: Project ID is required</p>';
        }

        $image_url = $this->get_project_image($atts['id']);
        if (is_wp_error($image_url)) {
            return '<p class="error">Error: ' . esc_html($image_url->get_error_message()) . '</p>';
        }

        if (empty($image_url)) {
            return '<p>No featured image available for this project.</p>';
        }

        ob_start();
        ?>
        <div class="procore-project-image">
            <img src="<?php echo esc_url($image_url); ?>" 
                 alt="Project Image" 
                 width="<?php echo esc_attr($atts['width']); ?>" 
                 height="<?php echo esc_attr($atts['height']); ?>">
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Project drawings shortcode
     */
    public function drawings_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'limit' => 10,
        ], $atts, 'procore_drawings');

        if (empty($atts['id'])) {
            return '<p class="error">Error: Project ID is required</p>';
        }

        $drawings = $this->get_project_drawings($atts['id']);
        if (is_wp_error($drawings)) {
            return '<p class="error">Error: ' . esc_html($drawings->get_error_message()) . '</p>';
        }

        if (empty($drawings)) {
            return '<p>No drawings found for this project.</p>';
        }

        // Limit the number of drawings
        $limit = intval($atts['limit']);
        if ($limit > 0 && count($drawings) > $limit) {
            $drawings = array_slice($drawings, 0, $limit);
        }

        ob_start();
        ?>
        <div class="procore-drawings">
            <h3>Project Drawings</h3>
            <ul class="procore-drawings-list">
                <?php foreach ($drawings as $drawing) : ?>
                    <li class="procore-drawing">
                        <div class="procore-drawing-name"><?php echo esc_html($drawing['name']); ?></div>
                        <?php if (!empty($drawing['description'])) : ?>
                            <div class="procore-drawing-description"><?php echo esc_html($drawing['description']); ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Project specifications shortcode
     */
    public function specifications_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'limit' => 10,
        ], $atts, 'procore_specifications');

        if (empty($atts['id'])) {
            return '<p class="error">Error: Project ID is required</p>';
        }

        $specs = $this->get_project_specifications($atts['id']);
        if (is_wp_error($specs)) {
            return '<p class="error">Error: ' . esc_html($specs->get_error_message()) . '</p>';
        }

        if (empty($specs)) {
            return '<p>No specifications found for this project.</p>';
        }

        // Limit the number of specifications
        $limit = intval($atts['limit']);
        if ($limit > 0 && count($specs) > $limit) {
            $specs = array_slice($specs, 0, $limit);
        }

        ob_start();
        ?>
        <div class="procore-specifications">
            <h3>Project Specifications</h3>
            <ul class="procore-specifications-list">
                <?php foreach ($specs as $spec) : ?>
                    <li class="procore-specification">
                        <div class="procore-spec-number"><?php echo esc_html($spec['number']); ?></div>
                        <div class="procore-spec-title"><?php echo esc_html($spec['title']); ?></div>
                        <?php if (!empty($spec['description'])) : ?>
                            <div class="procore-spec-description"><?php echo esc_html($spec['description']); ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Project data shortcode
     */
    public function project_data_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'field' => '',
            'label' => '',
        ], $atts, 'procore_project_data');

        if (empty($atts['id'])) {
            return '<p class="error">Error: Project ID is required</p>';
        }

        if (empty($atts['field'])) {
            return '<p class="error">Error: Field name is required</p>';
        }

        $project = $this->get_project($atts['id']);
        if (is_wp_error($project)) {
            return '<p class="error">Error: ' . esc_html($project->get_error_message()) . '</p>';
        }

        $field = $atts['field'];
        $value = isset($project[$field]) ? $project[$field] : '';

        if (empty($value)) {
            return '<p>No data available for field "' . esc_html($field) . '"</p>';
        }

        // Format value based on type
        if (is_array($value)) {
            $value = implode(', ', $value);
        } elseif (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }

        $label = !empty($atts['label']) ? $atts['label'] : ucwords(str_replace('_', ' ', $field));

        ob_start();
        ?>
        <div class="procore-project-data">
            <span class="procore-data-label"><?php echo esc_html($label); ?>: </span>
            <span class="procore-data-value"><?php echo esc_html($value); ?></span>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the plugin
function procore_integration_init() {
    return Procore_Integration::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'procore_integration_init');

// Add plugin CSS
function procore_integration_enqueue_styles() {
    wp_enqueue_style(
        'procore-integration-styles',
        PROCORE_INTEGRATION_URL . 'assets/css/procore-integration.css',
        [],
        PROCORE_INTEGRATION_VERSION
    );
}
add_action('wp_enqueue_scripts', 'procore_integration_enqueue_styles');

// Create default CSS file on activation
function procore_integration_activate() {
    // Create assets directory if it doesn't exist
    $css_dir = PROCORE_INTEGRATION_PATH . 'assets/css';
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }

    // Create default CSS file
    $css_file = $css_dir . '/procore-integration.css';
    if (!file_exists($css_file)) {
        $css_content = <<<CSS
/* Procore Integration Styles */
.procore-project {
    margin-bottom: 30px;
}
.procore-project-details {
    margin-top: 15px;
}
.procore-team-list, 
.procore-drawings-list, 
.procore-specifications-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.procore-team-member, 
.procore-drawing, 
.procore-specification {
    margin-bottom: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}
.procore-member-name, 
.procore-drawing-name, 
.procore-spec-title {
    font-weight: bold;
    margin-bottom: 5px;
}
.procore-project-image img {
    max-width: 100%;
    height: auto;
}
.procore-project-data {
    margin: 10px 0;
}
.procore-data-label {
    font-weight: bold;
}
.error {
    color: #d63638;
    font-weight: bold;
}
CSS;
        file_put_contents($css_file, $css_content);
    }
}
register_activation_hook(__FILE__, 'procore_integration_activate');
