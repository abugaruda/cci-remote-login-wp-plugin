<?php
/*
 * Plugin Name: CCI Remote Login
 * Description: Permette il login remoto utilizzando un web service e un blocco di login.
 * GitHub Plugin URI: https://github.com/abugaruda/cci-remote-login-wp-plugin
 * Primary Branch: main
 */

// Funzione per interrogare il server remoto
function remote_login($username, $password, $societyId) {
    $url = "https://registration.ccicongress.com/socweb/S2SRemoteLogin.asp";

    $xml_request = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
    $xml_request .= "<dataroot>";
    $xml_request .= "<form>";
    $xml_request .= "<field id=\"Username\">" . htmlspecialchars($username) . "</field>";
    $xml_request .= "<field id=\"Password\">" . htmlspecialchars($password) . "</field>";
    $xml_request .= "<field id=\"Society\">" . htmlspecialchars($societyId) . "</field>";
    $xml_request .= "</form>";
    $xml_request .= "</dataroot>";

    $encoded_request = urlencode(base64_encode($xml_request));
    $post_fields = "TKN=" . $encoded_request;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        return [
            'success' => false,
            'error_code' => 'curl_error',
            'error_description' => 'Failed to connect to the remote server: ' . $curl_error
        ];
    }

    $decoded_response = base64_decode($response);
    $xml_response = simplexml_load_string($decoded_response);

    if ($xml_response === false) {
        return [
            'success' => false,
            'error_code' => 'xml_error',
            'error_description' => 'Failed to parse XML response.'
        ];
    }

    if ((string)$xml_response->feedback->field[2] == 'OK') {
        return [
            'success' => true,
            'name' => (string)$xml_response->feedback->field[3],
            'surname' => (string)$xml_response->feedback->field[4],
            'participant_id' => (string)$xml_response->feedback->field[6],
            'email' => (string)$xml_response->feedback->field[8]
        ];
    } else {
        return [
            'success' => false,
            'error_code' => (string)$xml_response->feedback->field[0],
            'error_description' => (string)$xml_response->feedback->field[1]
        ];
    }
}

// Funzione di rendering per il blocco
function render_remote_login_block($attributes) {
    ob_start();

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        ?>
        <div id="remote-login-block">
            <input type="hidden" id="members-area-url" value="<?php echo get_theme_mod("members_area_url"); ?>">
            <div class="mb-2">
                <?php echo sprintf(__('Welcome, %s!', 'remote-login'), esc_html($current_user->user_firstname)); ?>
            </div>
            <div class="mb-2">
                <?php echo do_shortcode("[login_automatico]"); ?>
            </div>
            <div class="mb-2">
                <button type="button" class="btn btn-dark" id="logout-button"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-door-closed-fill" viewBox="0 0 16 16"><path d="M12 1a1 1 0 0 1 1 1v13h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V2a1 1 0 0 1 1-1zm-2 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/></svg> <?php _e('Logout', 'remote-login'); ?></button>
            </div>
            <div id="login-message" class="alert"></div>
        </div>
        <?php
    } else {
        ?>
        <div id="remote-login-block" class="mx-auto" style="max-width: 480px;">
            <div class="mb-2">
                <label for="username" class="form-label"><?php _e('Username:', 'remote-login'); ?></label>
                <input type="text" class="form-control" id="username" name="username" />
            </div>
            <div class="mb-2">
                <label for="password" class="form-label"><?php _e('Password:', 'remote-login'); ?></label>
                <input type="password" class="form-control" id="password" name="password" />
            </div>
            <input type="hidden" id="members-area-url" value="<?php echo get_theme_mod("members_area_url"); ?>">

            <div>
                <button type="button" class="btn btn-primary" id="login-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-person-fill-lock" viewBox="0 0 16 16"><path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5v-1a2 2 0 0 1 .01-.2 4.49 4.49 0 0 1 1.534-3.693Q8.844 9.002 8 9c-5 0-6 3-6 4m7 0a1 1 0 0 1 1-1v-1a2 2 0 1 1 4 0v1a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1zm3-3a1 1 0 0 0-1 1v1h2v-1a1 1 0 0 0-1-1"/></svg> <?php _e('Login using CCI credentials', 'remote-login'); ?>
                </button>
                <a type="button" class="btn btn-outline-secondary ms-1" id="reset-button" href="https://registration.ccicongress.com/socweb/MainRecoverPwd.asp?IDAssociazione=6232&Lang=EN&noflag=">
                    <?php _e('Reset password', 'remote-login'); ?>
                </a>
            </div>
            <div class="alert" id="login-message"></div>
        </div>
        <?php
    }

    return ob_get_clean();
}

