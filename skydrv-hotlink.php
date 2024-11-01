<?php
/*
Plugin Name: skydrv-hotlink
Plugin URI: http://wordpress.org/plugins/skydrv-hotlink/
Description: A plugin that converts links to OneDrive (nee skydrive) documents into direct download links. Simple to use, unobtrusive, adds convenience for users.
Version: 2014.07.03
Author: Dino Chiesa
Author URI: http://www.dinochiesa.net
Donate URI: http://dinochiesa.github.io/Skydrv-hotlink-Donate.html
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
*/

// prevent direct access
if ( !function_exists('skydrv_hl_safeRedirect') ) {
    function skydrv_hl_safeRedirect($location, $replace = 1, $Int_HRC = NULL) {
        if(!headers_sent()) {
            header('location: ' . urldecode($location), $replace, $Int_HRC);
            exit;
        }
        exit('<meta http-equiv="refresh" content="4; url=' .
             urldecode($location) . '"/>');
        return;
    }
}
if(!defined('WPINC')){
    skydrv_hl_safeRedirect("http://" . $_SERVER["HTTP_HOST"]);
}

$skydrv_hl_loglevel = 0; // bitfield: 1 == global, 2 = ajax/json, 4 = ajax/html

if (!function_exists('skydrv_hl_log')){
    function skydrv_hl_log( $lvl, $message ) {
        global $skydrv_hl_loglevel;
      if ( WP_DEBUG === true  || ($skydrv_hl_loglevel>0 &&
                                  ($lvl & $skydrv_hl_loglevel) != 0)) {
      if( is_array( $message ) || is_object( $message ) ){
          // error_log
          echo "<strong>skydrv_hl:</strong> " . print_r( $message, true ) . "<br/>\n";
      }
      else {
        echo "<strong>skydrv_hl:</strong> " . $message . "<br/>\n";
      }
    }
  }
}


skydrv_hl_log( 1, "definitions" );

