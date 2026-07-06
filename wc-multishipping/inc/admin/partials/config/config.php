<?php defined( 'ABSPATH' ) || exit;

$registration = $view_data['registration'];
$steps = $view_data['steps'];
$current_step = $view_data['current_step'];
$current_step_data = null;
$selected_service_ids = $view_data['selected_service_ids'];
$selected_carriers = $view_data['selected_carriers'];
$selected_services = $view_data['selected_services'];
$zone_choices = $view_data['zone_choices'];
$zone_id = $view_data['zone_id'];
$connection = $view_data['connection'];
$links = $view_data['links'];
$dashboard = $view_data['dashboard'];
$addresses = $view_data['addresses'];
$field_definitions = $view_data['field_definitions'];
$preview_products = $view_data['preview_products'];
$preview_product_id = $view_data['preview_product_id'];
$checkout_preview_opened = ! empty( $view_data['draft']['checkout_preview_opened_at'] ) && (int) ( $view_data['draft']['checkout_preview_product_id'] ?? 0 ) === (int) $preview_product_id;
$total_steps = count( $steps );
$current_step_index = 1;
$completed_steps_count = 0;

foreach ( $steps as $index => $step ) {
    if ( $step['is_complete'] ) {
        $completed_steps_count++;
    }

    if ( $step['is_current'] ) {
        $current_step_data = $step;
        $current_step_index = $index + 1;
    }
}

$progress_percent = $total_steps > 0 ? (int) round( ( $current_step_index / $total_steps ) * 100 ) : 100;
$dashboard_checks = [];
$dashboard_completed_checks = 0;
$chronopost_selected = in_array( 'chronopost', $dashboard['selected_carriers'] ?? [], true );
$mondial_relay_selected = in_array( 'mondial_relay', $dashboard['selected_carriers'] ?? [], true );
$chronopost_ready = 'jwt' === $connection['chronopost']['connection_type']
    ? ! empty( $connection['chronopost']['is_connected'] )
    : ( ! empty( $connection['chronopost']['account_number'] ) && ! empty( $connection['chronopost']['has_saved_password'] ) );
$mondial_relay_ready = ! empty( $connection['mondial_relay']['customer_code'] ) && ! empty( $connection['mondial_relay']['has_saved_private_key'] );
$dashboard_connection_ready = ( $chronopost_selected || $mondial_relay_selected )
    && ( ! $chronopost_selected || $chronopost_ready )
    && ( ! $mondial_relay_selected || $mondial_relay_ready );

if ( ! $view_data['show_wizard'] ) {
    $dashboard_checks = [
        'connection' => [
            'label' => __( 'Carrier access', 'wc-multishipping' ),
            'description' => __( 'Connect the accounts needed to create labels and pickup choices.', 'wc-multishipping' ),
            'complete' => $dashboard_connection_ready,
            'url' => admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=connection' ),
        ],
        'addresses' => [
            'label' => __( 'Sender details', 'wc-multishipping' ),
            'description' => __( 'Complete sender and billing blocks used by the selected carriers.', 'wc-multishipping' ),
            'complete' => ! empty( $dashboard['step_statuses']['addresses'] ),
            'url' => admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=addresses' ),
        ],
        'rates' => [
            'label' => __( 'Shipping rates', 'wc-multishipping' ),
            'description' => ! empty( $dashboard['step_statuses']['rates'] )
                ? __( 'A simple checkout rate is ready. Use WooCommerce for advanced pricing.', 'wc-multishipping' )
                : __( 'Apply a quick 10€ test rate or configure pricing in WooCommerce.', 'wc-multishipping' ),
            'complete' => ! empty( $dashboard['step_statuses']['rates'] ),
            'url' => admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=rates' ),
        ],
        'preview' => [
            'label' => __( 'Checkout preview', 'wc-multishipping' ),
            'description' => __( 'Open a real checkout with one product and verify the delivery methods.', 'wc-multishipping' ),
            'complete' => ! empty( $dashboard['step_statuses']['preview'] ),
            'url' => admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=preview' ),
        ],
    ];

    foreach ( $dashboard_checks as $check ) {
        if ( $check['complete'] ) {
            $dashboard_completed_checks++;
        }
    }
}

