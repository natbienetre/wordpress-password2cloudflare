<?php
class P2CFAdminPage {
    const OPTIONS_PAGE_ID         = 'p2cf-options';
    const SYNC_VARS_PAGE_ID       = 'p2cf-sync-env-vars';
    const DELETE_ENV_VARS_PAGE_ID = 'p2cf-delete-env-vars';

    const GENERAL_SECTION         = 'general';
    const SYNC_VARS_SECTION       = 'sync_vars';
    const CLOUDFLARE_SECTION      = 'cloudflare';
    const DELETE_ENV_VARS_SECTION = 'delete_env_vars';
    
    const SYNC_VARS_ACTION     = 'p2cf-sync-env-vars';
    const DELETE_VARS_ACTION   = 'p2cf-delete-env-vars';
    const CHECK_OPTIONS_ACTION = 'p2cf-options-check';

    const DELETE_VARS_PREFIX_INPUT = 'old_prefix';

    const STATUS_PARAM_NAME  = 'status';
    const MESSAGE_PARAM_NAME = 'message';

    const TRUNCATED_STATUS              = 'truncated';
    const NONCE_FAILED_STATUS           = 'nonce_failed';
    const UNAUTHORIZED_STATUS           = 'unauthorized';
    const SYNCHRONIZED_STATUS           = 'synchronized';
    const CHECK_FAILED_STATUS           = 'check_failed';
    const INVALID_PREFIX_STATUS         = 'invalid_prefix';
    const CHECK_SUCCEEDED_STATUS        = 'check_succeeded';
    const SYNCHRONIZATION_FAILED_STATUS = 'sync_failed';

    private string $load_hook_suffix;

    public static function register_hooks() {
        $instance = new self();

        add_action( 'admin_menu', array( $instance, 'add_admin_menu' ) );

        add_action( 'admin_init', array( $instance, 'settings_init' ) );
        add_action( 'admin_init', array( $instance, 'sync_vars_init' ) );
        add_action( 'admin_init', array( $instance, 'delete_env_vars_init' ) );

        add_action( 'admin_post_' . self::SYNC_VARS_ACTION, array( $instance, 'sync_env_vars' ) );
        add_action( 'admin_post_' . self::DELETE_VARS_ACTION, array( $instance, 'delete_env_vars' ) );

        add_action( 'wp_ajax_' . self::CHECK_OPTIONS_ACTION, array( $instance, 'check_options' ) );

        add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_admin_script' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( P2CF_PLUGIN_FILE ), array( $instance, 'plugin_settings' ) );
    }

    function plugin_settings( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( $this->get_url() ) . '&amp;sub=options">' . __( 'Settings', 'p2cf' ) . '</a>' );

