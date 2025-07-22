<div
    x-show="Object.keys(grouped).length === 0"
    class="flex flex-col items-center justify-center mt-20 space-y-6 text-gray-600">
    <!-- Lottie JSON animation (optional, needs lottie-player script) -->
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <lottie-player
        src="https://assets5.lottiefiles.com/packages/lf20_qp1q7mct.json"
        background="transparent"
        speed="1"
        style="width: 320px; height: 320px"
        loop
        autoplay>
    </lottie-player>

    <h2 class="!text-2xl sm:text-3xl !font-extrabold text-gray-800">No Entries Found</h2>

    <p class="!text-base !sm:text-lg text-gray-500 max-w-md text-center">
        Looks like this form hasn't received any submissions yet.<br class="hidden sm:block">
        Sit back and relax — we’ll show the entries here as soon as they arrive! 📨
    </p>
</div>