if ( ! defined( 'SKYDRVHL_PLUGIN_BASENAME' ) )
    define( 'SKYDRVHL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'SKYDRVHL_PLUGIN_NAME' ) )
    define( 'SKYDRVHL_PLUGIN_NAME', trim( dirname( SKYDRVHL_PLUGIN_BASENAME ), '/' ) );

if ( ! defined( 'SKYDRVHL_PLUGIN_DIR' ) )
    define( 'SKYDRVHL_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . SKYDRVHL_PLUGIN_NAME );


skydrv_hl_log( 1, "variable init" );

$skydrv_hl_v = 5;
$skydrv_hl_ajax_action = 'skydrv-hotlink';
$skydrv_hl_keylen = 56;
$skydrv_hl_options = 'skydrv_hl_options';
$skydrv_hl_settings_group = 'skydrv_hl_settings_group';
$skydrv_hl_settings_section1 = 'skydrv_hl_settings_section1';
$skydrv_hl_nonce = 'skydrv-hl-nonce';

skydrv_hl_log( 1, "register hooks" );
register_activation_hook(__FILE__, 'skydrv_hl_activate_fn');
register_activation_hook(__FILE__, 'skydrv_hl_delete_plugin_options');
register_uninstall_hook(__FILE__, 'skydrv_hl_delete_plugin_options');

skydrv_hl_log( 1, "skydrv_hl_activate_fn" );

function skydrv_hl_activate_fn() {
    global $skydrv_hl_options, $skydrv_hl_keylen, $skydrv_hl_v;
    // setup default values for the options.
    // This module always wipes out any old (stale) settings in the db,
    // upon activation.  If it were important, it could
    // do so conditionally.

    // the array of default values
    $a = array( "txt_cache_life" => "42",
                'txt_hashkey' => skydrv_hl_genRandomString($skydrv_hl_keylen),
                'v' => $skydrv_hl_v,
                'iv' => skydrv_hl_genRandomString(8));

    update_option($skydrv_hl_options, $a);
}

function skydrv_hl_delete_plugin_options() {
    global $skydrv_hl_options;
    delete_option($skydrv_hl_options);
}


if (is_admin()) {

    skydrv_hl_log( 1, "is_admin()" );

    add_action('admin_init', 'skydrv_hl_admin_init_fn' );
    add_action('admin_init', 'skydrv_hl_check_upgrade_fn' );
    add_action('admin_menu', 'skydrv_hl_admin_menu_fn');
    add_filter( 'plugin_action_links', 'skydrv_hl_plugin_action_links_fn', 10, 2 );

    function skydrv_hl_check_upgrade_fn() {
        global $skydrv_hl_options, $skydrv_hl_v;
        // Test for plugin upgrade - where maybe the option db is missing
        // newly-added data fields.
        $options = get_option($skydrv_hl_options);
        $v = (isset($options['v'])) ? intval($options['v']) : 0;

        if ($v == $skydrv_hl_v) { return; }

        if (!isset($options['txt_hashkey'])) {
            $options['txt_hashkey'] = skydrv_hl_genRandomString($skydrv_hl_keylen);
        }
        if (!isset($options['iv'])) {
            $options['iv'] = skydrv_hl_genRandomString(8);
        }
        $options['v'] = $skydrv_hl_v;

        update_option( $skydrv_hl_options, $options );
    }


    function skydrv_hl_admin_init_fn() {
        // check the version of Wordpress
        global $skydrv_hl_options,
            $skydrv_hl_settings_group,
            $skydrv_hl_settings_section1;
        $plugin = plugin_basename( __FILE__ );
        $plugin_data = get_plugin_data( __FILE__, false );

        register_setting( $skydrv_hl_settings_group,
                          $skydrv_hl_options, // db entry?
                          'skydrv_hl_validate_options_fn' );

        // I am not a huge fan of this settings api.
        // It seems much simpler to just render it via a VIEW form.

        add_settings_section($skydrv_hl_settings_section1,
                             '', // title
                             'skydrv_hl_plg_section1_head_display_fn',
                             __FILE__);

        add_settings_field(
            'skydrv_hl_cache_life',
            '<strong>Cache Lifetime (minutes)</strong><br/>'.
            '<em>The plugin will retain a previous result for a ' .
            'document, before checking again with Skydrive again. ' .
            'If you set this to zero, the plugin will not cache ' .
            'results, which leads to slower page rendering but more ' .
            'accuracy. If you set it to a higher number, it means ' .
            'the plugin will re-use older links for a given ' .
            'document, and those links may become stale. I don&apos;t ' .
            'know the optimal value. ' .
            '60 minutes is probably safe.</em>',
            'skydrv_hl_cache_life_fn',
            __FILE__,
            $skydrv_hl_settings_section1);

        add_settings_field(
            'skydrv_hl_hashkey',
            '<strong>key for encrypting timestamps</strong><br/>'.
            '<em>The plugin uses encrypted timestamps to prevent replay ' .
            'attacks. It uses Blowfish with a randomly-generated key and iv. ' .
            'The key is shown here. You can just keep the random value.</em>',
            'skydrv_hl_hashkey_fn',
            __FILE__,
            $skydrv_hl_settings_section1);

        add_settings_field(
            'skydrv_hl_iv',
            '<strong>Initialization vector for encryption</strong><br/>'.
            '<em>This is the IV used for encryption. You probably just want ' .
            'to keep the random value.</em>',
            'skydrv_hl_iv_fn',
            __FILE__,
            $skydrv_hl_settings_section1);
    }

    function skydrv_hl_plg_section1_head_display_fn() {
        echo "<!-- put a description of the section here -->\n";
    }

    function skydrv_hl_admin_menu_fn() {
        add_options_page('Skydrive Hotlink Options',
                         'Skydrive Hotlinks',
                         'administrator',
                         __FILE__,
                         'skydrv_hl_render_form');
    }

    function skydrv_hl_has_mcrypt_algo ($algo) {
        if ( !function_exists('mcrypt_list_algorithms') ) {
            return false;
        }
        $algorithms = mcrypt_list_algorithms();
        foreach ($algorithms as $cipher) {
            if (strcasecmp($cipher, $algo) == 0) { return true; }
        }
        return false;
    }

    function skydrv_hl_render_form () {
        global $skydrv_hl_settings_group, $skydrv_hl_options, $wp_version;
        $options = get_option($skydrv_hl_options);
        $notice = '';
        if (!$options || !is_array($options) || !isset($options['txt_cache_life'] )) {
            /* should never happen */
            skydrv_hl_log( 1, "skydrv_hl_render_form: forcing activation..." );
            skydrv_hl_activate_fn();
        }

        if ( version_compare($wp_version, "3.3", "<" ) ) {
            $notice .= '<li>Notice: This plugin requires WordPress 3.3 or higher. Your version is ' . $wp_version . '. This plugin will not work properly. Please upgrade WordPress and try again.</li>' . "\n";
        }

        if (!function_exists('mcrypt_encrypt') ||
            !function_exists('mcrypt_decrypt')) {
            $notice .= '<li>Notice: Your PHP is missing one of both of the mcrypt_{en,de}crypt() functions. This plugin will not work properly. </li>' . "\n";
        }
        elseif (!skydrv_hl_has_mcrypt_algo ("blowfish") ) {
            $notice .= '<li>Notice: Your PHP mcrypt cannot do Blowfish. This plugin will not work properly. </li>' . "\n";
        }

        if (strcmp($notice,'') != 0) {
            $notice = '<ul style="margin-left:32px;list-style-type:disc;color:red;">' .
                "\n" . $notice . "\n</ul>\n";
        }
        $sections_id = __FILE__;
        include SKYDRVHL_PLUGIN_DIR . '/view/admin-form.php';
    }


    function skydrv_hl_cache_life_fn() {
        global $skydrv_hl_options;
        $options = get_option($skydrv_hl_options);

        if (!$options || !is_array($options) || !isset($options['txt_cache_life'] )) {
            /* should never happen */
            skydrv_hl_activate_fn();
        }

        echo
            "<input type='text' id='skydrv_hl_cache_life' \n" .
            "    title='The period in minutes.' \n " .
            "    name='skydrv_hl_options[txt_cache_life]' \n" .
            "    value='" . $options['txt_cache_life'] . "'/>\n";
    }

    function skydrv_hl_hashkey_fn() {
        global $skydrv_hl_options;
        $options = get_option($skydrv_hl_options);

        if (!$options || !is_array($options) || !isset($options['txt_hashkey'] )) {
            /* should never happen */
            skydrv_hl_activate_fn();
        }

        echo
            "<input type='text' id='skydrv_hl_hashkey' \n" .
            "    title='The encryption key.' \n " .
            "    name='skydrv_hl_options[txt_hashkey]' \n" .
            "    value='" . $options['txt_hashkey'] . "'/>\n";
    }

    function skydrv_hl_iv_fn() {
        global $skydrv_hl_options;
        $options = get_option($skydrv_hl_options);

        if (!$options || !is_array($options) || !isset($options['iv'] )) {
            /* should never happen */
            skydrv_hl_activate_fn();
        }

        echo
            "<input type='text' id='skydrv_hl_iv' \n" .
            "    title='The initialization vector.' \n " .
            "    name='skydrv_hl_options[iv]' \n" .
            "    value='" . $options['iv'] . "'/>\n";
    }

    function skydrv_hl_genRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';
        $len = strlen($characters)-1;
        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, $len)];
        }
        return $string;
    }

    function skydrv_hl_validate_options_fn ($input) {
        global $skydrv_hl_keylen;
        $output = array();
        $output['txt_cache_life'] = intval($input['txt_cache_life']);
        $output['txt_hashkey'] = strval($input['txt_hashkey']);
        $keylen = strlen($output['txt_hashkey']);
        if ($keylen < $skydrv_hl_keylen) {
            $output['txt_hashkey'] .= skydrv_hl_genRandomString($skydrv_hl_keylen-$keylen);
        }

        $output['iv'] = (isset($input['iv'])) ? $input['iv'] :
            skydrv_hl_genRandomString(8);

        $ivlen = strlen($output['iv']);
        if ($ivlen < 8) {
            $output['iv'] .= skydrv_hl_genRandomString(8-$ivlen);
        }
        return $output;
    }

    function skydrv_hl_plugin_action_links_fn( $links, $file ) {
        if ( $file == plugin_basename( __FILE__ ) ) {
            $plg_link = '<a href="'. get_admin_url() .
                'options-general.php?page=skydrv-hotlink/skydrv-hotlink.php">' .
                __('Settings') . '</a>';
            // make our 'Settings' link appear last
            array_push( $links, $plg_link );
        }
        return $links;
    }
}