// Funzione per registrare il blocco e caricare gli script
function remote_login_block_assets() {
    wp_register_script(
        'remote-login-block',
        plugins_url('remote-login-block.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-data'],
        filemtime(plugin_dir_path(__FILE__) . 'remote-login-block.js')
    );

    wp_localize_script('remote-login-block', 'remoteLoginData', [
        'nonce' => wp_create_nonce('wp_rest'),
    ]);

    register_block_type_from_metadata(__DIR__, [
        'editor_script' => 'remote-login-block',
        'render_callback' => 'render_remote_login_block',
    ]);
}
add_action('init', 'remote_login_block_assets');

// Enqueue script solo nel frontend
function enqueue_remote_login_script_frontend() {
    if (!is_admin()) {
        wp_enqueue_script('remote-login-block');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_remote_login_script_frontend');

// Funzione per l'autenticazione personalizzata
function remote_authenticate($user, $username, $password) {
    if (empty($username) || empty($password)) {
        return null; // Lascia che WordPress gestisca questo caso
    }

    $societyId = '6232';
    $result = remote_login($username, $password, $societyId);

    if ($result['success']) {
        $user = get_user_by('login', $username);
        if (!$user) {
            $user_id = wp_create_user($username, wp_generate_password(), $result['email']);
            if (is_wp_error($user_id)) {
                return $user_id;
            }
            $user = get_user_by('id', $user_id);
        }

        wp_update_user([
            'ID' => $user->ID,
            'first_name' => $result['name'],
            'last_name' => $result['surname'],
            'user_email' => $result['email']
        ]);
        update_user_meta($user->ID, 'participant_id', $result['participant_id']);
        update_user_meta($user->ID, 'temporary_password', $password); // Salva la password temporanea

        return $user;
    } else {
        return new WP_Error('remote_login_failed', __('<strong>ERROR</strong>: ' . $result['error_description']));
    }
}
add_filter('authenticate', 'remote_authenticate', 10, 3);

// Aggiunge un endpoint REST API per gestire il logout
add_action('rest_api_init', function () {
    register_rest_route('remote-login/v1', '/logout', [
        'methods' => 'POST',
        'callback' => 'handle_remote_logout',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

// Funzione per gestire il logout
function handle_remote_logout() {
    wp_logout();
    return [
        'success' => true,
        'message' => __('You have been successfully logged out.', 'remote-login'),
    ];
}

// Aggiunge un endpoint REST API per gestire il login remoto
add_action('rest_api_init', function () {
    register_rest_route('remote-login/v1', '/authenticate', [
        'methods' => 'POST',
        'callback' => 'handle_remote_authenticate',
        'permission_callback' => '__return_true',
    ]);
});

// Funzione per gestire il login remoto
function handle_remote_authenticate($request) {
    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username'] ?? '');
    $password = sanitize_text_field($params['password'] ?? '');
    $societyId = '6232';

    $result = remote_login($username, $password, $societyId);

    if ($result['success']) {
        $user = get_user_by('login', $username);
        if (!$user) {
            $user_id = wp_create_user($username, wp_generate_password(), $result['email']);
            if (is_wp_error($user_id)) {
                return new WP_Error('user_creation_failed', __('User creation failed.'));
            }
            $user = get_user_by('id', $user_id);
        }

        wp_update_user([
            'ID' => $user->ID,
            'first_name' => $result['name'],
            'last_name' => $result['surname'],
            'user_email' => $result['email']
        ]);
        update_user_meta($user->ID, 'participant_id', $result['participant_id']);
        update_user_meta($user->ID, 'temporary_password', $password); // Salva la password temporanea

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        return [
            'success' => true,
            'name' => $result['name'],
        ];
    } else {
        return new WP_Error('authentication_failed', __($result['error_description']));
    }
}

// Funzione per il token di autorizzazione e generazione del token di login
function generate_authorization_token() {
    $public_key = 'CYPR180311';
    $date = (new DateTime("now", new DateTimeZone('Europe/Rome')))->format('YmdH');
    return 'ws3rdp' . $date . $public_key;
}

// Prima richiesta per ottenere TokenAuth e ParticipantID
function remote_initial_login($username, $password, $societyId) {
    $url = "https://registration.ccicongress.com/socweb/S2SLogin3RDParties.asp";

    $xml_request = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
    $xml_request .= "<dataroot>";
    $xml_request .= "<wsauth>" . generate_authorization_token() . "</wsauth>";
    $xml_request .= "<form>";
    $xml_request .= "<field id=\"Username\">" . htmlspecialchars($username) . "</field>";
    $xml_request .= "<field id=\"Password\">" . htmlspecialchars($password) . "</field>";
    $xml_request .= "<field id=\"Society\">" . htmlspecialchars($societyId) . "</field>";
    $xml_request .= "<field id=\"Lang\">EN</field>";
    $xml_request .= "</form>";
    $xml_request .= "</dataroot>";

    $encoded_request = urlencode(base64_encode($xml_request));
    $post_fields = "TKN=" . $encoded_request;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return ['success' => false, 'error' => 'Error in connecting to the remote server'];
    }

    $decoded_response = base64_decode($response);
    $xml_response = simplexml_load_string($decoded_response);

    if ($xml_response === false || (string)$xml_response->feedback->field[2] !== 'OK') {
        return ['success' => false, 'error' => 'Failed authentication'];
    }

    return [
        'success' => true,
        'TokenAuth' => (string)$xml_response->feedback->field[3],
        'ParticipantID' => (string)$xml_response->feedback->field[6],
        'RedirectUrl' => (string)$xml_response->feedback->field[7]
    ];
}




function generate_autologin_token($token_auth, $participant_id) {
    $society_id = '6232';
    $lang = 'EN';

    $xml_data = '<?xml version="1.0" encoding="utf-8"?>';
    $xml_data .= '<dataroot>';
    $xml_data .= '<wsauth>' . generate_authorization_token() . '</wsauth>';
    $xml_data .= '<form>';
    $xml_data .= '<field id="TokenAuth">' . md5($token_auth) . '</field>';
    $xml_data .= '<field id="Society">' . htmlspecialchars($society_id) . '</field>';
    $xml_data .= '<field id="ParticipantID">' . htmlspecialchars($participant_id) . '</field>';
    $xml_data .= '<field id="Lang">' . htmlspecialchars($lang) . '</field>';
    $xml_data .= '</form>';
    $xml_data .= '</dataroot>';

    return urlencode(base64_encode($xml_data));
}

// Seconda richiesta di login automatico usando TokenAuth e ParticipantID
function perform_autologin($TokenAuth, $ParticipantID, $societyId) {
    $url = "https://registration.ccicongress.com/socweb/AutoLogin.asp";

    $xml_request = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
    $xml_request .= "<dataroot>";
    $xml_request .= "<wsauth>" . generate_authorization_token() . "</wsauth>";
    $xml_request .= "<form>";
    $xml_request .= "<field id=\"TokenAuth\">" . $TokenAuth . "</field>";
    $xml_request .= "<field id=\"Society\">" . htmlspecialchars($societyId) . "</field>";
    $xml_request .= "<field id=\"ParticipantID\">" . htmlspecialchars($ParticipantID) . "</field>";
    $xml_request .= "<field id=\"Lang\">EN</field>";
    $xml_request .= "</form>";
    $xml_request .= "</dataroot>";

    $encoded_request = urlencode(base64_encode($xml_request));
    return $url . "?TKN=" . $encoded_request;
}

// Funzione per redirigere verso l'area membri remota dopo l'autologin
function redirect_to_remote_member_area() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_die(__('You must be logged in to access this feature.'));
    }

    $participant_id = get_user_meta($user_id, 'participant_id', true);
    $username = wp_get_current_user()->user_login;
    $password = get_user_meta($user_id, 'temporary_password', true);
    //$password = "12345678";
    $societyId = '6232';

    $initial_response = remote_initial_login($username, $password, $societyId);
    if (!$initial_response['success']) {
        wp_die(__('There was an error in the initial login attempt.'));
    }

    /*echo "<pre>";
    print_r($initial_response);
    echo "</pre>";*/

    $autologin_url = perform_autologin($initial_response['TokenAuth'], $initial_response['ParticipantID'], $societyId);
    /*echo "<br>";
    echo "<pre>";
    print_r($autologin_url);
    echo "</pre>";*/

    wp_redirect($autologin_url);
    exit;
}

function login_automatico_shortcode() {
    if (!is_user_logged_in()) {
        return __('You must be logged in to access your profile.');
    }

    $login_url = admin_url('admin-post.php?action=remote_member_login');
    return '<a href="' . esc_url($login_url) . '" target="_blank" class="btn btn-primary"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16"><path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1z"/></svg> Manage your account</a>';
}
add_shortcode('login_automatico', 'login_automatico_shortcode');

add_action('admin_post_remote_member_login', 'redirect_to_remote_member_area');
add_action('admin_post_nopriv_remote_member_login', 'redirect_to_remote_member_area');
?>
