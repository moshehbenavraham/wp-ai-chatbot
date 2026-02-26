<?php
/**
 * Appointments Admin Settings Page
 * 
 * Renders the Appointments admin page with tabs for listing, calendar, and settings.
 * 
 * @package AIChat
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render the main Appointments admin page
 */
function aichat_appointments_render_page() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page navigation.
    $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
    $usage = AIChat_Appointments_Manager::get_usage_info();
    ?>
    <div class="wrap aichat-appointments-wrap">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-calendar-alt" style="color:#2271b1"></span>
            <?php esc_html_e( 'Appointments', 'axiachat-ai' ); ?>
        </h1>
        <p class="description mb-3"><?php esc_html_e( 'Manage appointments booked through AI chat conversations.', 'axiachat-ai' ); ?></p>
        
        <?php if ( ! $usage['is_pro'] ) : ?>
        <!-- Usage Limit Banner -->
        <div class="alert <?php echo $usage['remaining'] <= 3 ? 'alert-warning' : 'alert-info'; ?> mb-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="bi <?php echo $usage['remaining'] <= 3 ? 'bi-exclamation-triangle-fill' : 'bi-calendar-check'; ?> me-2 fs-5"></i>
                <div>
                    <strong><?php esc_html_e( 'Free Plan', 'axiachat-ai' ); ?>:</strong>
                    <?php 
                    printf(
                        /* translators: 1: appointments used, 2: appointment limit */
                        esc_html__( '%1$d of %2$d appointments used', 'axiachat-ai' ),
                        intval( $usage['count'] ),
                        intval( $usage['limit'] )
                    );
                    ?>
                    <span class="text-muted ms-2">(<?php echo esc_html( $usage['remaining'] ); ?> <?php esc_html_e( 'remaining', 'axiachat-ai' ); ?>)</span>
                    <?php if ( $usage['remaining'] <= 3 && $usage['remaining'] > 0 ) : ?>
                        <span class="badge bg-warning text-dark ms-2"><?php esc_html_e( 'Running low!', 'axiachat-ai' ); ?></span>
                    <?php elseif ( $usage['remaining'] === 0 ) : ?>
                        <span class="badge bg-danger ms-2"><?php esc_html_e( 'Limit reached', 'axiachat-ai' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-settings-pricing' ) ); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-rocket me-1"></i><?php esc_html_e( 'Upgrade to Pro', 'axiachat-ai' ); ?>
            </a>
        </div>
        
        <!-- Progress Bar -->
        <div class="progress mb-4" style="height: 8px;">
            <div class="progress-bar <?php echo $usage['percent'] >= 80 ? 'bg-warning' : 'bg-primary'; ?> <?php echo $usage['percent'] >= 100 ? 'bg-danger' : ''; ?>" 
                 role="progressbar" 
                 style="width: <?php echo esc_attr( $usage['percent'] ); ?>%"
                 aria-valuenow="<?php echo esc_attr( $usage['count'] ); ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="<?php echo esc_attr( $usage['limit'] ); ?>">
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'settings' ? 'active' : ''; ?>" 
                   href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-appointments&tab=settings' ) ); ?>">
                    <i class="bi bi-gear me-1"></i><?php esc_html_e( 'Settings', 'axiachat-ai' ); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'list' ? 'active' : ''; ?>" 
                   href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-appointments&tab=list' ) ); ?>">
                    <i class="bi bi-list-ul me-1"></i><?php esc_html_e( 'List', 'axiachat-ai' ); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'calendar' ? 'active' : ''; ?>" 
                   href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-appointments&tab=calendar' ) ); ?>">
                    <i class="bi bi-calendar3 me-1"></i><?php esc_html_e( 'Calendar', 'axiachat-ai' ); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab === 'help' ? 'active' : ''; ?>" 
                   href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-appointments&tab=help' ) ); ?>">
                    <i class="bi bi-question-circle me-1"></i><?php esc_html_e( 'Help', 'axiachat-ai' ); ?>
                </a>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <?php
            switch ( $tab ) {
                case 'calendar':
                    aichat_appointments_render_calendar_tab();
                    break;
                case 'settings':
                    aichat_appointments_render_settings_tab();
                    break;
                case 'help':
                    aichat_appointments_render_help_tab();
                    break;
                default:
                    aichat_appointments_render_list_tab();
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Render List Tab
 */
function aichat_appointments_render_list_tab() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page filters and pagination.
    $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
    $per_page = 20;
    
    $appointments = AIChat_Appointments_Manager::get_list( [
        'search'    => $search,
        'status'    => $status_filter,
        'date_from' => $date_from,
        'date_to'   => $date_to,
        'limit'     => $per_page,
        'offset'    => ( $paged - 1 ) * $per_page,
    ] );
    ?>
    
    <!-- Filters -->
    <div class="card shadow-sm mb-4 card100">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="aichat-appointments">
                <input type="hidden" name="tab" value="list">
                
                <div class="col-md-3">
                    <label class="form-label small"><?php esc_html_e( 'Search', 'axiachat-ai' ); ?></label>
                    <input type="text" name="s" class="form-control form-control-sm" 
                           placeholder="<?php esc_attr_e( 'Name, email, or code...', 'axiachat-ai' ); ?>"
                           value="<?php echo esc_attr( $search ); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small"><?php esc_html_e( 'Status', 'axiachat-ai' ); ?></label>
                    <select name="status" class="form-select form-select-sm">
                        <option value=""><?php esc_html_e( 'All', 'axiachat-ai' ); ?></option>
                        <option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'axiachat-ai' ); ?></option>
                        <option value="confirmed" <?php selected( $status_filter, 'confirmed' ); ?>><?php esc_html_e( 'Confirmed', 'axiachat-ai' ); ?></option>
                        <option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php esc_html_e( 'Completed', 'axiachat-ai' ); ?></option>
                        <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'axiachat-ai' ); ?></option>
                        <option value="no_show" <?php selected( $status_filter, 'no_show' ); ?>><?php esc_html_e( 'No Show', 'axiachat-ai' ); ?></option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small"><?php esc_html_e( 'From', 'axiachat-ai' ); ?></label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo esc_attr( $date_from ); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small"><?php esc_html_e( 'To', 'axiachat-ai' ); ?></label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo esc_attr( $date_to ); ?>">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i><?php esc_html_e( 'Filter', 'axiachat-ai' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-appointments&tab=list' ) ); ?>" class="btn btn-outline-secondary btn-sm">
                        <?php esc_html_e( 'Reset', 'axiachat-ai' ); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Appointments Table -->
    <div class="card shadow-sm card100 appointment-list-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?php esc_html_e( 'Code', 'axiachat-ai' ); ?></th>
                        <th><?php esc_html_e( 'Customer', 'axiachat-ai' ); ?></th>
                        <th><?php esc_html_e( 'Date & Time', 'axiachat-ai' ); ?></th>
                        <th><?php esc_html_e( 'Service', 'axiachat-ai' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'axiachat-ai' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'axiachat-ai' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $appointments ) ) : ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                <?php esc_html_e( 'No appointments found.', 'axiachat-ai' ); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $appointments as $apt ) : ?>
                            <?php
                            $status_class = [
                                'pending'   => 'warning',
                                'confirmed' => 'success',
                                'completed' => 'secondary',
                                'cancelled' => 'danger',
                                'no_show'   => 'dark',
                            ][ $apt->status ] ?? 'secondary';
                            
                            $date_formatted = date_i18n( get_option( 'date_format' ), strtotime( $apt->appointment_date ) );
                            $time_formatted = date_i18n( 'H:i', strtotime( $apt->start_time ) ) . ' - ' . date_i18n( 'H:i', strtotime( $apt->end_time ) );
                            $is_past = strtotime( $apt->appointment_date . ' ' . $apt->start_time ) < time();
                            ?>
                            <tr class="<?php echo $is_past && $apt->status !== 'completed' && $apt->status !== 'cancelled' ? 'table-light' : ''; ?>">
                                <td>
                                    <code class="small"><?php echo esc_html( $apt->booking_code ); ?></code>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $apt->customer_name ); ?></strong><br>
                                    <small class="text-muted">
                                        <a href="mailto:<?php echo esc_attr( $apt->customer_email ); ?>"><?php echo esc_html( $apt->customer_email ); ?></a>
                                        <?php if ( $apt->customer_phone ) : ?>
                                            <br><?php echo esc_html( $apt->customer_phone ); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $date_formatted ); ?></strong><br>
                                    <small class="text-muted"><?php echo esc_html( $time_formatted ); ?></small>
                                </td>
                                <td><?php echo esc_html( $apt->service ?: '—' ); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo esc_attr( $status_class ); ?>">
                                        <?php echo esc_html( ucfirst( $apt->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-view-appointment" 
                                                data-id="<?php echo esc_attr( $apt->id ); ?>"
                                                title="<?php esc_attr_e( 'View', 'axiachat-ai' ); ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-edit-appointment"
                                                data-id="<?php echo esc_attr( $apt->id ); ?>"
                                                title="<?php esc_attr_e( 'Edit', 'axiachat-ai' ); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ( $apt->status === 'pending' ) : ?>
                                            <button type="button" class="btn btn-outline-success btn-confirm-appointment"
                                                    data-id="<?php echo esc_attr( $apt->id ); ?>"
                                                    title="<?php esc_attr_e( 'Confirm', 'axiachat-ai' ); ?>">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ( $apt->status === 'confirmed' ) : ?>
                                            <button type="button" class="btn btn-outline-secondary btn-complete-appointment"
                                                    data-id="<?php echo esc_attr( $apt->id ); ?>"
                                                    title="<?php esc_attr_e( 'Mark Completed', 'axiachat-ai' ); ?>">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-dark btn-noshow-appointment"
                                                    data-id="<?php echo esc_attr( $apt->id ); ?>"
                                                    title="<?php esc_attr_e( 'No Show', 'axiachat-ai' ); ?>">
                                                <i class="bi bi-person-x"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ( $apt->status !== 'cancelled' && $apt->status !== 'completed' ) : ?>
                                            <button type="button" class="btn btn-outline-danger btn-cancel-appointment"
                                                    data-id="<?php echo esc_attr( $apt->id ); ?>"
                                                    title="<?php esc_attr_e( 'Cancel', 'axiachat-ai' ); ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Render Calendar Tab
 */
function aichat_appointments_render_calendar_tab() {
    ?>
    <div class="card shadow-sm card100">
        <div class="card-body p-4">
            <div id="aichat-appointments-calendar" class="calendar-container">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?php esc_html_e( 'Loading...', 'axiachat-ai' ); ?></span>
                    </div>
                    <p class="mt-3 text-muted"><?php esc_html_e( 'Loading calendar...', 'axiachat-ai' ); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Handle Google Calendar OAuth callback
 */
add_action( 'admin_init', 'aichat_appointments_gcal_oauth_callback', 5 );
function aichat_appointments_gcal_oauth_callback() {
    // Check if this is a Google Calendar OAuth callback
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing check; no state change.
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'aichat-appointments' ) {
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing check; no state change.
    if ( ! isset( $_GET['gcal_callback'] ) || $_GET['gcal_callback'] !== '1' ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Check for authorization code
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google cannot include nonce.
    if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {
        $oauth = new AIChat_GCal_OAuth();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback; values sanitized below.
        $result = $oauth->exchange_code( 
            sanitize_text_field( wp_unslash( $_GET['code'] ) ), 
            sanitize_text_field( wp_unslash( $_GET['state'] ) ) 
        );
        
        if ( is_wp_error( $result ) ) {
            add_settings_error(
                'aichat_appointments',
                'gcal_error',
                /* translators: %s: Error message from Google Calendar connection */
                sprintf( __( 'Google Calendar connection failed: %s', 'axiachat-ai' ), $result->get_error_message() ),
                'error'
            );
        } else {
            add_settings_error(
                'aichat_appointments',
                'gcal_success',
                /* translators: %s: Google account email address */
                sprintf( __( 'Successfully connected to Google Calendar as %s', 'axiachat-ai' ), $result['email'] ?? '' ),
                'success'
            );
        }
        
        // Redirect to remove query params
        wp_safe_redirect( admin_url( 'admin.php?page=aichat-appointments&tab=settings' ) );
        exit;
    }
    
    // Check for error from Google
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth error response from Google cannot include nonce.
    if ( isset( $_GET['error'] ) ) {
        add_settings_error(
            'aichat_appointments',
            'gcal_error',
            /* translators: %s: Error code from Google OAuth */
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth error from Google; sanitized inline.
            sprintf( __( 'Google Calendar authorization denied: %s', 'axiachat-ai' ), sanitize_text_field( wp_unslash( $_GET['error'] ) ) ),
            'error'
        );
    }
}

/**
 * Render Settings Tab
 */
function aichat_appointments_render_settings_tab() {
    // Show any settings errors/notices
    settings_errors( 'aichat_appointments' );
    
    $settings = AIChat_Appointments_Manager::get_settings();
    $is_pro = AIChat_Appointments_Manager::has_pro_license();
    $has_integrations = AIChat_Appointments_Manager::has_integrations_license();
    $days = [
        1 => __( 'Monday', 'axiachat-ai' ),
        2 => __( 'Tuesday', 'axiachat-ai' ),
        3 => __( 'Wednesday', 'axiachat-ai' ),
        4 => __( 'Thursday', 'axiachat-ai' ),
        5 => __( 'Friday', 'axiachat-ai' ),
        6 => __( 'Saturday', 'axiachat-ai' ),
        0 => __( 'Sunday', 'axiachat-ai' ),
    ];
    
    // External integrations availability
    $bookly_available = class_exists( 'Bookly\Lib\Plugin' );
    $amelia_available = defined( 'AMELIA_VERSION' );
    $ssa_available = function_exists( 'ssa' ) || class_exists( 'Simply_Schedule_Appointments' );
    
    // PRO destinations (all except internal)
    $pro_destinations = [ 'bookly', 'amelia', 'ssa', 'google_calendar' ];
    ?>
    <form id="appointments-settings-form" method="post">
        <?php wp_nonce_field( 'aichat_appointments_settings', 'appointments_nonce' ); ?>
        
        <!-- Destination Card - Full Width First -->
        <div class="card shadow-sm mb-4 card100 destination-card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-signpost-2 me-2"></i>
                <strong><?php esc_html_e( 'Appointment Destination', 'axiachat-ai' ); ?></strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    <?php esc_html_e( 'Choose where your appointments will be stored. The internal calendar is managed within this plugin, while external options integrate with popular booking plugins.', 'axiachat-ai' ); ?>
                </p>
                
                <div class="row g-4">
                    <!-- Internal Calendar -->
                    <div class="col-md-3">
                        <label class="destination-option d-block h-100 <?php echo $settings['destination'] === 'internal' ? 'active' : ''; ?>">
                            <input type="radio" name="destination" value="internal" class="d-none" 
                                   <?php checked( $settings['destination'], 'internal' ); ?>>
                            <i class="bi bi-calendar-check icon text-primary"></i>
                            <span class="title"><?php esc_html_e( 'Internal Calendar', 'axiachat-ai' ); ?></span>
                            <span class="description">
                                <?php esc_html_e( 'Store appointments in the built-in calendar. Configure working hours below.', 'axiachat-ai' ); ?>
                            </span>
                            <span class="badge bg-success mt-2"><?php esc_html_e( 'Recommended', 'axiachat-ai' ); ?></span>
                        </label>
                    </div>
                    
                    <!-- Bookly -->
                    <?php 
                    $bookly_locked = ! $has_integrations;
                    $bookly_disabled = ! $bookly_available || $bookly_locked;
                    ?>
                    <div class="col-md-3">
                        <label class="destination-option d-block h-100 <?php echo $settings['destination'] === 'bookly' ? 'active' : ''; ?> <?php echo $bookly_disabled ? 'disabled' : ''; ?>" style="position: relative;">
                            <?php if ( $bookly_locked ) : ?>
                                <span class="position-absolute top-0 end-0 m-2 badge bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.65rem; z-index: 10;">
                                    <i class="bi bi-star-fill me-1"></i>Standard+
                                </span>
                            <?php endif; ?>
                            <input type="radio" name="destination" value="bookly" class="d-none"
                                   <?php checked( $settings['destination'], 'bookly' ); ?>
                                   <?php disabled( $bookly_disabled ); ?>>
                            <i class="bi bi-calendar2-week icon text-info"></i>
                            <span class="title">Bookly</span>
                            <span class="description">
                                <?php esc_html_e( 'Sync with Bookly plugin for staff management and advanced scheduling.', 'axiachat-ai' ); ?>
                            </span>
                            <?php if ( ! $bookly_available && ! $bookly_locked ) : ?>
                                <span class="badge bg-secondary mt-2"><?php esc_html_e( 'Not Installed', 'axiachat-ai' ); ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <!-- Amelia -->
                    <?php 
                    $amelia_locked = ! $has_integrations;
                    $amelia_disabled = ! $amelia_available || $amelia_locked;
                    ?>
                    <div class="col-md-3">
                        <label class="destination-option d-block h-100 <?php echo $settings['destination'] === 'amelia' ? 'active' : ''; ?> <?php echo $amelia_disabled ? 'disabled' : ''; ?>" style="position: relative;">
                            <?php if ( $amelia_locked ) : ?>
                                <span class="position-absolute top-0 end-0 m-2 badge bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.65rem; z-index: 10;">
                                    <i class="bi bi-star-fill me-1"></i>Standard+
                                </span>
                            <?php endif; ?>
                            <input type="radio" name="destination" value="amelia" class="d-none"
                                   <?php checked( $settings['destination'], 'amelia' ); ?>
                                   <?php disabled( $amelia_disabled ); ?>>
                            <i class="bi bi-calendar3-range icon text-success"></i>
                            <span class="title">Amelia</span>
                            <span class="description">
                                <?php esc_html_e( 'Integrate with Amelia for enterprise booking features.', 'axiachat-ai' ); ?>
                            </span>
                            <?php if ( ! $amelia_available && ! $amelia_locked ) : ?>
                                <span class="badge bg-secondary mt-2"><?php esc_html_e( 'Not Installed', 'axiachat-ai' ); ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <!-- Simply Schedule Appointments (SSA) -->
                    <?php 
                    $ssa_locked = ! $has_integrations;
                    $ssa_disabled = ! $ssa_available || $ssa_locked;
                    ?>
                    <div class="col-md-3">
                        <label class="destination-option d-block h-100 <?php echo $settings['destination'] === 'ssa' ? 'active' : ''; ?> <?php echo $ssa_disabled ? 'disabled' : ''; ?>" style="position: relative;">
                            <?php if ( $ssa_locked ) : ?>
                                <span class="position-absolute top-0 end-0 m-2 badge bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.65rem; z-index: 10;">
                                    <i class="bi bi-star-fill me-1"></i>Standard+
                                </span>
                            <?php endif; ?>
                            <input type="radio" name="destination" value="ssa" class="d-none"
                                   <?php checked( $settings['destination'], 'ssa' ); ?>
                                   <?php disabled( $ssa_disabled ); ?>>
                            <i class="bi bi-calendar-event icon text-warning"></i>
                            <span class="title">SSA</span>
                            <span class="description">
                                <?php esc_html_e( 'Integrate with Simply Schedule Appointments.', 'axiachat-ai' ); ?>
                            </span>
                            <?php if ( ! $ssa_available && ! $ssa_locked ) : ?>
                                <span class="badge bg-secondary mt-2"><?php esc_html_e( 'Not Installed', 'axiachat-ai' ); ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <!-- Google Calendar -->
                    <?php $gcal_locked = ! $has_integrations; ?>
                    <div class="col-md-3">
                        <label class="destination-option d-block h-100 <?php echo $settings['destination'] === 'google_calendar' ? 'active' : ''; ?> <?php echo $gcal_locked ? 'disabled' : ''; ?>" style="position: relative;">
                            <?php if ( $gcal_locked ) : ?>
                                <span class="position-absolute top-0 end-0 m-2 badge bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.65rem; z-index: 10;">
                                    <i class="bi bi-star-fill me-1"></i>Standard+
                                </span>
                            <?php endif; ?>
                            <input type="radio" name="destination" value="google_calendar" class="d-none"
                                   <?php checked( $settings['destination'], 'google_calendar' ); ?>
                                   <?php disabled( $gcal_locked ); ?>>
                            <i class="bi bi-google icon text-danger"></i>
                            <span class="title">Google Calendar</span>
                            <span class="description">
                                <?php esc_html_e( 'Sync appointments with your Google Calendar.', 'axiachat-ai' ); ?>
                            </span>
                            <?php if ( ! $gcal_locked ) : ?>
                                <span class="badge bg-info mt-2"><?php esc_html_e( 'Cloud Sync', 'axiachat-ai' ); ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
                
                <?php if ( ! $is_pro ) : ?>
                <div class="alert alert-info mt-4 mb-0 d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-info-circle me-2"></i>
                        <strong><?php esc_html_e( 'Free Plan:', 'axiachat-ai' ); ?></strong>
                        <?php esc_html_e( 'Internal Calendar only. Upgrade to Starter for unlimited appointments, or Standard for Bookly, Amelia, SSA, Google Calendar integrations.', 'axiachat-ai' ); ?>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=axiachat-ai-pricing' ) ); ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-rocket me-1"></i><?php esc_html_e( 'Upgrade', 'axiachat-ai' ); ?>
                    </a>
                </div>
                <?php elseif ( ! $has_integrations ) : ?>
                <div class="alert alert-info mt-4 mb-0 d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-info-circle me-2"></i>
                        <strong><?php esc_html_e( 'Starter Plan:', 'axiachat-ai' ); ?></strong>
                        <?php esc_html_e( 'Upgrade to Standard for Bookly, Amelia, SSA, and Google Calendar integrations.', 'axiachat-ai' ); ?>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=axiachat-ai-pricing' ) ); ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-rocket me-1"></i><?php esc_html_e( 'Upgrade', 'axiachat-ai' ); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bookly Settings Card - Only visible when Bookly is selected -->
        <div class="card shadow-sm mb-4 card100 bookly-settings-card" id="bookly-settings-card" style="<?php echo $settings['destination'] !== 'bookly' ? 'display:none;' : ''; ?>">
            <div class="card-header bg-light">
                <i class="bi bi-gear me-2"></i>
                <strong><?php esc_html_e( 'Bookly Integration Settings', 'axiachat-ai' ); ?></strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    <?php esc_html_e( 'Configure how the AI bot handles Bookly services and staff. You can use fixed defaults or let the bot ask customers for their preferences.', 'axiachat-ai' ); ?>
                </p>
                
                <div class="row g-4">
                    <!-- Service Configuration -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="bi bi-tag me-2"></i><?php esc_html_e( 'Service Selection', 'axiachat-ai' ); ?></h6>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php esc_html_e( 'Service Mode', 'axiachat-ai' ); ?></label>
                                <select name="bookly_service_mode" id="bookly_service_mode" class="form-select">
                                    <option value="default" <?php selected( $settings['bookly_service_mode'], 'default' ); ?>>
                                        <?php esc_html_e( 'Use default service', 'axiachat-ai' ); ?>
                                    </option>
                                    <option value="ask" <?php selected( $settings['bookly_service_mode'], 'ask' ); ?>>
                                        <?php esc_html_e( 'Let the bot ask the customer', 'axiachat-ai' ); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?php esc_html_e( 'Choose whether to use a fixed service or let the AI ask customers which service they want.', 'axiachat-ai' ); ?>
                                </div>
                            </div>
                            
                            <div class="mb-0 bookly-service-default-row" id="bookly-service-default-row" style="<?php echo $settings['bookly_service_mode'] !== 'default' ? 'display:none;' : ''; ?>">
                                <label class="form-label"><?php esc_html_e( 'Default Service', 'axiachat-ai' ); ?></label>
                                <select name="bookly_service_id" id="bookly_service_id" class="form-select">
                                    <option value="0"><?php esc_html_e( '— Select a service —', 'axiachat-ai' ); ?></option>
                                    <?php 
                                    // Load Bookly services if available
                                    if ( $bookly_available ) {
                                        global $wpdb;
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                        $services = $wpdb->get_results( "SELECT id, title, price, duration FROM {$wpdb->prefix}bookly_services WHERE visibility = 'public' ORDER BY position, title" );
                                        foreach ( $services as $service ) {
                                            $label = $service->title;
                                            if ( $service->price > 0 ) {
                                                $label .= sprintf( ' (%s)', number_format( (float) $service->price, 2 ) );
                                            }
                                            printf(
                                                '<option value="%d" %s>%s</option>',
                                                (int) $service->id,
                                                selected( $settings['bookly_service_id'], $service->id, false ),
                                                esc_html( $label )
                                            );
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Staff Configuration -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="bi bi-person me-2"></i><?php esc_html_e( 'Staff Selection', 'axiachat-ai' ); ?></h6>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php esc_html_e( 'Staff Mode', 'axiachat-ai' ); ?></label>
                                <select name="bookly_staff_mode" id="bookly_staff_mode" class="form-select">
                                    <option value="any" <?php selected( $settings['bookly_staff_mode'], 'any' ); ?>>
                                        <?php esc_html_e( 'Any available staff', 'axiachat-ai' ); ?>
                                    </option>
                                    <option value="default" <?php selected( $settings['bookly_staff_mode'], 'default' ); ?>>
                                        <?php esc_html_e( 'Use default staff member', 'axiachat-ai' ); ?>
                                    </option>
                                    <option value="ask" <?php selected( $settings['bookly_staff_mode'], 'ask' ); ?>>
                                        <?php esc_html_e( 'Let the bot ask the customer', 'axiachat-ai' ); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?php esc_html_e( 'Choose how to assign staff: auto-select any available, use a fixed staff member, or let customers choose.', 'axiachat-ai' ); ?>
                                </div>
                            </div>
                            
                            <div class="mb-0 bookly-staff-default-row" id="bookly-staff-default-row" style="<?php echo $settings['bookly_staff_mode'] !== 'default' ? 'display:none;' : ''; ?>">
                                <label class="form-label"><?php esc_html_e( 'Default Staff Member', 'axiachat-ai' ); ?></label>
                                <select name="bookly_staff_id" id="bookly_staff_id" class="form-select">
                                    <option value="0"><?php esc_html_e( '— Select a staff member —', 'axiachat-ai' ); ?></option>
                                    <?php 
                                    // Load Bookly staff if available
                                    if ( $bookly_available ) {
                                        global $wpdb;
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                        $staff_members = $wpdb->get_results( "SELECT id, full_name, email FROM {$wpdb->prefix}bookly_staff WHERE visibility = 'public' ORDER BY position, full_name" );
                                        foreach ( $staff_members as $staff ) {
                                            printf(
                                                '<option value="%d" %s>%s</option>',
                                                (int) $staff->id,
                                                selected( $settings['bookly_staff_id'], $staff->id, false ),
                                                esc_html( $staff->full_name )
                                            );
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ( ! $bookly_available ) : ?>
                <div class="alert alert-warning mt-4 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php esc_html_e( 'Bookly plugin is not installed. Please install and activate Bookly to use this integration.', 'axiachat-ai' ); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Amelia Settings Card - Only visible when Amelia is selected -->
        <div class="card shadow-sm mb-4 card100 amelia-settings-card" id="amelia-settings-card" style="<?php echo $settings['destination'] !== 'amelia' ? 'display:none;' : ''; ?>">
            <div class="card-header bg-light">
                <i class="bi bi-gear me-2"></i>
                <strong><?php esc_html_e( 'Amelia Integration Settings', 'axiachat-ai' ); ?></strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    <?php esc_html_e( 'Configure how the AI bot handles Amelia services and employees. You can use fixed defaults or let the bot ask customers for their preferences.', 'axiachat-ai' ); ?>
                </p>
                
                <div class="row g-4">
                    <!-- Service Configuration -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="bi bi-tag me-2"></i><?php esc_html_e( 'Service Selection', 'axiachat-ai' ); ?></h6>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php esc_html_e( 'Service Mode', 'axiachat-ai' ); ?></label>
                                <select name="amelia_service_mode" id="amelia_service_mode" class="form-select">
                                    <option value="default" <?php selected( $settings['amelia_service_mode'] ?? 'default', 'default' ); ?>>
                                        <?php esc_html_e( 'Use default service', 'axiachat-ai' ); ?>
                                    </option>
                                    <option value="ask" <?php selected( $settings['amelia_service_mode'] ?? 'default', 'ask' ); ?>>
                                        <?php esc_html_e( 'Let the bot ask the customer', 'axiachat-ai' ); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?php esc_html_e( 'Choose whether to use a fixed service or let the AI ask customers which service they want.', 'axiachat-ai' ); ?>
                                </div>
                            </div>
                            
                            <div class="mb-0 amelia-service-default-row" id="amelia-service-default-row" style="<?php echo ( $settings['amelia_service_mode'] ?? 'default' ) !== 'default' ? 'display:none;' : ''; ?>">
                                <label class="form-label"><?php esc_html_e( 'Default Service', 'axiachat-ai' ); ?></label>
                                <select name="amelia_service_id" id="amelia_service_id" class="form-select">
                                    <option value="0"><?php esc_html_e( '— Select a service —', 'axiachat-ai' ); ?></option>
                                    <?php 
                                    // Load Amelia services if available
                                    if ( $amelia_available ) {
                                        global $wpdb;
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                        $services = $wpdb->get_results( "SELECT id, name, price, duration FROM {$wpdb->prefix}amelia_services WHERE status = 'visible' ORDER BY position, name" );
                                        foreach ( $services as $service ) {
                                            $label = $service->name;
                                            if ( $service->price > 0 ) {
                                                $label .= sprintf( ' (%s)', number_format( (float) $service->price, 2 ) );
                                            }
                                            printf(
                                                '<option value="%d" %s>%s</option>',
                                                (int) $service->id,
                                                selected( $settings['amelia_service_id'] ?? 0, $service->id, false ),
                                                esc_html( $label )
                                            );
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Employee/Provider Configuration -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="bi bi-person me-2"></i><?php esc_html_e( 'Employee Selection', 'axiachat-ai' ); ?></h6>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php esc_html_e( 'Employee Mode', 'axiachat-ai' ); ?></label>
                                <select name="amelia_provider_mode" id="amelia_provider_mode" class="form-select">
                                    <option value="any" <?php selected( $settings['amelia_provider_mode'] ?? 'any', 'any' ); ?>>
                                        <?php esc_html_e( 'Any available employee', 'axiachat-ai' ); ?>
                                    </option>
                                    <option value="default" <?php selected( $settings['amelia_provider_mode'] ?? 'any', 'default' ); ?>>
                                        <?php esc_html_e( 'Use default employee', 'axiachat-ai' ); ?>
                                    </option>
                                    <option value="ask" <?php selected( $settings['amelia_provider_mode'] ?? 'any', 'ask' ); ?>>
                                        <?php esc_html_e( 'Let the bot ask the customer', 'axiachat-ai' ); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?php esc_html_e( 'Choose how to assign employees: auto-select any available, use a fixed employee, or let customers choose.', 'axiachat-ai' ); ?>
                                </div>
                            </div>
                            
                            <div class="mb-0 amelia-provider-default-row" id="amelia-provider-default-row" style="<?php echo ( $settings['amelia_provider_mode'] ?? 'any' ) !== 'default' ? 'display:none;' : ''; ?>">
                                <label class="form-label"><?php esc_html_e( 'Default Employee', 'axiachat-ai' ); ?></label>
                                <select name="amelia_provider_id" id="amelia_provider_id" class="form-select">
                                    <option value="0"><?php esc_html_e( '— Select an employee —', 'axiachat-ai' ); ?></option>
                                    <?php 
                                    // Load Amelia providers if available
                                    if ( $amelia_available ) {
                                        global $wpdb;
                                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                                        $providers = $wpdb->get_results( "SELECT id, firstName, lastName, email FROM {$wpdb->prefix}amelia_users WHERE type = 'provider' AND status = 'visible' ORDER BY firstName, lastName" );
                                        foreach ( $providers as $provider ) {
                                            printf(
                                                '<option value="%d" %s>%s</option>',
                                                (int) $provider->id,
                                                selected( $settings['amelia_provider_id'] ?? 0, $provider->id, false ),
                                                esc_html( trim( $provider->firstName . ' ' . $provider->lastName ) )
                                            );
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ( ! $amelia_available ) : ?>
                <div class="alert alert-warning mt-4 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php esc_html_e( 'Amelia plugin is not installed. Please install and activate Amelia Booking to use this integration.', 'axiachat-ai' ); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- SSA Settings Card - Only visible when SSA is selected -->
        <div class="card shadow-sm mb-4 card100 ssa-settings-card" id="ssa-settings-card" style="<?php echo $settings['destination'] !== 'ssa' ? 'display:none;' : ''; ?>">
            <div class="card-header bg-light">
                <i class="bi bi-gear me-2"></i>
                <strong><?php esc_html_e( 'Simply Schedule Appointments Settings', 'axiachat-ai' ); ?></strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    <?php esc_html_e( 'Configure how the AI bot handles SSA appointment types. You can use a fixed default or let the bot ask customers for their preference.', 'axiachat-ai' ); ?>
                </p>
                
                <div class="row g-4">
                    <!-- Appointment Type Configuration -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="bi bi-tag me-2"></i><?php esc_html_e( 'Appointment Type Selection', 'axiachat-ai' ); ?></h6>
                            
                            <div class="mb-3">
                                <label class="form-label"><?php esc_html_e( 'Appointment Type Mode', 'axiachat-ai' ); ?></label>
                                <select name="ssa_service_mode" id="ssa_service_mode" class="form-select">
                                    <option value="default" <?php selected( $settings['ssa_service_mode'] ?? 'default', 'default' ); ?>>
                                        <?php esc_html_e( 'Use default appointment type', 'axiachat-ai' ); ?>
                                    </option>
                                    <option value="ask" <?php selected( $settings['ssa_service_mode'] ?? 'default', 'ask' ); ?>>
                                        <?php esc_html_e( 'Let the bot ask the customer', 'axiachat-ai' ); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?php esc_html_e( 'Choose whether to use a fixed appointment type or let the AI ask customers which type they want.', 'axiachat-ai' ); ?>
                                </div>
                            </div>
                            
                            <div class="mb-0 ssa-service-default-row" id="ssa-service-default-row" style="<?php echo ( $settings['ssa_service_mode'] ?? 'default' ) !== 'default' ? 'display:none;' : ''; ?>">
                                <label class="form-label"><?php esc_html_e( 'Default Appointment Type', 'axiachat-ai' ); ?></label>
                                <select name="ssa_service_id" id="ssa_service_id" class="form-select">
                                    <option value="0"><?php esc_html_e( '— Select an appointment type —', 'axiachat-ai' ); ?></option>
                                    <?php 
                                    // Load SSA appointment types if available
                                    if ( $ssa_available && function_exists( 'ssa' ) ) {
                                        $ssa = call_user_func( 'ssa' );
                                        if ( isset( $ssa->appointment_type_model ) ) {
                                            $types = [];
                                            if ( method_exists( $ssa->appointment_type_model, 'get_all_appointment_types' ) ) {
                                                $types = $ssa->appointment_type_model->get_all_appointment_types();
                                            } elseif ( method_exists( $ssa->appointment_type_model, 'query' ) ) {
                                                $types = $ssa->appointment_type_model->query( [ 'status' => 'publish' ] );
                                            }
                                            foreach ( (array) $types as $type ) {
                                                $id = isset( $type['id'] ) ? (int) $type['id'] : 0;
                                                if ( $id <= 0 ) continue;
                                                $title = isset( $type['title'] ) ? $type['title'] : ( isset( $type['name'] ) ? $type['name'] : '' );
                                                $duration = isset( $type['duration'] ) ? (int) $type['duration'] : 0;
                                                $label = $title;
                                                if ( $duration > 0 ) {
                                                    $label .= sprintf( ' (%d min)', $duration );
                                                }
                                                printf(
                                                    '<option value="%d" %s>%s</option>',
                                                    intval( $id ),
                                                    selected( $settings['ssa_service_id'] ?? 0, $id, false ),
                                                    esc_html( $label )
                                                );
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info Panel -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i><?php esc_html_e( 'About SSA Integration', 'axiachat-ai' ); ?></h6>
                            <p class="small text-muted mb-2">
                                <?php esc_html_e( 'Simply Schedule Appointments provides flexible scheduling without the need for staff/provider selection.', 'axiachat-ai' ); ?>
                            </p>
                            <ul class="small text-muted mb-0">
                                <li><?php esc_html_e( 'Appointments sync directly with SSA', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Availability respects SSA settings', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Customer receives SSA notifications', 'axiachat-ai' ); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <?php if ( ! $ssa_available ) : ?>
                <div class="alert alert-warning mt-4 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php esc_html_e( 'Simply Schedule Appointments is not installed. Please install and activate SSA to use this integration.', 'axiachat-ai' ); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Google Calendar Settings Card - Only visible when Google Calendar is selected -->
        <?php 
        $gcal_adapter = AIChat_Appointments_Manager::get_adapter_by_id( 'google_calendar' );
        $gcal_connected = $gcal_adapter ? $gcal_adapter->is_connected() : false;
        $gcal_status = $gcal_adapter ? $gcal_adapter->get_oauth()->get_connection_status() : [];
        ?>
        <div class="card shadow-sm mb-4 card100 gcal-settings-card" id="gcal-settings-card" style="<?php echo $settings['destination'] !== 'google_calendar' ? 'display:none;' : ''; ?>">
            <div class="card-header bg-light">
                <i class="bi bi-google me-2"></i>
                <strong><?php esc_html_e( 'Google Calendar Connection', 'axiachat-ai' ); ?></strong>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- Connection Status -->
                    <div class="col-md-6">
                        <div class="border rounded p-4 h-100 <?php echo $gcal_connected ? 'border-success bg-success bg-opacity-10' : 'border-secondary'; ?>">
                            <?php if ( $gcal_connected ) : ?>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="bi bi-check-circle-fill text-success fs-3 me-3"></i>
                                    <div>
                                        <h6 class="mb-0"><?php esc_html_e( 'Connected', 'axiachat-ai' ); ?></h6>
                                        <small class="text-muted"><?php echo esc_html( $gcal_status['email'] ?? '' ); ?></small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted"><?php esc_html_e( 'Calendar', 'axiachat-ai' ); ?></label>
                                    <select name="gcal_calendar_id" id="gcal_calendar_id" class="form-select form-select-sm">
                                        <option value="primary"><?php esc_html_e( 'Primary Calendar', 'axiachat-ai' ); ?></option>
                                        <?php
                                        // Load calendars if connected
                                        if ( $gcal_connected ) {
                                            $calendars = $gcal_adapter->get_client()->list_calendars();
                                            if ( ! is_wp_error( $calendars ) ) {
                                                foreach ( $calendars as $cal ) {
                                                    if ( $cal['id'] === 'primary' ) continue;
                                                    printf(
                                                        '<option value="%s" %s>%s</option>',
                                                        esc_attr( $cal['id'] ),
                                                        selected( $gcal_status['calendar_id'] ?? 'primary', $cal['id'], false ),
                                                        esc_html( $cal['summary'] )
                                                    );
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gcal-reconnect">
                                        <i class="bi bi-arrow-repeat me-1"></i><?php esc_html_e( 'Reconnect', 'axiachat-ai' ); ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="gcal-disconnect">
                                        <i class="bi bi-x-circle me-1"></i><?php esc_html_e( 'Disconnect', 'axiachat-ai' ); ?>
                                    </button>
                                </div>
                            <?php else : ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-google fs-1 text-muted mb-3 d-block"></i>
                                    <h6 class="mb-2"><?php esc_html_e( 'Connect Google Calendar', 'axiachat-ai' ); ?></h6>
                                    <p class="small text-muted mb-3">
                                        <?php esc_html_e( 'Link your Google Calendar to sync appointments. Events will be created in your calendar and existing events will block availability.', 'axiachat-ai' ); ?>
                                    </p>
                                    <a href="<?php echo esc_url( $gcal_adapter ? $gcal_adapter->get_oauth()->get_auth_url() : '#' ); ?>" 
                                       class="btn btn-primary" id="gcal-connect">
                                        <i class="bi bi-box-arrow-in-right me-2"></i><?php esc_html_e( 'Connect with Google', 'axiachat-ai' ); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Info Panel -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i><?php esc_html_e( 'How it works', 'axiachat-ai' ); ?></h6>
                            <ul class="small text-muted mb-0">
                                <li class="mb-2">
                                    <strong><?php esc_html_e( 'Availability:', 'axiachat-ai' ); ?></strong>
                                    <?php esc_html_e( 'Based on Working Hours below. Existing Google Calendar events will block those time slots.', 'axiachat-ai' ); ?>
                                </li>
                                <li class="mb-2">
                                    <strong><?php esc_html_e( 'New bookings:', 'axiachat-ai' ); ?></strong>
                                    <?php esc_html_e( 'Appointments are saved locally AND created as events in your Google Calendar.', 'axiachat-ai' ); ?>
                                </li>
                                <li class="mb-2">
                                    <strong><?php esc_html_e( 'Cancellations:', 'axiachat-ai' ); ?></strong>
                                    <?php esc_html_e( 'When cancelled, the event is automatically removed from Google Calendar.', 'axiachat-ai' ); ?>
                                </li>
                                <li>
                                    <strong><?php esc_html_e( 'Notifications:', 'axiachat-ai' ); ?></strong>
                                    <?php esc_html_e( 'Google Calendar will send its own reminders to attendees.', 'axiachat-ai' ); ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Working Hours Card - Full Width -->
        <div class="card shadow-sm mb-4 card100 working-hours-card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-clock-history me-2"></i>
                    <strong><?php esc_html_e( 'Working Hours', 'axiachat-ai' ); ?></strong>
                </div>
                <small class="text-muted"><?php esc_html_e( 'Configure your available hours for each day of the week', 'axiachat-ai' ); ?></small>
            </div>
            <div class="card-body p-0">
                <?php foreach ( $days as $day_num => $day_name ) : 
                    $day_settings = $settings['working_hours'][ $day_num ] ?? [ 'enabled' => false, 'start' => '09:00', 'end' => '18:00' ];
                    $slots = $day_settings['slots'] ?? [
                        [ 'start' => $day_settings['start'], 'end' => $day_settings['end'] ]
                    ];
                ?>
                <div class="day-row d-flex align-items-start gap-3 <?php echo ! $day_settings['enabled'] ? 'disabled' : ''; ?>">
                    <div class="day-toggle">
                        <input type="checkbox" 
                               id="day-enabled-<?php echo esc_attr( $day_num ); ?>"
                               name="working_hours[<?php echo esc_attr( $day_num ); ?>][enabled]"
                               value="1" <?php checked( $day_settings['enabled'] ); ?>
                               onchange="this.closest('.day-row').classList.toggle('disabled', !this.checked)">
                    </div>
                    <div class="day-name">
                        <label for="day-enabled-<?php echo esc_attr( $day_num ); ?>" class="mb-0">
                            <?php echo esc_html( $day_name ); ?>
                        </label>
                    </div>
                    <div class="time-slots-container">
                        <div class="time-slots-list">
                            <?php foreach ( $slots as $slot_idx => $slot ) : ?>
                            <div class="time-slot-row d-flex gap-2 align-items-center mb-2">
                                <input type="time" class="form-control form-control-sm" style="width: 120px;"
                                       name="working_hours[<?php echo esc_attr( $day_num ); ?>][slots][<?php echo esc_attr( $slot_idx ); ?>][start]"
                                       value="<?php echo esc_attr( $slot['start'] ); ?>">
                                <span class="text-muted"><?php esc_html_e( 'to', 'axiachat-ai' ); ?></span>
                                <input type="time" class="form-control form-control-sm" style="width: 120px;"
                                       name="working_hours[<?php echo esc_attr( $day_num ); ?>][slots][<?php echo esc_attr( $slot_idx ); ?>][end]"
                                       value="<?php echo esc_attr( $slot['end'] ); ?>">
                                <?php if ( count( $slots ) > 1 ) : ?>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-time-slot">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm btn-add-time-slot mt-2" data-day="<?php echo esc_attr( $day_num ); ?>">
                            <i class="bi bi-plus me-1"></i><?php esc_html_e( 'Add time slot', 'axiachat-ai' ); ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-light">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <i class="bi bi-info-circle text-info"></i>
                    </div>
                    <div class="col">
                        <small class="text-muted">
                            <?php esc_html_e( 'Add multiple time slots per day for split schedules (e.g., 9:00-13:00 and 16:00-20:00). Useful for lunch breaks or different shifts.', 'axiachat-ai' ); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Main Settings -->
            <div class="col-lg-8">
                <!-- General Settings - For Internal and Google Calendar destinations -->
                <div class="card shadow-sm mb-4 internal-gcal-card" <?php echo ! in_array( $settings['destination'], [ 'internal', 'google_calendar' ], true ) ? 'style="display:none;"' : ''; ?>>
                    <div class="card-header">
                        <i class="bi bi-sliders me-2"></i><?php esc_html_e( 'Booking Settings', 'axiachat-ai' ); ?>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e( 'Timezone', 'axiachat-ai' ); ?></label>
                                <select name="timezone" class="form-select">
                                    <?php echo wp_timezone_choice( $settings['timezone'] ); ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e( 'Slot Duration', 'axiachat-ai' ); ?></label>
                                <select name="slot_duration" class="form-select">
                                    <option value="15" <?php selected( $settings['slot_duration'], 15 ); ?>>15 <?php esc_html_e( 'minutes', 'axiachat-ai' ); ?></option>
                                    <option value="30" <?php selected( $settings['slot_duration'], 30 ); ?>>30 <?php esc_html_e( 'minutes', 'axiachat-ai' ); ?></option>
                                    <option value="45" <?php selected( $settings['slot_duration'], 45 ); ?>>45 <?php esc_html_e( 'minutes', 'axiachat-ai' ); ?></option>
                                    <option value="60" <?php selected( $settings['slot_duration'], 60 ); ?>>1 <?php esc_html_e( 'hour', 'axiachat-ai' ); ?></option>
                                    <option value="90" <?php selected( $settings['slot_duration'], 90 ); ?>>1.5 <?php esc_html_e( 'hours', 'axiachat-ai' ); ?></option>
                                    <option value="120" <?php selected( $settings['slot_duration'], 120 ); ?>>2 <?php esc_html_e( 'hours', 'axiachat-ai' ); ?></option>
                                </select>
                                <small class="text-muted"><?php esc_html_e( 'Duration of each appointment slot', 'axiachat-ai' ); ?></small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e( 'Minimum Advance Time', 'axiachat-ai' ); ?></label>
                                <div class="input-group">
                                    <input type="number" name="min_advance" class="form-control" 
                                           value="<?php echo esc_attr( $settings['min_advance'] ); ?>" min="0">
                                    <span class="input-group-text"><?php esc_html_e( 'minutes', 'axiachat-ai' ); ?></span>
                                </div>
                                <small class="text-muted"><?php esc_html_e( 'How far in advance must appointments be booked', 'axiachat-ai' ); ?></small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label"><?php esc_html_e( 'Maximum Advance Booking', 'axiachat-ai' ); ?></label>
                                <div class="input-group">
                                    <input type="number" name="max_advance_days" class="form-control" 
                                           value="<?php echo esc_attr( $settings['max_advance_days'] ); ?>" min="1" max="365">
                                    <span class="input-group-text"><?php esc_html_e( 'days', 'axiachat-ai' ); ?></span>
                                </div>
                                <small class="text-muted"><?php esc_html_e( 'How far ahead can appointments be booked', 'axiachat-ai' ); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications - For Internal and Google Calendar destinations -->
                <div class="card shadow-sm mb-4 card100 internal-gcal-card" <?php echo ! in_array( $settings['destination'], [ 'internal', 'google_calendar' ], true ) ? 'style="display:none;"' : ''; ?>>
                    <div class="card-header">
                        <i class="bi bi-envelope me-2"></i><?php esc_html_e( 'Notifications & Confirmations', 'axiachat-ai' ); ?>
                    </div>
                    <div class="card-body">
                        <!-- Auto-confirm row -->
                        <div class="row mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                    <input type="checkbox" name="auto_confirm" id="auto_confirm" value="1" <?php checked( $settings['auto_confirm'] ); ?>>
                                    <strong><?php esc_html_e( 'Auto-confirm appointments', 'axiachat-ai' ); ?></strong>
                                </label>
                                <small class="text-muted d-block mt-1" style="margin-left: 22px;"><?php esc_html_e( 'When disabled, new appointments will be set to "Pending" status until manually confirmed.', 'axiachat-ai' ); ?></small>
                            </div>
                        </div>
                        
                        <!-- Confirmation Email row -->
                        <div class="row mb-4 pb-3 border-bottom">
                            <div class="col-md-8">
                                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                    <input type="checkbox" name="send_confirmation" id="send_confirmation" value="1" <?php checked( $settings['send_confirmation'] ); ?>>
                                    <strong><?php esc_html_e( 'Send confirmation email', 'axiachat-ai' ); ?></strong>
                                </label>
                                <small class="text-muted d-block mt-1" style="margin-left: 22px;"><?php esc_html_e( 'Send an email to the customer when an appointment is booked, with the date, time, and confirmation code.', 'axiachat-ai' ); ?></small>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#confirmationEmailModal">
                                    <i class="bi bi-pencil me-1"></i><?php esc_html_e( 'Edit Template', 'axiachat-ai' ); ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Reminder Email row -->
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                    <input type="checkbox" name="send_reminder" id="send_reminder" value="1" <?php checked( $settings['send_reminder'] ); ?>>
                                    <strong><?php esc_html_e( 'Send reminder email', 'axiachat-ai' ); ?></strong>
                                </label>
                                <small class="text-muted d-block mt-1" style="margin-left: 22px;"><?php esc_html_e( 'Send a reminder email before the appointment.', 'axiachat-ai' ); ?></small>
                            </div>
                            <div class="col-md-4">
                                <div class="reminder-time-selector d-flex align-items-center gap-2">
                                    <input type="number" name="reminder_time" class="form-control form-control-sm text-center" 
                                           value="<?php echo esc_attr( $settings['reminder_time'] ?? 24 ); ?>" min="1" max="168" style="width: 70px;">
                                    <select name="reminder_unit" class="form-select form-select-sm" style="width: 100px;">
                                        <option value="hours" <?php selected( $settings['reminder_unit'] ?? 'hours', 'hours' ); ?>><?php esc_html_e( 'hours', 'axiachat-ai' ); ?></option>
                                        <option value="days" <?php selected( $settings['reminder_unit'] ?? 'hours', 'days' ); ?>><?php esc_html_e( 'days', 'axiachat-ai' ); ?></option>
                                    </select>
                                    <span class="text-muted small text-nowrap"><?php esc_html_e( 'before', 'axiachat-ai' ); ?></span>
                                </div>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#reminderEmailModal">
                                    <i class="bi bi-pencil me-1"></i><?php esc_html_e( 'Edit Template', 'axiachat-ai' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Webhook Card -->
                <?php $webhook_url = get_option( 'aichat_appointments_webhook_url', '' ); ?>
                <div class="card shadow-sm card100 <?php echo ! $is_pro ? 'border-secondary' : ''; ?>" style="<?php echo ! $is_pro ? 'position: relative;' : ''; ?>">
                    <?php if ( ! $is_pro ) : ?>
                        <div class="position-absolute top-0 end-0 m-2" style="z-index: 10;">
                            <span class="badge bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-star-fill me-1"></i>PRO
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="card-header bg-light d-flex align-items-center">
                        <i class="bi bi-broadcast me-2"></i>
                        <strong><?php esc_html_e( 'Webhook Integration', 'axiachat-ai' ); ?></strong>
                        <span class="badge bg-secondary ms-2"><?php esc_html_e( 'Optional', 'axiachat-ai' ); ?></span>
                    </div>
                    <div class="card-body <?php echo ! $is_pro ? 'opacity-75' : ''; ?>">
                        <p class="text-muted small mb-3">
                            <?php esc_html_e( 'Send appointment data to an external service via webhook. Useful for integrations with automation tools like n8n, Zapier, Make, or custom APIs.', 'axiachat-ai' ); ?>
                        </p>
                        
                        <div class="row mb-3">
                            <label for="webhook_url" class="col-md-3 col-form-label"><?php esc_html_e( 'Webhook URL', 'axiachat-ai' ); ?></label>
                            <div class="col-md-9">
                                <input type="url" class="form-control" name="webhook_url" id="webhook_url"
                                       value="<?php echo esc_attr( $webhook_url ); ?>" 
                                       placeholder="https://your-n8n-instance.com/webhook/abc123"
                                       <?php disabled( ! $is_pro ); ?>>
                                <small class="text-muted d-block mt-1">
                                    <?php if ( $is_pro ) : ?>
                                        <?php esc_html_e( 'Leave empty to disable. A POST request with JSON data will be sent for each new appointment.', 'axiachat-ai' ); ?>
                                    <?php else : ?>
                                        <i class="bi bi-lock me-1"></i><?php esc_html_e( 'Webhooks are a PRO feature. Upgrade to enable automation integrations.', 'axiachat-ai' ); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="alert alert-light border small mb-0">
                            <strong><i class="bi bi-code-slash me-1"></i><?php esc_html_e( 'JSON Payload Format:', 'axiachat-ai' ); ?></strong>
                            <pre class="mb-0 mt-2" style="font-size: 12px;"><code>{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "date": "2026-01-26",
  "time": "14:30",
  "service": "Consultation",
  "notes": "Customer notes...",
  "confirmation_code": "APT-ABC123",
  "source": "axiachat"
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Save Button -->
                <div class="card shadow-sm mb-4 border-primary">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-check-lg me-2"></i><?php esc_html_e( 'Save Settings', 'axiachat-ai' ); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Quick Info -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i><?php esc_html_e( 'How It Works', 'axiachat-ai' ); ?>
                    </div>
                    <div class="card-body small">
                        <ol class="mb-0 ps-3">
                            <li class="mb-2"><?php esc_html_e( 'User asks to book appointment via chat', 'axiachat-ai' ); ?></li>
                            <li class="mb-2"><?php esc_html_e( 'AI checks available slots for requested date', 'axiachat-ai' ); ?></li>
                            <li class="mb-2"><?php esc_html_e( 'User selects a time and provides contact info', 'axiachat-ai' ); ?></li>
                            <li class="mb-2"><?php esc_html_e( 'AI books the appointment and provides confirmation code', 'axiachat-ai' ); ?></li>
                            <li><?php esc_html_e( 'Customer receives email confirmation', 'axiachat-ai' ); ?></li>
                        </ol>
                    </div>
                </div>
                
                <!-- Current Status -->
                <div class="card shadow-sm border-info">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-gear-wide-connected me-2"></i><?php esc_html_e( 'Current Configuration', 'axiachat-ai' ); ?>
                    </div>
                    <div class="card-body small">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><?php esc_html_e( 'Destination', 'axiachat-ai' ); ?></td>
                                <td class="text-end"><strong><?php echo esc_html( ucfirst( $settings['destination'] ) ); ?></strong></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Slot Duration', 'axiachat-ai' ); ?></td>
                                <td class="text-end"><strong><?php echo esc_html( $settings['slot_duration'] ); ?> <?php esc_html_e( 'min', 'axiachat-ai' ); ?></strong></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e( 'Timezone', 'axiachat-ai' ); ?></td>
                                <td class="text-end"><strong><?php echo esc_html( $settings['timezone'] ); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <!-- Confirmation Email Template Modal -->
    <div class="modal fade" id="confirmationEmailModal" tabindex="-1" aria-labelledby="confirmationEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="confirmationEmailModalLabel">
                        <i class="bi bi-envelope-check me-2"></i><?php esc_html_e( 'Confirmation Email Template', 'axiachat-ai' ); ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        <strong><?php esc_html_e( 'Available placeholders:', 'axiachat-ai' ); ?></strong>
                        <code>{customer_name}</code>, <code>{booking_code}</code>, <code>{date}</code>, <code>{time}</code>, <code>{service}</code>, <code>{site_name}</code>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php esc_html_e( 'Email Subject', 'axiachat-ai' ); ?></label>
                        <input type="text" class="form-control" id="confirmation_email_subject" name="confirmation_email_subject" 
                               value="<?php echo esc_attr( $settings['confirmation_email_subject'] ?? '' ); ?>"
                               placeholder="<?php
                                   /* translators: %s: Email placeholder {site_name} */
                                   echo esc_attr( sprintf( __( '[%s] Appointment Confirmation - {booking_code}', 'axiachat-ai' ), '{site_name}' ) );
                               ?>">
                        <small class="text-muted"><?php esc_html_e( 'Leave empty to use default subject', 'axiachat-ai' ); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php esc_html_e( 'Email Body', 'axiachat-ai' ); ?></label>
                        <textarea class="form-control" id="confirmation_email_body" name="confirmation_email_body" rows="10"
                                  placeholder="<?php echo esc_attr( __( "Hello {customer_name},\n\nYour appointment has been confirmed.\n\nDetails:\n- Date: {date}\n- Time: {time}\n- Service: {service}\n- Confirmation Code: {booking_code}\n\nTo cancel or reschedule, please contact us with your confirmation code.\n\nThank you!\n{site_name}", 'axiachat-ai' ) ); ?>"><?php echo esc_textarea( $settings['confirmation_email_body'] ?? '' ); ?></textarea>
                        <small class="text-muted"><?php esc_html_e( 'Leave empty to use default template', 'axiachat-ai' ); ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="resetConfirmationTemplate">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?php esc_html_e( 'Reset to Default', 'axiachat-ai' ); ?>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Close', 'axiachat-ai' ); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reminder Email Template Modal -->
    <div class="modal fade" id="reminderEmailModal" tabindex="-1" aria-labelledby="reminderEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="reminderEmailModalLabel">
                        <i class="bi bi-bell me-2"></i><?php esc_html_e( 'Reminder Email Template', 'axiachat-ai' ); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        <strong><?php esc_html_e( 'Available placeholders:', 'axiachat-ai' ); ?></strong>
                        <code>{customer_name}</code>, <code>{booking_code}</code>, <code>{date}</code>, <code>{time}</code>, <code>{service}</code>, <code>{site_name}</code>, <code>{hours_until}</code>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php esc_html_e( 'Email Subject', 'axiachat-ai' ); ?></label>
                        <input type="text" class="form-control" id="reminder_email_subject" name="reminder_email_subject" 
                               value="<?php echo esc_attr( $settings['reminder_email_subject'] ?? '' ); ?>"
                               placeholder="<?php
                                   /* translators: %s: Email placeholder {site_name} */
                                   echo esc_attr( sprintf( __( '[%s] Appointment Reminder - {date}', 'axiachat-ai' ), '{site_name}' ) );
                               ?>">
                        <small class="text-muted"><?php esc_html_e( 'Leave empty to use default subject', 'axiachat-ai' ); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php esc_html_e( 'Email Body', 'axiachat-ai' ); ?></label>
                        <textarea class="form-control" id="reminder_email_body" name="reminder_email_body" rows="10"
                                  placeholder="<?php echo esc_attr( __( "Hello {customer_name},\n\nThis is a friendly reminder about your upcoming appointment.\n\nDetails:\n- Date: {date}\n- Time: {time}\n- Service: {service}\n- Confirmation Code: {booking_code}\n\nIf you need to cancel or reschedule, please contact us as soon as possible.\n\nWe look forward to seeing you!\n{site_name}", 'axiachat-ai' ) ); ?>"><?php echo esc_textarea( $settings['reminder_email_body'] ?? '' ); ?></textarea>
                        <small class="text-muted"><?php esc_html_e( 'Leave empty to use default template', 'axiachat-ai' ); ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="resetReminderTemplate">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><?php esc_html_e( 'Reset to Default', 'axiachat-ai' ); ?>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e( 'Close', 'axiachat-ai' ); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(function($) {
        // Toggle destination options
        $('.destination-option input[type="radio"]').on('change', function() {
            $('.destination-option').removeClass('active');
            $(this).closest('.destination-option').addClass('active');
            
            // Show/hide settings cards based on destination
            var destination = $(this).val();
            if (destination === 'bookly') {
                $('#bookly-settings-card').slideDown(200);
                $('#amelia-settings-card').slideUp(200);
                $('#ssa-settings-card').slideUp(200);
                $('#gcal-settings-card').slideUp(200);
                $('.working-hours-card').slideUp(200);
                $('.internal-gcal-card').slideUp(200);
            } else if (destination === 'amelia') {
                $('#bookly-settings-card').slideUp(200);
                $('#amelia-settings-card').slideDown(200);
                $('#ssa-settings-card').slideUp(200);
                $('#gcal-settings-card').slideUp(200);
                $('.working-hours-card').slideUp(200);
                $('.internal-gcal-card').slideUp(200);
            } else if (destination === 'ssa') {
                $('#bookly-settings-card').slideUp(200);
                $('#amelia-settings-card').slideUp(200);
                $('#ssa-settings-card').slideDown(200);
                $('#gcal-settings-card').slideUp(200);
                $('.working-hours-card').slideUp(200);
                $('.internal-gcal-card').slideUp(200);
            } else if (destination === 'google_calendar') {
                $('#bookly-settings-card').slideUp(200);
                $('#amelia-settings-card').slideUp(200);
                $('#ssa-settings-card').slideUp(200);
                $('#gcal-settings-card').slideDown(200);
                $('.working-hours-card').slideDown(200); // GCal uses working hours
                $('.internal-gcal-card').slideDown(200); // Booking settings & notifications
            } else {
                // Internal
                $('#bookly-settings-card').slideUp(200);
                $('#amelia-settings-card').slideUp(200);
                $('#ssa-settings-card').slideUp(200);
                $('#gcal-settings-card').slideUp(200);
                $('.working-hours-card').slideDown(200);
                $('.internal-gcal-card').slideDown(200);
            }
        });
        
        // Initial visibility based on current destination
        var currentDestination = $('input[name="destination"]:checked').val();
        if (currentDestination !== 'internal' && currentDestination !== 'google_calendar') {
            $('.working-hours-card').hide();
            $('.internal-gcal-card').hide();
        }
        
        // Google Calendar disconnect button
        $('#gcal-disconnect').on('click', function(e) {
            e.preventDefault();
            if (!confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect Google Calendar?', 'axiachat-ai' ) ); ?>')) {
                return;
            }
            
            $.post(ajaxurl, {
                action: 'aichat_appointments_gcal_disconnect',
                nonce: $('#appointments_nonce').val()
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Error disconnecting');
                }
            });
        });
        
        // Google Calendar reconnect button
        $('#gcal-reconnect').on('click', function(e) {
            e.preventDefault();
            window.location.href = $('#gcal-connect').attr('href') || '<?php echo esc_js( $gcal_adapter ? $gcal_adapter->get_oauth()->get_auth_url() : '' ); ?>';
        });
        
        // Toggle Bookly service default dropdown based on mode
        $('#bookly_service_mode').on('change', function() {
            if ($(this).val() === 'default') {
                $('#bookly-service-default-row').slideDown(200);
            } else {
                $('#bookly-service-default-row').slideUp(200);
            }
        });
        
        // Toggle Bookly staff default dropdown based on mode
        $('#bookly_staff_mode').on('change', function() {
            if ($(this).val() === 'default') {
                $('#bookly-staff-default-row').slideDown(200);
            } else {
                $('#bookly-staff-default-row').slideUp(200);
            }
        });
        
        // Toggle Amelia service default dropdown based on mode
        $('#amelia_service_mode').on('change', function() {
            if ($(this).val() === 'default') {
                $('#amelia-service-default-row').slideDown(200);
            } else {
                $('#amelia-service-default-row').slideUp(200);
            }
        });
        
        // Toggle Amelia provider default dropdown based on mode
        $('#amelia_provider_mode').on('change', function() {
            if ($(this).val() === 'default') {
                $('#amelia-provider-default-row').slideDown(200);
            } else {
                $('#amelia-provider-default-row').slideUp(200);
            }
        });
        
        // Toggle SSA service default dropdown based on mode
        $('#ssa_service_mode').on('change', function() {
            if ($(this).val() === 'default') {
                $('#ssa-service-default-row').slideDown(200);
            } else {
                $('#ssa-service-default-row').slideUp(200);
            }
        });
        
        // Reset confirmation email template
        $('#resetConfirmationTemplate').on('click', function() {
            $('#confirmation_email_subject').val('');
            $('#confirmation_email_body').val('');
        });
        
        // Reset reminder email template
        $('#resetReminderTemplate').on('click', function() {
            $('#reminder_email_subject').val('');
            $('#reminder_email_body').val('');
        });
    });
    </script>
    <?php
}

/**
 * Render Help Tab
 */
function aichat_appointments_render_help_tab() {
    ?>
    <div class="row">
        <div class="col-lg-8">
            
            <!-- Overview Card -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong><?php esc_html_e( 'Appointment Booking Overview', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <p><?php esc_html_e( 'The Appointments add-on enables your AI chatbot to check availability, book appointments, and handle cancellations during conversations. Users can schedule appointments naturally through chat, and receive confirmation codes instantly.', 'axiachat-ai' ); ?></p>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-diagram-3 me-2"></i><?php esc_html_e( 'Available Destinations', 'axiachat-ai' ); ?></h6>
                    <ul class="mb-0">
                        <li><strong><?php esc_html_e( 'Internal Calendar:', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'Saves appointments to the plugin\'s built-in calendar. Configure working hours and manage bookings from this admin panel.', 'axiachat-ai' ); ?></li>
                        <li><strong>Simply Schedule Appointments (SSA):</strong> <?php esc_html_e( 'Integrates with SSA plugin using your existing appointment types and availability settings.', 'axiachat-ai' ); ?></li>
                        <li><strong>Bookly:</strong> <?php esc_html_e( 'Integrates with the Bookly plugin to use your existing staff, services, and availability settings.', 'axiachat-ai' ); ?></li>
                        <li><strong>Amelia:</strong> <?php esc_html_e( 'Integrates with Amelia booking plugin for advanced scheduling features.', 'axiachat-ai' ); ?></li>
                        <li><strong>Google Calendar:</strong> <?php esc_html_e( 'Sync appointments with Google Calendar. Requires connecting your Google account in the settings.', 'axiachat-ai' ); ?></li>
                    </ul>
                </div>
            </div>
            
            <!-- How It Works Card -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-light">
                    <i class="bi bi-gear-wide-connected me-2"></i>
                    <strong><?php esc_html_e( 'How It Works', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <ol>
                        <li class="mb-2"><strong><?php esc_html_e( 'Enable the Tools:', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'Go to your Bot settings and enable the appointment tools (get_available_slots, book_appointment, cancel_appointment) in the AI Tools section.', 'axiachat-ai' ); ?></li>
                        <li class="mb-2"><strong><?php esc_html_e( 'Configure Working Hours:', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'Set your available days and hours in the Settings tab.', 'axiachat-ai' ); ?></li>
                        <li class="mb-2"><strong><?php esc_html_e( 'Set Slot Duration:', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'Choose how long each appointment slot should be (15, 30, 45, 60, 90, or 120 minutes).', 'axiachat-ai' ); ?></li>
                        <li class="mb-2"><strong><?php esc_html_e( 'Add Instructions:', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'Include appointment booking instructions in your bot\'s System Instructions (see examples below).', 'axiachat-ai' ); ?></li>
                        <li class="mb-2"><strong><?php esc_html_e( 'Booking Flow:', 'axiachat-ai' ); ?></strong>
                            <ul>
                                <li><?php esc_html_e( 'User asks to book → Bot checks availability with get_available_slots', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'User selects time → Bot collects name and email', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Bot confirms with book_appointment → User receives confirmation code', 'axiachat-ai' ); ?></li>
                            </ul>
                        </li>
                    </ol>
                </div>
            </div>
            
            <!-- System Instructions Examples -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-code-slash me-2"></i>
                    <strong><?php esc_html_e( 'System Instructions Examples', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3"><?php esc_html_e( 'Add these instructions to your bot\'s System Instructions field to enable intelligent appointment booking:', 'axiachat-ai' ); ?></p>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-calendar-check me-2"></i><?php esc_html_e( 'Example 1: General Appointment Bot', 'axiachat-ai' ); ?></h6>
                    <div class="bg-light p-3 rounded mb-4">
                        <pre class="mb-0" style="white-space: pre-wrap; font-size: 13px;"><code>## Appointment Booking

You can help users book, check availability, and cancel appointments.

### Booking Flow:
1. When a user wants to book, ask for their preferred date
2. Use `get_available_slots` to check what times are available
3. Present the available times clearly to the user
4. Once they choose a time, collect their name and email
5. Use `book_appointment` to create the booking
6. Always provide the confirmation code (starts with APT-)

### Cancellations:
- Ask for the booking confirmation code
- Use `cancel_appointment` with the code
- Offer to help book a new appointment

### Important Rules:
- Always check availability BEFORE confirming a time is available
- Confirm all details before booking
- Never book without explicit user consent
- All times are in <?php echo esc_html( wp_timezone_string() ); ?></code></pre>
                    </div>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-briefcase me-2"></i><?php esc_html_e( 'Example 2: Consultation/Meeting Bot', 'axiachat-ai' ); ?></h6>
                    <div class="bg-light p-3 rounded mb-4">
                        <pre class="mb-0" style="white-space: pre-wrap; font-size: 13px;"><code>## Consultation Scheduling

Help users schedule free consultations with our team.

1. Be enthusiastic when someone wants to book: "Great! I'd love to help you schedule a consultation."
2. Ask what date works best for them
3. Check availability and present 3-4 options
4. Collect: Full name, email address, and optionally phone number
5. Ask what topics they'd like to discuss (save in notes)
6. Book the appointment and provide:
   - Confirmation code
   - Date and time
   - What to expect

Example confirmation:
"Your consultation is booked for [date] at [time]. Your confirmation code is [code]. You'll receive an email confirmation shortly. Looking forward to meeting you!"</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Best Practices Card -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong><?php esc_html_e( 'Best Practices', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success"><i class="bi bi-check-circle me-1"></i> <?php esc_html_e( 'Do', 'axiachat-ai' ); ?></h6>
                            <ul>
                                <li><?php esc_html_e( 'Always check availability before suggesting a time', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Confirm all details before booking', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Provide the confirmation code clearly', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Offer alternatives if requested time is unavailable', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Mention the timezone for clarity', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Send email confirmations', 'axiachat-ai' ); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger"><i class="bi bi-x-circle me-1"></i> <?php esc_html_e( 'Don\'t', 'axiachat-ai' ); ?></h6>
                            <ul>
                                <li><?php esc_html_e( 'Confirm a time without checking availability', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Book without collecting required information', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Skip the confirmation step', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Assume user timezone without asking', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Book appointments too far in advance', 'axiachat-ai' ); ?></li>
                                <li><?php esc_html_e( 'Allow bookings without minimum notice time', 'axiachat-ai' ); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            
            <!-- Quick Reference Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-bookmark me-2"></i>
                    <strong><?php esc_html_e( 'Quick Reference', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold"><?php esc_html_e( 'Available Tools', 'axiachat-ai' ); ?></h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th><?php esc_html_e( 'Tool', 'axiachat-ai' ); ?></th>
                                <th><?php esc_html_e( 'Purpose', 'axiachat-ai' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>get_available_slots</code></td><td><?php esc_html_e( 'Check availability', 'axiachat-ai' ); ?></td></tr>
                            <tr><td><code>book_appointment</code></td><td><?php esc_html_e( 'Create booking', 'axiachat-ai' ); ?></td></tr>
                            <tr><td><code>cancel_appointment</code></td><td><?php esc_html_e( 'Cancel booking', 'axiachat-ai' ); ?></td></tr>
                        </tbody>
                    </table>
                    
                    <h6 class="fw-bold mt-3"><?php esc_html_e( 'Required Fields for Booking', 'axiachat-ai' ); ?></h6>
                    <ul class="small mb-0">
                        <li><code>customer_name</code> - <?php esc_html_e( 'Full name', 'axiachat-ai' ); ?></li>
                        <li><code>customer_email</code> - <?php esc_html_e( 'Email address', 'axiachat-ai' ); ?></li>
                        <li><code>appointment_date</code> - <?php esc_html_e( 'YYYY-MM-DD format', 'axiachat-ai' ); ?></li>
                        <li><code>start_time</code> - <?php esc_html_e( 'HH:MM format (24h)', 'axiachat-ai' ); ?></li>
                    </ul>
                </div>
            </div>
            
            <!-- Troubleshooting Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-wrench me-2"></i>
                    <strong><?php esc_html_e( 'Troubleshooting', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold"><?php esc_html_e( 'No slots showing?', 'axiachat-ai' ); ?></h6>
                    <ul class="small">
                        <li><?php esc_html_e( 'Check that working hours are enabled for that day', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'Verify the date is within max advance days', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'Ensure min advance time hasn\'t passed', 'axiachat-ai' ); ?></li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3"><?php esc_html_e( 'Booking not working?', 'axiachat-ai' ); ?></h6>
                    <ul class="small mb-0">
                        <li><?php esc_html_e( 'Enable the appointment tools in Bot settings', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'Add booking instructions to System Instructions', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'Check the debug log for error details', 'axiachat-ai' ); ?></li>
                    </ul>
                </div>
            </div>
            
            <!-- Plan Limits Card -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-speedometer2 me-2"></i>
                    <strong><?php esc_html_e( 'Plan Limits', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body small">
                    <h6 class="fw-bold"><?php esc_html_e( 'Free Plan', 'axiachat-ai' ); ?></h6>
                    <ul class="mb-3">
                        <li><strong>15 <?php esc_html_e( 'appointments', 'axiachat-ai' ); ?></strong> <?php esc_html_e( 'total', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'All features included', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'Internal calendar', 'axiachat-ai' ); ?></li>
                    </ul>
                    
                    <h6 class="fw-bold"><?php esc_html_e( 'Pro Plan', 'axiachat-ai' ); ?></h6>
                    <ul class="mb-0">
                        <li><strong><?php esc_html_e( 'Unlimited appointments', 'axiachat-ai' ); ?></strong></li>
                        <li><?php esc_html_e( 'Bookly/Amelia integration', 'axiachat-ai' ); ?></li>
                        <li><?php esc_html_e( 'Priority support', 'axiachat-ai' ); ?></li>
                    </ul>
                    
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-settings-pricing' ) ); ?>" class="btn btn-outline-primary btn-sm w-100 mt-3">
                        <i class="bi bi-rocket me-1"></i><?php esc_html_e( 'Upgrade to Pro', 'axiachat-ai' ); ?>
                    </a>
                </div>
            </div>
            
            <!-- Statuses Card -->
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-flag me-2"></i>
                    <strong><?php esc_html_e( 'Appointment Statuses', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body small">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <span class="badge bg-warning text-dark me-2">pending</span>
                            <?php esc_html_e( 'Awaiting confirmation', 'axiachat-ai' ); ?>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-success me-2">confirmed</span>
                            <?php esc_html_e( 'Appointment confirmed', 'axiachat-ai' ); ?>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-secondary me-2">completed</span>
                            <?php esc_html_e( 'Appointment finished', 'axiachat-ai' ); ?>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-danger me-2">cancelled</span>
                            <?php esc_html_e( 'Cancelled by user/admin', 'axiachat-ai' ); ?>
                        </li>
                        <li>
                            <span class="badge bg-dark me-2">no_show</span>
                            <?php esc_html_e( 'Customer did not attend', 'axiachat-ai' ); ?>
                        </li>
                    </ul>
                </div>
            </div>
            
        </div>
    </div>
    <?php
}