$dashboard_total_checks = count( $dashboard_checks );
$connection_carriers = array_values( array_intersect( [ 'chronopost', 'mondial_relay' ], $selected_carriers ) );
$active_connection_carrier = sanitize_key( wp_unslash( $_GET['carrier'] ?? '' ) );
if ( ! in_array( $active_connection_carrier, $connection_carriers, true ) ) {
    $active_connection_carrier = $connection_carriers[0] ?? '';
}
$active_connection_index = array_search( $active_connection_carrier, $connection_carriers, true );
$active_connection_index = false === $active_connection_index ? 0 : (int) $active_connection_index;
$previous_connection_carrier = $connection_carriers[ $active_connection_index - 1 ] ?? '';
$next_connection_carrier = $connection_carriers[ $active_connection_index + 1 ] ?? '';
$connection_carrier_label = 'mondial_relay' === $active_connection_carrier ? __( 'Mondial Relay', 'wc-multishipping' ) : __( 'Chronopost', 'wc-multishipping' );
?>

<div class="wrap wms-config-wrapper">
    <div class="wms-shell<?php echo $view_data['show_wizard'] ? ' wms-shell--wizard' : ' wms-shell--dashboard'; ?>">
        <div class="wms-shell__hero<?php echo $view_data['show_wizard'] ? ' wms-shell__hero--wizard' : ' wms-shell__hero--dashboard'; ?>">
            <div>
                <p class="wms-shell__eyebrow"><?php esc_html_e( 'WCMultiShipping', 'wc-multishipping' ); ?></p>
                <h1><?php echo esc_html( $view_data['show_wizard'] ? ( $view_data['has_existing_setup'] ? __( 'Verify my configuration', 'wc-multishipping' ) : __( 'Configure my shipping setup', 'wc-multishipping' ) ) : __( 'Shipping setup', 'wc-multishipping' ) ); ?></h1>
                <p><?php echo esc_html( $view_data['show_wizard'] ? __( 'Set up carriers, addresses, price ranges, and a live checkout preview without bouncing across WooCommerce screens.', 'wc-multishipping' ) : __( 'Review the essentials, fix what needs work, or run a checkout preview.', 'wc-multishipping' ) ); ?></p>
            </div>
            <div class="wms-shell__actions">
                <a class="button" href="<?php echo esc_url( $links['shipping'] ); ?>"><?php esc_html_e( 'WooCommerce shipping price settings', 'wc-multishipping' ); ?></a>
            </div>
        </div>

        <?php
        ?>

        <?php if ( $view_data['show_wizard'] ) : ?>
            <div class="wms-wizard-layout">
                <aside class="wms-card wms-card--sidebar">
                    <div class="wms-card__header wms-card__header--progress">
                        <div class="wms-progress-copy">
                            <p class="wms-card__kicker"><?php esc_html_e( 'Onboarding', 'wc-multishipping' ); ?></p>
                            <h2><?php echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'wc-multishipping' ), $current_step_index, $total_steps ) ); ?></h2>
                            <div class="wms-progress-current-step">
                                <?php echo esc_html( $current_step_data['label'] ?? '' ); ?>
                            </div>
                        </div>
                        <div class="wms-progress-meta">
                            <strong><?php echo esc_html( $current_step_index . '/' . $total_steps ); ?></strong>
                            <span class="wms-progress-bar" aria-hidden="true">
                                <span style="width: <?php echo esc_attr( $progress_percent ); ?>%;"></span>
                            </span>
                        </div>
                    </div>
                    <ol class="wms-step-list" style="--wms-progress-percent: <?php echo esc_attr( $progress_percent ); ?>%;">
                        <?php foreach ( $steps as $index => $step ) : ?>
                            <li class="wms-step-list__item<?php echo $step['is_current'] ? ' is-current' : ''; ?><?php echo $step['is_complete'] ? ' is-complete' : ''; ?>">
                                <a href="<?php echo esc_url( $step['url'] ); ?>"<?php echo $step['is_current'] ? ' aria-current="step"' : ''; ?>>
                                    <span class="wms-step-list__index"><?php echo esc_html( $index + 1 ); ?></span>
                                    <span class="wms-step-list__copy">
                                        <span class="wms-step-list__meta"><?php echo esc_html( $step['kicker'] ); ?></span>
                                        <strong><?php echo esc_html( $step['label'] ); ?></strong>
                                    </span>
                                    <span class="wms-step-list__status<?php echo $step['is_complete'] ? ' is-complete' : ''; ?>">
                                        <?php
                                        echo esc_html(
                                            $step['is_current']
                                                ? __( 'Current', 'wc-multishipping' )
                                                : ( $step['is_complete'] ? __( 'Done', 'wc-multishipping' ) : __( 'Next', 'wc-multishipping' ) )
                                        );
                                        ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </aside>

                <section class="wms-card wms-card--content">
                    <div class="wms-card__header">
                        <div>
                            <p class="wms-card__kicker"><?php echo esc_html( $current_step_data['kicker'] ?? '' ); ?></p>
                            <h2>
                                <?php
                                echo esc_html(
                                    'connection' === $current_step && $active_connection_carrier
                                        ? sprintf( __( 'Connect %s', 'wc-multishipping' ), $connection_carrier_label )
                                        : ( $current_step_data['title'] ?? '' )
                                );
                                ?>
                            </h2>
                            <?php if ( 'connection' !== $current_step && ! empty( $current_step_data['description'] ) ) : ?>
                                <p><?php echo esc_html( $current_step_data['description'] ?? '' ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( 'activation' === $current_step ) : ?>
                        <?php if ( 'license' === $registration['mode'] ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-step-form wms-step-form--activation">
                                <input type="hidden" name="action" value="wms_save_config">
                                <?php wp_nonce_field( 'wms_save_config' ); ?>

                                <section class="wms-panel wms-panel--plain">
                                    <div class="wms-panel__body">
                                        <label class="wms-form-field">
                                            <span><?php esc_html_e( 'API Key', 'wc-multishipping' ); ?></span>
                                            <input type="text" class="wms-input" name="wms_api_key" id="wms_api_key" value="<?php echo esc_attr( $registration['wms_api_key'] ); ?>" placeholder="WMS_XXXX-XXXX-XXXX-XXXX" required>
                                        </label>
                                        <p class="wms-inline-note">
                                            <?php
                                            printf(
                                                wp_kses(
                                                    __( 'Sent in your order confirmation email, or available from your <a href="%s" target="_blank" rel="noopener noreferrer">WcMultiShipping account</a>.', 'wc-multishipping' ),
                                                    [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                                                ),
                                                esc_url( 'https://www.wcmultishipping.com/fr/mon-compte/view-license-keys/' )
                                            );
                                            ?>
                                        </p>
                                    </div>
                                </section>

                                <div class="wms-form-actions">
                                    <span></span>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save and continue', 'wc-multishipping' ); ?></button>
                                </div>
                            </form>
                        <?php else : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-step-form wms-step-form--activation">
                                <input type="hidden" name="action" value="wms_save_customer_email">
                                <?php wp_nonce_field( 'wms_customer_email_action', 'wms_customer_email_nonce' ); ?>

                                <section class="wms-panel wms-panel--plain">
                                    <div class="wms-panel__body">
                                        <label class="wms-form-field">
                                            <span><?php esc_html_e( 'Support email', 'wc-multishipping' ); ?></span>
                                            <input type="email" class="wms-input" name="wms_email" value="<?php echo esc_attr( $registration['customer_email'] ); ?>" placeholder="<?php esc_attr_e( 'your@email.com', 'wc-multishipping' ); ?>" required>
                                        </label>
                                        <p class="wms-inline-note"><?php esc_html_e( 'This email helps us identify this installation and attach support requests to the right shop.', 'wc-multishipping' ); ?></p>
                                    </div>
                                </section>

                                <div class="wms-form-actions">
                                    <span></span>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Continue to carriers', 'wc-multishipping' ); ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ( 'carriers' === $current_step ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-step-form">
                            <input type="hidden" name="action" value="wms_save_onboarding_step">
                            <input type="hidden" name="step" value="carriers">
                            <?php wp_nonce_field( 'wms_onboarding_action', 'wms_onboarding_nonce' ); ?>

                            <div class="wms-carrier-grid">
                                <label class="wms-carrier-choice">
                                    <input type="checkbox" data-carrier-toggle="chronopost" <?php checked( in_array( 'chronopost', $selected_carriers, true ) ); ?>>
                                    <span class="wms-carrier-choice__check" aria-hidden="true"></span>
                                    <span class="wms-carrier-choice__body">
                                        <strong><?php esc_html_e( 'Chronopost', 'wc-multishipping' ); ?></strong>
                                        <span><?php esc_html_e( 'Home delivery, pickup relay, and Chronopost PRO connection.', 'wc-multishipping' ); ?></span>
                                    </span>
                                    <?php foreach ( $view_data['services'] as $service_id => $service ) : ?>
                                        <?php if ( 'chronopost' !== $service['carrier'] ) { continue; } ?>
                                        <?php if ( 'chronopost_relais' !== $service_id ) { continue; } ?>
                                        <input type="hidden" class="wms-carrier-choice__service" name="selected_services[]" value="<?php echo esc_attr( $service_id ); ?>" data-service-carrier="chronopost" <?php disabled( ! in_array( 'chronopost', $selected_carriers, true ) ); ?>>
                                    <?php endforeach; ?>
                                </label>

                                <label class="wms-carrier-choice">
                                    <input type="checkbox" data-carrier-toggle="mondial_relay" <?php checked( in_array( 'mondial_relay', $selected_carriers, true ) ); ?>>
                                    <span class="wms-carrier-choice__check" aria-hidden="true"></span>
                                    <span class="wms-carrier-choice__body">
                                        <strong><?php esc_html_e( 'Mondial Relay', 'wc-multishipping' ); ?></strong>
                                        <span><?php esc_html_e( 'Relay points and lockers with native pickup selection.', 'wc-multishipping' ); ?></span>
                                    </span>
                                    <?php foreach ( $view_data['services'] as $service_id => $service ) : ?>
                                        <?php if ( 'mondial_relay' !== $service['carrier'] ) { continue; } ?>
                                        <?php if ( 'mondial_relay_point_relais' !== $service_id ) { continue; } ?>
                                        <input type="hidden" class="wms-carrier-choice__service" name="selected_services[]" value="<?php echo esc_attr( $service_id ); ?>" data-service-carrier="mondial_relay" <?php disabled( ! in_array( 'mondial_relay', $selected_carriers, true ) ); ?>>
                                    <?php endforeach; ?>
                                </label>
                            </div>

                            <div class="wms-form-actions">
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Continue to connection', 'wc-multishipping' ); ?></button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ( 'connection' === $current_step ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-step-form" id="wms-onboarding-connection-form">
                            <input type="hidden" name="action" value="wms_save_onboarding_step">
                            <input type="hidden" name="step" value="connection">
                            <input type="hidden" name="connection_carrier" value="<?php echo esc_attr( $active_connection_carrier ); ?>">
                            <?php wp_nonce_field( 'wms_onboarding_action', 'wms_onboarding_nonce' ); ?>

                            <div class="wms-form-error" id="wms-onboarding-connection-error" hidden aria-live="polite"></div>

                            <?php if ( 'chronopost' === $active_connection_carrier ) : ?>
                                <section class="wms-panel wms-panel--plain">
                                    <div class="wms-panel__body">
                                        <label class="wms-form-field wms-form-field--compact wms-select-control">
                                            <span><?php esc_html_e( 'Connection type', 'wc-multishipping' ); ?></span>
                                            <span class="wms-select-control__wrap">
                                                <select id="wms_chronopost_connection_type" name="wms_chronopost_connection_type" class="wms-input">
                                                    <option value="soap" <?php selected( 'soap', $connection['chronopost']['connection_type'] ); ?>><?php esc_html_e( 'Classic credentials', 'wc-multishipping' ); ?></option>
                                                    <option value="jwt" <?php selected( 'jwt', $connection['chronopost']['connection_type'] ); ?>><?php esc_html_e( 'Chronopost PRO', 'wc-multishipping' ); ?></option>
                                                </select>
                                            </span>
                                        </label>

                                        <div class="wms-connection-panels">
                                            <div data-chronopost-panel="soap" <?php echo 'jwt' === $connection['chronopost']['connection_type'] ? 'hidden' : ''; ?>>
                                                <div class="wms-form-grid">
                                                    <label class="wms-form-field">
                                                        <span><?php esc_html_e( 'Account number', 'wc-multishipping' ); ?></span>
                                                        <input type="text" class="wms-input" name="wms_chronopost_account_number" id="wms_chronopost_account_number" value="<?php echo esc_attr( $connection['chronopost']['account_number'] ); ?>">
                                                    </label>
                                                    <label class="wms-form-field">
                                                        <span><?php esc_html_e( 'Account name', 'wc-multishipping' ); ?></span>
                                                        <input type="text" class="wms-input" name="wms_chronopost_account_name" id="wms_chronopost_account_name" value="<?php echo esc_attr( $connection['chronopost']['account_name'] ); ?>">
                                                    </label>
                                                    <label class="wms-form-field">
                                                        <span><?php esc_html_e( 'Password', 'wc-multishipping' ); ?></span>
                                                        <input type="text" class="wms-input" name="wms_chronopost_account_password" id="wms_chronopost_account_password" value="<?php echo esc_attr( $connection['chronopost']['account_password'] ); ?>">
                                                    </label>
                                                </div>
                                                <div class="wms-inline-note">
                                                    <?php echo wp_kses_post( \WCMultiShipping\inc\admin\classes\chronopost\chronopost_settings::get_test_credentials_html() ); ?>
                                                </div>
                                                <div class="wms-inline-actions">
                                                    <button type="button" class="button" id="wms_chronopost_account_test_credentials"><?php esc_html_e( 'Test credentials', 'wc-multishipping' ); ?></button>
                                                    <span class="wms-inline-result" id="wms-chronopost-test-result" aria-live="polite"></span>
                                                </div>
                                            </div>

                                            <div data-chronopost-panel="pro" <?php echo 'jwt' !== $connection['chronopost']['connection_type'] ? 'hidden' : ''; ?>>
                                                <?php
                                                if ( ! empty( $connection['chronopost']['portal_error'] ) ) {
                                                    echo '<div class="wms-banner wms-banner--error"><span>' . esc_html( $connection['chronopost']['portal_error'] ) . '</span></div>';
                                                }
                                                echo wp_kses_post(
                                                    \WCMultiShipping\inc\admin\classes\chronopost\chronopost_settings::get_pro_section_html(
                                                        $connection['chronopost']['is_connected'],
                                                        $connection['chronopost']['has_keys'],
                                                        $connection['chronopost']['refresh_expired'],
                                                        $connection['chronopost']['portal_uri'],
                                                        $connection['chronopost']['portal_payload']
                                                    )
                                                );
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <?php if ( 'mondial_relay' === $active_connection_carrier ) : ?>
                                <section class="wms-panel wms-panel--plain">
                                    <div class="wms-panel__body">
                                        <div class="wms-form-grid">
                                            <label class="wms-form-field">
                                                <span><?php esc_html_e( 'Customer code', 'wc-multishipping' ); ?></span>
                                                <input type="text" class="wms-input" name="wms_mondial_relay_customer_code" id="wms_mondial_relay_customer_code" value="<?php echo esc_attr( $connection['mondial_relay']['customer_code'] ); ?>">
                                            </label>
                                            <label class="wms-form-field">
                                                <span><?php esc_html_e( 'Private key', 'wc-multishipping' ); ?></span>
                                                <input type="text" class="wms-input" name="wms_mondial_relay_private_key" id="wms_mondial_relay_private_key" value="<?php echo esc_attr( $connection['mondial_relay']['private_key'] ); ?>">
                                            </label>
                                            <label class="wms-form-field">
                                                <span><?php esc_html_e( 'Brand code', 'wc-multishipping' ); ?></span>
                                                <input type="text" class="wms-input" name="wms_mondial_relay_brand_code" id="wms_mondial_relay_brand_code" value="<?php echo esc_attr( $connection['mondial_relay']['brand_code'] ); ?>">
                                            </label>
                                        </div>
                                        <div class="wms-inline-note">
                                            <?php echo wp_kses_post( \WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_settings::get_test_credentials_html() ); ?>
                                        </div>
                                        <div class="wms-inline-actions">
                                            <button type="button" class="button" id="wms_mondial_relay_account_test_credentials"><?php esc_html_e( 'Test Mondial Relay credentials', 'wc-multishipping' ); ?></button>
                                            <span class="wms-inline-result" id="wms-mondial-relay-test-result" aria-live="polite"></span>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <div class="wms-form-actions">
                                <a class="button" href="<?php echo esc_url( $previous_connection_carrier ? admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=connection&carrier=' . $previous_connection_carrier ) : admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=carriers' ) ); ?>"><?php esc_html_e( 'Back', 'wc-multishipping' ); ?></a>
                                <button type="submit" class="button button-primary" id="wms-onboarding-connection-submit">
                                    <?php echo esc_html( $next_connection_carrier ? __( 'Save and continue', 'wc-multishipping' ) : __( 'Continue to addresses', 'wc-multishipping' ) ); ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ( 'addresses' === $current_step ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-step-form wms-step-form--addresses">
                            <input type="hidden" name="action" value="wms_save_onboarding_step">
                            <input type="hidden" name="step" value="addresses">
                            <?php wp_nonce_field( 'wms_onboarding_action', 'wms_onboarding_nonce' ); ?>

                            <?php if ( ! empty( $selected_carriers ) ) : ?>
                                <section class="wms-panel">
                                    <div class="wms-panel__header">
                                        <div>
                                            <h3><?php esc_html_e( 'Sender details', 'wc-multishipping' ); ?></h3>
                                        </div>
                                    </div>
                                    <div class="wms-panel__body">
                                        <div class="wms-form-grid" data-address-group="wms_sender">
                                            <?php foreach ( $field_definitions['sender'] as $field_id => $field ) : ?>
                                                <label class="wms-form-field">
                                                    <span><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? ' *' : ''; ?></span>
                                                    <?php if ( isset( $field['type'] ) && 'select' === $field['type'] ) : ?>
                                                        <select class="wms-input" name="<?php echo esc_attr( $field_id ); ?>">
                                                            <?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
                                                                <option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $addresses[ $field_id ], $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <input type="<?php echo isset( $field['type'] ) && 'email' === $field['type'] ? 'email' : 'text'; ?>" class="wms-input" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $addresses[ $field_id ] ); ?>">
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <?php if ( in_array( 'chronopost', $selected_carriers, true ) ) : ?>
                                <section class="wms-panel">
                                    <div class="wms-panel__header">
                                        <div>
                                            <h3><?php esc_html_e( 'Billing details', 'wc-multishipping' ); ?></h3>
                                        </div>
                                        <button type="button" class="button button-secondary" data-copy-fields="wms_sender" data-copy-target="wms_chronopost_customer"><?php esc_html_e( 'Use sender as billing', 'wc-multishipping' ); ?></button>
                                    </div>
                                    <div class="wms-panel__body">
                                        <div class="wms-form-grid" data-address-group="wms_chronopost_customer">
                                            <?php foreach ( $field_definitions['chronopost_customer'] as $field_id => $field ) : ?>
                                                <label class="wms-form-field">
                                                    <span><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? ' *' : ''; ?></span>
                                                    <?php if ( isset( $field['type'] ) && 'select' === $field['type'] ) : ?>
                                                        <select class="wms-input" name="<?php echo esc_attr( $field_id ); ?>">
                                                            <?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
                                                                <option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $addresses[ $field_id ], $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <input type="<?php echo isset( $field['type'] ) && 'email' === $field['type'] ? 'email' : 'text'; ?>" class="wms-input" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $addresses[ $field_id ] ); ?>">
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <div class="wms-form-actions">
                                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=connection' ) ); ?>"><?php esc_html_e( 'Back', 'wc-multishipping' ); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Continue to rates', 'wc-multishipping' ); ?></button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ( 'rates' === $current_step ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-step-form">
                            <input type="hidden" name="action" value="wms_save_onboarding_step">
                            <input type="hidden" name="step" value="rates">
                            <?php wp_nonce_field( 'wms_onboarding_action', 'wms_onboarding_nonce' ); ?>

                            <section class="wms-panel wms-panel--plain wms-panel--rate-setup">
                                <div class="wms-panel__body">
                                    <input type="hidden" name="zone_id" value="<?php echo esc_attr( $zone_id ); ?>">

                                    <p class="wms-rate-intro"><?php esc_html_e( 'We will apply a default 10€ shipping price so you can test the plugin right away.', 'wc-multishipping' ); ?></p>
                                    <p class="wms-rate-help">
                                        <?php
                                        printf(
                                            wp_kses(
                                                __( 'You can adjust your real shipping prices later from the <a href="%s">WooCommerce shipping price settings</a>.', 'wc-multishipping' ),
                                                [ 'a' => [ 'href' => [] ] ]
                                            ),
                                            esc_url( $links['shipping'] )
                                        );
                                        ?>
                                    </p>
                                </div>
                            </section>

                            <div class="wms-form-actions">
                                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-multishipping&view=wizard&step=addresses' ) ); ?>"><?php esc_html_e( 'Back', 'wc-multishipping' ); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Apply test rates and continue', 'wc-multishipping' ); ?></button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ( 'preview' === $current_step ) : ?>
                        <div class="wms-step-form wms-step-form--preview">
	                            <section class="wms-panel wms-panel--plain wms-preview-test-card">
	                                <div class="wms-panel__body">
	                                    <div class="wms-preview-test-card__header">
	                                        <p><?php esc_html_e( 'Open a real checkout to confirm the shipping methods and pickup selectors are displayed correctly.', 'wc-multishipping' ); ?></p>
	                                    </div>

	                                    <?php if ( empty( $preview_products ) ) : ?>
	                                        <div class="wms-banner wms-banner--error">
	                                            <span><?php esc_html_e( 'No compatible product was found. Add a shippable product or variation with a weight before using the checkout preview.', 'wc-multishipping' ); ?></span>
	                                        </div>
                                    <?php else : ?>
                                        <div class="wms-preview-test-card__content">
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-dashboard-preview-form wms-dashboard-preview-form--wizard" id="wms-onboarding-preview-form" target="_blank" rel="noopener">
                                                <input type="hidden" name="action" value="wms_onboarding_preview">
                                                <?php wp_nonce_field( 'wms_onboarding_action', 'wms_onboarding_nonce' ); ?>
                                                <div class="wms-preview-inline-row">
                                                    <label class="wms-form-field wms-form-field--wide">
                                                        <span><?php esc_html_e( 'Product', 'wc-multishipping' ); ?></span>
                                                        <select class="wms-input" name="preview_product_id" id="wms-onboarding-preview-product">
                                                            <?php foreach ( $preview_products as $product ) : ?>
                                                                <option value="<?php echo esc_attr( $product['id'] ); ?>" <?php selected( (int) $preview_product_id, (int) $product['id'] ); ?>><?php echo esc_html( $product['label'] ); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <button type="submit" class="button"><?php esc_html_e( 'Open checkout preview', 'wc-multishipping' ); ?></button>
                                                </div>
                                                <p class="wms-preview-help">
                                                    <span id="wms-preview-required-hint">
                                                        <?php echo esc_html( $checkout_preview_opened ? __( 'Checkout preview opened. You can finish onboarding.', 'wc-multishipping' ) : __( 'Open the checkout preview before finishing onboarding.', 'wc-multishipping' ) ); ?>
                                                    </span>
                                                    <span>
                                                        <?php esc_html_e( 'Can’t see the pickup selectors?', 'wc-multishipping' ); ?>
                                                        <a href="<?php echo esc_url( $links['support'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Contact support', 'wc-multishipping' ); ?></a>
                                                    </span>
                                                </p>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <div class="wms-form-actions">
                                        <span></span>

                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-inline-form">
                                            <input type="hidden" name="action" value="wms_onboarding_complete">
                                            <input type="hidden" name="preview_product_id" id="wms-onboarding-complete-preview-product" value="<?php echo esc_attr( $preview_product_id ); ?>">
                                            <?php wp_nonce_field( 'wms_onboarding_action', 'wms_onboarding_nonce' ); ?>
                                            <button type="submit" class="button button-primary" id="wms-onboarding-finish-button" <?php disabled( ! $checkout_preview_opened ); ?>><?php esc_html_e( 'Finish onboarding', 'wc-multishipping' ); ?></button>
                                        </form>
                                    </div>
                                </div>
                            </section>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php elseif ( ! $view_data['show_wizard'] ) : ?>
            <div class="wms-dashboard">
                <div class="wms-dashboard-layout">
                    <main class="wms-dashboard-main">
                        <section class="wms-dashboard-panel">
                            <div class="wms-dashboard-panel__header">
                                <div>
                                    <p class="wms-dashboard-label"><?php esc_html_e( 'Setup status', 'wc-multishipping' ); ?></p>
                                    <h2><?php echo esc_html( sprintf( __( '%1$d of %2$d ready', 'wc-multishipping' ), $dashboard_completed_checks, $dashboard_total_checks ) ); ?></h2>
                                </div>
                                <a class="button button-secondary" href="<?php echo esc_url( $links['wizard'] ); ?>"><?php esc_html_e( 'Review setup', 'wc-multishipping' ); ?></a>
                            </div>
                            <div class="wms-dashboard-checklist">
                                <?php foreach ( $dashboard_checks as $check ) : ?>
                                    <a class="wms-dashboard-check<?php echo $check['complete'] ? ' is-complete' : ' is-open'; ?>" href="<?php echo esc_url( $check['url'] ); ?>">
                                        <span class="wms-status-dot" aria-hidden="true"></span>
                                        <span>
                                            <strong><?php echo esc_html( $check['label'] ); ?></strong>
                                            <small><?php echo esc_html( $check['description'] ); ?></small>
                                        </span>
                                        <span class="wms-dashboard-badge"><?php echo esc_html( $check['complete'] ? __( 'Ready', 'wc-multishipping' ) : __( 'Needs work', 'wc-multishipping' ) ); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </main>

                    <aside class="wms-dashboard-side">
                        <section class="wms-dashboard-panel wms-dashboard-panel--preview">
                            <div class="wms-dashboard-panel__header">
                                <div>
                                    <p class="wms-dashboard-label"><?php esc_html_e( 'Preview', 'wc-multishipping' ); ?></p>
                                    <h2><?php esc_html_e( 'Checkout test', 'wc-multishipping' ); ?></h2>
                                    <p><?php esc_html_e( 'Open a real checkout to confirm the shipping methods and pickup selectors are displayed correctly.', 'wc-multishipping' ); ?></p>
                                </div>
                            </div>
                            <?php if ( empty( $preview_products ) ) : ?>
                                <div class="wms-dashboard-empty">
                                    <strong><?php esc_html_e( 'No preview product', 'wc-multishipping' ); ?></strong>
                                    <p><?php esc_html_e( 'Add one simple shippable product with a weight to test checkout.', 'wc-multishipping' ); ?></p>
                                </div>
                            <?php else : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wms-dashboard-preview-form" target="_blank" rel="noopener">
                                    <input type="hidden" name="action" value="wms_onboarding_preview">
                                    <?php wp_nonce_field( 'wms_onboarding_action', 'wms_onboarding_nonce' ); ?>
                                    <label class="wms-form-field wms-form-field--wide">
                                        <span><?php esc_html_e( 'Product', 'wc-multishipping' ); ?></span>
                                        <select class="wms-input" name="preview_product_id">
                                            <?php foreach ( $preview_products as $product ) : ?>
                                                <option value="<?php echo esc_attr( $product['id'] ); ?>" <?php selected( (int) $preview_product_id, (int) $product['id'] ); ?>><?php echo esc_html( $product['label'] ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Open checkout preview', 'wc-multishipping' ); ?></button>
                                </form>
                            <?php endif; ?>
                        </section>

                        <section class="wms-dashboard-panel wms-telemetry-card">
                            <div class="wms-dashboard-panel__header">
                                <div>
                                    <p class="wms-dashboard-label"><?php esc_html_e( 'Privacy', 'wc-multishipping' ); ?></p>
                                    <h2><?php esc_html_e( 'Technical diagnostics', 'wc-multishipping' ); ?></h2>
                                    <p><?php esc_html_e( 'Minimal diagnostics for onboarding, carrier setup, and checkout previews are always enabled to help prioritize fixes.', 'wc-multishipping' ); ?></p>
                                </div>
                            </div>
                            <div class="wms-telemetry-form">
                                <ul class="wms-telemetry-list">
                                    <li><?php esc_html_e( 'Never sends emails, raw license keys, site URLs, carrier credentials, order data, or logs.', 'wc-multishipping' ); ?></li>
                                    <li><?php esc_html_e( 'Uses a technical installation ID only for diagnostics.', 'wc-multishipping' ); ?></li>
                                </ul>
                                <div class="wms-telemetry-actions">
                                    <span class="wms-telemetry-status is-enabled">
                                        <?php esc_html_e( 'Always enabled', 'wc-multishipping' ); ?>
                                    </span>
                                </div>
                            </div>
                        </section>

                    </aside>
                </div>

                <section class="wms-dashboard-panel wms-dashboard-panel--links wms-dashboard-panel--full">
                    <div class="wms-dashboard-panel__header wms-dashboard-panel__header--compact">
                        <div>
                            <p class="wms-dashboard-label"><?php esc_html_e( 'Actions', 'wc-multishipping' ); ?></p>
                            <h2><?php esc_html_e( 'Shortcuts', 'wc-multishipping' ); ?></h2>
                        </div>
                    </div>
                    <div class="wms-dashboard-actions-list">
                        <a class="wms-dashboard-action-link" href="<?php echo esc_url( $links['chronopost'] ); ?>">
                            <span class="wms-dashboard-action-link__icon" aria-hidden="true">CP</span>
                            <span>
                                <strong><?php esc_html_e( 'Chronopost settings', 'wc-multishipping' ); ?></strong>
                                <small><?php esc_html_e( 'Open the advanced Chronopost configuration.', 'wc-multishipping' ); ?></small>
                            </span>
                        </a>
                        <a class="wms-dashboard-action-link" href="<?php echo esc_url( $links['mondial_relay'] ); ?>">
                            <span class="wms-dashboard-action-link__icon" aria-hidden="true">MR</span>
                            <span>
                                <strong><?php esc_html_e( 'Mondial Relay settings', 'wc-multishipping' ); ?></strong>
                                <small><?php esc_html_e( 'Open the advanced Mondial Relay configuration.', 'wc-multishipping' ); ?></small>
                            </span>
                        </a>
                        <a class="wms-dashboard-action-link" href="<?php echo esc_url( $links['shipping'] ); ?>">
                            <span class="wms-dashboard-action-link__icon" aria-hidden="true">WC</span>
                            <span>
                                <strong><?php esc_html_e( 'WooCommerce shipping price settings', 'wc-multishipping' ); ?></strong>
                                <small><?php esc_html_e( 'Manage zones, methods, and detailed price rules.', 'wc-multishipping' ); ?></small>
                            </span>
                        </a>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
</div>