/* ------------------------------------------------------- */
/* operational stuff follows  */
/* ------------------------------------------------------- */

skydrv_hl_log( 1, "operational declarations" );

function skydrv_hl_genRandomNonnumericString($length = 10) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ*&$#!@()[]{}/?\':;|~`<>';
    $string = '';
    $len = strlen($characters)-1;
    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[mt_rand(0, $len)];
    }
    return $string;
}


function skydrv_hl_getPickledTimeToken() {
    global $skydrv_hl_options;
    // To authenticate ajax requests, the caller must send a token that
    // this script generates and provides, and then validates on the
    // reading side. The token is time(), which is salted, then
    // encrypted, then base64 encoded.
    $tm = time();
    // To prevent forgery and discourage key discovery, add salt, as a
    // prefix and postfix to the time value. The salt can be anything,
    // but must be non-numeric, to allow it to be stripped on the
    // reading side. See skydrv_hl_unpickleTimeToken(). It may be overkill
    // for this purpose, but it works.
    $salt1 = skydrv_hl_genRandomNonnumericString(mt_rand(7, 12));
    $salt2 = skydrv_hl_genRandomNonnumericString(mt_rand(7, 12));
    $token = $salt1 . strval($tm) . $salt2;

    if (function_exists('mcrypt_encrypt')) {
        $options = get_option($skydrv_hl_options);
        $enc = mcrypt_encrypt( MCRYPT_BLOWFISH,
                               $options['txt_hashkey'],
                               $token,
                               MCRYPT_MODE_CBC,
                               $options['iv']);
        $b64 = base64_encode($enc);
    }
    else {
        // mcrypt is not available.
        // resort to "light scrambling."
        $enc = base64_encode($token);
        $b64 = str_rot13($enc);
    }
    $b64 = preg_replace("/=/", "%3D", $b64);
    return $b64;
}


