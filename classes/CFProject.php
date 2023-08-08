<?php
class CFProject {
    const PRODUCTION_ENVIRONMENT = 'production';

    public array $deployment_configs;

    public array $domains;

    protected string $account_id;

    protected array $_data;

    function __construct( string $account_id, string $id, string $name, string $created_on, array $domains, array $deployment_configs ) {
        $this->domains            = $domains;
        $this->account_id         = $account_id;
        $this->deployment_configs = $deployment_configs;

        $this->_data = array(
            'id'                => $id,
            'name'              => $name,
            'created_on'        => $created_on,
        );
    }

    public static function from_response( string $account_id, array $response ): CFProject {
        return new self(
            $account_id,
            $response['id'],
            $response['name'],
            $response['created_on'],
            $response['domains'],
            array_map( array( 'CFProjectDeploymentConfig', 'from_response' ), $response['deployment_configs'] )
        );
    }

    public function __get( $name ) {
        if ( isset( $this->_data[ $name ] ) ) {
            return $this->_data[ $name ];
        }

        /* translators: %s is the name of the property */
        throw new Exception( sprintf( __( 'Property %s does not exist', 'p2cf' ), $var_name ) );
    }

    public static function load(): CFProject {
        global $p2cf_client;

        $opts = P2CFOptions::load();

        return $p2cf_client->get_project( $opts->cf_account_id, $opts->cf_project_name );
    }

    public function add_env_var( CFProjectDeploymentConfigEnvVar $var, string $env = self::PRODUCTION_ENVIRONMENT ) {
        $this->add_env_vars( array( $var ), $env );
    }

    public function replace_env_vars( array $vars, string $env = self::PRODUCTION_ENVIRONMENT ) {
        global $p2cf_client;

        $patch_vars = array_merge(
            array_map( function ( CFProjectDeploymentConfigEnvVar $var ): CFProjectDeploymentConfigEnvVar {
                return new CFProjectDeploymentConfigEnvVar( $var->name, new CFProjectDeploymentConfigEnvVarValue( null ) );
            }, $this->deployment_configs[ $env ]->env_vars ),
            $vars,
        );

        $p2cf_client->add_env_vars( $this->account_id, $this->name, $env, ...$patch_vars );

        $this->deployment_configs[ $env ]->env_vars = $vars;
    }

    public function delete_env_var( string $var_name, string $env = self::PRODUCTION_ENVIRONMENT ) {
        global $p2cf_client;

        $p2cf_client->delete_env_vars( $this->account_id, $this->name, $env, $var_name );

        $this->deployment_configs[ $env ]->env_vars = array_filter( $this->deployment_configs[ $env ]->env_vars, function ( CFProjectDeploymentConfigEnvVar $var ) use ( $var_name ) {
            return $var->name != $var_name;
        } );
    }

    public function delete_all_env_var( string $prefix, string $env = self::PRODUCTION_ENVIRONMENT ): array {
        global $p2cf_client;

        $vars = $this->deployment_configs[ $env ]->env_vars;
        $vars = array_map( function ( CFProjectDeploymentConfigEnvVar $var ): string {
            return $var->name;
        }, $vars );
        $vars = array_filter( $vars, function ( string $var_name ) use ( $prefix ): bool {
            return strpos( $var_name, $prefix ) === 0;
        } );

        if ( empty( $vars ) ) {
            return array();
        }

        $p2cf_client->delete_env_vars( $this->account_id, $this->name, $env, ...$vars );

        $this->deployment_configs[ $env ]->env_vars = array_filter( $this->deployment_configs[ $env ]->env_vars, function ( CFProjectDeploymentConfigEnvVar $var ) use ( $prefix ) {
            return strpos( $var->name, $prefix ) !== 0;
        } );

        return $vars;
    }
}
