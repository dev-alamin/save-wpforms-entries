=== Forms Entries Manager ===
Contributors: almn-me
Tags: entries, submissions, wpforms, contact form 7, google sheets
Requires at least: 5.4
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The ultimate dashboard to manage, search, and sync entries from WPForms, Contact Form 7, and more. Transform your WordPress site into a mini-CRM.

== Description ==

Tired of juggling form submissions from different plugins? Struggling to find, export, or manage user-submitted data effectively?

**Forms Entries Manager** transforms your WordPress dashboard into a powerful, centralized hub for all your form entries. It provides a beautiful, modern interface to view, search, and manage submissions from popular plugins like **WPForms** and **Contact Form 7**, with more on the way.

Stop letting valuable data get lost in your email inbox. With Forms Entries Manager, you can treat your form submissions like the valuable leads and information they are. It’s more than just an entry logger; it's a mini-CRM that lives right inside your WordPress admin.

### ✨ Why Choose Forms Entries Manager?

* **All Your Entries in One Place:** A unified, clean dashboard for entries from multiple form plugins.
* **A Mini-CRM at Your Fingertips:** Add notes, mark favorites, and manage your workflow without ever leaving your site.
* **Hassle-Free Google Sheets Sync:** Connect your Google account with our secure, one-click OAuth 2.0 process. No more copying and pasting complex API keys! Automatically or manually sync entries to a spreadsheet for backup, reporting, or collaboration.
* **Powerful Search & Filtering:** Instantly find any entry with global search and date-range filtering.
* **Boost Your Productivity:** With bulk actions, quick-view modals, and an intuitive interface, you'll save hours of administrative work.

### 🚀 Key Features

* **Centralized Entry Listing:** View all your forms and their submission counts on a single screen. Navigate entries with smooth pagination.
* **Detailed Modal View:** Click any entry to open a beautiful modal displaying all submitted data without leaving the page.
* **Status Management (Read/Unread):** Entries are automatically marked as "read" when you view them. You can also manually toggle the status to keep your workflow organized.
* **Mark as Favorite:** Star important entries for quick and easy access later.
* **Secure Entry Deletion:** Safely delete single or multiple entries with a simple click.
* **Print-Friendly Entries:** Generate a clean, formatted print view for any submission, perfect for physical records.
* **Seamless Google Sheets Sync:**
    * **One-Click OAuth:** Securely connect to Google without needing to generate or manage API keys. It’s hassle-free!
    * **Manual & Auto Sync:** Sync entries automatically upon submission or manually sync a single entry with a button click.
* **Powerful Data Export:**
    * Export all entries or just a filtered selection.
    * Multiple formats supported, including **CSV, Excel, and PDF**. (PDF/Excel might be a Pro feature)
* **Efficient Bulk Actions:** Select multiple entries to delete, mark as read/unread, favorite, or sync to Google Sheets all at once.
* **Advanced Filtering & Search:**
    * **Date Range Filter:** Easily filter submissions from a specific period.
    * **Global Search:** A powerful search bar that looks through all entry data to find exactly what you need, fast.
* **Internal Notes & Comments:** Add private notes to any entry to track follow-ups, add context, or collaborate with your team—just like a real CRM!

Whether you're a small business owner tracking leads, a blogger managing contact requests, or a developer needing a robust solution for clients, **Forms Entries Manager** is the ultimate tool to supercharge your form submission workflow.

== Installation ==

1.  **From your WordPress dashboard (easiest method):**
    * Navigate to `Plugins > Add New`.
    * Search for "Forms Entries Manager".
    * Click `Install Now` and then `Activate`.

2.  **Via direct upload:**
    * Download the `.zip` file from the WordPress.org plugin repository.
    * Navigate to `Plugins > Add New` in your WordPress dashboard.
    * Click `Upload Plugin` and choose the downloaded `.zip` file.
    * Click `Install Now` and then `Activate`.

3.  **Via FTP:**
    * Download the `.zip` file and unzip it.
    * Upload the `forms-entries-manager` folder to the `/wp-content/plugins/` directory on your server.
    * Navigate to the `Plugins` page in your WordPress dashboard and activate the plugin.

Once activated, you will find the "Entries Manager" menu in your WordPress admin sidebar.

== Frequently Asked Questions ==

**Which form plugins are supported?**
Version 1.0.0 comes with full support for WPForms (Lite & Pro) and Contact Form 7. We are actively working on integrating other popular plugins like Gravity Forms, Ninja Forms, and more in future updates.

**Is the Google Sheets connection secure?**
Absolutely. We use the official Google OAuth 2.0 protocol, which is the industry standard for secure authentication. You grant permission directly to Google, and our plugin securely stores an authorization token. We never see or store your Google password, and you can revoke access at any time.

**Can I export my entries?**
Yes! You can export entries to CSV. We are planning to add support for Excel and PDF exports in a future update.

**Will this plugin slow down my website?**
No. The plugin operates entirely within the WordPress admin area and only runs queries when you are viewing the entries pages. It is built with performance in mind and will not impact your site's front-end speed.

**Is there a Pro version?**
Currently, all the incredible features listed are available in the free version. We may introduce a Pro version in the future with even more advanced capabilities like advanced reporting, additional export formats, and support for more form plugins.

== Screenshots ==

1.  The main dashboard showing an overview of all your forms and their entry counts.
2.  The clean and modern entry listing for a selected form with search and filter options.
3.  The detailed modal view of a single entry, showing all submitted fields.
4.  Using bulk actions to mark multiple entries as read.
5.  The simple, one-click Google Sheets authentication process.
6.  An entry with a private note added, demonstrating the mini-CRM functionality.

== Changelog ==

= 1.0.0 =
* Initial public release. Hooray!
* Full support for WPForms and Contact Form 7.
* Complete feature set including entry listing, modal view, read/unread, favorite, delete, print, and notes.
* Secure, one-click OAuth 2.0 integration for Google Sheets sync.
* Powerful search, date-range filtering, and bulk actions.
* CSV Export functionality.

== Upgrade Notice ==

= 1.0.0 =
This is the first version of Forms Entries Manager. We're excited for you to try it out and transform your form management workflow!