function skydrv_hl_unpickleTimeToken($b64) {
    global $skydrv_hl_options;
    $response = array();
    $response['b64'] = $b64;
    $b64 = preg_replace("/%3D/", "=", $b64); // unnecessary?

    if (function_exists('mcrypt_encrypt')) {
        $enc = base64_decode($b64);
        // To authenticate ajax requests, the caller must send a token that
        // this script generates and provides, and then validates on the
        // reading side. The token is a salted-then-encrypted time
        // value. This fn is used on the reading side. It base64-decodes the
        // token, then decrypts, then strips the leading non-numerics,
        // then takes the intval() of the result.
        $options = get_option($skydrv_hl_options);
        $token = mcrypt_decrypt( MCRYPT_BLOWFISH,
                                 $options['txt_hashkey'],
                                 $enc,
                                 MCRYPT_MODE_CBC,
                                 $options['iv']);
        $response['token'] = $token;
    }
    else {
        // mcrypt is not available.
        $enc = str_rot13($b64);
        $token = base64_decode($enc);
    }

    // $token contains the time value enveloped in leading and
    // trailing salt strings.
    $timeval = preg_replace('/[^0-9]/Uis', '', $token);
    $response['timeval'] = $timeval;
    $tm = intval($timeval);
    $response['tm'] = $tm;
    //return $tm;
    return $response;
}


/*
 * In Wordpress, all ajax requests *should* go through admin-ajax.php,
 * which routes requests to the registered modules. This block provides
 * the admin-ajax.php url to the page.
 *
 * In the following, the plugin registers for ajax requests with a
 * particular action.  wp_ajax_FOO is the 'action id' for a GET or POST
 * to admin-ajax.php that has the query parameter named 'action' set to
 * 'FOO'.  wp_ajax_nopriv_FOO is the corresponding action id for users
 * that are not logged in.
 *
 * Here, call add_action() to register a handler for my AJAX requests.
 * Since both logged in and not logged in users can send this AJAX
 * request, this module calls add_action() twice.
 *
 * There is a companion browser-side JS module that uses jquery to find
 * all specially-marked anchor tags in a document, and for each one,
 * sends out an AJAX request, gets hotlinks, and replaces all links on
 * those anchors with those hotlinks.  That logic is contained in z.js .
 *
 *
 ******/

skydrv_hl_log( 1, "ajax setup" );

add_action( "wp_ajax_nopriv_{$skydrv_hl_ajax_action}",
            'skydrv_hl_handle_ajax_request' );
add_action( "wp_ajax_{$skydrv_hl_ajax_action}",
            'skydrv_hl_handle_ajax_request' );

