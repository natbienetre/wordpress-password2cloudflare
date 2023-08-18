<?php
class CFProjectDeploymentConfigEnvVar {
    public string $name;
    public CFProjectDeploymentConfigEnvVarValue $value;

    function __construct( string $name, CFProjectDeploymentConfigEnvVarValue $value ) {
        $this->name  = $name;
        $this->value = $value;
    }

    public static function from_response( array $response ): array {
        $envs = array();

        $opts = Pass2CFOptions::load();

        foreach ( $response as $name => $value ) {
            if ( ! str_starts_with( $name, $opts->env_var_prefix ) ) {
                continue;
            }

            $envs[] = new self( $name, CFProjectDeploymentConfigEnvVarValue::from_response( $value ) );
        }

        return $envs;
    }
}
