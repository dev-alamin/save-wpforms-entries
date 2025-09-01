<?php
defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;

$has_access_token = Helper::has_access_token();

$per_page  = Helper::get_option( 'entries_per_page', 20 );
$sheet_id  = Helper::get_option( 'google_sheet_id' );
$auto_sync = Helper::get_option( 'google_sheet_auto_sync', true );
$sheet_tab = Helper::get_option( 'google_sheet_tab', 'Sheet1' );

$custom_columns = Helper::get_option( 'cusom_form_columns_settings', array() );


?>

<div x-data="toastHandler()" x-init="init()" x-show="visible"
	x-transition
	:class="{
		'!bg-green-100 text-green-800 border-green-200': type === 'success',
		'!bg-red-100 text-red-800 border-red-200': type === 'error'
	}"
	class="!fixed !bottom-6 !right-6 !px-6 !py-3 !rounded-lg !border !shadow-lg !text-sm !font-medium z-50">
	<span x-text="message"></span>
</div>


<div class="wrap fem-admin-page min-h-screen max-w-7xl !m-auto bg-gray-50 px-8 py-10 text-[15px] font-inter text-gray-800 space-y-10 !m-auto"
	x-data="settingsForm()">
	<!-- Header -->
	<div class="mb-8 bg-slate-700 text-white px-4 py-2 rounded-lg">
		<div class="flex items-center gap-4">
                <img src="<?php echo esc_url( FEM_ASSETS_URL . 'images/logo.jpg' ); ?>" alt="<?php esc_attr_e( 'Forms Entries Manager', 'forms-entries-manager' ); ?>" class="w-16 h-16 object-cover rounded-sm" />
                <div>
                    <h1 class="!text-3xl !font-extrabold !text-indigo-100 !tracking-tight mb-2 flex items-center gap-3">
                    <?php esc_html_e( 'Forms Entries Manager Settings', 'forms-entries-manager' ); ?>
                </h1>
                <p class="text-gray-200 !text-[15px] leading-relaxed !m-0 !mt-2">
                    <?php
                    esc_html_e( 'Browse and manage form entries submitted by users. Click on a form to view its submissions, mark entries as read/unread, or delete them as needed.', 'forms-entries-manager' );
                    ?>
                </p>
                </div>
        </div>
	</div>
	<?php if ( Helper::is_user_revoked() ) : ?>
	<div class="text-center items-center justify-between gap-4 mb-6 border border-yellow-400 bg-yellow-50 text-yellow-800 rounded-lg p-4 shadow-sm">
		<div class="flex-1">
			<p><?php esc_html_e( '✅ Connection to Google Sheets has been successfully revoked. You can connect again with the below button.', 'forms-entries-manager' ); ?></p>
		</div>
	</div>
	<?php endif; ?>

	<div x-data="{ tab: 'google' }" class="fem-settings-tabs mb-10">
		<!-- Tab Control Navigation -->
		<nav class="flex flex-wrap gap-3 border-b border-indigo-200 text-sm font-medium">
			<button
				@click="tab = 'google'"
				:class="tab === 'google' 
					? '!text-white !border-b-2 !border-indigo-600 !bg-indigo-700' 
					: '!text-gray-500 hover:!text-indigo-600 hover:!bg-gray-100'"
				class="!transition-all !px-5 !py-2 !rounded-t-lg !border-b-2 !border-transparent !bg-gray-50 flex items-center gap-2">
				<span>
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="fill-current text-inherit">
						<path d="M160-160v-80h110l-16-14q-52-46-73-105t-21-119q0-111 66.5-197.5T400-790v84q-72 26-116 88.5T240-478q0 45 17 87.5t53 78.5l10 10v-98h80v240H160Zm400-10v-84q72-26 116-88.5T720-482q0-45-17-87.5T650-648l-10-10v98h-80v-240h240v80H690l16 14q49 49 71.5 106.5T800-482q0 111-66.5 197.5T560-170Z"/>
					</svg>
				</span>
				<?php esc_html_e( 'Google Sync', 'forms-entries-manager' ); ?>
			</button>

			<button
				@click="tab = 'csv'"
				:class="tab === 'csv' 
					? '!text-white !border-b-2 !border-indigo-600 !bg-indigo-700' 
					: '!text-gray-500 hover:!text-indigo-600 hover:!bg-gray-100'"
				class="!transition-all !px-5 !py-2 !rounded-t-lg !border-b-2 !border-transparent !bg-gray-50 flex items-center gap-2">
				<span>
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="fill-current text-inherit">
						<path d="M480-480ZM202-65l-56-57 118-118h-90v-80h226v226h-80v-89L202-65Zm278-15v-80h240v-440H520v-200H240v400h-80v-400q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H480Z"/>
					</svg>
				</span>
				<?php esc_html_e( 'Advanced Export', 'forms-entries-manager' ); ?>
			</button>

			<button
				@click="tab = 'general'"
				:class="tab === 'general' 
					? '!text-white !border-b-2 !border-indigo-600 !bg-indigo-700' 
					: '!text-gray-500 hover:!text-indigo-600 hover:!bg-gray-100'"
				class="!transition-all !px-5 !py-2 !rounded-t-lg !border-b-2 !border-transparent !bg-gray-50 flex items-center gap-2">
				<span>
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="fill-current text-inherit">
						<path d="M440-280h80l12-60q12-5 22.5-10.5T576-364l58 18 40-68-46-40q2-14 2-26t-2-26l46-40-40-68-58 18q-11-8-21.5-13.5T532-620l-12-60h-80l-12 60q-12 5-22.5 10.5T384-596l-58-18-40 68 46 40q-2 14-2 26t2 26l-46 40 40 68 58-18q11 8 21.5 13.5T428-340l12 60Zm40-120q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/>
					</svg>
				</span>
				<?php esc_html_e( 'General Settings', 'forms-entries-manager' ); ?>
			</button>
		</nav>
		
		<div x-show="tab === 'csv'">
			<?php require __DIR__ . '/tab/csv-export.php'; ?>
		</div>

		<div x-show="tab === 'google'">
			<!-- ✅ Connected Notice -->
			<?php require __DIR__ . '/tab/google-connection.php'; ?>
		</div>

		<form id="fem-settings-form" @submit.prevent="saveSettings" class="space-y-6">
			<div x-show="tab === 'general'">
				<?php require __DIR__ . '/tab/general-settings.php'; ?>
			</div>
			
			<button type="submit"
				class="!inline-flex mt-5 !items-center !gap-2 !px-6 !py-3 !bg-indigo-600 hover:!bg-indigo-700 !text-white !text-sm !font-semibold !rounded-lg !shadow-sm hover:!shadow-md !transition-all !duration-200"
				:disabled="isSaving">
				<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
					stroke="currentColor" stroke-width="2">
					<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
				</svg>
				<?php echo esc_html__( 'Save Changes', 'forms-entries-manager' ); ?>
			</button>

			<p x-text="message" class="text-sm mt-2 text-green-600 font-medium"></p>
		</form>

	</div>
</div>