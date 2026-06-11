<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_admin_menus'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));

        // Shortcodes for frontend lead manager & dashboard
        add_shortcode('idlm_leads_list', array(__CLASS__, 'render_leads_list_shortcode'));
        add_shortcode('idlm_dashboard', array(__CLASS__, 'render_dashboard_shortcode'));
        
        // AJAX endpoints
        add_action('wp_ajax_idlm_get_lead_details', array(__CLASS__, 'ajax_get_lead_details'));
        add_action('wp_ajax_idlm_get_lead_activity', array(__CLASS__, 'ajax_get_lead_activity'));
        add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));
        add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));
        add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));
        add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));
        add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));
        add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));
        add_action('wp_ajax_idlm_save_cost_per_lead', array(__CLASS__, 'ajax_save_cost_per_lead'));
        add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));
        add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));
        add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));
        add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));
        add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));
        add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));
        add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));
        add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));
        add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));
        add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));
        add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));
    }

    /**
     * Get list of allowed roles for representative users
     */
    public static function get_allowed_rep_roles() {
        return array(
            'administrator',
            'editor',
            'author',
            'contributor',
            'marketing',
            'sales_manager',
            'sales-manager',
            'lead_owner',
            'lead-owner',
            'marketing_manager',
            'marketing-manager',
            'sales_executive',
            'sales-executive',
            'agent',
            'agentadmin'
        );
    }

    /**
     * Format a database datetime string using the WordPress timezone settings
     */
    public static function format_datetime($mysql_date, $format = 'M d, Y h:i A') {
        if (empty($mysql_date)) {
            return '';
        }
        try {
            $dt = new DateTime($mysql_date, wp_timezone());
            return wp_date($format, $dt->getTimestamp());
        } catch (Exception $e) {
            return $mysql_date;
        }
    }

    /**
     * Check if the current user has representative access (administrator or allowed rep role)
     */
    public static function current_user_has_rep_access() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $current_user = wp_get_current_user();
        if (empty($current_user->roles)) {
            return false;
        }

        $allowed_roles = self::get_allowed_rep_roles();
        foreach ($current_user->roles as $role) {
            if (in_array($role, $allowed_roles, true)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Get all active follow-up reminder rules (with fallback to singular option settings)
     */
    public static function get_reminder_rules() {
        $rules = get_option('idlm_reminders_config');
        if (empty($rules)) {
            $reminder_status = get_option('idlm_reminder_status', 'Call Back Requested');
            $reminder_hours = get_option('idlm_reminder_hours', 24);
            $rules = array(
                array(
                    'status' => $reminder_status,
                    'hours'  => $reminder_hours
                )
            );
        }
        return $rules;
    }

    /**
     * Check if a lead's follow-up is overdue based on rules
     */
    public static function check_lead_overdue_status($lead, $rules = null) {
        if (!$rules) {
            $rules = self::get_reminder_rules();
        }

        $current_status = $lead->call_status;
        $matched_rule = null;
        foreach ($rules as $rule) {
            if ($rule['status'] === $current_status) {
                $matched_rule = $rule;
                break;
            }
        }

        if (!$matched_rule) {
            return false;
        }

        $interval_hours = intval($matched_rule['hours']);
        $now_time = current_time('timestamp');

        // Check if Call Back Requested (callback_datetime holds priority if populated)
        if ($current_status === 'Call Back Requested' && !empty($lead->callback_datetime)) {
            $callback_time = strtotime($lead->callback_datetime);
            if ($now_time > ($callback_time + $interval_hours * 3600)) {
                return array(
                    'overdue' => true,
                    'hours_overdue' => max(0, round(($now_time - $callback_time) / 3600)),
                    'reason' => 'Callback scheduled for ' . wp_date('M d, h:i A', $callback_time)
                );
            }
            return false;
        }

        // For standard status queues, measure elapsed time from last update (fallback: created_at)
        $ref_time = !empty($lead->updated_at) ? strtotime($lead->updated_at) : strtotime($lead->created_at);
        $elapsed_hours = ($now_time - $ref_time) / 3600;

        if ($elapsed_hours > $interval_hours) {
            return array(
                'overdue' => true,
                'hours_overdue' => max(0, round($elapsed_hours - $interval_hours)),
                'reason' => "Status '$current_status' for " . round($elapsed_hours) . " hrs"
            );
        }

        return false;
    }

    public static function get_pending_calls_count() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $reps_see_all = get_option('idlm_reps_see_all_leads', '1');

        // Dynamically get statuses from active reminder rules, plus 'Not Called' as baseline
        $statuses = array('Not Called');
        $rules = self::get_reminder_rules();
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                if (!empty($rule['status'])) {
                    $statuses[] = $rule['status'];
                }
            }
        }
        $statuses = array_unique($statuses);

        // Build IN query safely
        $in_clause = implode("','", array_map('esc_sql', $statuses));

        if (!$is_admin && !$reps_see_all) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            return intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(id) FROM {$table_leads} WHERE call_status IN ('{$in_clause}') AND assigned_rep_id = %d",
                $current_user_id
            )));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            return intval($wpdb->get_var("SELECT COUNT(id) FROM {$table_leads} WHERE call_status IN ('{$in_clause}')"));
        }
    }

    /**
     * Register shared core style and script assets
     */
    public static function register_core_assets() {
        // Web Fonts (stored locally)
        wp_register_style('idlm-google-fonts', plugin_dir_url(dirname(__FILE__)) . 'assets/css/fonts.css', array(), IDLM_VERSION);
        
        // ChartJS
        wp_register_script('idlm-chartjs', plugin_dir_url(dirname(__FILE__)) . 'assets/js/chart.js', array(), '4.4.1', true);

        // Core Styles
        wp_register_style('idlm-admin-styles', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-style.css', array(), IDLM_VERSION);
        wp_register_style('idlm-dashboard-styles', plugin_dir_url(dirname(__FILE__)) . 'assets/css/dashboard.css', array(), IDLM_VERSION);

        // JS logic
        wp_register_script('idlm-admin-app', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-app.js', array('jquery'), IDLM_VERSION, true);
        wp_register_script('idlm-dashboard-app', plugin_dir_url(dirname(__FILE__)) . 'assets/js/dashboard.js', array('jquery', 'idlm-chartjs'), IDLM_VERSION, true);

        // Localize vars
        wp_localize_script('idlm-admin-app', 'idlm_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('idlm_admin_nonce')
        ));
    }

    /**
     * Enqueue Google Fonts, Chart.js, styles and admin client JS inside backend
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'interior-design-lead-manager') === false && strpos($hook, 'idlm_') === false) {
            return;
        }

        self::register_core_assets();

        wp_enqueue_style('idlm-google-fonts');
        wp_enqueue_script('idlm-chartjs');
        wp_enqueue_style('idlm-admin-styles');
        wp_enqueue_style('idlm-dashboard-styles');
        wp_enqueue_script('idlm-admin-app');
        wp_enqueue_script('idlm-dashboard-app');
    }

    /**
     * Conditionally enqueue style and script assets on frontend pages
     */
    public static function enqueue_frontend_assets() {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_leads_list = has_shortcode($post->post_content, 'idlm_leads_list');
        $has_dashboard  = has_shortcode($post->post_content, 'idlm_dashboard');

        if ($has_leads_list || $has_dashboard) {
            self::register_core_assets();

            wp_enqueue_style('idlm-google-fonts');
            wp_enqueue_style('idlm-admin-styles');
            wp_enqueue_script('idlm-admin-app');

            if ($has_dashboard) {
                wp_enqueue_style('idlm-dashboard-styles');
                wp_enqueue_script('idlm-chartjs');
                wp_enqueue_script('idlm-dashboard-app');
            }
        }
    }

    /**
     * Frontend Shortcode handler for Leads List Workspace [idlm_leads_list]
     */
    public static function render_leads_list_shortcode() {
        if (!self::current_user_has_rep_access()) {
            return '<div class="idlm-wrap"><div class="idlm-card" style="padding: 24px; text-align: center; color: #64748b; font-family: \'Inter\', sans-serif;"><h3 style="color: #1e293b; margin-bottom: 8px;">Access Restricted</h3><p>You do not have permission to access this resource. Please log in with an authorized account.</p></div></div>';
        }

        // Force register & enqueue assets (fallback for builders/widgets)
        self::register_core_assets();
        wp_enqueue_style('idlm-google-fonts');
        wp_enqueue_style('idlm-admin-styles');
        wp_enqueue_script('idlm-admin-app');

        ob_start();
        self::render_leads_list_page();
        return ob_get_clean();
    }

    /**
     * Frontend Shortcode handler for Analytics Dashboard [idlm_dashboard]
     */
    public static function render_dashboard_shortcode() {
        if (!self::current_user_has_rep_access()) {
            return '<div class="idlm-wrap"><div class="idlm-card" style="padding: 24px; text-align: center; color: #64748b; font-family: \'Inter\', sans-serif;"><h3 style="color: #1e293b; margin-bottom: 8px;">Access Restricted</h3><p>You do not have permission to access this resource. Please log in with an authorized account.</p></div></div>';
        }

        // Force register & enqueue assets (fallback for builders/widgets)
        self::register_core_assets();
        wp_enqueue_style('idlm-google-fonts');
        wp_enqueue_style('idlm-admin-styles');
        wp_enqueue_style('idlm-dashboard-styles');
        wp_enqueue_script('idlm-chartjs');
        wp_enqueue_script('idlm-admin-app');
        wp_enqueue_script('idlm-dashboard-app');

        ob_start();
        self::render_dashboard_page();
        return ob_get_clean();
    }

    /**
     * Set up admin menus
     */
    public static function register_admin_menus() {
        add_menu_page(
            'Lead Manager',
            'Lead Manager',
            'read', // Reps can see leads page, access logic restricted per lead
            'interior-design-lead-manager',
            array(__CLASS__, 'render_dashboard_page'),
            'dashicons-groups',
            26
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Dashboard',
            'Dashboard',
            'read',
            'interior-design-lead-manager',
            array(__CLASS__, 'render_dashboard_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Leads List',
            'Leads List',
            'read',
            'idlm_leads_list',
            array(__CLASS__, 'render_leads_list_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'CAPI Audit Logs',
            'CAPI Audit Logs',
            'manage_options',
            'idlm_capi_logs',
            array(__CLASS__, 'render_capi_logs_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Audience Sync Logs',
            'Audience Sync Logs',
            'manage_options',
            'idlm_audience_sync_logs',
            array(__CLASS__, 'render_audience_sync_logs_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Settings',
            'Settings',
            'manage_options',
            'idlm_settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Dashboard Render Page
     */
    public static function render_dashboard_page() {
        if (!self::current_user_has_rep_access()) {
            echo '<div class="wrap idlm-wrap"><div class="idlm-card" style="padding: 24px; text-align: center; color: #64748b; font-family: \'Inter\', sans-serif;"><h3 style="color: #1e293b; margin-bottom: 8px;">Access Restricted</h3><p>You do not have permission to access this resource. Please log in with an authorized account.</p></div></div>';
            return;
        }
        ?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>Lead Management Dashboard</h1>
                    <p class="idlm-header-subtitle">Performance Analytics and Conversion Pipelines</p>
                </div>
                <div class="idlm-header-actions">
                    <select id="idlm-period-selector" class="idlm-input">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="this_month" selected>This Month</option>
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="last_month">Last Month</option>
                        <option value="this_year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                    <div id="idlm-custom-range-picker" style="display:none; align-items:center; gap: 5px;">
                        <input type="date" id="idlm-start-date" class="idlm-input" style="width:130px;">
                        <span>to</span>
                        <input type="date" id="idlm-end-date" class="idlm-input" style="width:130px;">
                        <button id="idlm-apply-custom" class="idlm-btn idlm-btn-primary">Apply</button>
                    </div>
                </div>
            </header>

            <!-- Dashboard Tabs -->
            <div class="idlm-tabs">
                <button class="idlm-tab-link active" onclick="switchDashboardTab(event, 'idlm-marketing-tab')">Marketing Section (Source Performance)</button>
                <button class="idlm-tab-link" onclick="switchDashboardTab(event, 'idlm-sales-tab')">Sales Section (Pipeline Performance)</button>
            </div>

            <!-- Targets Progress Area -->
            <div id="idlm-targets-container" class="idlm-card idlm-targets-card">
                <h3>Monthly Conversion Progress</h3>
                <div class="idlm-progress-wrapper">
                    <div class="idlm-progress-item">
                        <div class="idlm-progress-header">
                            <span>Leads Goal</span>
                            <strong id="lead-target-ratio">0/0</strong>
                        </div>
                        <div class="idlm-progress-bar-bg">
                            <div id="lead-target-bar" class="idlm-progress-bar" style="width: 0%;"></div>
                        </div>
                    </div>
                    <div class="idlm-progress-item">
                        <div class="idlm-progress-header">
                            <span>Won Conversions Goal</span>
                            <strong id="conv-target-ratio">0/0</strong>
                        </div>
                        <div class="idlm-progress-bar-bg">
                            <div id="conv-target-bar" class="idlm-progress-bar idlm-progress-bar-success" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Marketing Tab -->
            <div id="idlm-marketing-tab" class="idlm-tab-content active">
                <div class="idlm-grid-stats">
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Total Leads (Period)</span>
                        <div class="idlm-stat-val-wrapper">
                            <span id="mkt-total-leads" class="idlm-stat-val">0</span>
                            <span id="mkt-growth-pct" class="idlm-stat-trend">0%</span>
                        </div>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Total Leads (Previous Period)</span>
                        <span id="mkt-prev-leads" class="idlm-stat-val-secondary">0</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Top Channel</span>
                        <span id="mkt-top-channel" class="idlm-stat-val-secondary">N/A</span>
                    </div>
                </div>

                <div class="idlm-card" style="margin-top: 25px;">
                    <h3>Leads Over Time</h3>
                    <div class="idlm-chart-container" style="height: 320px;">
                        <canvas id="chart-leads-over-time"></canvas>
                    </div>
                </div>

                <div class="idlm-card" style="margin-top: 25px;">
                    <h3>Lead Quality by Channel</h3>
                    <div class="idlm-chart-container">
                        <canvas id="chart-leads-by-channel"></canvas>
                    </div>
                </div>

                <div class="idlm-grid-charts" style="margin-top: 25px;">
                    <div class="idlm-card">
                        <h3>Leads by Property Type</h3>
                        <div class="idlm-chart-container">
                            <canvas id="chart-property-types"></canvas>
                        </div>
                    </div>
                    <div class="idlm-card">
                        <h3>Leads by Room Configuration (BHK)</h3>
                        <div class="idlm-chart-container">
                            <canvas id="chart-room-configs"></canvas>
                        </div>
                    </div>
                </div>

                <div class="idlm-grid-charts" style="grid-template-columns: 1fr; margin-top: 25px;">
                    <div class="idlm-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                            <h3 id="idlm-utm-chart-title" style="margin: 0 !important;">Top UTM Campaigns</h3>
                            <select id="idlm-utm-type-selector" class="idlm-input" style="width: 180px;">
                                <option value="utm_campaign" selected>UTM Campaign</option>
                                <option value="utm_source">UTM Source</option>
                                <option value="utm_medium">UTM Medium</option>
                                <option value="utm_adgroup">UTM Adgroup</option>
                                <option value="utm_term">UTM Term</option>
                                <option value="utm_content">UTM Content</option>
                            </select>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-utm-campaigns"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Tab -->
            <div id="idlm-sales-tab" class="idlm-tab-content">
                <div class="idlm-grid-stats">
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Total Converted (Won)</span>
                        <span id="sales-total-won" class="idlm-stat-val">0</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Average Speed to Connected</span>
                        <span id="sales-avg-connected" class="idlm-stat-val-secondary">N/A</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Average Speed Connected to Won</span>
                        <span id="sales-avg-won" class="idlm-stat-val-secondary">N/A</span>
                    </div>
                </div>

                <div class="idlm-card" style="margin-bottom: 25px;">
                    <h3>Sales Conversion Funnel</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 25px;">
                        Analyze the cumulative progression and conversion velocity of leads through the sales lifecycle.
                    </p>
                    <div class="idlm-funnel-wrapper" id="sales-funnel-container">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>

                <div class="idlm-grid-charts">
                    <div class="idlm-card">
                        <h3>Pipeline Stage Funnel</h3>
                        <div class="idlm-chart-container">
                            <canvas id="chart-pipeline-stages"></canvas>
                        </div>
                    </div>
                    <div class="idlm-card">
                        <h3>Call Status Distribution</h3>
                        <div class="idlm-chart-container">
                            <canvas id="chart-call-status-dist"></canvas>
                        </div>
                    </div>
                </div>

                <div class="idlm-grid-charts" style="margin-top: 25px;">
                    <div class="idlm-card">
                        <h3>Overall Lead Quality Distribution</h3>
                        <div class="idlm-chart-container">
                            <canvas id="chart-overall-quality"></canvas>
                        </div>
                    </div>
                    <div class="idlm-card">
                        <h3>Preferred Studio Distribution</h3>
                        <div class="idlm-chart-container">
                            <canvas id="chart-studio-dist"></canvas>
                        </div>
                    </div>
                </div>

                <div class="idlm-card" style="margin-top: 25px;">
                    <h3>Studio Branch Conversion Performance</h3>
                    <div class="idlm-table-wrapper">
                        <table class="idlm-table" id="studio-performance-table">
                            <thead>
                                <tr>
                                    <th>Studio Branch</th>
                                    <th>Total Leads</th>
                                    <th>Converted (Won)</th>
                                    <th>Conversion Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="idlm-card" style="margin-top: 25px;">
                    <h3>Sales Representatives Performance</h3>
                    <div class="idlm-table-wrapper">
                        <table class="idlm-table" id="sales-rep-table">
                            <thead>
                                <tr>
                                    <th>Rep Name</th>
                                    <th>Assigned Leads</th>
                                    <th>Contacted Leads</th>
                                    <th>Avg Speed to Contact</th>
                                    <th>Qualified</th>
                                    <th>Unqualified</th>
                                    <th>Converted (Won)</th>
                                    <th>Avg Speed to Close</th>
                                    <th>Conversion Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Leads List Page
     */
    public static function render_leads_list_page() {
        if (!self::current_user_has_rep_access()) {
            echo '<div class="wrap idlm-wrap"><div class="idlm-card" style="padding: 24px; text-align: center; color: #64748b; font-family: \'Inter\', sans-serif;"><h3 style="color: #1e293b; margin-bottom: 8px;">Access Restricted</h3><p>You do not have permission to access this resource. Please log in with an authorized account.</p></div></div>';
            return;
        }
        global $wpdb;
        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $call_statuses = get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number'));
        $studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio'));
        $num_rooms_options = get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK'));
        $reps = get_users(array('role__in' => self::get_allowed_rep_roles()));
        $active_rep_ids = get_option('idlm_active_assignment_reps', array());

        // Check Rep Permissions
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $reps_see_all = get_option('idlm_reps_see_all_leads', '1');
        $pending_calls_count = self::get_pending_calls_count();

        // Calculate dynamic summary metrics counts
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $today_start = current_time('Y-m-d 00:00:00');
        
        $rep_clause = '';
        if (!$is_admin && !$reps_see_all) {
            $rep_clause = $wpdb->prepare(" AND assigned_rep_id = %d", $current_user_id);
        }

        // 1. New Leads (overall)
        $new_leads_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_leads} WHERE stage = %s AND is_duplicate = 0 {$rep_clause}", 'New')));
        
        // 2. Not Called
        $not_called_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_leads} WHERE call_status = %s {$rep_clause}", 'Not Called')));
        
        // 3. Follow-up (overall)
        $followup_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_leads} 
             WHERE call_status = %s {$rep_clause}",
            'Call Back Requested'
        )));
        
        // 4. Unassigned
        $unassigned_count = 0;
        if ($is_admin || $reps_see_all) {
            $unassigned_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_leads} WHERE assigned_rep_id = 0"));
        }
        
        // 5. Qualified Leads
        $qualified_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_leads} WHERE lead_quality = %s {$rep_clause}", 'Qualified')));
        ?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>Leads Database</h1>
                    <p class="idlm-header-subtitle">Manage interior design pipelines, communications and assignment distributions</p>
                </div>
                <div class="idlm-header-actions" style="display: flex; align-items: center; gap: 15px;">
                    <?php if ($pending_calls_count > 0): ?>
                        <div class="idlm-pending-calls-pill" id="idlm-pending-calls-pill" title="<?php echo esc_attr($pending_calls_count); ?> calls currently in pending queues">
                            <span class="idlm-pending-pulse-dot"></span>
                            <span class="idlm-pending-text"><strong><?php echo esc_html($pending_calls_count); ?></strong> Pending Calls</span>
                        </div>
                    <?php endif; ?>
                    <button class="idlm-btn idlm-btn-secondary" id="idlm-import-btn" onclick="openImportModal()">Import CSV</button>
                    <button class="idlm-btn idlm-btn-secondary" id="idlm-export-btn">Export CSV</button>
                    <button class="idlm-btn idlm-btn-primary" onclick="openLeadModal()">+ Add Lead</button>
                </div>
            </header>

            <!-- Metrics Summary Grid -->
            <section class="idlm-metric-grid" aria-label="Lead summary">
                <button class="idlm-metric" type="button" data-filter="New">
                    <span class="idlm-metric-label">New Leads</span>
                    <span class="idlm-metric-value"><?php echo esc_html($new_leads_count); ?> <span class="idlm-metric-hint">overall</span></span>
                </button>
                <button class="idlm-metric" type="button" data-filter="Not Called">
                    <span class="idlm-metric-label">Not Called</span>
                    <span class="idlm-metric-value"><?php echo esc_html($not_called_count); ?> <span class="idlm-metric-hint">pending</span></span>
                </button>
                <button class="idlm-metric" type="button" data-filter="Follow-up">
                    <span class="idlm-metric-label">Follow-up</span>
                    <span class="idlm-metric-value"><?php echo esc_html($followup_count); ?> <span class="idlm-metric-hint">overall</span></span>
                </button>
                <?php if ($is_admin || $reps_see_all): ?>
                <button class="idlm-metric" type="button" data-filter="Unassigned">
                    <span class="idlm-metric-label">Unassigned</span>
                    <span class="idlm-metric-value"><?php echo esc_html($unassigned_count); ?> <span class="idlm-metric-hint">needs owner</span></span>
                </button>
                <?php endif; ?>
                <button class="idlm-metric" type="button" data-filter="Qualified">
                    <span class="idlm-metric-label">Qualified Leads</span>
                    <span class="idlm-metric-value"><?php echo esc_html($qualified_count); ?> <span class="idlm-metric-hint">active</span></span>
                </button>
            </section>

            <!-- Table Filters Panel -->
            <div class="idlm-card idlm-filters-panel">
                <!-- Primary Filters Row -->
                <div class="idlm-filters-primary-row">
                    <div class="idlm-filter-group idlm-filter-group-search">
                        <label>Search Leads</label>
                        <input type="text" id="filter-search" class="idlm-input" placeholder="Search...">
                    </div>
                    <div class="idlm-filter-group">
                        <label>Date Added</label>
                        <select id="filter-date-range" class="idlm-input">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="last_month">Last Month</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label>Stage</label>
                        <select id="filter-stage" class="idlm-input">
                            <option value="">All Stages</option>
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label>Call Status</label>
                        <select id="filter-call-status" class="idlm-input">
                            <option value="">All Statuses</option>
                            <?php foreach ($call_statuses as $cs): ?>
                                <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($is_admin || $reps_see_all): ?>
                    <div class="idlm-filter-group">
                        <label>Assigned Rep</label>
                        <select id="filter-rep" class="idlm-input">
                            <option value="">All Reps</option>
                            <option value="0">Unassigned</option>
                            <?php foreach ($reps as $rep): 
                                if (!in_array($rep->ID, $active_rep_ids)) {
                                    continue;
                                }
                            ?>
                                <option value="<?php echo esc_attr($rep->ID); ?>"><?php echo esc_html($rep->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="idlm-filter-group idlm-filter-buttons-group">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" id="btn-toggle-more-filters" class="idlm-btn idlm-btn-secondary" style="height: 29px; padding: 0 10px; display: flex; width: auto; box-sizing: border-box; align-items: center; justify-content: center; gap: 4px; font-size: 11px;" title="More Filters">
                                <span class="dashicons dashicons-filter" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px; margin: 0;"></span>
                                <span>More Filters</span>
                            </button>
                            <button type="button" id="btn-clear-filters" class="idlm-btn idlm-btn-secondary" style="height: 29px; padding: 0 10px; display: flex; width: auto; box-sizing: border-box; align-items: center; justify-content: center; gap: 4px; font-size: 11px;" title="Clear Filters">
                                <span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px; margin: 0;"></span>
                                <span>Clear</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Secondary Filters Row -->
                <div class="idlm-filters-secondary-row" id="idlm-secondary-filters-container" style="display: none;">
                    <div class="idlm-filter-group">
                        <label>Channel Source</label>
                        <select id="filter-source" class="idlm-input">
                            <option value="">All Channels</option>
                            <option value="Website">Website</option>
                            <option value="Meta Ads">Meta Ads</option>
                            <option value="Google Ads">Google Ads</option>
                            <option value="LinkedIn Ads">LinkedIn Ads</option>
                            <option value="Referral">Referral</option>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label>Lead Quality</label>
                        <select id="filter-quality" class="idlm-input">
                            <option value="">All Qualities</option>
                            <option value="Qualified">Qualified</option>
                            <option value="Unqualified">Unqualified</option>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label>Follow-up Status</label>
                        <select id="filter-followup" class="idlm-input">
                            <option value="">All Leads</option>
                            <option value="overdue">⏳ Overdue Follow-up</option>
                            <option value="pending_calls">📞 Pending Calls</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Panel -->
            <div class="idlm-bulk-bar" style="display:none;">
                <span class="idlm-selected-count">0 items selected</span>
                <div class="idlm-bulk-actions">
                    <select id="idlm-bulk-action-select" class="idlm-input">
                        <option value="">Select Bulk Action</option>
                        <option value="bulk_assign">Assign to Rep</option>
                        <option value="bulk_stage">Change Stage</option>
                        <option value="bulk_unqualified">Mark Unqualified</option>
                        <option value="bulk_audience_queue">Add to Audience Sync Queue</option>
                        <?php if (current_user_can('manage_options')): ?>
                            <option value="bulk_delete" style="color: #ef4444; font-weight: 600;">🗑️ Delete Leads</option>
                        <?php endif; ?>
                    </select>

                    <!-- Contextual dynamic parameters for bulk options -->
                    <select id="idlm-bulk-rep" class="idlm-input" style="display:none;">
                        <option value="">Select Rep</option>
                        <?php foreach ($reps as $rep): 
                            if (!in_array($rep->ID, $active_rep_ids)) {
                                continue;
                            }
                        ?>
                            <option value="<?php echo esc_attr($rep->ID); ?>"><?php echo esc_html($rep->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="idlm-bulk-stage" class="idlm-input" style="display:none;">
                        <option value="">Select Stage</option>
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button id="idlm-apply-bulk" class="idlm-btn idlm-btn-primary">Apply Action</button>
                </div>
            </div>

            <!-- Leads Data Table -->
            <div class="idlm-table-wrapper">
                <table class="idlm-table" id="idlm-leads-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="idlm-select-all"></th>
                            <th>Name</th>
                            <th>Channel</th>
                            <th>Budget</th>
                            <th>Call Status</th>
                            <th>Stage</th>
                            <th>Assigned Rep</th>
                            <th>Lead Quality</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loaded via dynamic script ajax lists -->
                    </tbody>
                </table>
            </div>
            <div class="idlm-pagination">
                <button class="idlm-pagination-btn" id="prev-page">Previous</button>
                <span id="page-num">Page 1 of 1</span>
                <button class="idlm-pagination-btn" id="next-page">Next</button>
            </div>
        </div>

        <!-- Add Manual Lead Modal -->
        <div class="idlm-modal" id="idlm-lead-modal">
            <div class="idlm-modal-content">
                <div class="idlm-modal-header">
                    <h2>Create Manual Lead</h2>
                    <span class="idlm-close" onclick="closeLeadModal()">&times;</span>
                </div>
                <form id="idlm-add-lead-form">
                    <div class="idlm-form-grid">
                        <div class="idlm-form-group">
                            <label>First Name*</label>
                            <input type="text" name="first_name" class="idlm-input" required>
                        </div>
                        <div class="idlm-form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="idlm-input">
                        </div>
                        <div class="idlm-form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="idlm-input">
                        </div>
                        <div class="idlm-form-group">
                            <label>Phone Number*</label>
                            <input type="tel" name="phone" class="idlm-input" required>
                        </div>
                        <div class="idlm-form-group">
                            <label>Source Channel</label>
                            <select name="source" id="idlm-lead-source-select" class="idlm-input">
                                <option value="Website">Website</option>
                                <option value="Meta Ads">Meta Ads</option>
                                <option value="Google Ads">Google Ads</option>
                                <option value="LinkedIn Ads">LinkedIn Ads</option>
                                <option value="Referral">Referral</option>
                                <option value="Offline/Walk-in">Offline/Walk-in</option>
                            </select>
                        </div>
                        <!-- Referrer Text Area (Hidden by default, shown when Source is Referral) -->
                        <div class="idlm-form-group" id="idlm-referrer-field" style="display:none; grid-column: span 2;">
                            <label>Referrer Details</label>
                            <textarea name="referrer_details" class="idlm-input" rows="2" placeholder="Mention referral details..."></textarea>
                        </div>
                        <div class="idlm-form-group">
                            <label>Property Type</label>
                            <select name="property_type" class="idlm-input">
                                <option value="Apartment">Apartment</option>
                                <option value="Villa">Villa</option>
                                <option value="Independent House">Independent House</option>
                                <option value="Commercial">Commercial</option>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Number of Rooms</label>
                            <select name="num_rooms" class="idlm-input">
                                <?php foreach ($num_rooms_options as $opt): ?>
                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Preferred Studio Location</label>
                            <select name="preferred_studio" class="idlm-input">
                                <?php foreach ($studios as $studio): ?>
                                    <option value="<?php echo esc_attr($studio); ?>"><?php echo esc_html($studio); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Lead Quality</label>
                            <select name="lead_quality" class="idlm-input">
                                <option value="" selected>Choose Quality...</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Unqualified">Unqualified</option>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Lead Budget</label>
                            <input type="text" name="budget" class="idlm-input" placeholder="e.g. $15,000 or 25000">
                        </div>
                        <div class="idlm-form-group">
                            <label>Project Timeline</label>
                            <input type="text" name="project_timeline" class="idlm-input" placeholder="e.g. Immediate or 1-3 Months">
                        </div>
                        <div class="idlm-form-group">
                            <label>Property Location / Pincode</label>
                            <input type="text" name="property_location" class="idlm-input" placeholder="e.g. Downtown or 560001">
                        </div>
                    </div>
                    <div style="margin-top: 20px; display:flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" onclick="closeLeadModal()">Cancel</button>
                        <button type="submit" class="idlm-btn idlm-btn-primary">Save Lead</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Leads Modal -->
        <div class="idlm-modal" id="idlm-import-modal">
            <div class="idlm-modal-content" style="max-width: 650px;">
                <div class="idlm-modal-header">
                    <h2>Import Leads from CSV</h2>
                    <span class="idlm-close" onclick="closeImportModal()">&times;</span>
                </div>

                <!-- Steps Navigation Indicator -->
                <div class="idlm-import-steps">
                    <div class="idlm-import-step-item active" id="import-step-nav-1">
                        <span class="idlm-import-step-number">1</span> Upload CSV
                    </div>
                    <div class="idlm-import-step-item" id="import-step-nav-2">
                        <span class="idlm-import-step-number">2</span> Map Fields
                    </div>
                    <div class="idlm-import-step-item" id="import-step-nav-3">
                        <span class="idlm-import-step-number">3</span> Preview & Options
                    </div>
                    <div class="idlm-import-step-item" id="import-step-nav-4">
                        <span class="idlm-import-step-number">4</span> Progress
                    </div>
                </div>

                <!-- STEP 1: UPLOAD CSV -->
                <div class="idlm-import-step-content active" id="import-step-content-1">
                    <div class="idlm-import-drag-zone" id="idlm-import-drag-zone">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span class="idlm-import-drag-text">Drag and drop your CSV file here, or click to browse</span>
                        <span class="idlm-import-drag-subtext">Supports standard .csv files up to 5MB</span>
                        <input type="file" id="idlm-import-file-input" accept=".csv" style="display: none;">
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <a href="#" class="idlm-btn idlm-btn-secondary" id="idlm-import-download-template" style="text-decoration: none; display: inline-flex; gap: 5px;">
                            📥 Download Sample Template
                        </a>
                        <button type="button" class="idlm-btn idlm-btn-secondary" onclick="closeImportModal()">Cancel</button>
                    </div>
                </div>

                <!-- STEP 2: MAP FIELDS -->
                <div class="idlm-import-step-content" id="import-step-content-2">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">Map the headers from your CSV file to Lead Manager fields. Fields marked with * are highly recommended.</p>
                    
                    <div class="idlm-import-mapping-wrapper">
                        <table class="idlm-import-mapping-table">
                            <thead>
                                <tr>
                                    <th>CRM Field</th>
                                    <th>CSV Column</th>
                                </tr>
                            </thead>
                            <tbody id="idlm-import-mapping-tbody">
                                <!-- Dynamically populated by JS -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Default Settings Collapsible/Section -->
                    <div style="margin-top: 15px; border-top: 1px solid #dfe7f1; padding-top: 15px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600;">Import Defaults</h4>
                        <div class="idlm-form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                            <div class="idlm-form-group">
                                <label style="font-size: 11px;">Default Source Channel</label>
                                <select id="import-default-source" class="idlm-input">
                                    <option value="CSV Import" selected>CSV Import</option>
                                    <option value="Website">Website</option>
                                    <option value="Meta Ads">Meta Ads</option>
                                    <option value="Google Ads">Google Ads</option>
                                    <option value="LinkedIn Ads">LinkedIn Ads</option>
                                    <option value="Referral">Referral</option>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-size: 11px;">Default Pipeline Stage</label>
                                <select id="import-default-stage" class="idlm-input">
                                    <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-size: 11px;">Default Call Status</label>
                                <select id="import-default-call-status" class="idlm-input">
                                    <?php foreach ($call_statuses as $cs): ?>
                                        <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="import-btn-back-1">Back</button>
                        <button type="button" class="idlm-btn idlm-btn-primary" id="import-btn-next-2">Next: Preview</button>
                    </div>
                </div>

                <!-- STEP 3: PREVIEW & OPTIONS -->
                <div class="idlm-import-step-content" id="import-step-content-3">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">Confirm the parsed data preview (showing first 3 rows) and configure additional processing rules.</p>
                    
                    <div class="idlm-import-preview-wrapper">
                        <table class="idlm-import-preview-table" id="idlm-import-preview-table">
                            <thead>
                                <tr id="idlm-import-preview-thead-tr">
                                    <!-- Dynamic headers -->
                                </tr>
                            </thead>
                            <tbody id="idlm-import-preview-tbody">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 15px; border-top: 1px solid #dfe7f1; padding-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" id="import-opt-route" checked>
                            Run Lead Assignment Rules (e.g. Round Robin) for imported leads
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" id="import-opt-capi" checked>
                            Trigger Conversion API (CAPI) events if imported stage triggers conversion
                        </label>
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="import-btn-back-2">Back</button>
                        <button type="button" class="idlm-btn idlm-btn-primary" id="import-btn-start">Start Importing</button>
                    </div>
                </div>

                <!-- STEP 4: PROGRESS & STATUS -->
                <div class="idlm-import-step-content" id="import-step-content-4">
                    <div class="idlm-import-progress-wrapper">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 500;">
                            <span id="import-progress-text">Initializing import...</span>
                            <span id="import-progress-percent">0%</span>
                        </div>
                        <div class="idlm-import-progress-bar-bg">
                            <div class="idlm-import-progress-bar-fill" id="idlm-import-progress-fill"></div>
                        </div>
                    </div>

                    <div class="idlm-import-status-list" id="idlm-import-status-list">
                        <!-- Code activity details populated dynamically -->
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-primary" id="import-btn-close-final" style="display: none;" onclick="closeImportModal()">Close & Refresh</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sliding Drawer Panel (Right side slideout) -->
        <div class="idlm-drawer" id="idlm-lead-drawer">
            <div class="idlm-drawer-content-wrapper" id="idlm-drawer-content-wrapper-inner">
                <!-- Header, Tabs, and Body loaded dynamically via AJAX to match mockup -->
                <div style="text-align:center; padding:50px;"><span class="spinner is-active" style="float:none;"></span> Loading Lead details...</div>
            </div>
        </div>

        <!-- Callback Scheduler Modal -->
        <div class="idlm-modal" id="idlm-callback-modal">
            <div class="idlm-modal-content" style="max-width: 400px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px;">Schedule Callback</h2>
                    <span class="idlm-close" id="idlm-callback-modal-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Select Date & Time</label>
                    <input type="datetime-local" id="idlm-callback-datetime-input" class="idlm-input" style="width: 100%;">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-callback-modal-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-callback-modal-save" style="padding: 6px 12px;">Schedule</button>
                </div>
            </div>
        </div>

        <!-- Deal Value Input Modal -->
        <div class="idlm-modal" id="idlm-deal-value-modal">
            <div class="idlm-modal-content" style="max-width: 400px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px;">Enter Deal Value</h2>
                    <span class="idlm-close" id="idlm-deal-value-modal-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Deal Value (INR)*</label>
                    <input type="number" step="0.01" min="0" id="idlm-deal-value-input" class="idlm-input" style="width: 100%;" placeholder="e.g. 150000">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-deal-value-modal-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-deal-value-modal-save" style="padding: 6px 12px;">Save Value</button>
                </div>
            </div>
        </div>

        <!-- Unsaved Changes Confirmation Modal -->
        <div class="idlm-modal" id="idlm-unsaved-changes-modal" style="z-index: 110000;">
            <div class="idlm-modal-content" style="max-width: 450px; padding: 25px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 18px; font-weight: 700; color: #111827;">⚠️ Unsaved Changes</h2>
                    <span class="idlm-close" id="idlm-unsaved-modal-close">&times;</span>
                </div>
                <p style="font-size: 13px; line-height: 1.5; color: #63728a; margin-bottom: 10px;">
                    You have made edits to the lead details. Would you like to save your changes before closing, or discard them?
                </p>
                <div id="idlm-unsaved-fields-list" style="margin-bottom: 20px; font-weight: 600; color: #111827; font-size: 12px; line-height: 1.4;"></div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-unsaved-save-exit" style="width: 100%; justify-content: center; min-height: 40px;">Save & Exit</button>
                    <button type="button" class="idlm-btn" id="idlm-unsaved-discard-exit" style="width: 100%; justify-content: center; min-height: 40px; background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;">Discard & Exit</button>
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-unsaved-cancel" style="width: 100%; justify-content: center; min-height: 40px;">Keep Editing</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * CAPI Log View
     */
    public static function render_capi_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }
        global $wpdb;
        $logs = $wpdb->get_results("
            SELECT c.*, l.first_name, l.last_name, l.email 
            FROM {$wpdb->prefix}idlm_capi_logs c
            LEFT JOIN {$wpdb->prefix}idlm_leads l ON c.lead_id = l.id
            ORDER BY c.id DESC LIMIT 50
        ");
        ?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>Conversion API (CAPI) Audit Logs</h1>
                    <p class="idlm-header-subtitle">Debugging dashboards, validation tracking and platform response packages</p>
                </div>
            </header>

            <div class="idlm-card">
                <!-- CAPI Bulk Retry Action Bar -->
                <div class="idlm-bulk-capi-bar" style="display:none; background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #c99644; padding:12px; border-radius:8px; margin-bottom:15px; justify-content:space-between; align-items:center;">
                    <span class="idlm-selected-capi-count" style="font-size:13px; font-weight:600; color:#c99644; font-family:'Outfit', sans-serif;">0 logs selected</span>
                    <button class="idlm-btn" id="idlm-retry-capi-btn" style="background:#c99644; color:#fff; border:none; padding:6px 15px; border-radius:4px; font-size:12px; cursor:pointer;">Retry/Send Selected Events</button>
                </div>

                <div class="idlm-table-wrapper">
                    <table class="idlm-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;"><input type="checkbox" id="idlm-select-all-capi"></th>
                                <th>Timestamp</th>
                                <th>Lead Email</th>
                                <th>Platform</th>
                                <th>Event Name</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="7" style="text-align:center;">No CAPI logs recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="text-align: center;"><input type="checkbox" class="idlm-capi-log-selector" value="<?php echo esc_attr($log->id); ?>"></td>
                                        <td><?php echo esc_html(self::format_datetime($log->created_at)); ?></td>
                                        <td><?php echo esc_html($log->email ? $log->email : 'N/A'); ?></td>
                                        <td><span class="idlm-badge idlm-badge-<?php echo esc_attr($log->platform); ?>"><?php echo esc_html(ucfirst($log->platform)); ?></span></td>
                                        <td><code><?php echo esc_html($log->event_name); ?></code></td>
                                        <td>
                                            <span class="idlm-badge idlm-badge-<?php echo ($log->status === 'success') ? 'success' : 'failed'; ?>">
                                                <?php echo esc_html(strtoupper($log->status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="idlm-btn idlm-btn-secondary" onclick="viewLogPayload(<?php echo esc_attr($log->id); ?>)">Inspect JSON</button>
                                            <div id="log-details-<?php echo esc_attr($log->id); ?>" style="display:none; margin-top: 10px;">
                                                <h5>Request Payload:</h5>
                                                <pre class="idlm-code-block"><?php echo esc_html(wp_json_encode(maybe_unserialize($log->payload), JSON_PRETTY_PRINT)); ?></pre>
                                                <h5>Platform Response:</h5>
                                                <pre class="idlm-code-block"><?php echo esc_html($log->response); ?></pre>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
        function viewLogPayload(id) {
            var el = document.getElementById('log-details-' + id);
            if (el.style.display === 'none') {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }

        jQuery(document).ready(function($) {
            // Select all checkbox handler
            $('#idlm-select-all-capi').on('change', function() {
                var checked = $(this).prop('checked');
                $('.idlm-capi-log-selector').prop('checked', checked).trigger('change');
            });

            // Single checkbox handler
            $(document).on('change', '.idlm-capi-log-selector', function() {
                var selectedCount = $('.idlm-capi-log-selector:checked').length;
                if (selectedCount > 0) {
                    $('.idlm-selected-capi-count').text(selectedCount + ' log' + (selectedCount > 1 ? 's' : '') + ' selected');
                    $('.idlm-bulk-capi-bar').css('display', 'flex');
                } else {
                    $('.idlm-bulk-capi-bar').hide();
                    $('#idlm-select-all-capi').prop('checked', false);
                }
            });

            // Retry action click handler
            $('#idlm-retry-capi-btn').on('click', function() {
                var selectedIds = [];
                $('.idlm-capi-log-selector:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    alert('Please select at least one log to retry.');
                    return;
                }

                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Retrying...').prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idlm_retry_capi_events',
                        nonce: '<?php echo esc_js(wp_create_nonce("idlm_admin_nonce")); ?>',
                        log_ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message || 'Bulk retry failed.');
                        }
                    },
                    error: function() {
                        alert('Server error occurred during retry.');
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Audience Sync Logs View
     */
    public static function render_audience_sync_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }
        global $wpdb;
        $logs = $wpdb->get_results("
            SELECT * 
            FROM {$wpdb->prefix}idlm_audience_sync_logs
            ORDER BY id DESC LIMIT 50
        ");
        ?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>Audience Batch Sync Logs</h1>
                    <p class="idlm-header-subtitle">Debugging logs, audience segments and sync success statuses</p>
                </div>
            </header>

            <div class="idlm-card">
                <div class="idlm-table-wrapper">
                    <table class="idlm-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Platform</th>
                                <th>Audience ID</th>
                                <th>Leads Synced</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="6" style="text-align:center;">No audience sync logs recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html(self::format_datetime($log->created_at)); ?></td>
                                        <td><span class="idlm-badge idlm-badge-<?php echo esc_attr($log->platform); ?>"><?php echo esc_html(ucfirst($log->platform)); ?></span></td>
                                        <td><code><?php echo esc_html($log->audience_id); ?></code></td>
                                        <td><?php echo esc_html($log->lead_count); ?> leads</td>
                                        <td>
                                            <span class="idlm-badge idlm-badge-<?php echo ($log->status === 'success') ? 'success' : 'failed'; ?>">
                                                <?php echo esc_html(strtoupper($log->status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="idlm-btn idlm-btn-secondary" onclick="viewAudienceLogPayload(<?php echo esc_attr($log->id); ?>)">Inspect JSON</button>
                                            <div id="audience-log-details-<?php echo esc_attr($log->id); ?>" style="display:none; margin-top: 10px;">
                                                <h5>Request Payload:</h5>
                                                <pre class="idlm-code-block"><?php echo esc_html(wp_json_encode(maybe_unserialize($log->payload), JSON_PRETTY_PRINT)); ?></pre>
                                                <h5>Platform Response:</h5>
                                                <pre class="idlm-code-block"><?php echo esc_html(maybe_unserialize($log->response)); ?></pre>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
        function viewAudienceLogPayload(logId) {
            var el = document.getElementById('audience-log-details-' + logId);
            if (el.style.display === 'none') {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }
        </script>
        <?php
    }

    /**
     * Settings Page Render
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }
        if (isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['idlm_save_settings'])) {
            check_admin_referer('idlm_settings_save_action');

            // Save basic settings
            update_option('idlm_monthly_lead_target', isset($_POST['lead_target']) ? intval(wp_unslash($_POST['lead_target'])) : 0);
            update_option('idlm_monthly_conversion_target', isset($_POST['conversion_target']) ? intval(wp_unslash($_POST['conversion_target'])) : 0);
            update_option('idlm_reps_see_all_leads', isset($_POST['reps_see_all']) ? '1' : '0');
            update_option('idlm_assignment_mode', isset($_POST['assignment_mode']) ? sanitize_text_field(wp_unslash($_POST['assignment_mode'])) : 'manual');

            // Save Reps for auto assignments
            $active_reps = (!empty($_POST['active_reps']) && is_array($_POST['active_reps'])) ? array_map('intval', wp_unslash($_POST['active_reps'])) : array();
            update_option('idlm_active_assignment_reps', $active_reps);

            // Save source based mappings
            $sources = array('Website', 'Meta Ads', 'Google Ads', 'LinkedIn Ads', 'Referral');
            $source_maps = array();
            foreach ($sources as $src) {
                $field_name = 'source_map_' . str_replace(' ', '_', strtolower($src));
                if (isset($_POST[$field_name])) {
                    $source_maps[$src] = intval(wp_unslash($_POST[$field_name]));
                }
            }
            update_option('idlm_source_assignment_mappings', $source_maps);

            // Save custom options lists
            $custom_stages = isset($_POST['custom_stages']) ? sanitize_text_field(wp_unslash($_POST['custom_stages'])) : '';
            $stages = array_filter(array_map('sanitize_text_field', explode(',', $custom_stages)));
            update_option('idlm_custom_stages', $stages);
            
            $custom_call_statuses = isset($_POST['custom_call_statuses']) ? sanitize_text_field(wp_unslash($_POST['custom_call_statuses'])) : '';
            $call_statuses = array_filter(array_map('sanitize_text_field', explode(',', $custom_call_statuses)));
            update_option('idlm_custom_call_statuses', $call_statuses);

            $custom_studios = isset($_POST['custom_studios']) ? sanitize_text_field(wp_unslash($_POST['custom_studios'])) : '';
            $studios = array_filter(array_map('sanitize_text_field', explode(',', $custom_studios)));
            update_option('idlm_custom_studios', $studios);

            $custom_num_rooms = isset($_POST['custom_num_rooms']) ? sanitize_text_field(wp_unslash($_POST['custom_num_rooms'])) : '';
            $num_rooms = array_filter(array_map('sanitize_text_field', explode(',', $custom_num_rooms)));
            update_option('idlm_custom_num_rooms', $num_rooms);

            // Save CAPI configurations
            update_option('idlm_meta_capi_enabled', isset($_POST['meta_capi_enabled']) ? '1' : '0');
            update_option('idlm_meta_pixel_id', isset($_POST['meta_pixel_id']) ? sanitize_text_field(wp_unslash($_POST['meta_pixel_id'])) : '');
            update_option('idlm_meta_access_token', isset($_POST['meta_access_token']) ? sanitize_text_field(wp_unslash($_POST['meta_access_token'])) : '');
            if (isset($_POST['meta_audience_id'])) {
                update_option('idlm_meta_audience_id', sanitize_text_field(wp_unslash($_POST['meta_audience_id'])));
            }

            update_option('idlm_google_capi_enabled', isset($_POST['google_capi_enabled']) ? '1' : '0');
            update_option('idlm_google_measurement_id', isset($_POST['google_measurement_id']) ? sanitize_text_field(wp_unslash($_POST['google_measurement_id'])) : '');
            update_option('idlm_google_api_secret', isset($_POST['google_api_secret']) ? sanitize_text_field(wp_unslash($_POST['google_api_secret'])) : '');
            if (isset($_POST['google_user_list_resource'])) {
                update_option('idlm_google_user_list_resource', sanitize_text_field(wp_unslash($_POST['google_user_list_resource'])));
            }

            update_option('idlm_linkedin_capi_enabled', isset($_POST['linkedin_capi_enabled']) ? '1' : '0');
            update_option('idlm_linkedin_conversion_id', isset($_POST['linkedin_conversion_id']) ? sanitize_text_field(wp_unslash($_POST['linkedin_conversion_id'])) : '');
            update_option('idlm_linkedin_access_token', isset($_POST['linkedin_access_token']) ? sanitize_text_field(wp_unslash($_POST['linkedin_access_token'])) : '');
            if (isset($_POST['linkedin_segment_urn'])) {
                update_option('idlm_linkedin_segment_urn', sanitize_text_field(wp_unslash($_POST['linkedin_segment_urn'])));
            }

            // Save dynamic mappings
            $platforms = array('meta', 'google', 'linkedin', 'website');
            $fields = array('first_name', 'last_name', 'email', 'phone', 'budget', 'project_timeline', 'property_location', 'property_type', 'num_rooms', 'preferred_studio', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'li_fat_id', 'fb_lead_id');
            foreach ($platforms as $plat) {
                $plat_maps = array();
                foreach ($fields as $f) {
                    $key_name = "map_{$plat}_{$f}";
                    if (isset($_POST[$key_name])) {
                        $plat_maps[$f] = sanitize_text_field(wp_unslash($_POST[$key_name]));
                    }
                }
                update_option('idlm_field_mappings_' . $plat, $plat_maps);
            }

            // Save platform Sync Toggles
            update_option('idlm_sync_meta_audience', isset($_POST['sync_meta_audience']) ? '1' : '0');
            update_option('idlm_sync_google_audience', isset($_POST['sync_google_audience']) ? '1' : '0');
            update_option('idlm_sync_linkedin_audience', isset($_POST['sync_linkedin_audience']) ? '1' : '0');

            // Save Dynamic Custom Audience Sync Rules
            $audience_rules_saved = array();
            if (isset($_POST['audience_rules']) && is_array($_POST['audience_rules'])) {
                $raw_rules = wp_unslash($_POST['audience_rules']);
                if (isset($raw_rules['platform']) && is_array($raw_rules['platform'])) {
                    $count = count($raw_rules['platform']);
                    for ($i = 0; $i < $count; $i++) {
                        $plat = isset($raw_rules['platform'][$i]) ? sanitize_text_field($raw_rules['platform'][$i]) : '';
                        $aud_id = isset($raw_rules['audience_id'][$i]) ? sanitize_text_field($raw_rules['audience_id'][$i]) : '';
                        $stage = isset($raw_rules['stage'][$i]) ? sanitize_text_field($raw_rules['stage'][$i]) : '';
                        $call_status = isset($raw_rules['call_status'][$i]) ? sanitize_text_field($raw_rules['call_status'][$i]) : '';
                        $lead_quality = isset($raw_rules['lead_quality'][$i]) ? sanitize_text_field($raw_rules['lead_quality'][$i]) : '';
                        $enabled = isset($raw_rules['enabled'][$i]) ? sanitize_text_field($raw_rules['enabled'][$i]) : '';

                        if (!empty($plat) && !empty($aud_id)) {
                            $audience_rules_saved[] = array(
                                'platform' => $plat,
                                'audience_id' => $aud_id,
                                'stage' => $stage,
                                'call_status' => $call_status,
                                'lead_quality' => $lead_quality,
                                'enabled' => $enabled
                            );
                        }
                    }
                }
            }
            update_option('idlm_audience_sync_rules', $audience_rules_saved);

            // Save Reminder Intervals
            $reminder_rules_saved = array();
            if (isset($_POST['reminder_rules_status']) && isset($_POST['reminder_rules_hours'])) {
                $rules_status = wp_unslash($_POST['reminder_rules_status']);
                $rules_hours = wp_unslash($_POST['reminder_rules_hours']);
                if (is_array($rules_status) && is_array($rules_hours)) {
                    $count = count($rules_status);
                    for ($i = 0; $i < $count; $i++) {
                        $status = isset($rules_status[$i]) ? sanitize_text_field($rules_status[$i]) : '';
                        $hours = isset($rules_hours[$i]) ? intval($rules_hours[$i]) : 0;
                        if (!empty($status) && $hours > 0) {
                            $reminder_rules_saved[] = array(
                                'status' => $status,
                                'hours'  => $hours
                            );
                        }
                    }
                }
            }
            update_option('idlm_reminders_config', $reminder_rules_saved);

            if (!empty($reminder_rules_saved)) {
                update_option('idlm_reminder_status', $reminder_rules_saved[0]['status']);
                update_option('idlm_reminder_hours', $reminder_rules_saved[0]['hours']);
            }

            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        }

        // Fetch values
        $lead_target = get_option('idlm_monthly_lead_target', 100);
        $conv_target = get_option('idlm_monthly_conversion_target', 10);
        $reps_see_all = get_option('idlm_reps_see_all_leads', '1');
        $assignment_mode = get_option('idlm_assignment_mode', 'manual');
        $active_reps = get_option('idlm_active_assignment_reps', array());
        $source_maps = get_option('idlm_source_assignment_mappings', array());

        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $call_statuses = get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number'));
        $studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio'));
        $num_rooms_options = get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK'));

        // CAPI
        $meta_capi_enabled = get_option('idlm_meta_capi_enabled', '0');
        $meta_pixel_id = get_option('idlm_meta_pixel_id', '');
        $meta_access_token = get_option('idlm_meta_access_token', '');
        $meta_audience_id = get_option('idlm_meta_audience_id', '');

        $google_capi_enabled = get_option('idlm_google_capi_enabled', '0');
        $google_measurement_id = get_option('idlm_google_measurement_id', '');
        $google_api_secret = get_option('idlm_google_api_secret', '');
        $google_user_list_resource = get_option('idlm_google_user_list_resource', '');

        $linkedin_capi_enabled = get_option('idlm_linkedin_capi_enabled', '0');
        $linkedin_conversion_id = get_option('idlm_linkedin_conversion_id', '');
        $linkedin_access_token = get_option('idlm_linkedin_access_token', '');
        $linkedin_segment_urn = get_option('idlm_linkedin_segment_urn', '');

        // Sync Toggles
        $sync_meta_audience = get_option('idlm_sync_meta_audience', '0');
        $sync_google_audience = get_option('idlm_sync_google_audience', '0');
        $sync_linkedin_audience = get_option('idlm_sync_linkedin_audience', '0');

        // Reminders
        $reminder_hours = get_option('idlm_reminder_hours', 24);
        $reminder_status = get_option('idlm_reminder_status', 'Call Back Requested');
        $reminder_rules = self::get_reminder_rules();

        // Custom Audience rules
        $audience_rules = get_option('idlm_audience_sync_rules');
        if ($audience_rules === false) {
            $audience_rules = array();
            // Migrate old settings if present
            $sync_meta = get_option('idlm_sync_meta_audience', '0');
            $meta_aud = get_option('idlm_meta_audience_id');
            if ($sync_meta === '1' && !empty($meta_aud)) {
                $audience_rules[] = array(
                    'platform' => 'meta',
                    'audience_id' => $meta_aud,
                    'stage' => 'all',
                    'call_status' => 'all',
                    'lead_quality' => 'all',
                    'enabled' => '1'
                );
            }
            $sync_google = get_option('idlm_sync_google_audience', '0');
            $google_list = get_option('idlm_google_user_list_resource');
            if ($sync_google === '1' && !empty($google_list)) {
                $audience_rules[] = array(
                    'platform' => 'google',
                    'audience_id' => $google_list,
                    'stage' => 'all',
                    'call_status' => 'all',
                    'lead_quality' => 'all',
                    'enabled' => '1'
                );
            }
            $sync_linkedin = get_option('idlm_sync_linkedin_audience', '0');
            $linkedin_seg = get_option('idlm_linkedin_segment_urn');
            if ($sync_linkedin === '1' && !empty($linkedin_seg)) {
                $audience_rules[] = array(
                    'platform' => 'linkedin',
                    'audience_id' => $linkedin_seg,
                    'stage' => 'all',
                    'call_status' => 'all',
                    'lead_quality' => 'all',
                    'enabled' => '1'
                );
            }
        }

        $reps = get_users(array('role__in' => self::get_allowed_rep_roles()));

        // Generate api key if missing
        $webhook_key = get_option('idlm_api_webhook_key');
        if (empty($webhook_key)) {
            $webhook_key = wp_generate_password(32, false);
            update_option('idlm_api_webhook_key', $webhook_key);
        }
        ?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>System Settings & Mappings</h1>
                    <p class="idlm-header-subtitle">Route assignments, Conversion APIs and field definitions</p>
                </div>
            </header>

            <form method="POST" action="">
                <?php wp_nonce_field('idlm_settings_save_action'); ?>
                <input type="hidden" name="idlm_save_settings" value="1">

                <div class="idlm-grid-charts" style="grid-template-columns: 1fr 1fr;">
                    <!-- Sales Target Trackers -->
                    <div class="idlm-card">
                        <h3>Firm Metrics & Targets</h3>
                        <div class="idlm-form-group">
                            <label>Monthly Lead Volume Target</label>
                            <input type="number" name="lead_target" class="idlm-input" value="<?php echo esc_attr($lead_target); ?>">
                        </div>
                        <div class="idlm-form-group">
                            <label>Monthly Won Deals Target</label>
                            <input type="number" name="conversion_target" class="idlm-input" value="<?php echo esc_attr($conv_target); ?>">
                        </div>
                        <div class="idlm-form-group" style="display:flex; align-items:center; gap:10px; margin-top:20px;">
                            <input type="checkbox" name="reps_see_all" value="1" <?php checked($reps_see_all, '1'); ?>>
                            <label><strong>Reps see all leads (Shared Inbox Model)</strong></label>
                        </div>
                    </div>

                    <!-- Dynamic Options setup -->
                    <div class="idlm-card">
                        <h3>Custom Pipelines Stages & Statuses</h3>
                        <div class="idlm-form-group">
                            <label>Configure Sales Stages (Comma separated)</label>
                            <input type="text" name="custom_stages" class="idlm-input" value="<?php echo esc_attr(implode(',', $stages)); ?>">
                        </div>
                        <div class="idlm-form-group">
                            <label>Configure Call Status Options (Comma separated)</label>
                            <input type="text" name="custom_call_statuses" class="idlm-input" value="<?php echo esc_attr(implode(',', $call_statuses)); ?>">
                        </div>
                        <div class="idlm-form-group" style="margin-top: 15px;">
                            <label>Configure Preferred Studio Locations (Comma separated)</label>
                            <input type="text" name="custom_studios" class="idlm-input" value="<?php echo esc_attr(implode(',', $studios)); ?>">
                        </div>
                        <div class="idlm-form-group" style="margin-top: 15px;">
                            <label>Configure Number of Rooms Options (Comma separated)</label>
                            <input type="text" name="custom_num_rooms" class="idlm-input" value="<?php echo esc_attr(implode(',', $num_rooms_options)); ?>">
                        </div>
                    </div>
                </div>

                <!-- Frontend Shortcodes Documentation -->
                <div class="idlm-card" style="margin-top:20px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-left: 4px solid #c99644;">
                    <h3 style="margin-top:0; font-family: 'Outfit', sans-serif; display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-shortcode" style="color:#c99644; font-size:22px; width:22px; height:22px;"></span>
                        Frontend Shortcode Integrations
                    </h3>
                    <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin-bottom:15px;">
                        Display the Leads Database Workspace and Analytics Dashboard on any frontend page or post. Access is strictly restricted to logged-in users with authorized roles (e.g. Sales Reps, Editors, Administrators) to preserve CRM security.
                    </p>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div style="background:#ffffff; border:1px solid #dfe7f1; padding:15px; border-radius:8px; display:flex; flex-direction:column; gap:8px;">
                            <h4 style="margin:0 0 5px 0; color:#1e293b; font-size:14px; font-weight:600; display:flex; justify-content:space-between; align-items:center;">
                                Leads Database Workspace
                                <button type="button" class="idlm-btn idlm-btn-secondary" style="padding: 2px 6px; font-size: 10px;" onclick="idlmCopyToClipboard('[idlm_leads_list]', 'Leads List Shortcode');">Copy</button>
                            </h4>
                            <code style="font-size:12px; font-family:monospace; padding:8px; background:#f8fafc; border-radius:4px; color:#c99644; text-align:center;">[idlm_leads_list]</code>
                            <span style="font-size:11px; color:#64748b;">Embeds the main CRM workspace table with quick-edit, search, filters, and detailed drawers.</span>
                        </div>
                        
                        <div style="background:#ffffff; border:1px solid #dfe7f1; padding:15px; border-radius:8px; display:flex; flex-direction:column; gap:8px;">
                            <h4 style="margin:0 0 5px 0; color:#1e293b; font-size:14px; font-weight:600; display:flex; justify-content:space-between; align-items:center;">
                                Analytics Dashboard Widgets
                                <button type="button" class="idlm-btn idlm-btn-secondary" style="padding: 2px 6px; font-size: 10px;" onclick="idlmCopyToClipboard('[idlm_dashboard]', 'Dashboard Shortcode');">Copy</button>
                            </h4>
                            <code style="font-size:12px; font-family:monospace; padding:8px; background:#f8fafc; border-radius:4px; color:#c99644; text-align:center;">[idlm_dashboard]</code>
                            <span style="font-size:11px; color:#64748b;">Embeds marketing performance charts, pipeline stage funnel, and sales metrics.</span>
                        </div>
                    </div>
                </div>

                <!-- Assignment Modes configuration -->
                <div class="idlm-card" style="margin-top:20px;">
                    <h3>Lead Routing & Assignment Rules</h3>
                    <div class="idlm-form-group">
                        <label>Assignment Strategy</label>
                        <select name="assignment_mode" id="idlm-assignment-mode-select" class="idlm-input" style="max-width: 300px;">
                            <option value="manual" <?php selected($assignment_mode, 'manual'); ?>>Manual Only (Admin delegates from tables)</option>
                            <option value="round_robin" <?php selected($assignment_mode, 'round_robin'); ?>>Round Robin (Autosync rotation across active reps)</option>
                            <option value="source_based" <?php selected($assignment_mode, 'source_based'); ?>>Source-Based (Route channels to specific reps)</option>
                        </select>
                    </div>

                    <div class="idlm-form-group">
                        <label><strong>Active Representative Pool:</strong></label>
                        <div class="idlm-checkbox-list" style="margin-top:10px; display:flex; gap:15px; flex-wrap:wrap;">
                            <?php foreach ($reps as $rep): ?>
                                <label style="display:flex; align-items:center; gap:5px;">
                                    <input type="checkbox" name="active_reps[]" value="<?php echo esc_attr($rep->ID); ?>" <?php checked(in_array($rep->ID, $active_reps)); ?>>
                                    <?php echo esc_html($rep->display_name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Source maps sub panel -->
                    <div id="idlm-source-mapping-subpanel" style="display:<?php echo ($assignment_mode === 'source_based') ? 'block' : 'none'; ?>; border-top:1px solid #eee; padding-top:15px; margin-top:15px;">
                        <h4>Configure Source Routing Maps</h4>
                        <div class="idlm-form-grid" style="grid-template-columns: 1fr 1fr;">
                            <?php foreach (array('Website', 'Meta Ads', 'Google Ads', 'LinkedIn Ads', 'Referral') as $src):
                                $field_name = 'source_map_' . str_replace(' ', '_', strtolower($src));
                                $val = isset($source_maps[$src]) ? $source_maps[$src] : 0;
                            ?>
                                <div class="idlm-form-group">
                                    <label>Route <?php echo esc_html($src); ?> to</label>
                                    <select name="<?php echo esc_attr($field_name); ?>" class="idlm-input">
                                        <option value="0">Unassigned</option>
                                        <?php foreach ($reps as $rep): 
                                            if (!in_array($rep->ID, $active_reps) && $val != $rep->ID) {
                                                continue;
                                            }
                                        ?>
                                            <option value="<?php echo esc_attr($rep->ID); ?>" <?php selected($val, $rep->ID); ?>><?php echo esc_html($rep->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Webhooks and mapping keys -->
                <div class="idlm-card" style="margin-top:20px;">
                    <h3>Incoming Ad webhook setup</h3>
                    <div class="idlm-form-group">
                        <label>Target Webhook Endpoint URL</label>
                        <input type="text" class="idlm-input" value="<?php echo esc_attr(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>" readonly style="background:#eee;">
                    </div>
                    <div class="idlm-form-group">
                        <label>Security Key (Send header <code>X-IDLM-API-Key</code> or URL parameter <code>api_key</code>)</label>
                        <input type="text" class="idlm-input" value="<?php echo esc_attr($webhook_key); ?>" readonly style="background:#eee; font-family: monospace;">
                    </div>

                    <!-- Cloudflare Warning Notice -->
                    <div style="background-color: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #d97706; border-radius: 8px; padding: 15px; margin-top: 15px; margin-bottom: 15px;">
                        <h4 style="margin-top: 0; color: #b45309; font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-warning" style="color:#d97706; font-size:20px; width:20px; height:20px;"></span>
                            Cloudflare "Under Attack" Mode & Security Blocks Guidance
                        </h4>
                        <p style="font-size: 12px; color: #78350f; margin-bottom: 8px; line-height: 1.6; font-weight: normal;">
                            If you have enabled Cloudflare's <strong>"Under Attack" Mode</strong> or high security levels to mitigate bot attacks, incoming third-party ad webhooks (Meta, Google, LinkedIn) will be blocked with a <code>"Just a moment..." (503 challenge)</code> page because API clients cannot execute the required JavaScript challenges.
                        </p>
                        <h5 style="margin: 8px 0 4px 0; font-size: 12px; color: #78350f; font-weight: 600;">How to bypass the block for webhook leads:</h5>
                        <ol style="margin: 0; padding-left: 20px; font-size: 11px; color: #78350f; line-height: 1.6; font-weight: normal;">
                            <li>Log in to your <strong>Cloudflare Dashboard</strong> and select your website domain.</li>
                            <li>Navigate to <strong>Security &gt; WAF &gt; Custom Rules</strong> in the sidebar.</li>
                            <li>Click the <strong>Create rule</strong> button.</li>
                            <li>Name the rule (e.g., <code>Bypass Lead Webhook Verification</code>).</li>
                            <li>Define the incoming request matching condition:
                                <ul>
                                    <li>Field: <strong>URI Path</strong></li>
                                    <li>Operator: <strong>contains</strong></li>
                                    <li>Value: <code>/wp-json/interior-design-lead-manager/v1/webhook</code></li>
                                </ul>
                            </li>
                            <li>Under the <strong>Choose action</strong> dropdown, select <strong>Skip</strong>.</li>
                            <li>In the <strong>WAF components to skip</strong> checklist, select:
                                <ul>
                                    <li><strong>WAF Managed Rules</strong></li>
                                    <li><strong>Super Bot Fight Mode</strong></li>
                                    <li><strong>Security Level</strong> (this bypasses "Under Attack" challenge checks for this endpoint)</li>
                                </ul>
                            </li>
                            <li>Click <strong>Deploy</strong> on the bottom-right to activate the exception instantly.</li>
                        </ol>
                    </div>

                    <!-- Webhook Setup Guide & Platform URLs -->
                    <div class="idlm-webhook-notes" style="background-color: #f8fafc; border: 1px solid #dfe7f1; border-radius: 8px; padding: 15px; margin-top: 20px; margin-bottom: 20px;">
                        <h4 style="margin-top: 0; color: #111827; font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 600;">Platform Setup Instructions & Webhook Formats</h4>
                        <p style="font-size: 12px; color: #64748b; margin-bottom: 12px; line-height: 1.5;">You should configure the same base webhook endpoint to capture leads across all advertising platforms. Distinguish incoming streams by passing the <code>platform</code> name as a query parameter alongside your security key:</p>
                        
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; flex-direction: column; gap: 4px; padding: 10px; background-color: #ffffff; border: 1px solid #dfe7f1; border-radius: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="idlm-badge idlm-badge-meta">Meta Ads / Facebook Lead Forms</span>
                                    <button type="button" class="idlm-btn idlm-btn-secondary" style="padding: 2px 6px; font-size: 10px;" onclick="idlmCopyToClipboard('<?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=meta&amp;api_key=<?php echo esc_attr($webhook_key); ?>', 'Meta Webhook URL');">Copy URL</button>
                                </div>
                                <code style="font-size: 11px; word-break: break-all; margin-top: 4px; color: #c99644;"><?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=meta&amp;api_key=<?php echo esc_attr($webhook_key); ?></code>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 4px; padding: 10px; background-color: #ffffff; border: 1px solid #dfe7f1; border-radius: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="idlm-badge idlm-badge-google">Google Ads Lead Forms</span>
                                    <button type="button" class="idlm-btn idlm-btn-secondary" style="padding: 2px 6px; font-size: 10px;" onclick="idlmCopyToClipboard('<?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=google&amp;api_key=<?php echo esc_attr($webhook_key); ?>', 'Google Webhook URL');">Copy URL</button>
                                </div>
                                <code style="font-size: 11px; word-break: break-all; margin-top: 4px; color: #c99644;"><?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=google&amp;api_key=<?php echo esc_attr($webhook_key); ?></code>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 4px; padding: 10px; background-color: #ffffff; border: 1px solid #dfe7f1; border-radius: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="idlm-badge idlm-badge-linkedin">LinkedIn Lead Gen Forms</span>
                                    <button type="button" class="idlm-btn idlm-btn-secondary" style="padding: 2px 6px; font-size: 10px;" onclick="idlmCopyToClipboard('<?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=linkedin&amp;api_key=<?php echo esc_attr($webhook_key); ?>', 'LinkedIn Webhook URL');">Copy URL</button>
                                </div>
                                <code style="font-size: 11px; word-break: break-all; margin-top: 4px; color: #c99644;"><?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=linkedin&amp;api_key=<?php echo esc_attr($webhook_key); ?></code>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 4px; padding: 10px; background-color: #ffffff; border: 1px solid #dfe7f1; border-radius: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="idlm-badge idlm-badge-website">Website Custom Forms (e.g. Elementor)</span>
                                    <button type="button" class="idlm-btn idlm-btn-secondary" style="padding: 2px 6px; font-size: 10px;" onclick="idlmCopyToClipboard('<?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=website&amp;api_key=<?php echo esc_attr($webhook_key); ?>', 'Website Webhook URL');">Copy URL</button>
                                </div>
                                <code style="font-size: 11px; word-break: break-all; margin-top: 4px; color: #c99644;"><?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=website&amp;api_key=<?php echo esc_attr($webhook_key); ?></code>
                            </div>
                        </div>
                    </div>

                    <!-- Field mappings tables -->
                    <h4 style="margin-top: 25px;">Webhooks Payload Field Mappings</h4>
                    <div class="idlm-tabs" style="background:#f9f9f9; padding:5px; border-radius:5px;">
                        <button type="button" class="idlm-tab-link active" onclick="switchFieldMappingTab(event, 'map-meta')">Meta Ads Forms</button>
                        <button type="button" class="idlm-tab-link" onclick="switchFieldMappingTab(event, 'map-google')">Google Ads Forms</button>
                        <button type="button" class="idlm-tab-link" onclick="switchFieldMappingTab(event, 'map-linkedin')">LinkedIn Ads Forms</button>
                        <button type="button" class="idlm-tab-link" onclick="switchFieldMappingTab(event, 'map-website')">Website Custom Webhook</button>
                    </div>

                    <?php
                    $platforms = array('meta', 'google', 'linkedin', 'website');
                    $fields = array(
                        'first_name' => 'First Name Payload Variable',
                        'last_name' => 'Last Name Payload Variable',
                        'email' => 'Email Address Payload Variable',
                        'phone' => 'Phone Number Payload Variable',
                        'budget' => 'Lead Budget Value',
                        'project_timeline' => 'Project Timeline',
                        'property_location' => 'Property Location/Pincode',
                        'property_type' => 'Property Type (Apartment/Villa)',
                        'num_rooms' => 'Number of Rooms (BHK)',
                        'preferred_studio' => 'Studio Location Variable',
                        'utm_source' => 'UTM Source',
                        'utm_medium' => 'UTM Medium',
                        'utm_campaign' => 'UTM Campaign',
                        'utm_adgroup' => 'UTM Adgroup',
                        'utm_term' => 'UTM Term',
                        'utm_content' => 'UTM Content',
                        'gclid' => 'GCLID',
                        'fbclid' => 'FBCLID',
                        'fb_lead_id' => 'Meta Lead ID',
                        'li_fat_id' => 'LI FAT ID'
                    );

                    foreach ($platforms as $idx => $plat):
                        $mappings = get_option('idlm_field_mappings_' . $plat, array());
                        $active_class = ($idx === 0) ? 'active' : '';
                    ?>
                        <div id="map-<?php echo esc_attr($plat); ?>" class="idlm-mapping-tab-content <?php echo esc_attr($active_class); ?>" style="padding:15px 0;">
                            <div class="idlm-form-grid" style="grid-template-columns: 1fr 1fr;">
                                <?php foreach ($fields as $key => $lbl): 
                                    $val = isset($mappings[$key]) ? $mappings[$key] : '';
                                ?>
                                    <div class="idlm-form-group">
                                        <label><?php echo esc_html($lbl); ?></label>
                                        <input type="text" name="map_<?php echo esc_attr($plat); ?>_<?php echo esc_attr($key); ?>" class="idlm-input" placeholder="e.g. <?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Conversion API Integrations configurations -->
                <div class="idlm-card" style="margin-top:20px;">
                    <h3>Platform Conversion APIs (CAPI) Settings</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Configure the real-time Conversions API settings to transmit server-side conversion signals to Meta, Google Ads, and LinkedIn upon pipeline stage changes.</p>
                    
                    <!-- Meta panel -->
                    <div style="border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:15px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="meta_capi_enabled" value="1" <?php checked($meta_capi_enabled, '1'); ?>>
                            <h4 style="margin:0;">Meta Conversion API (CAPI)</h4>
                        </div>
                        <div class="idlm-form-grid" style="grid-template-columns:1fr 1fr; margin-top:10px;">
                            <div class="idlm-form-group">
                                <label>Meta Pixel ID</label>
                                <input type="text" name="meta_pixel_id" class="idlm-input" value="<?php echo esc_attr($meta_pixel_id); ?>">
                            </div>
                            <div class="idlm-form-group">
                                <label>Meta Access Token</label>
                                <input type="password" name="meta_access_token" class="idlm-input" value="<?php echo esc_attr($meta_access_token); ?>">
                            </div>
                        </div>
                        <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                            <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-test-meta-capi" style="border-color:#c99644; color:#c99644; padding: 6px 15px;">Test Meta CAPI Connection</button>
                        </div>
                    </div>

                    <!-- Google panel -->
                    <div style="border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:15px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="google_capi_enabled" value="1" <?php checked($google_capi_enabled, '1'); ?>>
                            <h4 style="margin:0;">Google Ads Offline Conversions (GA4 Measurement Protocol)</h4>
                        </div>
                        <div class="idlm-form-grid" style="grid-template-columns:1fr 1fr; margin-top:10px;">
                            <div class="idlm-form-group">
                                <label>GA4 Measurement ID</label>
                                <input type="text" name="google_measurement_id" class="idlm-input" value="<?php echo esc_attr($google_measurement_id); ?>">
                            </div>
                            <div class="idlm-form-group">
                                <label>GA4 API Secret</label>
                                <input type="password" name="google_api_secret" class="idlm-input" value="<?php echo esc_attr($google_api_secret); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- LinkedIn panel -->
                    <div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="linkedin_capi_enabled" value="1" <?php checked($linkedin_capi_enabled, '1'); ?>>
                            <h4 style="margin:0;">LinkedIn Conversion API</h4>
                        </div>
                        <div class="idlm-form-grid" style="grid-template-columns:1fr 1fr; margin-top:10px;">
                            <div class="idlm-form-group">
                                <label>LinkedIn Conversion Action Partner ID</label>
                                <input type="text" name="linkedin_conversion_id" class="idlm-input" value="<?php echo esc_attr($linkedin_conversion_id); ?>">
                            </div>
                            <div class="idlm-form-group">
                                <label>LinkedIn Access Token</label>
                                <input type="password" name="linkedin_access_token" class="idlm-input" value="<?php echo esc_attr($linkedin_access_token); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Audience Sync configurations -->
                <div class="idlm-card" style="margin-top:20px;">
                    <h3>Custom Audience Batch Sync Rules</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Configure multiple Custom Audience batch sync jobs to run daily at 2:00 AM. You can target specific segments by choosing custom pipeline stages, call statuses, or lead qualities for each platform's destination list.</p>
                    
                    <div class="idlm-table-wrapper">
                        <table class="idlm-table" id="idlm-audience-rules-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Platform</th>
                                    <th>Audience / Segment ID</th>
                                    <th>Stage Filter</th>
                                    <th>Call Status Filter</th>
                                    <th>Lead Quality Filter</th>
                                    <th style="width: 120px;">Status</th>
                                    <th style="width: 140px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="idlm-audience-rules-container">
                                <?php foreach ($audience_rules as $rule_idx => $rule): ?>
                                    <tr class="idlm-audience-rule-row">
                                        <td>
                                            <select name="audience_rules[platform][]" class="idlm-input" style="width: 100%;">
                                                <option value="meta" <?php selected($rule['platform'], 'meta'); ?>>Meta Ads</option>
                                                <option value="google" <?php selected($rule['platform'], 'google'); ?>>Google Ads</option>
                                                <option value="linkedin" <?php selected($rule['platform'], 'linkedin'); ?>>LinkedIn Ads</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="audience_rules[audience_id][]" class="idlm-input" value="<?php echo esc_attr($rule['audience_id']); ?>" placeholder="e.g. Audience ID or URN" required style="width: 100%;">
                                        </td>
                                        <td>
                                            <select name="audience_rules[stage][]" class="idlm-input" style="width: 100%;">
                                                <option value="all">All Stages</option>
                                                <?php foreach ($stages as $stage): ?>
                                                    <option value="<?php echo esc_attr($stage); ?>" <?php selected($rule['stage'], $stage); ?>><?php echo esc_html($stage); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="audience_rules[call_status][]" class="idlm-input" style="width: 100%;">
                                                <option value="all">All Statuses</option>
                                                <?php foreach ($call_statuses as $cs): ?>
                                                    <option value="<?php echo esc_attr($cs); ?>" <?php selected($rule['call_status'], $cs); ?>><?php echo esc_html($cs); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="audience_rules[lead_quality][]" class="idlm-input" style="width: 100%;">
                                                <option value="all" <?php selected($rule['lead_quality'], 'all'); ?>>All Qualities</option>
                                                <option value="Qualified" <?php selected($rule['lead_quality'], 'Qualified'); ?>>Qualified</option>
                                                <option value="Unqualified" <?php selected($rule['lead_quality'], 'Unqualified'); ?>>Unqualified</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="audience_rules[enabled][]" class="idlm-input" style="width: 100%;">
                                                <option value="1" <?php selected($rule['enabled'], '1'); ?>>Enabled</option>
                                                <option value="0" <?php selected($rule['enabled'], '0'); ?>>Disabled</option>
                                            </select>
                                        </td>
                                        <td style="text-align: center; display: flex; gap: 5px; justify-content: center; align-items: center;">
                                            <button type="button" class="idlm-btn idlm-btn-secondary idlm-test-audience-rule" style="border-color: #c99644; color: #c99644; padding: 4px 10px;">Test</button>
                                            <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-audience-rule" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-add-audience-rule" style="margin-top:15px;">+ Add Sync Rule</button>
                </div>

                <!-- Follow-up Reminders configurations -->
                <div class="idlm-card" style="margin-top:20px;">
                    <h3>Followup Reminder Settings</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Configure different reminder intervals (in hours) for various Call Statuses. When a lead's call status remains in one of these statuses longer than the specified hours, it will be highlighted as "Overdue" in the leads list.</p>
                    
                    <div id="idlm-reminders-container">
                        <?php foreach ($reminder_rules as $rule): ?>
                            <div class="idlm-reminder-row" style="display: flex; gap: 15px; align-items: center; margin-bottom: 12px;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 11px; margin-bottom: 4px; font-weight: 500;">Call Status</label>
                                    <select name="reminder_rules_status[]" class="idlm-input">
                                        <?php foreach ($call_statuses as $cs): ?>
                                            <option value="<?php echo esc_attr($cs); ?>" <?php selected($rule['status'], $cs); ?>><?php echo esc_html($cs); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 11px; margin-bottom: 4px; font-weight: 500;">Time Interval (Hours)</label>
                                    <input type="number" name="reminder_rules_hours[]" class="idlm-input" value="<?php echo esc_attr($rule['hours']); ?>" min="1" required>
                                </div>
                                <div style="margin-top: 15px;">
                                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-reminder-rule" style="border-color: #ef4444; color: #ef4444;">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-add-reminder-rule" style="margin-top:10px;">+ Add Reminder Rule</button>
                </div>

                       </div>
                
                <div style="margin-top: 30px; margin-bottom: 10px;">
                    <button type="submit" class="idlm-btn idlm-btn-primary" style="font-size: 15px; padding: 12px 35px;">Save Settings</button>
                </div>
            </form>

            <?php if (current_user_can('manage_options')): ?>
                <!-- Danger Zone: Reset CRM Data -->
                <div class="idlm-card" style="margin-top: 30px; border: 1px solid #fee2e2; background-color: #fef2f2;">
                    <h3 style="color: #991b1b; margin-top: 0;">Danger Zone: Reset CRM Leads & Analytics</h3>
                    <p style="font-size: 13px; color: #7f1d1d; margin-bottom: 15px;">
                        This operation will permanently delete all leads, activity logs, Conversions API logs, and marketing cost spent records from the database. All performance analytics and conversion pipelines will start from scratch. Your plugin configuration settings (API keys, stages, routing rules) will remain untouched.
                    </p>
                    <button type="button" id="idlm-reset-data-btn" class="idlm-btn" style="background-color: #ef4444; color: white; border: none; font-size: 14px; padding: 10px 20px;">
                        Delete All Leads & Reset Analytics
                    </button>
                </div>

                <!-- 2-Step Verification Modal -->
                <div id="idlm-reset-modal" class="idlm-modal">
                    <div class="idlm-modal-content" style="max-width: 450px; border: 1px solid #fee2e2;">
                        <div class="idlm-modal-header" style="border-bottom: 1px solid #fee2e2; padding-bottom: 10px; margin-bottom: 15px;">
                            <h2 style="color: #991b1b; margin: 0; display: flex; align-items: center; gap: 8px;">
                                ⚠️ Confirm Permanent Reset
                            </h2>
                            <span class="idlm-close" id="idlm-reset-close-btn">&times;</span>
                        </div>
                        <p style="font-size: 13px; line-height: 1.5; color: #374151; margin-bottom: 15px;">
                            This action is **irreversible** and will permanently wipe out all leads and database analytics.
                        </p>
                        <p style="font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 12px;" id="idlm-reset-instruction-text">
                            To confirm, please enter the total number of leads currently in the database to proceed:
                        </p>
                        <div class="idlm-form-group" style="margin-bottom: 20px;">
                            <input type="text" id="idlm-reset-confirm-input" class="idlm-input" placeholder="Enter total lead count..." style="width: 100%; border-color: #ef4444; padding: 10px;">
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" id="idlm-reset-cancel-btn" class="idlm-btn idlm-btn-secondary">Cancel</button>
                            <button type="button" id="idlm-reset-confirm-btn" class="idlm-btn" style="background-color: #ef4444; color: white; border: none;" disabled>Confirm Reset</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>    </div>
        <script>
        function switchFieldMappingTab(evt, tabId) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("idlm-mapping-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = evt.currentTarget.parentNode.getElementsByClassName("idlm-tab-link");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabId).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        jQuery(document).ready(function($) {
            $('#idlm-assignment-mode-select').change(function() {
                if ($(this).val() === 'source_based') {
                    $('#idlm-source-mapping-subpanel').slideDown();
                } else {
                    $('#idlm-source-mapping-subpanel').slideUp();
                }
            });

            // Dynamic reminders settings rule builder
            $('#idlm-add-reminder-rule').click(function() {
                var statusesHtml = '';
                <?php foreach ($call_statuses as $cs): ?>
                    statusesHtml += '<option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>';
                <?php endforeach; ?>
                
                var rowHtml = '\
                <div class="idlm-reminder-row" style="display: flex; gap: 15px; align-items: center; margin-bottom: 12px;">\
                    <div style="flex: 1;">\
                        <select name="reminder_rules_status[]" class="idlm-input">\
                            ' + statusesHtml + '\
                        </select>\
                    </div>\
                    <div style="flex: 1;">\
                        <input type="number" name="reminder_rules_hours[]" class="idlm-input" placeholder="Hours" min="1" required>\
                    </div>\
                    <div>\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-reminder-rule" style="border-color: #ef4444; color: #ef4444;">Delete</button>\
                    </div>\
                </div>';
                
                $('#idlm-reminders-container').append(rowHtml);
            });
            
            $(document).on('click', '.idlm-remove-reminder-rule', function() {
                $(this).closest('.idlm-reminder-row').remove();
            });

            // Dynamic custom audience sync rule builder
            $('#idlm-add-audience-rule').click(function() {
                var stagesHtml = '<option value="all">All Stages</option>';
                <?php foreach ($stages as $stage): ?>
                    stagesHtml += '<option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>';
                <?php endforeach; ?>

                var callStatusesHtml = '<option value="all">All Statuses</option>';
                <?php foreach ($call_statuses as $cs): ?>
                    callStatusesHtml += '<option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>';
                <?php endforeach; ?>

                var rowHtml = '\
                <tr class="idlm-audience-rule-row">\
                    <td>\
                        <select name="audience_rules[platform][]" class="idlm-input" style="width: 100%;">\
                            <option value="meta">Meta Ads</option>\
                            <option value="google">Google Ads</option>\
                            <option value="linkedin">LinkedIn Ads</option>\
                        </select>\
                    </td>\
                    <td>\
                        <input type="text" name="audience_rules[audience_id][]" class="idlm-input" placeholder="e.g. Audience ID or URN" required style="width: 100%;">\
                    </td>\
                    <td>\
                        <select name="audience_rules[stage][]" class="idlm-input" style="width: 100%;">\
                            ' + stagesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[call_status][]" class="idlm-input" style="width: 100%;">\
                            ' + callStatusesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[lead_quality][]" class="idlm-input" style="width: 100%;">\
                            <option value="all">All Qualities</option>\
                            <option value="Qualified">Qualified</option>\
                            <option value="Unqualified">Unqualified</option>\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[enabled][]" class="idlm-input" style="width: 100%;">\
                            <option value="1">Enabled</option>\
                            <option value="0">Disabled</option>\
                        </select>\
                    <td style="text-align: center; display: flex; gap: 5px; justify-content: center; align-items: center;">\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-test-audience-rule" style="border-color: #c99644; color: #c99644; padding: 4px 10px;">Test</button>\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-audience-rule" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>\
                    </td>\
                </tr>';

                $('#idlm-audience-rules-container').append(rowHtml);
            });

            $(document).on('click', '.idlm-remove-audience-rule', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX Endpoint: Detailed Drawer populator
     */
    public static function ajax_get_lead_details() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }

        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare("
            SELECT l.*, u.display_name AS assigned_rep_name 
            FROM {$wpdb->prefix}idlm_leads l
            LEFT JOIN {$wpdb->users} u ON l.assigned_rep_id = u.ID 
            WHERE l.id = %d
        ", $lead_id));
        if (!$lead) {
            wp_send_json_error(array('message' => 'Lead not found'));
        }

        // Fetch duplicate leads if any
        $duplicates = array();
        if ($lead->is_duplicate == 0) {
            $duplicates = $wpdb->get_results($wpdb->prepare(
                "SELECT id, first_name, last_name, created_at FROM {$wpdb->prefix}idlm_leads 
                 WHERE is_duplicate = 1 AND duplicate_of_id = %d",
                $lead_id
            ));
        }

        // Render HTML for Drawer
        ob_start();
        ?>
        <!-- Header -->
        <header class="idlm-drawer-header">
            <div class="idlm-drawer-identity">
                <div class="idlm-drawer-avatar"><?php echo esc_html($lead->first_name ? strtoupper($lead->first_name[0]) : '?'); ?></div>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <h2 id="lead-title"><?php echo esc_html($lead->first_name . ' ' . $lead->last_name); ?></h2>
                    <?php if ($lead->source): ?>
                        <span class="idlm-drawer-chip idlm-badge-<?php echo esc_attr(strtolower(str_replace(array(' ', '/', '-'), '', $lead->source))); ?>" style="align-self: flex-start;"><?php echo esc_html($lead->source); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="idlm-drawer-status-grid" aria-label="Lead status">
                <div class="idlm-drawer-status-item">
                    <span class="idlm-drawer-status-label">CALL STATUS</span>
                    <span class="idlm-drawer-status-value"><?php echo esc_html($lead->call_status ? $lead->call_status : 'Not Called'); ?></span>
                </div>
                <div class="idlm-drawer-status-item">
                    <span class="idlm-drawer-status-label">STAGE</span>
                    <span class="idlm-drawer-status-value"><?php echo esc_html($lead->stage ? $lead->stage : 'New'); ?></span>
                </div>
                <div class="idlm-drawer-status-item">
                    <span class="idlm-drawer-status-label">ASSIGNED REP</span>
                    <span class="idlm-drawer-status-value"><?php echo esc_html($lead->assigned_rep_name ? $lead->assigned_rep_name : 'Unassigned'); ?></span>
                </div>
                <div class="idlm-drawer-status-item">
                    <span class="idlm-drawer-status-label">LEAD QUALITY</span>
                    <span class="idlm-drawer-status-value"><?php echo esc_html($lead->lead_quality ? $lead->lead_quality : 'Choose...'); ?></span>
                </div>
                <div class="idlm-drawer-status-item">
                    <span class="idlm-drawer-status-label">CREATED AT</span>
                    <span class="idlm-drawer-status-value"><?php echo esc_html(self::format_datetime($lead->created_at)); ?></span>
                </div>
            </div>

            <button type="button" class="idlm-close" aria-label="Close" onclick="closeLeadDrawer()">&times;</button>
        </header>

        <nav class="idlm-drawer-tabs" aria-label="Lead sections">
            <button class="idlm-drawer-tab active" type="button" onclick="switchDrawerTab(event, 'drawer-tab-info')">Details</button>
            <button class="idlm-drawer-tab" type="button" onclick="switchDrawerTab(event, 'drawer-tab-activity')">Activity Log & Comments</button>
        </nav>

        <div class="idlm-drawer-body">
            <div id="drawer-tab-info" class="idlm-drawer-tab-content active">
                <form id="idlm-edit-lead-form">
                    <input type="hidden" name="lead_id" value="<?php echo esc_attr($lead->id); ?>">
                    <div class="idlm-drawer-workspace">
                        <div>
                            <?php if ($lead->is_duplicate): ?>
                                <div class="idlm-alert idlm-alert-warning" style="margin-bottom:15px;">
                                    <strong>Warning!</strong> Duplicate Lead detected. Linked to original lead: 
                                    <a href="#" onclick="openLeadDrawer(<?php echo esc_attr($lead->duplicate_of_id); ?>)">Lead ID #<?php echo esc_html($lead->duplicate_of_id); ?></a>
                                </div>
                            <?php endif; ?>

                            <section class="idlm-drawer-panel">
                                <div class="idlm-drawer-panel-head">
                                    <h3 class="idlm-drawer-panel-title">Details</h3>
                                </div>
                                <div class="idlm-drawer-form-grid">
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-first-name">First Name</label>
                                        <input type="text" name="first_name" class="idlm-drawer-control" id="idlm-edit-first-name" value="<?php echo esc_attr($lead->first_name); ?>" required />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-last-name">Last Name</label>
                                        <input type="text" name="last_name" class="idlm-drawer-control" id="idlm-edit-last-name" value="<?php echo esc_attr($lead->last_name); ?>" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-email">Email</label>
                                        <input type="email" name="email" class="idlm-drawer-control" id="idlm-edit-email" value="<?php echo esc_attr($lead->email); ?>" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-phone">Phone</label>
                                        <input type="tel" name="phone" class="idlm-drawer-control" id="idlm-edit-phone" value="<?php echo esc_attr($lead->phone); ?>" required />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-lead-quality">Lead Quality</label>
                                        <select name="lead_quality" class="idlm-drawer-control" id="idlm-edit-lead-quality">
                                            <option value="" <?php selected($lead->lead_quality, ''); ?>>Choose Quality...</option>
                                            <option value="Qualified" <?php selected($lead->lead_quality, 'Qualified'); ?>>✅ Qualified</option>
                                            <option value="Unqualified" <?php selected($lead->lead_quality, 'Unqualified'); ?>>❌ Unqualified</option>
                                        </select>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-lead-budget">Lead Budget</label>
                                        <input type="text" name="budget" class="idlm-drawer-control" id="idlm-edit-lead-budget" value="<?php echo esc_attr($lead->budget); ?>" placeholder="e.g. ₹15-25 Lakhs" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-deal-value">Deal Value (INR)</label>
                                        <input type="number" step="0.01" min="0" name="deal_value" class="idlm-drawer-control" id="idlm-edit-deal-value" value="<?php echo esc_attr($lead->deal_value ? $lead->deal_value : ''); ?>" placeholder="e.g. 150000" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-timeline">Project Timeline</label>
                                        <input type="text" name="project_timeline" class="idlm-drawer-control" id="idlm-edit-timeline" value="<?php echo esc_attr($lead->project_timeline); ?>" placeholder="Immediately" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-location">Property Location / Pincode</label>
                                        <input type="text" name="property_location" class="idlm-drawer-control" id="idlm-edit-location" value="<?php echo esc_attr($lead->property_location); ?>" placeholder="North Bangalore" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-property-type">Property Type</label>
                                        <select name="property_type" class="idlm-drawer-control" id="idlm-edit-property-type">
                                            <option value="Apartment" <?php selected($lead->property_type, 'Apartment'); ?>>Apartment</option>
                                            <option value="Villa" <?php selected($lead->property_type, 'Villa'); ?>>Villa</option>
                                            <option value="Independent House" <?php selected($lead->property_type, 'Independent House'); ?>>Independent House</option>
                                            <option value="Commercial" <?php selected($lead->property_type, 'Commercial'); ?>>Commercial</option>
                                        </select>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-rooms">Rooms (BHK)</label>
                                        <?php $num_rooms_options = get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK')); ?>
                                        <select name="num_rooms" class="idlm-drawer-control" id="idlm-edit-rooms">
                                            <?php foreach ($num_rooms_options as $opt): ?>
                                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($lead->num_rooms, $opt); ?>><?php echo esc_html($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-floor-plan">Floor Plan Recd</label>
                                        <select name="floor_plan_received" class="idlm-drawer-control" id="idlm-edit-floor-plan" style="margin-bottom: 8px;">
                                            <option value="1" <?php selected($lead->floor_plan_received, 1); ?>>Yes</option>
                                            <option value="0" <?php selected($lead->floor_plan_received, 0); ?>>No</option>
                                        </select>
                                        <div class="idlm-drawer-file-row">
                                            <input type="hidden" name="floor_plan_file" id="idlm_floor_plan_file" value="<?php echo esc_attr(isset($lead->floor_plan_file) ? $lead->floor_plan_file : ''); ?>">
                                            <input type="file" class="idlm-file-input" data-input-id="idlm_floor_plan_file" data-preview-id="idlm_floor_plan_preview" style="display: none;">
                                            <button type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">
                                                <?php echo !empty($lead->floor_plan_file) ? 'Change File' : 'Upload Floor Plan'; ?>
                                            </button>
                                            <span id="idlm_floor_plan_preview" class="idlm-drawer-file-note">
                                                <?php if (!empty($lead->floor_plan_file)): ?>
                                                    <a href="<?php echo esc_url($lead->floor_plan_file); ?>" target="_blank" class="idlm-file-link" style="color: var(--gold); font-weight: 500; text-decoration: none;">📄 View File</a>
                                                    <button type="button" class="idlm-remove-file-btn" data-input-id="idlm_floor_plan_file" data-preview-id="idlm_floor_plan_preview" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:12px; padding:0; margin-left:5px; font-weight:500;">Remove</button>
                                                <?php else: ?>
                                                    No file uploaded
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="idlm-drawer-panel">
                                <div class="idlm-drawer-form-grid idlm-drawer-form-grid-two">
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-quote-shared">Quote Shared</label>
                                        <select name="quote_shared" class="idlm-drawer-control" id="idlm-edit-quote-shared" style="margin-bottom: 8px;">
                                            <option value="1" <?php selected($lead->quote_shared, 1); ?>>Yes</option>
                                            <option value="0" <?php selected($lead->quote_shared, 0); ?>>No</option>
                                        </select>
                                        <div class="idlm-drawer-file-row">
                                            <input type="hidden" name="quote_file" id="idlm_quote_file" value="<?php echo esc_attr(isset($lead->quote_file) ? $lead->quote_file : ''); ?>">
                                            <input type="file" class="idlm-file-input" data-input-id="idlm_quote_file" data-preview-id="idlm_quote_preview" style="display: none;">
                                            <button type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">
                                                <?php echo !empty($lead->quote_file) ? 'Change File' : 'Upload Quote'; ?>
                                            </button>
                                            <span id="idlm_quote_preview" class="idlm-drawer-file-note">
                                                <?php if (!empty($lead->quote_file)): ?>
                                                    <a href="<?php echo esc_url($lead->quote_file); ?>" target="_blank" class="idlm-file-link" style="color: var(--gold); font-weight: 500; text-decoration: none;">📄 View File</a>
                                                    <button type="button" class="idlm-remove-file-btn" data-input-id="idlm_quote_file" data-preview-id="idlm_quote_preview" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:12px; padding:0; margin-left:5px; font-weight:500;">Remove</button>
                                                <?php else: ?>
                                                    No file uploaded
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-studio-location">Studio Location</label>
                                        <?php $studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio')); ?>
                                        <select name="preferred_studio" class="idlm-drawer-control" id="idlm-edit-studio-location">
                                            <?php foreach ($studios as $studio): ?>
                                                <option value="<?php echo esc_attr($studio); ?>" <?php selected($lead->preferred_studio, $studio); ?>><?php echo esc_html($studio); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <?php if ($lead->source === 'Referral'): ?>
                                        <div class="idlm-drawer-field" style="grid-column: span 2;">
                                            <label for="idlm-edit-referrer">Referrer Details</label>
                                            <textarea name="referrer_details" class="idlm-drawer-control" id="idlm-edit-referrer" rows="2" style="min-height: 48px;"><?php echo esc_html($lead->referrer_details); ?></textarea>
                                        </div>
                                    <?php endif; ?>

                                    <?php 
                                    $is_callback_requested = ($lead->call_status === 'Call Back Requested');
                                    if ($is_callback_requested || !empty($lead->callback_datetime)): 
                                        $callback_val = '';
                                        if (!empty($lead->callback_datetime)) {
                                            $callback_val = wp_date('Y-m-d\TH:i', strtotime($lead->callback_datetime));
                                        }
                                    ?>
                                        <div class="idlm-drawer-field" style="grid-column: span 2;">
                                            <label for="idlm-edit-callback-datetime">Callback Scheduled</label>
                                            <input type="datetime-local" name="callback_datetime" class="idlm-drawer-control" id="idlm-edit-callback-datetime" value="<?php echo esc_attr($callback_val); ?>" style="width: 100%;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>

                            <section class="idlm-drawer-panel">
                                <div class="idlm-drawer-panel-head">
                                    <h3 class="idlm-drawer-panel-title">CAMPAIGN ATTRIBUTION DETAILS</h3>
                                </div>
                                <div class="idlm-drawer-data-grid">
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Source</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_source ? $lead->utm_source : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Medium</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_medium ? $lead->utm_medium : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Campaign</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_campaign ? $lead->utm_campaign : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Adgroup</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_adgroup ? $lead->utm_adgroup : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Term</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_term ? $lead->utm_term : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Content</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_content ? $lead->utm_content : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">GCLID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->gclid ? $lead->gclid : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">FBCLID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->fbclid ? $lead->fbclid : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">Meta Lead ID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->fb_lead_id ? $lead->fb_lead_id : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">LI FAT ID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->li_fat_id ? $lead->li_fat_id : 'N/A'); ?></div>
                                    </div>
                                </div>
                            </section>

                            <?php if (!empty($duplicates)): ?>
                                <div style="margin-top:20px; padding: 0 14px;">
                                    <h4 style="margin: 0 0 10px 0; font-size:13px; font-weight:700;">Linked Duplicate Leads (Merged):</h4>
                                    <ul class="idlm-duplicate-list" style="margin:0; padding-left:18px;">
                                        <?php foreach ($duplicates as $dup): ?>
                                            <li style="margin-bottom:6px; font-size:12px;">
                                                <a href="#" onclick="openLeadDrawer(<?php echo esc_attr($dup->id); ?>)" style="color:var(--gold); text-decoration:none; font-weight:600;"><?php echo esc_html($dup->first_name . ' ' . $dup->last_name); ?></a>
                                                <span style="font-size:11px; color:#64748b;">(Added <?php echo esc_html(self::format_datetime($dup->created_at)); ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sidebar Notes & Comments -->
                        <aside class="idlm-drawer-panel idlm-drawer-activity">
                            <div class="idlm-drawer-panel-head">
                                <h3 class="idlm-drawer-panel-title">Notes &amp; Comments</h3>
                            </div>
                            <div class="idlm-drawer-activity-body">
                                <div>
                                    <h4 class="idlm-drawer-activity-title">Add Note / Comment</h4>
                                    <textarea id="idlm-comment-text-details" class="idlm-drawer-textarea idlm-drawer-textarea-compact" placeholder="Add activity notes or comment..."></textarea>
                                    <div class="idlm-drawer-comment-actions">
                                        <button type="button" class="idlm-btn" onclick="saveActivityCommentDetails(<?php echo esc_attr($lead->id); ?>)">Add Comment</button>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="idlm-drawer-activity-title">Recent Comments</h4>
                                    <div class="idlm-timeline-container-details" id="idlm-timeline-container-details-inner">
                                        <div style="text-align:center; padding:15px;"><span class="spinner is-active" style="float:none;"></span> Loading...</div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>

                </form>
            </div>

            <div id="drawer-tab-activity" class="idlm-drawer-tab-content">
                <div class="idlm-drawer-activity-full">
                    <!-- Left column: Add comment -->
                    <section class="idlm-drawer-panel">
                        <div class="idlm-drawer-panel-head">
                            <h3 class="idlm-drawer-panel-title">Add Note / Comment</h3>
                        </div>
                        <div class="idlm-drawer-activity-body">
                            <textarea id="idlm-comment-text" class="idlm-drawer-textarea" placeholder="Add activity notes or comment..."></textarea>
                            <div class="idlm-drawer-comment-actions">
                                <button type="button" class="idlm-btn idlm-btn-primary" onclick="saveActivityComment(<?php echo esc_attr($lead->id); ?>)">Add Comment</button>
                            </div>
                        </div>
                    </section>

                    <!-- Right column: Activities list -->
                    <section class="idlm-drawer-panel">
                        <div class="idlm-drawer-panel-head">
                            <h3 class="idlm-drawer-panel-title">Activity History</h3>
                        </div>
                        <div class="idlm-drawer-activity-body">
                            <div class="idlm-timeline-container" id="idlm-timeline-container-inner">
                                <div style="text-align:center; padding:30px;"><span class="spinner is-active" style="float:none;"></span> Loading timeline...</div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <footer class="idlm-drawer-form-footer">
            <?php if (current_user_can('manage_options')): ?>
                <button type="button" class="idlm-btn idlm-btn-danger-outline" onclick="deleteSingleLead(<?php echo esc_attr($lead->id); ?>)">Delete Lead</button>
            <?php endif; ?>
            <button type="submit" form="idlm-edit-lead-form" class="idlm-btn idlm-btn-primary">Save Lead Details</button>
        </footer>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(array(
            'html' => $html,
            'first_name' => $lead->first_name,
            'full_name' => $lead->first_name . ' ' . $lead->last_name,
            'source' => $lead->source
        ));
    }

    /**
     * AJAX Endpoint: Get lead activity log history for lazy-loading
     */
    public static function ajax_get_lead_activity() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }

        global $wpdb;
        
        // Fetch activity logs
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name 
             FROM {$wpdb->prefix}idlm_lead_activity_log a 
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
             WHERE a.lead_id = %d ORDER BY a.id DESC", 
            $lead_id
        ));

        // Filter comments only for the Details tab sidebar
        $comments = array();
        if (!empty($logs)) {
            foreach ($logs as $log) {
                if ($log->activity_type === 'comment') {
                    $comments[] = $log;
                }
            }
        }
        // Show only the recent 5 comments (3-5 comments limit)
        $recent_comments = array_slice($comments, 0, 5);

        // 1. Render all activities & comments (Full Timeline)
        ob_start();
        ?>
        <div class="idlm-timeline" style="margin-top:0;">
            <?php if (empty($logs)): ?>
                <div style="text-align: center; color: #64748b; padding: 20px 0;">No activity logs found for this lead.</div>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $author = $log->display_name ? $log->display_name : 'System Pipeline';
                ?>
                    <div class="idlm-timeline-item">
                        <div class="idlm-timeline-meta" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($author); ?></strong>
                                <span><?php echo esc_html(self::format_datetime($log->created_at)); ?></span>
                            </div>
                            <?php if ($log->activity_type === 'comment' && (current_user_can('manage_options') || get_current_user_id() == $log->user_id)): ?>
                                <div class="idlm-timeline-actions" style="font-size: 11px;">
                                    <a href="#" class="idlm-edit-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #c99644; text-decoration: none; margin-right: 8px;">Edit</a>
                                    <a href="#" class="idlm-delete-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #b5472e; text-decoration: none;">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="idlm-timeline-details">
                            <?php echo esc_html($log->details); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $full_html = ob_get_clean();

        // 2. Render only recent comments
        ob_start();
        ?>
        <div class="idlm-timeline" style="margin-top:0;">
            <?php if (empty($recent_comments)): ?>
                <div style="text-align: center; color: #64748b; padding: 20px 0;">No comments found for this lead.</div>
            <?php else: ?>
                <?php foreach ($recent_comments as $log): 
                    $author = $log->display_name ? $log->display_name : 'System Pipeline';
                ?>
                    <div class="idlm-timeline-item">
                        <div class="idlm-timeline-meta" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($author); ?></strong>
                                <span><?php echo esc_html(self::format_datetime($log->created_at)); ?></span>
                            </div>
                            <?php if (current_user_can('manage_options') || get_current_user_id() == $log->user_id): ?>
                                <div class="idlm-timeline-actions" style="font-size: 11px;">
                                    <a href="#" class="idlm-edit-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #c99644; text-decoration: none; margin-right: 8px;">Edit</a>
                                    <a href="#" class="idlm-delete-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #b5472e; text-decoration: none;">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="idlm-timeline-details">
                            <?php echo esc_html($log->details); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $comments_html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $full_html,
            'comments_html' => $comments_html
        ));
    }

    /**
     * AJAX Endpoint: Inline Quick-Edit row changes
     */
    public static function ajax_quick_edit_lead() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        $field = isset($_POST['field']) ? sanitize_key(wp_unslash($_POST['field'])) : '';
        $value = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';

        if (!$lead_id || !in_array($field, array('call_status', 'stage', 'assigned_rep_id', 'lead_quality'), true)) {
            wp_send_json_error(array('message' => 'Invalid edit request parameters'));
        }

        $data = array($field => $value);
        
        // Custom logic for Call Back Requested addition callback fields
        if ($field === 'call_status' && $value === 'Call Back Requested' && isset($_POST['callback_time'])) {
            $cb_time = sanitize_text_field(wp_unslash($_POST['callback_time']));
            $data['callback_datetime'] = !empty($cb_time) ? str_replace('T', ' ', $cb_time) : null;
        }

        // Custom logic for Won stage deal value
        if ($field === 'stage' && $value === 'Won' && isset($_POST['deal_value'])) {
            $data['deal_value'] = floatval($_POST['deal_value']);
        }

        $updated = IDLM_DB::update_lead($lead_id, $data);
        if ($updated) {
            wp_send_json_success(array(
                'message' => 'Lead updated successfully',
                'pending_calls_count' => self::get_pending_calls_count()
            ));
        }

        wp_send_json_error(array('message' => 'Failed to save changes. Value unchanged or database lock.'));
    }

    /**
     * AJAX Endpoint: Add comment notes in drawer
     */
    public static function ajax_save_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if (!$lead_id || empty($comment)) {
            wp_send_json_error(array('message' => 'Empty comment or invalid Lead ID'));
        }

        IDLM_DB::log_activity($lead_id, 'comment', $comment);
        wp_send_json_success(array('message' => 'Comment added successfully'));
    }

    /**
     * AJAX Endpoint: Edit activity comment
     */
    public static function ajax_edit_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $log_id = isset($_POST['log_id']) ? intval(wp_unslash($_POST['log_id'])) : 0;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if (!$log_id || empty($comment)) {
            wp_send_json_error(array('message' => 'Invalid parameters.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_activity} WHERE id = %d", $log_id));
        if (!$log || $log->activity_type !== 'comment') {
            wp_send_json_error(array('message' => 'Comment not found.'));
        }

        if (!current_user_can('manage_options') && get_current_user_id() != $log->user_id) {
            wp_send_json_error(array('message' => 'Unauthorized operation.'));
        }

        $updated = $wpdb->update($table_activity, array('details' => $comment), array('id' => $log_id));

        if ($updated !== false) {
            wp_send_json_success(array('message' => 'Comment updated successfully.'));
        }

        wp_send_json_error(array('message' => 'Failed to update comment.'));
    }

    /**
     * AJAX Endpoint: Delete activity comment
     */
    public static function ajax_delete_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $log_id = isset($_POST['log_id']) ? intval(wp_unslash($_POST['log_id'])) : 0;
        if (!$log_id) {
            wp_send_json_error(array('message' => 'Invalid parameters.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_activity} WHERE id = %d", $log_id));
        if (!$log || $log->activity_type !== 'comment') {
            wp_send_json_error(array('message' => 'Comment not found.'));
        }

        if (!current_user_can('manage_options') && get_current_user_id() != $log->user_id) {
            wp_send_json_error(array('message' => 'Unauthorized operation.'));
        }

        $deleted = $wpdb->delete($table_activity, array('id' => $log_id));

        if ($deleted !== false) {
            wp_send_json_success(array('message' => 'Comment deleted successfully.'));
        }

        wp_send_json_error(array('message' => 'Failed to delete comment.'));
    }

    /**
     * AJAX Endpoint: Export leads database to CSV format
     */
    public static function ajax_export_leads() {
        if (!self::current_user_has_rep_access()) {
            wp_die('Unauthorized access');
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // Check rep restriction
        $rep_id = 0;
        if (!current_user_can('manage_options')) {
            $reps_see_all = get_option('idlm_reps_see_all_leads', '1');
            if (!$reps_see_all) {
                $rep_id = get_current_user_id();
            }
        }

        $query = "SELECT * FROM {$table_leads}";
        $where = array();
        $params = array();

        if ($rep_id) {
            $where[] = "assigned_rep_id = %d";
            $params[] = $rep_id;
        }

        // If bulk list is provided
        if (isset($_GET['ids'])) {
            $ids = array_map('intval', explode(',', wp_unslash($_GET['ids'])));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $where[] = "id IN ($placeholders)";
                $params = array_merge($params, $ids);
            }
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " ORDER BY id DESC";

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared_query = $wpdb->prepare($query, $params);
        } else {
            $prepared_query = $query;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads = $wpdb->get_results($prepared_query);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=interior-design-leads-' . gmdate('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($output) {
            fputcsv($output, array('ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Budget', 'Project Timeline', 'Property Location / Pincode', 'Source', 'Referrer Details', 'Property Type', 'Rooms', 'Floor Plan', 'Floor Plan File URL', 'Quote Shared', 'Quote File URL', 'Stage', 'Call Status', 'Callback Scheduled', 'Quality', 'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Adgroup', 'UTM Term', 'UTM Content', 'GCLID', 'FBCLID', 'Meta Lead ID', 'LI FAT ID', 'Created At'));

            foreach ($leads as $l) {
                fputcsv($output, array(
                    $l->id,
                    $l->first_name,
                    $l->last_name,
                    $l->email,
                    $l->phone,
                    $l->budget,
                    $l->project_timeline,
                    $l->property_location,
                    $l->source,
                    $l->referrer_details,
                    $l->property_type,
                    $l->num_rooms,
                    $l->floor_plan_received ? 'Yes' : 'No',
                    isset($l->floor_plan_file) ? $l->floor_plan_file : '',
                    $l->quote_shared ? 'Yes' : 'No',
                    isset($l->quote_file) ? $l->quote_file : '',
                    $l->stage,
                    $l->call_status,
                    $l->callback_datetime,
                    $l->lead_quality,
                    $l->utm_source,
                    $l->utm_medium,
                    $l->utm_campaign,
                    $l->utm_adgroup,
                    $l->utm_term,
                    $l->utm_content,
                    $l->gclid,
                    $l->fbclid,
                    $l->fb_lead_id,
                    $l->li_fat_id,
                    $l->created_at
                ));
            }

            fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
        exit;
    }

    /**
     * AJAX Endpoint: Create manual lead in backend popup
     */
    public static function ajax_add_lead_manual() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        parse_str($_POST['form_data'], $form_fields);

        $first_name = sanitize_text_field($form_fields['first_name']);
        $phone = sanitize_text_field($form_fields['phone']);

        if (empty($first_name) || empty($phone)) {
            wp_send_json_error(array('message' => 'First Name and Phone are required.'));
        }

        $lead_data = array(
            'first_name' => $first_name,
            'last_name' => sanitize_text_field($form_fields['last_name']),
            'email' => sanitize_email($form_fields['email']),
            'phone' => $phone,
            'budget' => isset($form_fields['budget']) ? IDLM_Integrations::normalize_budget(sanitize_text_field($form_fields['budget'])) : '',
            'project_timeline' => isset($form_fields['project_timeline']) ? sanitize_text_field($form_fields['project_timeline']) : '',
            'property_location' => isset($form_fields['property_location']) ? sanitize_text_field($form_fields['property_location']) : '',
            'source' => sanitize_text_field($form_fields['source']),
            'property_type' => sanitize_text_field($form_fields['property_type']),
            'num_rooms' => IDLM_Integrations::normalize_num_rooms(sanitize_text_field($form_fields['num_rooms'])),
            'preferred_studio' => sanitize_text_field($form_fields['preferred_studio']),
            'lead_quality' => sanitize_text_field($form_fields['lead_quality']),
            'stage' => 'New',
            'call_status' => 'Not Called'
        );

        if ($lead_data['source'] === 'Referral') {
            $lead_data['referrer_details'] = sanitize_textarea_field($form_fields['referrer_details']);
        }

        // Capture local UTMs if present in admin browser session
        $captured = IDLM_Integrations::get_captured_attribution();
        foreach ($captured as $k => $v) {
            if (!empty($v)) {
                $lead_data[$k] = $v;
            }
        }

        $lead_id = IDLM_DB::insert_lead($lead_data);
        if ($lead_id) {
            // Apply assignments
            IDLM_Integrations::route_lead_assignment($lead_id, $lead_data['source']);

            wp_send_json_success(array(
                'message' => 'Manual Lead added successfully!',
                'pending_calls_count' => self::get_pending_calls_count()
            ));
        }

        wp_send_json_error(array('message' => 'Failed to create lead. Database write error.'));
    }

    /**
     * AJAX Endpoint: Cost-per-lead updates
     */
    public static function ajax_save_cost_per_lead() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        $channel = isset($_POST['channel']) ? sanitize_text_field(wp_unslash($_POST['channel'])) : '';
        $month = isset($_POST['month']) ? sanitize_text_field(wp_unslash($_POST['month'])) : ''; // YYYY-MM
        $spend = isset($_POST['spend']) ? floatval(wp_unslash($_POST['spend'])) : 0.0;

        if (empty($channel) || empty($month)) {
            wp_send_json_error(array('message' => 'Invalid parameters.'));
        }

        global $wpdb;
        $table_cost = $wpdb->prefix . 'idlm_cost_per_lead';

        $saved = $wpdb->replace($table_cost, array(
            'channel' => $channel,
            'month_year' => $month,
            'cost' => $spend
        ), array('%s', '%s', '%f'));

        if ($saved !== false) {
            wp_send_json_success(array('message' => 'Marketing spend recorded successfully!'));
        }

        wp_send_json_error(array('message' => 'Database update failed.'));
    }

    /**
     * AJAX Endpoint: Manual sync triggers
     */
    public static function ajax_trigger_manual_audience_sync() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        // Trigger the batch sync immediately
        IDLM_Audiences::run_batch_sync();

        wp_send_json_success(array('message' => 'Custom Audience background batch sync triggered!'));
    }

    /**
     * AJAX Endpoint: Test platform connection and credentials
     */
    public static function ajax_test_api_connection() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        $platform = isset($_POST['platform']) ? sanitize_key(wp_unslash($_POST['platform'])) : '';
        $test_type = isset($_POST['test_type']) ? sanitize_key(wp_unslash($_POST['test_type'])) : '';
        $target_id = isset($_POST['target_id']) ? sanitize_text_field(wp_unslash($_POST['target_id'])) : '';
        $access_token = isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '';

        if (empty($platform) || empty($target_id)) {
            wp_send_json_error(array('message' => 'Missing platform or target ID to test.'));
        }

        if ($platform === 'meta') {
            if (empty($access_token)) {
                wp_send_json_error(array('message' => 'Access token is required to test Meta connection.'));
            }

            // We can check Pixel ID (CAPI) or Custom Audience ID
            $endpoint = "https://graph.facebook.com/v25.0/{$target_id}?access_token=" . urlencode($access_token);
            if ($test_type === 'capi') {
                $endpoint .= "&fields=name";
            } else {
                $endpoint .= "&fields=name,subtype";
            }

            $response = wp_remote_get($endpoint, array('timeout' => 15));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Connection request failed: ' . $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code === 200) {
                $name = isset($data['name']) ? $data['name'] : 'Unnamed';
                if ($test_type === 'capi') {
                    wp_send_json_success(array('message' => sprintf('Meta Conversions API connection successful! Verified Pixel Name: "%s".', $name)));
                } else {
                    $subtype = isset($data['subtype']) ? $data['subtype'] : 'CUSTOM';
                    wp_send_json_success(array('message' => sprintf('Meta Custom Audience sync connection successful! Verified Audience Name: "%s" (%s).', $name, $subtype)));
                }
            } else {
                $err_msg = 'Meta Graph API Error: ';
                if (isset($data['error']['message'])) {
                    $err_msg .= $data['error']['message'];
                    if (isset($data['error']['error_subcode'])) {
                        $err_msg .= ' (Subcode: ' . $data['error']['error_subcode'] . ')';
                    }
                } else {
                    $err_msg .= 'HTTP Code ' . $code;
                }
                wp_send_json_error(array('message' => $err_msg));
            }
        } elseif ($platform === 'linkedin') {
            if (empty($access_token)) {
                wp_send_json_error(array('message' => 'Access token is required to test LinkedIn connection.'));
            }

            $endpoint = "https://api.linkedin.com/v2/adSegments/" . rawurlencode($target_id);
            $response = wp_remote_get($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'X-Restli-Protocol-Version' => '2.0.0'
                ),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'LinkedIn connection failed: ' . $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code === 200) {
                $name = isset($data['name']) ? $data['name'] : 'Unnamed Segment';
                wp_send_json_success(array('message' => sprintf('LinkedIn connection successful! Verified Segment Name: "%s".', $name)));
            } else {
                $err_msg = 'LinkedIn API Error: ';
                if (isset($data['message'])) {
                    $err_msg .= $data['message'];
                } else {
                    $err_msg .= 'HTTP Code ' . $code;
                }
                wp_send_json_error(array('message' => $err_msg));
            }
        } elseif ($platform === 'google') {
            $dev_token = get_option('idlm_google_developer_token');
            if (empty($dev_token) && strpos($target_id, 'customers/') !== 0) {
                wp_send_json_error(array('message' => 'Google verification failed: Ensure Google User List Resource starts with "customers/..." or Developer Token is configured.'));
            }
            wp_send_json_success(array('message' => 'Google Customer Match Resource ID validated format successfully (Google sync runs in mock/test mode).'));
        } else {
            wp_send_json_error(array('message' => 'Unsupported platform connection test.'));
        }
    }

    /**
     * AJAX Endpoint: Bulk retry Conversions API events
     */
    public static function ajax_retry_capi_events() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        $log_ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? array_map('intval', wp_unslash($_POST['log_ids'])) : array();

        if (empty($log_ids)) {
            wp_send_json_error(array('message' => 'No logs selected for retry.'));
        }

        $success_count = 0;
        $failed_count = 0;
        $errors = array();

        foreach ($log_ids as $log_id) {
            $result = IDLM_CAPI::retry_capi_log($log_id);
            if ($result['success']) {
                $success_count++;
            } else {
                $failed_count++;
                $errors[] = "Log #{$log_id}: " . $result['message'];
            }
        }

        $msg = sprintf('Completed bulk CAPI resend: %d succeeded, %d failed.', $success_count, $failed_count);
        if (!empty($errors)) {
            $msg .= "\n\nErrors:\n" . implode("\n", $errors);
        }

        wp_send_json_success(array('message' => $msg));
    }

    /**
     * AJAX Endpoint: Bulk operations from Leads Table
     */
    public static function ajax_bulk_action() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $ids = (isset($_POST['ids']) && is_array($_POST['ids'])) ? array_map('intval', wp_unslash($_POST['ids'])) : array();
        $action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';

        if (empty($ids) || empty($action)) {
            wp_send_json_error(array('message' => 'No items or action selected.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        switch ($action) {
            case 'bulk_assign':
                $rep_id = isset($_POST['rep_id']) ? intval(wp_unslash($_POST['rep_id'])) : 0;
                if ($rep_id >= 0) {
                    foreach ($ids as $id) {
                        IDLM_DB::update_lead($id, array('assigned_rep_id' => $rep_id));
                    }
                    wp_send_json_success(array('message' => 'Successfully reassigned selected leads.'));
                }
                break;

            case 'bulk_stage':
                $stage = isset($_POST['stage']) ? sanitize_text_field(wp_unslash($_POST['stage'])) : '';
                if (!empty($stage)) {
                    foreach ($ids as $id) {
                        IDLM_DB::update_lead($id, array('stage' => $stage));
                    }
                    wp_send_json_success(array('message' => 'Successfully updated stages for selected leads.'));
                }
                break;

            case 'bulk_unqualified':
                foreach ($ids as $id) {
                    IDLM_DB::update_lead($id, array('lead_quality' => 'Unqualified'));
                }
                wp_send_json_success(array('message' => 'Successfully marked selected leads as Unqualified.'));
                break;

            case 'bulk_audience_queue':
                $queue = get_option('idlm_audience_sync_queue', array());
                $new_queue = array_unique(array_merge($queue, $ids));
                update_option('idlm_audience_sync_queue', $new_queue);

                foreach ($ids as $id) {
                    IDLM_DB::log_activity($id, 'audience_queue', 'Lead manually added to 2:00 AM custom audience sync queue.', 0);
                }
                wp_send_json_success(array('message' => 'Selected leads added to 2:00 AM audience batch sync queue.'));
                break;

            case 'bulk_delete':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(array('message' => 'Permission denied. Only administrators can delete leads.'));
                }
                foreach ($ids as $id) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->delete($table_leads, array('id' => $id));
                    $wpdb->delete($wpdb->prefix . 'idlm_lead_activity_log', array('lead_id' => $id));
                    $wpdb->delete($wpdb->prefix . 'idlm_capi_logs', array('lead_id' => $id));
                    // phpcs:enable
                }
                wp_send_json_success(array('message' => 'Successfully permanently deleted selected leads.'));
                break;
        }

        wp_send_json_error(array('message' => 'Failed to execute bulk action parameters.'));
    }

    /**
     * AJAX Endpoint: Server-side search, paginate and filter leads table
     */
    public static function ajax_get_leads_table() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        $page = isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $where = array();
        
        // Rep permissions check
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $reps_see_all = get_option('idlm_reps_see_all_leads', '1');

        if (!$is_admin && !$reps_see_all) {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", $current_user_id);
        }

        // Apply filters
        $post_date_range = isset($_POST['date_range']) ? sanitize_key(wp_unslash($_POST['date_range'])) : '';
        if (!empty($post_date_range)) {
            $timezone = wp_timezone();
            $now = new DateTime('now', $timezone);
            if ($post_date_range === 'today') {
                $where[] = $wpdb->prepare("created_at >= %s", $now->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'yesterday') {
                $yesterday = clone $now;
                $yesterday->modify('-1 day');
                $where[] = $wpdb->prepare(
                    "created_at >= %s AND created_at <= %s",
                    $yesterday->format('Y-m-d 00:00:00'),
                    $yesterday->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'week') {
                $start = clone $now;
                $start->modify('monday this week');
                $where[] = $wpdb->prepare("created_at >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'month') {
                $where[] = $wpdb->prepare("created_at >= %s", $now->format('Y-m-01 00:00:00'));
            } elseif ($post_date_range === 'last_7_days') {
                $start = clone $now;
                $start->modify('-7 days');
                $where[] = $wpdb->prepare("created_at >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'last_30_days') {
                $start = clone $now;
                $start->modify('-30 days');
                $where[] = $wpdb->prepare("created_at >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'last_month') {
                $start = clone $now;
                $start->modify('first day of last month');
                $end = clone $now;
                $end->modify('last day of last month');
                $where[] = $wpdb->prepare(
                    "created_at >= %s AND created_at <= %s",
                    $start->format('Y-m-d 00:00:00'),
                    $end->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'year' || $post_date_range === 'this_year') {
                $where[] = $wpdb->prepare("created_at >= %s", $now->format('Y-01-01 00:00:00'));
            }
        }

        $post_source          = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $post_rep_id          = isset($_POST['rep_id']) ? sanitize_text_field(wp_unslash($_POST['rep_id'])) : '';
        $post_stage           = isset($_POST['stage']) ? sanitize_text_field(wp_unslash($_POST['stage'])) : '';
        $post_call_status     = isset($_POST['call_status']) ? sanitize_text_field(wp_unslash($_POST['call_status'])) : '';
        $post_quality         = isset($_POST['quality']) ? sanitize_text_field(wp_unslash($_POST['quality'])) : '';
        $post_followup_filter = isset($_POST['followup_filter']) ? sanitize_text_field(wp_unslash($_POST['followup_filter'])) : '';
        $post_search          = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        if (!empty($post_source)) {
            $where[] = $wpdb->prepare("source = %s", $post_source);
        }

        if ($post_rep_id !== '') {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", intval($post_rep_id));
        }

        if (!empty($post_stage)) {
            $where[] = $wpdb->prepare("stage = %s", $post_stage);
        }

        if (!empty($post_call_status)) {
            $where[] = $wpdb->prepare("call_status = %s", $post_call_status);
        }

        if (!empty($post_quality)) {
            $where[] = $wpdb->prepare("lead_quality = %s", $post_quality);
        }

        if (!empty($post_followup_filter) && $post_followup_filter === 'pending_calls') {
            $statuses = array('Not Called');
            $rules = self::get_reminder_rules();
            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    if (!empty($rule['status'])) {
                        $statuses[] = $rule['status'];
                    }
                }
            }
            $statuses = array_unique($statuses);
            $in_clause = implode("','", array_map('esc_sql', $statuses));
            $where[] = "call_status IN ('{$in_clause}')";
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'overdue') {
            $rules = self::get_reminder_rules();
            $or_clauses = array();
            $now_mysql = current_time('mysql');
            
            foreach ($rules as $rule) {
                $status = $rule['status'];
                $hours = intval($rule['hours']);
                
                if ($status === 'Call Back Requested') {
                    $or_clauses[] = $wpdb->prepare(
                        "(call_status = %s AND (
                            (callback_datetime IS NOT NULL AND callback_datetime < DATE_SUB(%s, INTERVAL %d HOUR)) OR
                            (callback_datetime IS NULL AND COALESCE(updated_at, created_at) < DATE_SUB(%s, INTERVAL %d HOUR))
                        ))",
                        $status, $now_mysql, $hours, $now_mysql, $hours
                    );
                } else {
                    $or_clauses[] = $wpdb->prepare(
                        "(call_status = %s AND COALESCE(updated_at, created_at) < DATE_SUB(%s, INTERVAL %d HOUR))",
                        $status, $now_mysql, $hours
                    );
                }
            }
            
            if (!empty($or_clauses)) {
                $where[] = "(" . implode(" OR ", $or_clauses) . ")";
            } else {
                $where[] = "1=0";
            }
        }

        if (!empty($post_search)) {
            $search = '%' . $wpdb->esc_like($post_search) . '%';
            $where[] = $wpdb->prepare("(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)", $search, $search, $search, $search);
        }

        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Query leads
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $total_items = intval($wpdb->get_var("SELECT COUNT(id) FROM {$table_leads} {$where_clause}"));
        $total_pages = ceil($total_items / $limit);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as rep_name 
             FROM {$table_leads} l 
             LEFT JOIN {$wpdb->users} u ON l.assigned_rep_id = u.ID 
             {$where_clause} ORDER BY l.id DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ));

        // Get config data for inline edit options
        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $call_statuses = get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number'));
        $reps = get_users(array(
            'role__in' => self::get_allowed_rep_roles(),
            'fields'   => array('ID', 'display_name')
        ));
        $active_rep_ids = get_option('idlm_active_assignment_reps', array());
        $reminder_rules = self::get_reminder_rules();

        ob_start();
        if (empty($leads)) {
            echo '<tr><td colspan="8" style="text-align:center;">No leads found matching your active filters.</td></tr>';
        } else {
            foreach ($leads as $l) {
                $name = esc_html($l->first_name . ' ' . $l->last_name);
                if (empty($name)) {
                    $name = 'Unnamed Lead';
                }
                $dup_badge = $l->is_duplicate ? '<span class="idlm-badge idlm-badge-warning" style="margin-left:5px; font-size:9px; padding:2px 6px;">DUP</span>' : '';
                ?>
                <tr data-id="<?php echo esc_attr($l->id); ?>" class="<?php echo $l->is_duplicate ? 'idlm-duplicate-row' : ''; ?>">
                    <td><input type="checkbox" class="idlm-row-selector" value="<?php echo esc_attr($l->id); ?>"></td>
                    <td onclick="openLeadDrawer(<?php echo esc_attr($l->id); ?>)">
                        <strong><?php echo esc_html($name); ?></strong> <?php echo wp_kses_post($dup_badge); ?>
                        <div style="font-size:11px; color:#666; margin-top:2px;"><?php echo esc_html($l->email); ?> | <?php echo esc_html($l->phone); ?></div>
                    </td>
                    <td><span class="idlm-badge idlm-badge-<?php echo esc_attr(strtolower(str_replace(' ', '', $l->source))); ?>"><?php echo esc_html($l->source); ?></span></td>
                    <td><strong><?php echo !empty($l->budget) ? esc_html($l->budget) : 'N/A'; ?></strong></td>
                    
                    <!-- Call Status Quick Edit dropdown -->
                    <td>
                        <select class="idlm-table-select idlm-quick-edit" data-field="call_status" data-id="<?php echo esc_attr($l->id); ?>">
                            <?php foreach ($call_statuses as $cs): ?>
                                <option value="<?php echo esc_attr($cs); ?>" <?php selected($l->call_status, $cs); ?>><?php echo esc_html($cs); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php 
                        $overdue_info = self::check_lead_overdue_status($l, $reminder_rules);
                        if ($overdue_info && $overdue_info['overdue']):
                        ?>
                            <div class="idlm-overdue-alert" title="<?php echo esc_attr($overdue_info['reason']); ?>" style="font-size: 10px; color: #ef4444; margin-top: 4px; display: flex; align-items: center; gap: 3px;">
                                <span style="display:inline-block; width:6px; height:6px; background:#ef4444; border-radius:50%; animation: idlm-pulse 1.5s infinite;"></span>
                                Overdue by <?php echo esc_html($overdue_info['hours_overdue']); ?>h
                            </div>
                        <?php endif; ?>
                    </td>
 
                    <!-- Stage Quick Edit dropdown -->
                    <td>
                        <select class="idlm-table-select idlm-quick-edit" data-field="stage" data-id="<?php echo esc_attr($l->id); ?>">
                            <?php foreach ($stages as $st): ?>
                                <option value="<?php echo esc_attr($st); ?>" <?php selected($l->stage, $st); ?>><?php echo esc_html($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
 
                    <!-- Rep Quick Edit dropdown -->
                    <td>
                        <?php if ($is_admin || $reps_see_all): ?>
                            <select class="idlm-table-select idlm-quick-edit" data-field="assigned_rep_id" data-id="<?php echo esc_attr($l->id); ?>">
                                <option value="0">Unassigned</option>
                                <?php foreach ($reps as $rep): 
                                    if (!in_array($rep->ID, $active_rep_ids) && $l->assigned_rep_id != $rep->ID) {
                                        continue;
                                    }
                                ?>
                                    <option value="<?php echo esc_attr($rep->ID); ?>" <?php selected($l->assigned_rep_id, $rep->ID); ?>><?php echo esc_html($rep->display_name); ?></option>
                                  <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?php 
                            $assigned = !empty($l->rep_name) ? $l->rep_name : 'Unassigned';
                            echo esc_html($assigned);
                            ?>
                        <?php endif; ?>
                    </td>
 
                    <!-- Lead Quality dropdown -->
                    <td>
                        <select class="idlm-table-select idlm-quick-edit" data-field="lead_quality" data-id="<?php echo esc_attr($l->id); ?>">
                            <option value="" <?php selected($l->lead_quality, ''); ?>>Choose...</option>
                            <option value="Qualified" <?php selected($l->lead_quality, 'Qualified'); ?>>✅ Qualified</option>
                            <option value="Unqualified" <?php selected($l->lead_quality, 'Unqualified'); ?>>❌ Unqualified</option>
                        </select>
                    </td>
 
                    <td><?php echo esc_html(self::format_datetime($l->created_at)); ?></td>
                </tr>
                <?php
            }
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'total_pages' => max(1, $total_pages),
            'current_page' => $page,
            'pending_calls_count' => self::get_pending_calls_count()
        ));
    }

    /**
     * AJAX Endpoint: Fetch JSON dataset for dashboard widgets & ChartJS
     */
    public static function ajax_get_dashboard_stats() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $period = isset($_POST['period']) ? sanitize_key(wp_unslash($_POST['period'])) : 'this_month';
        $custom_start = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $custom_end = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

        $stats = IDLM_Dashboard::get_stats($period, $custom_start, $custom_end);

        wp_send_json_success($stats);
    }

    /**
     * AJAX Endpoint: Save complete Lead details form changes
     */
    public static function ajax_save_lead_details() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $form_raw = isset($_POST['form_data']) ? wp_unslash($_POST['form_data']) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        parse_str($form_raw, $form_fields);

        $lead_id = isset($form_fields['lead_id']) ? intval($form_fields['lead_id']) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }

        $data = array(
            'first_name' => sanitize_text_field($form_fields['first_name']),
            'last_name'  => sanitize_text_field($form_fields['last_name']),
            'email'      => sanitize_email($form_fields['email']),
            'phone'      => sanitize_text_field($form_fields['phone']),
            'budget'     => isset($form_fields['budget']) ? IDLM_Integrations::normalize_budget(sanitize_text_field($form_fields['budget'])) : '',
            'deal_value' => isset($form_fields['deal_value']) ? floatval($form_fields['deal_value']) : 0.00,
            'project_timeline' => isset($form_fields['project_timeline']) ? sanitize_text_field($form_fields['project_timeline']) : '',
            'property_location' => isset($form_fields['property_location']) ? sanitize_text_field($form_fields['property_location']) : '',
            'lead_quality' => sanitize_text_field($form_fields['lead_quality']),
            'property_type' => sanitize_text_field($form_fields['property_type']),
            'num_rooms' => IDLM_Integrations::normalize_num_rooms(sanitize_text_field($form_fields['num_rooms'])),
            'floor_plan_received' => intval($form_fields['floor_plan_received']),
            'floor_plan_file' => isset($form_fields['floor_plan_file']) ? esc_url_raw($form_fields['floor_plan_file']) : '',
            'quote_shared' => intval($form_fields['quote_shared']),
            'quote_file' => isset($form_fields['quote_file']) ? esc_url_raw($form_fields['quote_file']) : '',
            'preferred_studio' => sanitize_text_field($form_fields['preferred_studio'])
        );

        if (isset($form_fields['referrer_details'])) {
            $data['referrer_details'] = sanitize_textarea_field($form_fields['referrer_details']);
        }
        if (isset($form_fields['callback_datetime'])) {
            $callback_dt = sanitize_text_field($form_fields['callback_datetime']);
            $data['callback_datetime'] = !empty($callback_dt) ? str_replace('T', ' ', $callback_dt) : null;
        }

        $updated = IDLM_DB::update_lead($lead_id, $data);
        if ($updated !== false) {
            wp_send_json_success(array(
                'message' => 'Lead details saved successfully!',
                'pending_calls_count' => self::get_pending_calls_count()
            ));
        }

        wp_send_json_error(array('message' => 'Failed to save lead updates. Database error.'));
    }

    /**
     * AJAX Endpoint: Upload lead file directly via standard HTML5 file uploader
     */
    public static function ajax_upload_lead_file() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }

        // Use WordPress core functions to handle the upload
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Let WordPress handle the upload as an attachment
        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        $file_url = wp_get_attachment_url($attachment_id);

        if (!$file_url) {
            wp_send_json_error(array('message' => 'Failed to get uploaded file URL.'));
        }

        wp_send_json_success(array(
            'url' => $file_url,
            'attachment_id' => $attachment_id
        ));
    }

    /**
     * AJAX Endpoint: Get the total number of leads in the database for reset verification
     */
    public static function ajax_get_total_leads_count() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can perform this action.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $total_leads = intval($wpdb->get_var("SELECT COUNT(id) FROM {$table_leads}"));

        wp_send_json_success(array('count' => $total_leads));
    }

    /**
     * AJAX Endpoint: Reset all CRM lead tables & analytics
     */
    public static function ajax_reset_crm_data() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can reset data.'));
        }

        $input_count = isset($_POST['confirm_count']) ? sanitize_text_field(wp_unslash($_POST['confirm_count'])) : '';

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        $table_cost = $wpdb->prefix . 'idlm_cost_per_lead';
        $table_audience_logs = $wpdb->prefix . 'idlm_audience_sync_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $actual_count = intval($wpdb->get_var("SELECT COUNT(id) FROM {$table_leads}"));

        if (strval($input_count) !== strval($actual_count)) {
            wp_send_json_error(array('message' => 'Verification failed. The entered count does not match the actual number of leads.'));
        }

        // Truncate tables to delete all leads & reset analytics
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("TRUNCATE TABLE {$table_leads}");
        $wpdb->query("TRUNCATE TABLE {$table_activity}");
        $wpdb->query("TRUNCATE TABLE {$table_capi}");
        $wpdb->query("TRUNCATE TABLE {$table_cost}");
        $wpdb->query("TRUNCATE TABLE {$table_audience_logs}");
        // phpcs:enable

        wp_send_json_success(array('message' => 'CRM leads database and analytics have been successfully reset to starting state!'));
    }

    /**
     * AJAX Endpoint: Delete a single lead and associated activity/CAPI logs (Admin only)
     */
    public static function ajax_delete_lead() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can delete leads.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        if ($lead_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid lead ID.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // Delete from leads table
        $deleted = $wpdb->delete($table_leads, array('id' => $lead_id));
        if ($deleted) {
            // Cleanup activity logs and CAPI logs
            $wpdb->delete($table_activity, array('lead_id' => $lead_id));
            $wpdb->delete($table_capi, array('lead_id' => $lead_id));
            // phpcs:enable
            wp_send_json_success(array('message' => 'Lead successfully permanently deleted.'));
        }

        wp_send_json_error(array('message' => 'Failed to delete lead from database.'));
    }

    /**
     * AJAX Endpoint: Import a batch of leads from CSV
     */
    public static function ajax_import_leads_batch() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $leads = isset($_POST['leads']) ? (array) wp_unslash($_POST['leads']) : array();
        if (empty($leads)) {
            wp_send_json_error(array('message' => 'No lead records received in this batch.'));
        }

        $default_source = isset($_POST['default_source']) ? sanitize_text_field(wp_unslash($_POST['default_source'])) : 'CSV Import';
        $default_stage = isset($_POST['default_stage']) ? sanitize_text_field(wp_unslash($_POST['default_stage'])) : 'New';
        $default_call_status = isset($_POST['default_call_status']) ? sanitize_text_field(wp_unslash($_POST['default_call_status'])) : 'Not Called';
        $run_routing = !empty($_POST['run_routing']) ? true : false;
        $run_capi = !empty($_POST['run_capi']) ? true : false;

        $imported = 0;
        $duplicates = 0;
        $failed = 0;

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        foreach ($leads as $lead) {
            $first_name = isset($lead['first_name']) ? sanitize_text_field($lead['first_name']) : '';
            $phone = isset($lead['phone']) ? sanitize_text_field($lead['phone']) : '';

            // First Name and Phone are required for database schema integrity
            if (empty($first_name) || empty($phone)) {
                $failed++;
                continue;
            }

            $lead_data = array(
                'first_name' => $first_name,
                'last_name' => isset($lead['last_name']) ? sanitize_text_field($lead['last_name']) : '',
                'email' => isset($lead['email']) ? sanitize_email($lead['email']) : '',
                'phone' => $phone,
                'budget' => isset($lead['budget']) ? IDLM_Integrations::normalize_budget(sanitize_text_field($lead['budget'])) : '',
                'project_timeline' => isset($lead['project_timeline']) ? sanitize_text_field($lead['project_timeline']) : '',
                'property_location' => isset($lead['property_location']) ? sanitize_text_field($lead['property_location']) : '',
                'source' => !empty($lead['source']) ? sanitize_text_field($lead['source']) : $default_source,
                'property_type' => isset($lead['property_type']) ? sanitize_text_field($lead['property_type']) : '',
                'num_rooms' => isset($lead['num_rooms']) ? IDLM_Integrations::normalize_num_rooms(sanitize_text_field($lead['num_rooms'])) : '',
                'preferred_studio' => isset($lead['preferred_studio']) ? sanitize_text_field($lead['preferred_studio']) : '',
                'lead_quality' => isset($lead['lead_quality']) ? sanitize_text_field($lead['lead_quality']) : '',
                'stage' => !empty($lead['stage']) ? sanitize_text_field($lead['stage']) : $default_stage,
                'call_status' => !empty($lead['call_status']) ? sanitize_text_field($lead['call_status']) : $default_call_status,
            );

            if (isset($lead['floor_plan_received'])) {
                $lead_data['floor_plan_received'] = intval($lead['floor_plan_received']);
            }
            if (isset($lead['floor_plan_file'])) {
                $lead_data['floor_plan_file'] = esc_url_raw($lead['floor_plan_file']);
                if (!empty($lead_data['floor_plan_file']) && empty($lead_data['floor_plan_received'])) {
                    $lead_data['floor_plan_received'] = 1;
                }
            }

            if (isset($lead['quote_shared'])) {
                $lead_data['quote_shared'] = intval($lead['quote_shared']);
            }
            if (isset($lead['quote_file'])) {
                $lead_data['quote_file'] = esc_url_raw($lead['quote_file']);
                if (!empty($lead_data['quote_file']) && empty($lead_data['quote_shared'])) {
                    $lead_data['quote_shared'] = 1;
                }
            }

            // Sanitize tracking click-IDs and UTM attribution strings
            $utm_keys = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'fb_lead_id', 'li_fat_id');
            foreach ($utm_keys as $ukey) {
                if (isset($lead[$ukey])) {
                    $lead_data[$ukey] = sanitize_text_field($lead[$ukey]);
                }
            }

            $lead_id = IDLM_DB::insert_lead($lead_data);

            if ($lead_id) {
                // Determine duplicate status
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
                $is_duplicate = $wpdb->get_var($wpdb->prepare("SELECT is_duplicate FROM {$table_leads} WHERE id = %d", $lead_id));
                if ($is_duplicate) {
                    $duplicates++;
                } else {
                    $imported++;
                }

                // Apply dynamic routing assignments if checked
                if ($run_routing) {
                    IDLM_Integrations::route_lead_assignment($lead_id, $lead_data['source']);
                }

                // Trigger server-side Conversion API events if stage matches conversion hooks or quality is Qualified
                if ($run_capi && (in_array($lead_data['stage'], array('Qualified', 'Quote Shared', 'Won')) || $lead_data['lead_quality'] === 'Qualified')) {
                    $capi_stage = in_array($lead_data['stage'], array('Qualified', 'Quote Shared', 'Won')) ? $lead_data['stage'] : 'Qualified';
                    // Triggers Action Scheduler background conversion CAPI enqueuing automatically
                    do_action('idlm_lead_stage_converted', $lead_id, $capi_stage, (object) array('stage' => ''));
                }
            } else {
                $failed++;
            }
        }

        wp_send_json_success(array(
            'imported' => $imported,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'message' => 'Batch completed successfully'
        ));
    }
}

