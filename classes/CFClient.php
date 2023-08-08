<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException as ClientException;
use GuzzleHttp\Exception\ServerException as ServerException;

class CFClient {
    const BASE_URL = 'https://api.cloudflare.com/client/v4/';
    const TIMEOUT = 30.0;

    public Client $client;
    
    function __construct( string $api_key ) {
        $this->client = new Client( array(
            'base_uri' => self::BASE_URL,
            'timeout'  => self::TIMEOUT,
            'headers' => array(
                'Authorization' => "Bearer {$api_key}",
            ),
        ) );
    }

    protected function request( string $method, string $path, array $opts = array() ): array {
        try {
            $response = $this->client->request( $method, $path, $opts );
            $result = json_decode( $response->getBody(), true );
        } catch ( ServerException | ClientException $e ) {
            $result = json_decode( (string) $e->getResponse()->getBody(), true );
            if ( ! $result ) {
                throw $e;
            }
        }

        if ( $result['success'] == false ) {
            throw CFException::from_response( $result );
        }

        return $result['result'];
    }

    public function get_project( string $account_id, string $project_name ): CFProject {
        $result = $this->request(
            'GET',
            'accounts/' . urlencode( $account_id ) . '/pages/projects/' . urlencode( $project_name ),
        );

        return CFProject::from_response( $account_id, $result );
    }

    function add_env_vars( string $account_id, string $project_name, string $deployment, CFProjectDeploymentConfigEnvVar ...$vars ) {
        if ( count( $vars ) == 0 ) {
            return;
        }

        $body = json_encode( array(
            'deployment_configs' => array(
                $deployment => array(
                    'env_vars' => array_combine(
                        array_map( function ( CFProjectDeploymentConfigEnvVar $env ): string {
                            return $env->name;
                        }, $vars ),
                        array_map( function ( CFProjectDeploymentConfigEnvVar $env ): null|array {
                            return $env->value->to_response();
                        }, $vars ),
                    ),
                ),
            ),
        ) );

        $this->request(
            'PATCH',
            'accounts/' . urlencode( $account_id ) . '/pages/projects/' . urlencode( $project_name ),
            array(
                'body' => $body,
            )
        );
    }

    function delete_env_vars( string $account_id, string $project_name, string $deployment, string ...$vars ) {
        $body = json_encode( array(
            'deployment_configs' => array(
                $deployment => array(
                    'env_vars' => array_combine( $vars, array_fill(0, count( $vars ), null ) ),
                ),
            ),
        ) );

        $this->request(
            'PATCH',
            'accounts/' . urlencode( $account_id ) . '/pages/projects/' . urlencode( $project_name ),
            array(
                'body' => $body,
            )
        );
    }
}
