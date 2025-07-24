<?php
function swpfe_get_access_token() {
    $access_token = get_option('swpfe_google_access_token');
    $expires      = (int) get_option('swpfe_google_token_expires', 0);

    // If token is valid and not close to expiry, return it
    if ($access_token && $expires > time() + 60) { // 1 min buffer
        return $access_token;
    }

    // Otherwise, try refreshing token via proxy
    $site = site_url();

    $response = wp_remote_get("https://api.almn.me/oauth/refresh?site=" . urlencode($site));
    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($body['success']) && !empty($body['data']['access_token'])) {
        update_option('swpfe_google_access_token', sanitize_text_field($body['data']['access_token']));
        if (!empty($body['data']['expires_in'])) {
            update_option('swpfe_google_token_expires', time() + intval($body['data']['expires_in']));
        }
        return $body['data']['access_token'];
    }

    return false;
}
