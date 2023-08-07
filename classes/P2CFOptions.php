<?php

class P2CFOptions {
    const OPTION_NAME = 'p2cf_options';
    
    const NO_HASH_ALGO = 'none';

    const NO_PATH_ENCODING     = 'plain';
    const PATH_ENCODING_BASE64 = 'base64';

    public string $hash_algo;
    public string $env_var_prefix;
    public string $path_encoding_method;

    public string $cf_api_key;
    public string $cf_account_id;
    public string $cf_project_name;

    public function __construct( array $options ) {
        $options = wp_parse_args( $options, self::defaults() );

        $this->hash_algo            = $options['hash_algo'];
        $this->env_var_prefix       = $options['env_var_prefix'];
        $this->path_encoding_method = $options['path_encoding_method'];
        
        $this->cf_api_key      = $options['cf_api_key'];
        $this->cf_account_id   = $options['cf_account_id'];
        $this->cf_project_name = $options['cf_project_name'];
    }

    public static function defaults(): array {
        return array(
            'hash_algo'            => self::NO_HASH_ALGO,
            'env_var_prefix'       => 'WP_PASSWORD_',
            'path_encoding_method' => self::NO_PATH_ENCODING,
            
            'cf_api_key'      => '',
            'cf_account_id'   => '',
            'cf_project_name' => '',
        );
    }

    public static function load(): P2CFOptions {
        return new P2CFOptions( (array) get_option( self::OPTION_NAME, self::defaults() ) );
    }

    public static function sanitize( array $value ): array {
        $sanitized = self::defaults();

        if ( self::NO_HASH_ALGO == $value['hash_algo'] || in_array( $value['hash_algo'], hash_algos() ) ) {
            $sanitized['hash_algo'] = $value['hash_algo'];
        }
        if ( self::NO_PATH_ENCODING == $value['path_encoding_method'] || self::PATH_ENCODING_BASE64 == $value['path_encoding_method'] ) {
            $sanitized['path_encoding_method'] = $value['path_encoding_method'];
        }
        $sanitized['cf_api_key'] = $value['cf_api_key'];
        $sanitized['cf_account_id'] = $value['cf_account_id'];
        $sanitized['cf_project_name'] = $value['cf_project_name'];
        $sanitized['env_var_prefix'] = $value['env_var_prefix'];

        $client = new CFClient( $sanitized['cf_api_key'] );

        try {
            $project = $client->get_project( $sanitized['cf_account_id'], $sanitized['cf_project_name'] );
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e( 'Connected to CloudFlare!', 'p2cf' ); ?></p>
                </div>
            <?php
        } catch ( CFException $e ) {
            add_settings_error( self::OPTION_NAME, 'project', $e->getMessage() );
        }

        return $sanitized;
    }
}
