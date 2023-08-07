<?php
/**
 * Plugin Name:       Password 2 Cloudflare
 * Plugin URI:        https://github.com/natbienetre/wordpress-password2cloudflare
 */

global $p2cf_client;

require 'vendor/autoload.php';

spl_autoload_register( static function ( $class_name ) {
    $file_name = path_join( path_join( __DIR__, 'classes' ), $class_name . '.php' );
    if ( file_exists( $file_name ) ) {
        require_once $file_name;
    }
} );

register_activation_hook( __FILE__, 'p2cf_add_options' );
function p2cf_add_options() {
    add_option( P2CFOptions::OPTION_NAME, P2CFOptions::defaults() );
}

P2CFAdminPage::register_hooks();

add_action( 'init', 'p2cf_init' );
function p2cf_init() {
    $opts = P2CFOptions::load();

    global $p2cf_client;

    $p2cf_client = new CFClient( $opts->cf_api_key );
}

add_action( 'post_updated', 'p2cf_update_password', 10, 3 );
function p2cf_update_password( $post_ID, $post_after, $post_before ) {
    if ( $post_before->post_password != $post_after->post_password ) {
        $var = p2cf_get_env_var( $post_after );
        if ( ! $var ) {
            return;
        }

        $project = CFProject::load();

        if ( $post_after->post_password == '' ) {
            $project->delete_env_var( $var->name );
            syslog( LOG_INFO, "Env var {$var->name} deleted from CloudFlare project {$project->name}." );
        } else {
            $project->add_env_var( $var );
            syslog( LOG_INFO, "Env var {$var->name} added to CloudFlare project {$project->name}." );
        }
    
    }
}

add_action( 'update_option_' . P2CFOptions::OPTION_NAME, 'p2cf_reconcile_option', 10, 2 );
function p2cf_reconcile_option( array $old, array $new ) {
    $old = new P2CFOptions( $old );
    $new = new P2CFOptions( $new );

    if ( $old->cf_account_id != $new->cf_account_id || $old->cf_project_name != $new->cf_project_name ) {
        $old_client = new CFClient( $old->cf_api_key );

        try {
            $project = $old_client->get_project( $old->cf_account_id, $old->cf_project_name );

            $project->delete_all_env_var( $old->env_var_prefix );
        } catch ( CFException $e ) {
            syslog( LOG_ERR, "Error while getting CloudFlare project {$old->cf_project_name} from account {$old->cf_account_id}: {$e->getMessage()}" );
        }

        $new_client = new CFClient( $new->cf_api_key );

        try {
            $project = $new_client->get_project( $new->cf_account_id, $new->cf_project_name );

            $vars = p2cf_get_env_vars();
            $project->add_env_vars( $vars );
        } catch ( CFException $e ) {
            syslog( LOG_ERR, "Error while getting CloudFlare project {$old->cf_project_name} from account {$old->cf_account_id}: {$e->getMessage()}" );
        }
    }

    if ( $old->cf_api_key != $new->cf_api_key ) {
        global $p2cf_client;

        $p2cf_client = new CFClient( $new->cf_api_key );
    }
}


function p2cf_get_env_vars(): array {
    $posts = get_posts( array(
        'post_type'    => 'any',
        'post_status'  => 'any',
        'numberposts'  => -1,
        'has_password' => true,
    ) );

    $vars = array();

    foreach ( $posts as $post ) {
        $var = p2cf_get_env_var( $post );
        if ( ! $var ) {
            continue;
        }

        $vars[] = $var;
    }

    return $vars;
}

function p2cf_get_env_var( WP_Post $post ): ?CFProjectDeploymentConfigEnvVar {
    $permalink = get_permalink( $post->ID );

    if ( ! $permalink ) {
        return null;
    }

    $path = parse_url( $permalink, PHP_URL_PATH );
    
    $opts = P2CFOptions::load();

    if ( $opts->path_encoding_method == P2CFOptions::PATH_ENCODING_BASE64 ) {
        $var_name = $opts->env_var_prefix . base64( $path );
    } else {
        $var_name = $opts->env_var_prefix . $path;
    }

    $value = $post->post_password;
    if ( $opts->hash_algo != P2CFOptions::NO_HASH_ALGO ) {
        $value = hash( $opts->hash_algo, $value );
    }

    return new CFProjectDeploymentConfigEnvVar( $var_name, new CFProjectDeploymentConfigEnvVarValue( $value, true ) );
}
