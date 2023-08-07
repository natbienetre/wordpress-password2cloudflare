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

        foreach ( $response as $name => $value ) {
            $envs[] = new self( $name, CFProjectDeploymentConfigEnvVarValue::from_response( $value ) );
        }

        return $envs;
    }
}
