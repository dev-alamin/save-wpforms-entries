<div class="wrap swpfe-admin-page min-h-screen bg-gray-50 px-8 py-10 text-[15px] font-inter text-gray-800">

    <div class="!max-w-3xl !mx-auto">
        <h1 class="!text-4xl !font-extrabold !text-indigo-700 !tracking-tight !flex !items-center !gap-3 !mb-6">
            🔗 WPForms to Google Sheets
        </h1>

        <?php if ($connected): ?>
            <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                ✅ Successfully connected to Google Sheets!
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded-xl p-6 mb-10 border border-gray-200">
            <form method="post" action="options.php" class="space-y-6">
                <?php
                settings_fields('swpfe_google_settings');
                do_settings_sections('swpfe_google_settings');
                ?>

                <div>
                    <label for="swpfe_google_client_id" class="block text-sm font-semibold text-gray-700 mb-1">Google Client ID</label>
                    <input type="text" id="swpfe_google_client_id" name="swpfe_google_client_id" value="<?php echo esc_attr($client_id); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <div>
                    <label for="swpfe_google_client_secret" class="block text-sm font-semibold text-gray-700 mb-1">Google Client Secret</label>
                    <input type="text" id="swpfe_google_client_secret" name="swpfe_google_client_secret" value="<?php echo esc_attr($client_secret); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <?php submit_button('💾 Save Credentials', 'primary', '', false); ?>
            </form>
        </div>

        <div class="bg-white shadow-sm rounded-xl border border-indigo-100 p-6 text-center">
            <h2 class="!text-xl !font-bold !text-gray-800 !mb-3">Google Account Connection</h2>

            <?php if ($access_token): ?>
                <p class="text-green-700 font-medium">✅ Already connected to Google Sheets.</p>
            <?php else: ?>
                <?php
                $oauth_url = "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
                    'client_id'     => $client_id,
                    'redirect_uri'  => 'https://shaliktheme.com/wp-json/wpforms/entries/v1/oauth/callback',
                    'response_type' => 'code',
                    'scope'         => 'https://www.googleapis.com/auth/spreadsheets',
                    'access_type'   => 'offline',
                    'prompt'        => 'consent',
                ]);
                ?>
                <a href="<?php echo esc_url($oauth_url); ?>"
                   class="inline-block bg-indigo-600 hover:bg-indigo-700 !text-white font-medium px-6 py-2 rounded-lg shadow-md transition">
                    🔐 Connect Google Account
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
