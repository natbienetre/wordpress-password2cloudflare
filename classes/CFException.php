<?php
class CFException extends Exception {
    const NO_ROUTE = 7000;
    const MISSING_CREDENTIALS = 9106;
    const AUTHENTICATION = 10000;
    const UNKNOWN_ERROR = 8000000;
    const PROJECT_NOT_FOUND = 8000007;

    public static function from_response( array $response ): CFException {
        $code = $response['errors'][0]['code'];
        $message = $response['errors'][0]['message'];

        switch ( $code ) {
            case self::NO_ROUTE:
            case self::UNKNOWN_ERROR:
            case self::MISSING_CREDENTIALS:
                return new CFPluginException( $message, $code );
            case self::AUTHENTICATION:
                return new CFConfigurationException( $message, $code, 'cf_account_id', 'cf_api_key' );
            case self::PROJECT_NOT_FOUND:
                return new CFConfigurationException( $message, $code, 'cf_project_name' );
            default:
                /* translators: %1$d is the error code, %2$s is the message of the root error */
                return new CFException( sprintf( __( 'Unknown error (code %1$d): %2$s', 'p2cf' ), $code, $message ), $code );
        }
    }

    public function handle( ?string $context = null ) {
        if ( wp_doing_ajax() ) {
            wp_send_json_error( $message, 500 );
            return;
        }

        $screen = get_current_screen();
        if ( $screen->in_admin() ) {
            add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
            return;
        }

        throw $this;
    }

    public function display_admin_notice() {
        ?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $this->getMessage() ); ?></p>
            </div>
        <?php
    }
}

class CFConfigurationException extends CFException {
    public array $setting_field;

    public function __construct( string $message, int $code, string ...$settings ) {        
        /* translators: %s is the message of the root error */
        parent::__construct( sprintf( __( 'Configuration error: %s', 'p2cf' ), $message ), $code );

        $this->setting_field = $settings;
    }

    protected function build_message( ?string $context ): string {
        if ( ! $context ) {
            return $this->getMessage();
        }

        return sprintf( _x( '%s: %s', 'error message: root cause', 'p2cf2' ), $context, $this->getMessage() );
    }

    public function handle( ?string $context = null ) {
        $screen = get_current_screen();

        if ( $screen->in_admin() ) {
            if ( $screen->id == 'options' ) {
                add_settings_error( P2CFOptions::OPTION_NAME, $this->setting_field[0], $this->build_message( $context ) );
                return;
            }
            switch ( $screen->parent_base ) {
                case 'options-general':
                    if ( $screen->id == P2CFAdminPage::PAGE_ID ) {
                        add_settings_error( P2CFOptions::OPTION_NAME, $this->setting_field[0], $this->build_message( $context ) );
                        return;
                    }

                    throw $this;
                case 'admin-actions':
                    header( 'Location:' . $_SERVER["HTTP_REFERER"] . '&' . urlencode( P2CFAdminPage::STATUS_PARAM_NAME ) . '=' . urlencode( self::CHECK_FAILED_STATUS ) . '&' . urlencode( P2CFAdminPage::MESSAGE_PARAM_NAME ) . '=' . urlencode( $this->build_message( $context ) ) );

                    throw $this;
                case null:
                    if ( $screen->id == 'options' ) {
                        add_action( 'admin_notices', array( new AdminNoticeForConfiguration( $this->build_message( $context ) ), 'display_admin_notice' ) );
                        return;
                    }
                default:
                    add_action( 'admin_notices', array( new AdminNoticeForConfiguration( $this->build_message( $context ) ), 'display_admin_notice' ) );
                    return;
            }
        }

        parent::handle();
    }
}

class AdminNoticeForConfiguration {
    public string $message;

    public function __construct( string $message ) {
        $this->message = $message;
    }

    public function display_admin_notice() {
        $setting_url = admin_url( 'options-general.php?page=' . P2CFAdminPage::PAGE_ID );
        ?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $this->message ); ?></p>
                <p><a href="<?php echo esc_attr( $setting_url ); ?>"><?php esc_html_e( 'Go to settings', 'p2cf' ); ?></a></p>
            </div>
        <?php
    }
}
