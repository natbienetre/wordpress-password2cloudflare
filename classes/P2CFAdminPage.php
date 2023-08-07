<?php
class P2CFAdminPage {
    public string $page_id = 'password-sync-to-cloudflare';

    const GENERAL_SECTION = 'general';
    const CLOUDFLARE_SECTION = 'cloud_flare';

    public static function register_hooks() {
        $instance = new self();

        register_activation_hook( __FILE__, array( $instance, 'add_options' ) );
        
        add_action( 'admin_menu', array( $instance, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $instance, 'settings_init' ) );
    }
    
    function add_options() {
        add_option( P2CFOptions::OPTION_NAME, P2CFOptions::defaults() );
    }
    
    function add_admin_menu() {
        add_options_page(
            __( 'Password Sync to Cloudflare', 'p2cf' ),
            __( 'Password Sync to Cloudflare', 'p2cf' ),
            'manage_options',
            $this->page_id,
            array( $this, 'options_page' ),
        );
    }
    
    function options_page() {
        ?><form action="options.php" method="post">
            <h1><? esc_html_e( 'Password Sync to Cloudflare', 'p2cf' ); ?></h1>
            <?php
                settings_fields( P2CFOptions::OPTION_NAME );
                do_settings_sections( $this->page_id );
                submit_button();
            ?>
        </form><?php
    }

    function settings_init() {
        register_setting( 'p2cf_options', P2CFOptions::OPTION_NAME, array(
            'type'              => 'array',
            'default'           => P2CFOptions::defaults(),
            'sanitize_callback' => array( 'P2CFOptions', 'sanitize' ),
        ) );
        
        add_settings_section(
            self::GENERAL_SECTION,
            esc_html__( 'General', 'p2cf' ),
            array( $this, 'synchronization_section_callback' ),
            $this->page_id,
        );

        add_settings_field(
            'env_var_prefix',
            esc_html__( 'Environment Variable Prefix', 'p2cf' ),
            array( $this, 'env_var_prefix_render' ),
            $this->page_id,
            self::GENERAL_SECTION,
        );
        
        add_settings_field(
            'path_encoding_method',
            esc_html__( 'Path encoding method', 'p2cf' ),
            array( $this, 'path_encoding_method_render' ),
            $this->page_id,
            self::GENERAL_SECTION,
        );
        
        add_settings_field(
            'hash_algo',
            esc_html__( 'Hash algorithm', 'p2cf' ),
            array( $this, 'hash_algo_render' ),
            $this->page_id,
            self::GENERAL_SECTION,
        );
        
        add_settings_section(
            self::CLOUDFLARE_SECTION,
            esc_html__( 'CloudFlare', 'p2cf' ),
            array( $this, 'cloudflare_section_callback' ),
            $this->page_id,
        );
        
        add_settings_field(
            'cf_api_key',
            esc_html__( 'Cloudflare API Key', 'p2cf' ) . wp_required_field_indicator(),
            array( $this, 'cf_api_key_render' ),
            $this->page_id,
            self::CLOUDFLARE_SECTION,
        );
        
        add_settings_field(
            'cf_account_id',
            esc_html__( 'Account ID', 'p2cf' ) . wp_required_field_indicator(),
            array( $this, 'account_id_render' ),
            $this->page_id,
            self::CLOUDFLARE_SECTION,
        );
        
        add_settings_field(
            'cf_project_name',
            esc_html__( 'Pages project name', 'p2cf' ) . wp_required_field_indicator(),
            array( $this, 'project_name_render' ),
            $this->page_id,
            self::CLOUDFLARE_SECTION,
        );
    }
    
    // Cloudflare Pages Read
    // Cloudflare Pages Edit
    
    function synchronization_section_callback() {
        esc_html_e( 'These options define how to synchronize passwords with CloudFlare.', 'p2cf' );
    }
    
    function env_var_prefix_render() {
        ?>
        <input type="text" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[env_var_prefix]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->env_var_prefix ); ?>">
        <?php
    }

    function hash_algo_render() {
        ?>
        <input type="text" list="hash-algo" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[hash_algo]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->hash_algo ); ?>">
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
        <select name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[path_encoding_method]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->path_encoding_method ); ?>">
            <option id="<?php echo esc_html( P2CFOptions::NO_PATH_ENCODING ); ?>"><?php esc_html_e( 'Plain', 'p2cf' ); ?></option>
            <option id="<?php echo esc_html( P2CFOptions::PATH_ENCODING_BASE64 ); ?>"><?php esc_html_e( 'Base64', 'p2cf' ); ?></option>
        </select>  
        <?php
    }

    function cloudflare_section_callback() {
        try {
            $project = CFProject::load();
            ?>
                <div class="notice notice-success">
                    <p><?php printf(
                        _n( 'Successfully linked to project %s, using the domain %s', 'Successfully linked to project %s, using the following domains: %s', count( $project->domains ), 'p2cf' ),
                        "<code>{$project->name}</code>",
                        join(
                            esc_html_x( ', ', 'The separator for a list of domain', 'p2cf' ),
                            array_map( function ( string $domain ): string {
                                return "<code>{$domain}</code>";
                            }, $project->domains )
                        )
                    ); ?></p>
                </div>
            <?php
        } catch ( CFException $e ) {
            ?>
                <div class="notice notice-error">
                    <h3><?php _e( 'Error getting CloudFlare project', 'p2cf' ); ?></h3>
                    <p><?php echo esc_html( $e->getMessage() ); ?></p>
                </div>
            <?php
        }
        esc_html_e( 'These options are used to interact with the right Cloudflare project.', 'p2cf' );
        ?>
            <a href="https://developers.cloudflare.com/fundamentals/api/get-started/create-token/" target="_blank">
                <?php esc_html_e( 'See how to create a Cloudflare API token.', 'p2cf' ); ?>
            </a>
        <?php
    }

    function account_id_render() {
        ?>
        <input required maxlength="32" type="text" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[cf_account_id]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->cf_account_id ); ?>">
        <?php
    }

    function project_name_render() {
        ?>
        <input required pattern="^[a-z0-9][a-z0-9-]*$" type="text" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[cf_project_name]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->cf_project_name ); ?>">
        <?php
    }

    function cf_api_key_render() {
        ?>
        <input required type="password" name="<?php echo esc_attr( P2CFOptions::OPTION_NAME . '[cf_api_key]' ); ?>" value="<?php echo esc_attr( P2CFOptions::load()->cf_api_key ); ?>">
        <?php
    }
}
