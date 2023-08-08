<?php
class CFProjectDeploymentConfigEnvVarValue {
    const TYPE_PLAIN_TEXT  = 'plain_text';
    const TYPE_SECRET_TEXT = 'secret_text';

    protected ?string $value;
    protected bool $secret;

    function __construct( ?string $value, bool $secret = true ) {
        $this->value = $value;
        $this->secret = $secret;
    }

    public static function from_response( array $response ): CFProjectDeploymentConfigEnvVarValue {
        return new self( $response['value'], $response['type'] === self::TYPE_SECRET_TEXT);
    }

    public function to_response(): null|array {
        if ( $this->value == null ) {
            return null;
        }

        return array(
            'type'  => $this->secret ? 'secret_text' : 'plain_text',
            'value' => $this->value,
        );
    }
}
