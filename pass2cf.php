<?php
/**
 * Plugin Name:       Password 2 Cloudflare
 * Plugin URI:        https://github.com/natbienetre/wordpress-password2cloudflare
 * Version:           0.2.0
 * GitHub Plugin URI: natbienetre/wordpress-password2cloudflare
 * Funding URI:       https://github.com/sponsors/holyhope
 * Description:       Synchronize WordPress password-protected posts with secret environment variables in Cloudflare Pages.
 * Author:            @holyhope
 * Author URI:        https://github.com/holyhope
 * Text Domain:       pass2cf
 * Domain Path:       /languages
 */

require 'autoload.php';

define( 'pass2cf_PLUGIN_FILE', __FILE__ );

global $pass2cf_client;
global $pass2cf_opts;

register_activation_hook( __FILE__, 'pass2cf_add_options' );
function pass2cf_add_options() {
    Pass2CFOptions::add_options();
}

function pass2cf_plugin_loaded( $plugin ) {
    if ( $plugin == __FILE__ ) {
        Pass2CFAdminPage::register_hooks();
    }
}

add_action( 'init', 'pass2cf_init_pass2cf_client' );
function pass2cf_init_pass2cf_client() {
    $opts = Pass2CFOptions::load();

    global $pass2cf_client;

    $pass2cf_client = new CFClient( $opts->cf_api_key );
}

add_action( 'init', 'pass2cf_load_textdomain' );
function pass2cf_load_textdomain() {
    load_plugin_textdomain( 'pass2cf', false, dirname( plugin_basename( pass2cf_PLUGIN_FILE ) ) . '/languages' );
}

add_action( 'post_updated', 'pass2cf_update_password', 10, 3 );
function pass2cf_update_password( $post_ID, $post_after, $post_before ) {
    $opts = Pass2CFOptions::load();

    if ( ! $opts->enabled ) {
        return;
    }

    if ( $post_before->post_password != $post_after->post_password ) {
        $var = pass2cf_get_env_var( $post_after, $opts );
        if ( ! $var ) {
            return;
        }

        $project = CFProject::load();

        if ( $post_after->post_password == '' ) {
            try {
                $project->delete_env_var( $var->name );
            } catch ( CFException $e ) {
                /* translators: %1$s is the name of the environment variable, %2$s is the name of the Cloudflare project */
                $e->handle( sprintf( __( 'Error while deleting environment varariable %1$s from Cloudflare project %2$s.', 'pass2cf' ), $var->name, $project->name ) );
                return;
            }
            return;
        }

        try {
            $project->add_env_var( $var );
        } catch ( CFException $e ) {
            /* translators: %1$s is the name of the environment variable, %2$s is the name of the Cloudflare project */
            $e->handle( sprintf( __( 'Error while adding environment variable %1$s to Cloudflare project %2$s.', 'pass2cf' ), $var->name, $project->name ) );
        }
    }
}

add_action( 'update_option_' . Pass2CFOptions::OPTION_NAME, 'pass2cf_reconcile_option', 10, 2 );
function pass2cf_reconcile_option( array $old_opts, array $new_opts ) {
    $old_opts = new Pass2CFOptions( $old_opts );
    $new_opts = new Pass2CFOptions( $new_opts );

    $need_sync = pass2cf_need_sync( $old_opts, $new_opts );

    if ( $need_sync && $old_opts->enabled ) {
        $old_client = new CFClient( $old_opts->cf_api_key );

        try {
            $project = $old_client->get_project( $old_opts->cf_account_id, $old_opts->cf_project_name );
            $project->delete_all_env_var( $old_opts->env_var_prefix );
        } catch ( CFException $e ) {
            /* translators: %1$s is the prefix for environment variable, %2$s is the name of the Cloudflare project */
            $e->handle( sprintf( __( 'Error while deleting all environment variables (starting with %1$s) from Cloudflare project %2$s', 'pass2cf' ), '<code>' . $old_opts->env_var_prefix . '</code>', $old_opts->cf_project_name ) );
            return;
        }
    }

    if ( $old_opts->cf_api_key != $new_opts->cf_api_key ) {
        global $pass2cf_client;

        $pass2cf_client = new CFClient( $new_opts->cf_api_key );
    }

    if ( $need_sync && $new_opts->enabled ) {
        try {
            $nb = pass2cf_sync_all();
        } catch ( CFException $e ) {
            /* translators: %s is the project name */
            $e->handle( sprintf( __( 'Failed to synchronize environment variables with the project %s.', 'pass2cf' ), $new_opts->cf_project_name ) );
            return;
        }
    }
}

function pass2cf_sync_all(): int {
    global $pass2cf_client;

    $opts = Pass2CFOptions::load();
    
    $vars = pass2cf_get_env_vars( $opts );
    CFProject::load()->replace_env_vars( $vars );

    return count( $vars );
}

function pass2cf_need_sync( Pass2CFOptions $old, Pass2CFOptions $new ): bool {
    return $old->hash_algo        != $new->hash_algo
    || $old->cf_account_id        != $new->cf_account_id
    || $old->env_var_prefix       != $new->env_var_prefix
    || $old->cf_project_name      != $new->cf_project_name
    || $old->path_encoding_method != $new->path_encoding_method;
}

function pass2cf_get_env_vars( Pass2CFOptions $opts ): array {
    $posts = get_posts( array(
        'post_type'    => 'any',
        'post_status'  => 'any',
        'numberposts'  => -1,
        'has_password' => true,
    ) );

    $vars = array();

    foreach ( $posts as $post ) {
        $var = pass2cf_get_env_var( $post, $opts );
        if ( ! $var ) {
            continue;
        }

        $vars[] = $var;
    }

    return $vars;
}

function pass2cf_get_env_var( WP_Post $post, Pass2CFOptions $opts ): ?CFProjectDeploymentConfigEnvVar {
    $permalink = get_permalink( $post->ID );

    if ( ! $permalink ) {
        return null;
    }

    $path = parse_url( $permalink, PHP_URL_PATH );

    if ( $opts->path_encoding_method == Pass2CFOptions::PATH_ENCODING_BASE64 ) {
        $var_name = $opts->env_var_prefix . base64_encode( $path );
    } else {
        $var_name = $opts->env_var_prefix . $path;
    }

    $value = $post->post_password;
    if ( $opts->hash_algo != Pass2CFOptions::NO_HASH_ALGO ) {
        $value = hash( $opts->hash_algo, $value );
    }

    return new CFProjectDeploymentConfigEnvVar( $var_name, new CFProjectDeploymentConfigEnvVarValue( $value, true ) );
}
