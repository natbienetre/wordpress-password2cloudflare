<?php

class Pass2CFOptions {
    const OPTION_NAME = 'pass2cf_options';
    
    const NO_HASH_ALGO = 'none';

    const NO_PATH_ENCODING     = 'plain';
    const PATH_ENCODING_BASE64 = 'base64';

    public bool $enabled;

    public string $hash_algo;
    public string $env_var_prefix;
    public string $path_encoding_method;

    public string $cf_api_key;
    public string $cf_account_id;
    public string $cf_project_name;

    public function __construct( array $options ) {
        $options = wp_parse_args( $options, self::defaults() );

        $this->enabled = $options['enabled'];

        $this->hash_algo            = $options['hash_algo'];
        $this->env_var_prefix       = $options['env_var_prefix'];
        $this->path_encoding_method = $options['path_encoding_method'];
        
        $this->cf_api_key      = $options['cf_api_key'];
        $this->cf_account_id   = $options['cf_account_id'];
        $this->cf_project_name = $options['cf_project_name'];
    }

    public function sanitize( string $message ): string {
        $message = str_replace( $this->cf_api_key, '*cf-api-key*', $message );
        $message = str_replace( $this->cf_account_id, '*cf-account-id*', $message );
        $message = str_replace( $this->cf_project_name, '*cf-project-name*', $message );

        return $message;
    }

    public static function defaults(): array {
        return array(
            'enabled' => false,

            'hash_algo'            => self::NO_HASH_ALGO,
            'env_var_prefix'       => _x( 'WP_PASSWORD_', 'Default prefix for environment variable', 'pass2cf' ),
            'path_encoding_method' => self::NO_PATH_ENCODING,
            
            'cf_api_key'      => '',
            'cf_account_id'   => '',
            'cf_project_name' => '',
        );
    }

    public static function load(): Pass2CFOptions {
        global $pass2cf_opts;

        if ( empty( $pass2cf_opts ) ) {
            $pass2cf_opts = new self( (array) get_option( self::OPTION_NAME, self::defaults() ) );
        }
        
        return $pass2cf_opts;
    }

    public static function add_options() {
        add_option( self::OPTION_NAME, self::defaults() );
    }
}
