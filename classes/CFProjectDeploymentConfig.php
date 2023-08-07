<?php

class CFProjectDeploymentConfig {
    public array $env_vars;

    function __construct( array $env_vars ) {
        $this->env_vars = $env_vars;
    }

    public static function from_response( array $response ): CFProjectDeploymentConfig {
        return new self( CFProjectDeploymentConfigEnvVar::from_response($response['env_vars'] ) );
    }
}
