<div
    x-show="setError && Object.keys(forms).length === 0"
    class="flex flex-col items-center justify-center mt-20 space-y-6 text-gray-600">

    <lottie-player
        src="<?php echo esc_url( SWPFE_URL . 'assets/admin/lottie/empty-page.json' ); ?>"
        background="transparent"
        speed="1"
        style="width: 320px; height: 320px"
        loop
        autoplay>
    </lottie-player>

    <h2 class="!text-2xl sm:text-3xl !font-extrabold text-gray-800">
        <?php esc_html_e( 'No Entries Found', 'advanced-entries-manager-for-wpforms' ); ?>
    </h2>

    <p class="!text-base !sm:text-lg text-gray-500 max-w-md text-center">
        <?php echo wp_kses_post( sprintf(
            __( "Looks like this form hasn't received any submissions yet.
            <br class='hidden sm:block'>Sit back and relax — 
            we’ll show the entries here as soon as they arrive! 📨", 
            'advanced-entries-manager-for-wpforms' )
        ) ); ?>
    </p>
</div>
