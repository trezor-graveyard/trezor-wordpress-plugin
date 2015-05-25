<?php
/*
Plugin Name: TREZOR WordPress Plugin
Plugin URI: http://buytrezor.com
Description: WordPress plugin that allows login via TREZOR Connect.
Version: 0.3
Author: SatoshiLabs
Author URI: http://satoshilabs.com/
Author Email: integration@satoshilabs.com
License:

  Copyright (c) 2015 Jan Čejka (posta@jancejka.cz)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require "BitcoinECDSA.php";

use BitcoinPHP\BitcoinECDSA\BitcoinECDSA;

class WPTrezorPlugin {

    /*--------------------------------------------*
     * Constants
     *--------------------------------------------*/
    const name = 'Trezor Plugin';
    const slug = 'wp_trezor_plugin';

    /**
     * Constructor
     */
    function __construct() {
        // register an activation hook for the plugin
        register_activation_hook(__FILE__, array(&$this, 'install_trezor_plugin'));

        // Hook up to the init action
        add_action('init', array(&$this, 'init_trezor_plugin'));

        // add link to login form
        add_action('login_footer', array(&$this, 'trezor_login_footer'));

        // add user profile field
        add_action('show_user_profile', array(&$this, 'add_extra_profile_fields'));
        add_action('edit_user_profile', array(&$this, 'add_extra_profile_fields'));
        add_action('personal_options_update', array(&$this, 'save_extra_profile_fields'));
        add_action('edit_user_profile_update', array(&$this, 'save_extra_profile_fields'));
    }

    /**
     * Runs when the plugin is activated
     */
    function install_trezor_plugin() {
        // do not generate any output here
    }

    /**
     * Runs when the plugin is initialized
     */
    function init_trezor_plugin() {
        ob_start();
        // Setup localization
        load_plugin_textdomain(self::slug, false, dirname(plugin_basename(__FILE__)) . '/lang');
        // Load JavaScript and stylesheets
//        $this->register_scripts_and_styles();

        if ($_GET['trezor_action'] == 'login') {
            $this->login(
                filter_input(INPUT_GET, 'address'),
                filter_input(INPUT_GET, 'public_key'),
                filter_input(INPUT_GET, 'challenge_visual'),
                filter_input(INPUT_GET, 'challenge_hidden'),
                filter_input(INPUT_GET, 'signature')
            );
        }

        if ($_GET['trezor_action'] == 'link') {
            $psw = filter_input(INPUT_POST, 'psw');
            $userdata = wp_get_current_user();

            if (wp_check_password($psw, $userdata->user_pass, $userdata->ID)) {
                $result = $this->save_extra_profile_fields($userdata->ID);
                echo(json_encode(array(
                    'result' => $result ? 'success' : 'error',
                    'message' => $result ? '' : 'Wrong signature',
                )));
            } else {
                echo(json_encode(array(
                    'result' => 'error',
                    'message' => 'Wrong password',
                )));
            }
            exit;
        }

        if ($_GET['trezor_action'] == 'unlink') {
            $psw = filter_input(INPUT_POST, 'psw');
            $userdata = wp_get_current_user();

            if (wp_check_password($psw, $userdata->user_pass, $userdata->ID)) {
                update_user_meta($userdata->ID,'trezor_address', '');
                update_user_meta($userdata->ID,'trezor_publickey', '');

                echo(json_encode(array(
                    'result' => 'success',
                )));
            } else {
                echo(json_encode(array(
                    'result' => 'error',
                    'message' => 'Wrong password',
                )));
            }

            exit;
        }

        if (is_admin()) {
            add_action('admin_menu', array(&$this, 'create_menu'));
        } else {
            //this will run when on the frontend
        }

    }

    function create_menu() {

        //create new top-level menu
        add_menu_page('TREZOR Connect Settings', 'TREZOR Connect', 'administrator', 'trezor_settings', array(&$this, 'trezor_settings_page'),plugins_url('/images/logo_square-menu.png', __FILE__));

        //call register settings function
        add_action('admin_init', array(&$this, 'register_settings'));
        add_action('admin_enqueue_scripts', array(&$this, 'load_wp_media_files'));

    }

    function register_settings() { // whitelist options
        register_setting('trezor-option-group', 'logo_url');
    }

    /**
     * Load media files needed for Uploader
     */
    function load_wp_media_files() {
        wp_enqueue_media();
    }

    function trezor_settings_page() {
        $this->load_file(self::slug . '-trezor-settings-script', '/js/settings.js', true);
        ?>
        <div class="wrap">
            <h2>TREZOR Connect</h2>

            <form method="post" action="options.php">
                <?php settings_fields('trezor-option-group'); ?>
                <?php do_settings_sections('trezor-option-group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Logo image for Signup window</th>
                        <td>
                            <input type="text" name="logo_url" class="media-input" value="<?php echo esc_attr(get_option('logo_url')); ?>" />
                            <button class="media-button">Select image</button>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>

            </form>
        </div>
        <?php
    }

    function login($address, $public_key, $challenge_visual, $challenge_hidden, $signature) {

        if ($this->verify($challenge_hidden, $challenge_visual, $public_key, $signature)) {

            $args = array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key'      => 'trezor_address',
                        'value'   => $address,
                        'compare' => '='
                    ),
                    array(
                        'key'      => 'trezor_publickey',
                        'value'   => $public_key,
                        'compare' => '='
                    )
                )
            );

            $users = new WP_User_Query($args);

            if (!empty($users->results)) {
                $user = $users->results[0]; // first user
                wp_set_auth_cookie($user->ID);

                $redirect_url = $_GET['redirect_to'];
                if (($redirect_url != "") && ($redirect_url != "null")){
                    wp_redirect($redirect_url);
                } else {
                    wp_redirect(get_home_url('wp-admin'));
                }
            } else {
                wp_redirect(wp_login_url().'?error=trezor_unpaired');
            }
        }

    }

    function action_callback_method_name() {
        // TODO define your action method here
    }

    function filter_callback_method_name() {
        // TODO define your filter method here
    }

    /**
     * Registers and enqueues stylesheets for the administration panel and the
     * public facing site.
     */
    private function register_scripts_and_styles() {
        if (is_admin()) {
            $this->load_file(self::slug . '-admin-script', '/js/admin.js', true);
            $this->load_file(self::slug . '-admin-style', '/css/admin.css');
        } else {
            $this->load_file(self::slug . '-script', '/js/widget.js', true);
            $this->load_file(self::slug . '-style', '/css/widget.css');
        } // end if/else
    } // end register_scripts_and_styles

    /**
     * Helper function for registering and enqueueing scripts and styles.
     *
     * @name    The        ID to register with WordPress
     * @file_path        The path to the actual file
     * @is_script        Optional argument for if the incoming file_path is a JavaScript source file.
     */
    private function load_file($name, $file_path, $is_script = false) {

        $url = plugins_url($file_path, __FILE__);
        $file = plugin_dir_path(__FILE__) . $file_path;

        if (file_exists($file)) {
            if ($is_script) {
                wp_register_script($name, $url);
//                wp_register_script($name, $url, array('jquery')); //depends on jquery
                wp_enqueue_script($name);
            } else {
                wp_register_style($name, $url);
                wp_enqueue_style($name);
            } // end if
        } // end if

    } // end load_file

    function verify($challenge_hidden, $challenge_visual, $pubkey, $signature) {
        $message = hex2bin($challenge_hidden) . $challenge_visual;

        $R = substr($signature, 2, 64);
        $S = substr($signature, 66, 64);

        $ecdsa = new BitcoinECDSA();
        $hash = strtolower($ecdsa->hash256("\x18Bitcoin Signed Message:\n" . $ecdsa->numToVarIntString(strlen($message)) . $message));

        $success = (bool)$ecdsa->checkSignaturePoints($pubkey, $R, $S, $hash);

        return $success;
    }

    function getLogoUrl() {
        $default = plugins_url('/images/logo_square.png', __FILE__);
        $logo_url = get_option('logo_url');
        return $logo_url ? $logo_url : $default;
    }

    function add_extra_profile_fields($user)
    {
        $this->load_file(self::slug . '-trezor-admin-script', '/js/admin.js', true);
        $this->load_file(self::slug . '-trezor-admin-style', '/css/admin.css');

        $trezor_address = get_the_author_meta('trezor_address', $user->ID);
        $trezor_publickey = get_the_author_meta('trezor_publickey', $user->ID);
        $trezor_connected = !empty($trezor_address) && !empty($trezor_publickey);

        ?>
        <h3>TREZOR Connect</h3>

        <table class="form-table">
            <tr>
                <th>
                    <label for="trezor_address"><?= _("Trezor Connection State") ?></label>
                    <input type="hidden" name="trezor_address" id="trezor_address" value="<?php echo esc_attr($trezor_address); ?>" class="regular-text" readonly="readonly" aria-readonly="true" />
                    <input type="hidden" name="trezor_publickey" id="trezor_publickey" value="<?php echo esc_attr($trezor_publickey); ?>" />
                    <input type="hidden" name="trezor_connected" id="trezor_connected" value="<?php echo esc_attr($trezor_connected ? 1 : 0); ?>" />
                    <input type="hidden" name="trezor_challenge_visual" id="trezor_challenge_visual" value="" />
                    <input type="hidden" name="trezor_challenge_hidden" id="trezor_challenge_hidden" value="" />
                    <input type="hidden" name="trezor_signature" id="trezor_signature" value="" />
                    <input type="hidden" name="trezor_connect_changed" id="trezor_connect_changed" value="0" />
                </th>
                <td>
                    <span class="trezor_linked"<?php if (!$trezor_connected) echo(' style="display: none;"'); ?>>TREZOR device is linked to your account.</span>
                    <span class="trezor_unlinked"<?php if ($trezor_connected) echo(' style="display: none;"'); ?>>TREZOR device not linked. To link it with your account click on Sign in with TREZOR.</span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="trezor_address"><span class="trezor_linked"<?php if (!$trezor_connected) echo(' style="display: none;"'); ?>>Unlink TREZOR</span><span class="trezor_unlinked"<?php if ($trezor_connected) echo(' style="display: none;"'); ?>>Sign In</span></label>
                </th>
                <td>
                    <div class="trezor_linked"<?php if (!$trezor_connected) echo(' style="display: none;"'); ?>>
                        <div id="unlink_password" style="display: none;">
                            <input type="password" name="unlink_password" placeholder="Zadejte své přihlašovací heslo" size="25" />
                        </div>
                        <button id="unlink_button" type="button" class="button button-default" onclick="javascript:showUnlinkPasswordField()">Unlink TREZOR</button>
                    </div>
                    <div class="trezor_unlinked"<?php if ($trezor_connected) echo(' style="display: none;"'); ?>>
                        <div id="link_password" style="display: none;">
                            <input type="password" name="link_password" placeholder="Zadejte své přihlašovací heslo" size="25" />
                            <button id="link_button" type="button" class="button button-primary" onclick="javascript:trezorLink()">Link TREZOR</button>
                        </div>
                        <div id="link_sign">
                            <trezor:login callback="trezorConnect" icon="<?php echo $this->getLogoUrl() ?>"></trezor:login>
                            <script src="https://trezor.github.io/connect/login.js" type="text/javascript"></script>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <span id="trezor_connect_result"></span>
                </td>
            </tr>
        </table>
    <?php
    }

    function save_extra_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id))
            return false;

        $address            = sanitize_text_field($_POST['trezor_address']);
        $public_key            = sanitize_text_field($_POST['trezor_publickey']);
        $challenge_visual    = sanitize_text_field($_POST['trezor_challenge_visual']);
        $challenge_hidden    = sanitize_text_field($_POST['trezor_challenge_hidden']);
        $signature            = sanitize_text_field($_POST['trezor_signature']);

        $connect_changed    = sanitize_text_field($_POST['trezor_connect_changed']);
        $connected            = sanitize_text_field($_POST['trezor_connected']);

        if ($connect_changed == "0")
            return false;

        if (($address !== '') && !($this->verify($challenge_hidden, $challenge_visual, $public_key, $signature)))
            return false;

        update_user_meta($user_id,'trezor_address', $address);
        update_user_meta($user_id,'trezor_publickey', $public_key);

        return true;
    }

    function trezor_login_footer() {
        $this->load_file(self::slug . '-trezor-login-script', '/js/frontend.js', true);
        $this->load_file(self::slug . '-trezor-login-style',  '/css/login.css');

        $content = ob_get_contents();
        $content = preg_replace('/\<\/form\>/', '<div id="wp-trezor-login"><trezor:login callback="trezorLogin" icon="'.$this->getLogoUrl().'"></trezor:login><script src="https://trezor.github.io/connect/login.js" type="text/javascript"></script></div></form>',$content);

        if (filter_input(INPUT_GET, 'error') == "trezor_unpaired") {
            if (!preg_match('/\<div id="login_error"\>/', $content)) {
                $content = preg_replace('/\<\/h1\>/', '</h1><div id="login_error"></div>',$content);
            }
            $content = preg_replace('/\<div id="login_error"\>[^\<]*\<\/div\>/', '<div id="login_error"><strong>ERROR</strong>: TREZOR device not linked. Please login into your account and go to user profile setting to link it.<br></div>',$content);
        }

        ob_get_clean();
        echo $content;
    }

} // end class
new WPTrezorPlugin();

?>