        return $links;
	}

    public function get_url( array $params = array() ): string {
        $params['page'] = 'password-sync-to-cloudflare';

        return admin_url( 'options-general.php?' . http_build_query( $params ) );
    }

    function enqueue_admin_script( string $hook ) {
        if ( $hook != $this->load_hook_suffix ) {
            return;
        }

        wp_enqueue_script( 'p2cf-admin-settings', plugin_dir_url( P2CF_PLUGIN_FILE ) . '/js/admin-settings.js', array(
            'jquery',
            'jquery-ui-tooltip',
            'wp-i18n',
        ), '1.0' );

        wp_enqueue_style( 'p2cf-admin-settings', plugin_dir_url( P2CF_PLUGIN_FILE ) . '/css/admin-settings.css' );

        wp_set_script_translations( 'p2cf-admin-settings', 'p2cf', plugin_dir_path( P2CF_PLUGIN_FILE ) . 'languages/' );
    }

    function load_page() {
        $status = sanitize_text_field( $_GET[ self::STATUS_PARAM_NAME ] ?? '' );
        switch ( $status ) {
            case self::NONCE_FAILED_STATUS:
                add_settings_error( P2CFOptions::OPTION_NAME, $status, __( 'Security check failed. Please retry.', 'p2cf' ), 'error' );
                break;
            case self::UNAUTHORIZED_STATUS:
                add_settings_error( P2CFOptions::OPTION_NAME, $status, __( 'You are not authorized to perform this action.', 'p2cf' ), 'error' );
                break;
            case self::TRUNCATED_STATUS:
                $nb = sanitize_text_field( $_GET[ self::MESSAGE_PARAM_NAME ] ?? 0 );
                if ( ! $nb ) {
                    add_settings_error( P2CFOptions::OPTION_NAME, $status, __( 'No environment variable to delete.', 'p2cf' ), 'updated' );
                    break;
                }
                /* translators: %d is the number of environment variables that were deleted */
                add_settings_error( P2CFOptions::OPTION_NAME, $status, sprintf( _n( '%d environment variable deleted.', '%d environment variables deleted.', $nb, 'p2cf' ), $nb ), 'updated' );
                break;
            case self::SYNCHRONIZED_STATUS:
                add_settings_error( P2CFOptions::OPTION_NAME, $status, __( 'Environment variables synchronized.', 'p2cf' ), 'updated' );
                break;
            case self::SYNCHRONIZATION_FAILED_STATUS:
                add_settings_error( P2CFOptions::OPTION_NAME, $status, __( 'Environment variables synchronization failed.', 'p2cf' ), 'error' );
                break;
            case self::INVALID_PREFIX_STATUS:
                $prefix = sanitize_text_field( $_GET[ self::MESSAGE_PARAM_NAME ] ?? '' );
                /* translators: %s is the prefix of the environment variable  that was invalid */
                add_settings_error( P2CFOptions::OPTION_NAME, $status, sprintf( __( 'Invalid prefix %s.', 'p2cf' ), $prefix ), 'error' );
                break;
            case self::CHECK_FAILED_STATUS:
                $message = sanitize_text_field( $_GET[ self::MESSAGE_PARAM_NAME ] ?? 'Unknown error.' );
                /* translators: %s is the message of the root error */
                add_settings_error( P2CFOptions::OPTION_NAME, $status, sprintf( __( 'Error while checking settings: %s', 'p2cf' ), $message ), 'error' );
                break;
            case self::CHECK_SUCCEEDED_STATUS:
                add_settings_error( P2CFOptions::OPTION_NAME, $status, __( 'Settings are valid.', 'p2cf' ), 'updated' );
                break;
            case '':
                break;
            default:
                add_settings_error( P2CFOptions::OPTION_NAME, $status, __( 'Unknown status.', 'p2cf' ), 'error' );
                break;
        }
    }
    
    function add_admin_menu() {
        $this->load_hook_suffix = add_options_page(
            __( 'Password 2 Cloudflare', 'p2cf' ),
            __( 'Password 2 Cloudflare', 'p2cf' ),
            'manage_options',
            'password-sync-to-cloudflare',
            array( $this, 'options_page' ),
        );

        add_action( 'load-' . $this->load_hook_suffix, array( $this, 'load_page' ) );
    }

    public function delete_env_vars(){
        $nonce = sanitize_text_field( @$_POST['_wpnonce'] );

        if( ! wp_verify_nonce( $nonce, self::DELETE_VARS_ACTION ) ){
            wp_redirect( $this->get_url( array(
                self::STATUS_PARAM_NAME => self::NONCE_FAILED_STATUS,
            ) ) );
            exit;
        }

        if( ! current_user_can( 'administrator' ) ){
            wp_redirect( $this->get_url( array(
                self::STATUS_PARAM_NAME => self::UNAUTHORIZED_STATUS,
            ) ) );
            exit;
        }

        $prefix = $_POST[ self::DELETE_VARS_PREFIX_INPUT ];

        if ( empty( $prefix ) ) {
            wp_redirect( $this->get_url( array(
                self::STATUS_PARAM_NAME  => self::INVALID_PREFIX_STATUS,
                self::MESSAGE_PARAM_NAME => $prefix,
            ) ) );
            exit;
        }

        try {
            $vars = CFProject::load()->delete_all_env_var( $prefix );
            wp_redirect( $this->get_url( array(
                self::STATUS_PARAM_NAME  => self::TRUNCATED_STATUS,
                self::MESSAGE_PARAM_NAME => count( $vars ),
            ) ) );
            exit;
        } catch ( CFException $e ) {
            $e->raise( __( 'Error while deleting environment variables', 'p2cf' ) );
            return;
        }
    }
    
    public function sync_env_vars(){
        $nonce = sanitize_text_field( @$_POST['_wpnonce'] );

        if( ! wp_verify_nonce( $nonce, self::SYNC_VARS_ACTION ) ){
            wp_redirect( $this->get_url( array(
                self::STATUS_PARAM_NAME => self::NONCE_FAILED_STATUS,
            ) ) );
            exit;
        }

        if( ! current_user_can( 'administrator' ) ){
            wp_redirect( $this->get_url( array(
                self::STATUS_PARAM_NAME => self::UNAUTHORIZED_STATUS,
            ) ) );
            exit;
        }

        try {
            $nb = p2cf_sync_all();
            wp_redirect( $this->get_url( array(
                self::STATUS_PARAM_NAME  => self::SYNCHRONIZED_STATUS,
                self::MESSAGE_PARAM_NAME => $nb,
            ) ) );
            exit;
        } catch ( CFException $e ) {
            $e->raise( __( 'Error while synchronizing environment variables', 'p2cf' ) );
            return;
        }
    }

    public function check_options() {
        $nonce = sanitize_text_field( @$_POST['_wpnonce'] );

        if( ! wp_verify_nonce( $nonce, P2CFOptions::OPTION_NAME . '-options' ) ){
            wp_send_json_error( array(
                self::STATUS_PARAM_NAME => self::NONCE_FAILED_STATUS,
            ) );
            return;
        }

        if( ! current_user_can( 'administrator' ) ){
            wp_send_json_error( array(
                self::STATUS_PARAM_NAME => self::UNAUTHORIZED_STATUS,
            ) );
            return;
        }

        $opts = new P2CFOptions( $_POST[ P2CFOptions::OPTION_NAME ] );
        $client = new CFClient( $opts->cf_api_key );

        try {
            $project = $client->get_project( $opts->cf_account_id, $opts->cf_project_name );
        } catch ( CFException $e ) {
            wp_send_json_error( array(
                self::STATUS_PARAM_NAME  => self::CHECK_FAILED_STATUS,
                self::MESSAGE_PARAM_NAME => $e->getMessage(),
            ) );
            return;
        }

	    wp_send_json_success( array(
            self::STATUS_PARAM_NAME => self::CHECK_SUCCEEDED_STATUS,
        ) );
    }

    function options_page() {
        $opts = P2CFOptions::load();
        ?>
            <h1><? esc_html_e( 'Password 2 Cloudflare', 'p2cf' ); ?></h1>

            <div class="wrap" id="p2cf-content">
                <form action="options.php" method="post" data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
                    <?php
                        // This remove custom query parameters from the URL
                        // so the notices are not displayed twice
                        $request_URI = @$_SERVER['REQUEST_URI'];
                        try {
                            $_SERVER['REQUEST_URI'] = remove_query_arg( array( self::STATUS_PARAM_NAME, self::MESSAGE_PARAM_NAME ), $request_URI );
                            settings_fields( P2CFOptions::OPTION_NAME );
                        } finally {
                            $_SERVER['REQUEST_URI'] = $request_URI;
                        }
                        do_settings_sections( self::OPTIONS_PAGE_ID );
                        submit_button();
                    ?>
                </form>
                <hr />
                <form action="/wp-admin/admin-post.php" method="post">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::SYNC_VARS_ACTION ); ?>">
                    <?php wp_nonce_field( self::SYNC_VARS_ACTION ); ?>
                    <?php do_settings_sections( self::SYNC_VARS_PAGE_ID ); ?>
                    <?php
                        /* translators: %s is the name of the Cloudflare project */
                        submit_button( sprintf( __( 'Synchronize with Cloudflare project %s', 'p2cf' ), $opts->cf_project_name ), 'secondary', self::SYNC_VARS_ACTION );
                    ?>
                </form>
                <form action="/wp-admin/admin-post.php" method="post">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_VARS_ACTION ); ?>">
                    <?php wp_nonce_field( self::DELETE_VARS_ACTION ); ?>
                    <?php do_settings_sections( self::DELETE_ENV_VARS_PAGE_ID ); ?>
                    <?php
                        /* translators: %s is the name of the Cloudflare project */
                        submit_button( sprintf( __( 'Delete environment variables from Cloudflare project %s', 'p2cf' ), $opts->cf_project_name ), 'delete', self::DELETE_VARS_ACTION );
                    ?>
                </form>
            </div>
        <?php
    }

    public function sanitize_setting( array $value ): array {
        $sanitized = (array) P2CFOptions::load();

        if ( P2CFOptions::NO_HASH_ALGO == $value['hash_algo'] ) {
            $sanitized['hash_algo'] = P2CFOptions::NO_HASH_ALGO;
        } elseif ( ! in_array( $value['hash_algo'], hash_algos() ) ) {
            add_settings_error( P2CFOptions::OPTION_NAME, 'invalid_hash_algo', __( 'Invalid hash algorithm', 'p2cf' ) );
        } else {
            $sanitized['hash_algo'] = $value['hash_algo'];
        }
        if ( in_array( $value['path_encoding_method'], array( P2CFOptions::NO_PATH_ENCODING, P2CFOptions::PATH_ENCODING_BASE64 ) ) ) {
            $sanitized['path_encoding_method'] = $value['path_encoding_method'];
        } else {
            add_settings_error( P2CFOptions::OPTION_NAME, 'invalid_path_encoding_method', __( 'Invalid path encoding method', 'p2cf' ) );
        }

        $sanitized['enabled'] = isset( $value['enabled'] ) && $value['enabled'] == 'on';
        $sanitized['cf_api_key'] = $value['cf_api_key'];
        $sanitized['cf_account_id'] = $value['cf_account_id'];
        $sanitized['cf_project_name'] = $value['cf_project_name'];
        $sanitized['env_var_prefix'] = $value['env_var_prefix'];

        $client = new CFClient( $sanitized['cf_api_key'] );

        try {
            $project = $client->get_project( $sanitized['cf_account_id'], $sanitized['cf_project_name'] );
            /* translators: %s is the name of the Cloudflare project */
            add_settings_error( P2CFOptions::OPTION_NAME, 'clouflare-success', sprintf( __( 'Successfully connected to Cloudflare project %s', 'p2cf' ), '<code>' . $project->name . '</code>' ), 'updated' );
        } catch ( CFException $e ) {
            $e->handle();
        }

        return $sanitized;
    }

    function sync_vars_init() {
        add_settings_section(
            self::SYNC_VARS_SECTION,
            '<span class="dashicons dashicons-update"></span> ' . esc_html_x( 'Synchronization', 'Header for the setting section', 'p2cf' ),
            array( $this, 'sync_vars_section_callback' ),
            self::SYNC_VARS_PAGE_ID,
        );
    }

    function delete_env_vars_init() {
        add_settings_section(
            self::DELETE_ENV_VARS_SECTION,
            '<span class="dashicons dashicons-warning"></span> ' . esc_html_x( 'Dangerous zone', 'Header for the setting section', 'p2cf' ),
            array( $this, 'delete_env_vars_section_callback' ),
            self::DELETE_ENV_VARS_PAGE_ID,
        );

        add_settings_field(
            'delete_env_var_prefix',
            $this->label_for( 'p2cf-delete-env-var-prefix', esc_html_x( 'Environment Variable Prefix', 'Label for the setting field', 'p2cf' ) ),
            array( $this, 'delete_env_var_prefix_render' ),
            self::DELETE_ENV_VARS_PAGE_ID,
            self::DELETE_ENV_VARS_SECTION,
        );
    }

    protected function label_for( string $id, string $label ): string {
        return '<label for="' . esc_attr( $id ) . '">' . $label . '</label>';
    }

    function settings_init() {
        register_setting( P2CFOptions::OPTION_NAME, P2CFOptions::OPTION_NAME, array(
            'type'              => 'array',
            'default'           => P2CFOptions::defaults(),
            'sanitize_callback' => array( $this, 'sanitize_setting' ),
        ) );
        
        add_settings_section(
            self::GENERAL_SECTION,
            esc_html_x( 'General', 'Header for the setting section', 'p2cf' ),
            array( $this, 'synchronization_section_callback' ),
            self::OPTIONS_PAGE_ID,
        );

        add_settings_field(
            'enabled',
            $this->label_for( 'p2cf-enabled', esc_html_x( 'Check to enable the plugin', 'Label for the setting field', 'p2cf' ) ),
            array( $this, 'enabled_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'env_var_prefix',
            $this->label_for( 'p2cf-env-var-prefix', esc_html_x( 'Environment Variable Prefix', 'Label for the setting field', 'p2cf' ) ),
            array( $this, 'env_var_prefix_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );
        
        add_settings_field(
            'path_encoding_method',
            $this->label_for( 'p2cf-path-encoding-method', esc_html_x( 'Path encoding method', 'Label for the setting field', 'p2cf' ) ),
            array( $this, 'path_encoding_method_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );
        
        add_settings_field(
            'hash_algo',
            $this->label_for( 'p2cf-hash-algo', esc_html_x( 'Hash algorithm', 'Label for the setting field', 'p2cf' ) ),
            array( $this, 'hash_algo_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );
        
        add_settings_section(
            self::CLOUDFLARE_SECTION,
            esc_html_x( 'Cloudflare', 'Header for the setting section', 'p2cf' ),
            array( $this, 'cloudflare_section_callback' ),
            self::OPTIONS_PAGE_ID,
        );
        
        add_settings_field(
            'cf_api_key',
            $this->label_for( 'p2cf-cf-api-key', esc_html_x( 'Cloudflare API Key', 'Label for the setting field', 'p2cf' ) . wp_required_field_indicator() ),
            array( $this, 'cf_api_key_render' ),
            self::OPTIONS_PAGE_ID,
            self::CLOUDFLARE_SECTION,
        );
        
        add_settings_field(
            'cf_account_id',
            $this->label_for( 'p2cf-cf-account-id', esc_html_x( 'Account ID', 'Label for the setting field', 'p2cf' ) . wp_required_field_indicator() ),
            array( $this, 'account_id_render' ),
            self::OPTIONS_PAGE_ID,
            self::CLOUDFLARE_SECTION,
        );
        
        add_settings_field(
            'cf_project_name',
            $this->label_for( 'p2cf-cf-project-name', esc_html_x( 'Pages project name', 'Label for the setting field', 'p2cf' ) . wp_required_field_indicator() ),
            array( $this, 'project_name_render' ),
            self::OPTIONS_PAGE_ID,
            self::CLOUDFLARE_SECTION,
        );
    }

    function sync_vars_section_callback() {
        ?>
            <p><?php esc_html_e( 'These buttons allow you to reconcile the passwords stored in the database with the environment variables stored in Cloudflare.', 'p2cf' ); ?></p>
            <div class="notice notice-warning inline">
                <p><?php printf(
                    /* translators: %s is the prefix for environment variable */
                    esc_html__( 'Warning, this will remove all environment variables starting with %s that does not match a protected page.', 'p2cf' ),
                    '<code>' . P2CFOptions::load()->env_var_prefix . '</code>'
                ); ?></p>
            </div>
        <?php
    }
    
    function delete_env_vars_section_callback() {
        ?>
            <p><?php esc_html_e( 'These buttons allow you to delete all environment variables from Cloudflare.', 'p2cf' ); ?> </p>
        <?php
    }
    
    function delete_env_var_prefix_render() {
        ?>
        <input type="text" id="p2cf-delete-env-var-prefix" class="code" required minlength="1" name="<?php echo esc_attr( self::DELETE_VARS_PREFIX_INPUT ); ?>" placeholder="<?php echo esc_attr_x( 'OLD_PREFIX_', 'The default prefix for environment variable', 'p2cf' ); ?>">
        <?php
    }
    
    function synchronization_section_callback() {
        ?>
            <p><?php esc_html_e( 'These settings define how to synchronize passwords with Cloudflare.', 'p2cf' ); ?></p>
        <?php
    }
    
    function enabled_render() {
        ?>
        <input type="checkbox" id="p2cf-enabled" <?php checked( P2CFOptions::load()->enabled ); ?> class="code" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[enabled]' ); ?>">
        <?php
    }
    
    function env_var_prefix_render() {
        ?>
        <input type="text" id="p2cf-env-var-prefix" class="code" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[env_var_prefix]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->env_var_prefix ); ?>">
        <?php
    }

    function hash_algo_render() {
        ?>
        <input type="text" id="p2cf-hash-algo" list="hash-algo" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[hash_algo]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->hash_algo ); ?>">
        <datalist id="hash-algo">
            <option value="<?php echo esc_attr( P2CFOptions::NO_HASH_ALGO ); ?>" />
            <?php foreach ( hash_algos() as $algo ): ?>
                <option value="<?php echo esc_attr( $algo ); ?>" />
            <?php endforeach; ?>
        </datalist>  
        <?php
    }

    function path_encoding_method_render() {
        ?>
        <select id="p2cf-path-encoding-method" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[path_encoding_method]' ); ?>">
            <option <?php selected( P2CFOptions::load()->path_encoding_method == P2CFOptions::NO_PATH_ENCODING ); ?> value="<?php echo esc_html( P2CFOptions::NO_PATH_ENCODING ); ?>"><?php echo esc_html( _ex( 'Plain', 'Represents the method name that does not encode the string', 'p2cf' ) ); ?></option>
            <option <?php selected( P2CFOptions::load()->path_encoding_method == P2CFOptions::PATH_ENCODING_BASE64 ); ?> value="<?php echo esc_html( P2CFOptions::PATH_ENCODING_BASE64 ); ?>"><?php echo esc_html( _ex( 'Base64', 'Represents the method name that encode string in base64', 'p2cf' ) ); ?></option>
        </select>  
        <?php
    }

    function cloudflare_section_callback() {
        ?>
            <p>
                <?php esc_html_e( 'These settings are used to interact with the right Cloudflare project.', 'p2cf' ); ?>
                <a href="https://developers.cloudflare.com/fundamentals/api/get-started/create-token/" target="_blank">
                    <?php esc_html_e( 'See how to create a Cloudflare API token.', 'p2cf' ); ?>
                </a>
                <?php printf(
                    /* translators: %1$s and %2$s are the names of the Cloudflare permissions */
                    esc_html__( 'The token must have the %1$s and %2$s permissions:', 'p2cf' ),
                    '<code title="' . esc_attr_x( 'Grants access to view Cloudflare Pages projects.', 'Reference: https://developers.cloudflare.com/fundamentals/api/reference/permissions/', 'p2cf' ) . '">' . 
                        esc_html_x( 'Cloudflare Pages Read', 'Reference: https://developers.cloudflare.com/fundamentals/api/reference/permissions/', 'p2cf' ) .
                    '</code>',
                    '<code title="' . esc_attr_x( 'Grants access to create, edit and delete Cloudflare Pages projects.', 'Reference: https://developers.cloudflare.com/fundamentals/api/reference/permissions/', 'p2cf' ) . '">' .
                        esc_html_x( 'Cloudflare Pages Edit', 'Reference: https://developers.cloudflare.com/fundamentals/api/reference/permissions/', 'p2cf' ) .
                    '</code>',
                ); ?>
            </p>
        <?php
    }

    function cf_api_key_render() {
        ?>
        <input required type="password" id="p2cf-cf-api-key" class="large-text" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[cf_api_key]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->cf_api_key ); ?>">
        <?php
    }

    function account_id_render() {
        ?>
        <input required maxlength="32" type="text" id="p2cf-cf-account-id" class="large-text" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[cf_account_id]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->cf_account_id ); ?>">
        <?php
    }

    function project_name_render() {
        ?>
        <input required pattern="^[a-z0-9][a-z0-9-]*$" type="text" id="p2cf-cf-project-name" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[cf_project_name]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->cf_project_name ); ?>">
        <?php
    }
}