require_once SKYDRVHL_PLUGIN_DIR . '/scraper.php';

function skydrv_hl_handle_ajax_request() {
    global $skydrv_hl_nonce, $skydrv_hl_loglevel;
    skydrv_hl_log( 4, "ajax request" );

    // no need to validate 'action'. admin-ajax.php has already done so.
    if (isset($_GET['docid']) && isset($_GET['nonce']) && isset($_GET['token'])) {
        //$nonce = $_GET['nonce']; // no need to explicitly hold this
        skydrv_hl_log( 4, "check referer" );
        check_ajax_referer( $skydrv_hl_nonce, 'nonce' );
        $response = array();

        skydrv_hl_log( 4, "unpack" );
        // check that the request is fresh/authentic
        $token = $_GET['token'];
        $token = str_replace(" ","+",$token); // fixup post encoding

        if ((0x2 & $skydrv_hl_loglevel) != 0) {
            $response['token'] = $token;
        }
        $unpickle = skydrv_hl_unpickleTimeToken($token);
        $tm = $unpickle['tm'];
        if ((0x2 & $skydrv_hl_loglevel) != 0) {
            $response = array_merge($response, $unpickle);
        }
        $now = time();
        if ((0x2 & $skydrv_hl_loglevel) != 0) {
            $response['now'] = $now;
        }
        $delta = $now - $tm;
        skydrv_hl_log( 4, "delta: " . $delta );
        if ((0x2 & $skydrv_hl_loglevel) != 0) {
            $response['delta'] = $delta;
        }
        // the token is valid for 60 seconds
        if ($delta < 0 || $delta > 60) {
            //die(-3);
        }
        else {
            $docid = $_GET['docid'];
            $response['docid'] = $docid;
            $response = array_merge($response,
                                    SkydrvHotlinkScraper::linkForDocument($docid));
        }
    }
    else {
        $response = array("error" => "you must specify these parameters: docid, nonce, tm.");
        //echo json_encode( $response ) ;
    }

    $r = json_encode( $response ) ;

    header( "Content-Type: application/json" );
    echo $r;
    exit;
}


skydrv_hl_log( 1,"script block" );
/*
 * Here, emit the necessary script resources to communicate with
 * the plugin.
 */
function skydrv_hl_scriptblock() {
    global $skydrv_hl_nonce, $skydrv_hl_ajax_action;
    $nonce = wp_create_nonce($skydrv_hl_nonce);
    echo "<script type='text/javascript'>\n" .
        " var SkydrvHotPlg = {\n" .
        "   ajaxurl : '" . admin_url( 'admin-ajax.php' ) . "',\n" .
        "   action : '" . $skydrv_hl_ajax_action . "',\n" .
        "   token : '" . skydrv_hl_getPickledTimeToken() . "',\n" .
        "   nonce : '" . $nonce . "'\n" .
        " };\n" .
        "</script>\n";
}

add_action('wp_print_scripts','skydrv_hl_scriptblock');
add_action( 'wp_enqueue_scripts', 'skydrv_hl_add_script');

function skydrv_hl_add_script() {
    // external script dependencies
    wp_enqueue_script('jquery');
    wp_enqueue_script('skydrv-hotlink', plugin_dir_url( __FILE__ ) . 'z.js',
                      array('jquery'));
}

/*
 *
 * An example. Suppose you embed a link like this into a Wordpress
 * page or post:
 *
 * <a href='https://skydrive.live.com/?id=842434EBE9688900!1123&cid=842434ebe9688900#'
 *    class='skydrv-hotlink'
 *    title='My Design Document'>Download</a>
 *
 * After the Wordpress page is rendered in the browser, the z.js logic
 * that is part of this plugin scans the html and finds the anchor. It
 * then makes this asynchronous HTTP request:
 *
 * GET /wp-admin/admin-ajax.php?
 *  action=skydrive-hotlink&nonce=xxxx&token=zzzzz&docid=842434EBE9688900!1123
 *
 * The nonce and the token are generated by this php module.
 *
 * The javascript client then gets a result something like this:
 *    { "link" : "http://ffff.xxx.zz/kdjdkjdkdj" }
 *
 * The actual link will be very very long. The logic in z.js then replaces
 * the href on the anchor with the new direct-download link. It does this for
 * each anchor in turn.
 *
 */

skydrv_hl_log( 1, "ends" );

?>