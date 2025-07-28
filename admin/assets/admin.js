function formTable(form) {
    return {
        open: false,
        formId: form.form_id,
        formTitle: form.form_title,
        totalEntries: form.entry_count,
        entries: [],
        currentPage: 1,
        pageSize: swpfeSettings.perPage,
        totalPages: 1,
        sortAsc: true,
        sortAscStatus: true,
        dateFrom: "",
        dateTo: "",
        loading: false,
        jumpTo: 1,
        noteOpen: false,
        bulkSelected: [],
        selectAll: false,
        lastCheckedIndex: null,


        entryModalOpen: false,
        selectedEntry: {},
        bgClasses: [
            "swpfe-row-bg-1",
            "swpfe-row-bg-2",
            "swpfe-row-bg-3",
            "swpfe-row-bg-4",
        ],

        get paginatedEntries() {
            return this.entries;
        },
        async performBulkAction(action) {
            if (!this.bulkSelected.length) return;

            const label = action.replace(/_/g, ' ');

            try {
                const res = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/bulk`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': swpfeSettings.nonce
                    },
                    body: JSON.stringify({ ids: this.bulkSelected, action })
                });

                const data = await res.json();

                this.$dispatch('toast', {
                    type: 'success', // or 'error', 'info', etc.
                    message: '✅ Bulk action completed successfully!'
                });

                this.bulkSelected = [];
                this.selectAll = false;

            } catch (error) {
                console.error("Bulk action failed:", error);
                alert('Bulk action failed. Please try again.');
            }
        },

        handleCheckbox(event, entryId) {
            const index = this.entries.findIndex(e => e.id === entryId);
            if (index === -1) return;

            if (event.shiftKey && this.lastCheckedIndex !== null) {
                const start = Math.min(index, this.lastCheckedIndex);
                const end = Math.max(index, this.lastCheckedIndex);

                for (let i = start; i <= end; i++) {
                    const id = this.entries[i].id;
                    if (!this.bulkSelected.includes(id)) {
                        this.bulkSelected.push(id);
                    }
                }
            } else {
                const i = this.bulkSelected.indexOf(entryId);
                if (i > -1) {
                    this.bulkSelected.splice(i, 1);
                } else {
                    this.bulkSelected.push(entryId);
                }
            }

            this.lastCheckedIndex = index;
        },
        async fetchEntries() {
            this.loading = true;
            try {
                const query = new URLSearchParams({
                    form_id: this.formId,
                    page: this.currentPage,
                    per_page: this.pageSize,
                });

                const res = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/entries?${query}`, {
                    headers: {
                        'X-WP-Nonce': swpfeSettings.nonce,
                    },
                });
                const data = await res.json();

                // Flat array now, no more need for lookup
                const rawEntries = Array.isArray(data.entries) ? data.entries : [];

                this.entries = rawEntries.map(entry => ({
                    ...entry,
                    is_favorite: Number(entry.is_favorite),
                    synced_to_gsheet: Number(entry.synced_to_gsheet),
                    exported_to_csv: Number(entry.exported_to_csv),
                    printed_at: entry.printed_at ?? null,
                    resent_at: entry.resent_at ?? null,
                    status: entry.status ?? 'unread',
                    is_spam: entry.is_spam,
                }));

                this.totalEntries = Number(data.total) || rawEntries.length;
                this.totalPages = Math.ceil(this.totalEntries / this.pageSize);
                this.domKey = Date.now();
            } catch (error) {
                console.error("Failed to fetch entries:", error);
            } finally {
                this.loading = false;
            }
        },
        toggleSelectAll(event) {
            if (event.target.checked) {
                this.bulkSelected = this.paginatedEntries.map(entry => entry.id);
            } else {
                this.bulkSelected = [];
            }
        },
        toggleOpen() {
            this.open = !this.open;
            if (this.open && this.entries.length === 0) {
                this.fetchEntries();
            }
        },
        goToPage(page) {
            page = Number(page);
            if (page > 0 && page <= this.totalPages) {
                this.currentPage = page;
                this.fetchEntries();
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchEntries();
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchEntries();
            }
        },
        sortByDate() {
            this.entries = [...this.entries].sort((a, b) => {
                return this.sortAsc
                    ? new Date(a.date) - new Date(b.date)
                    : new Date(b.date) - new Date(a.date);
            });
            this.sortAsc = !this.sortAsc;
        },
        sortByStatus() {
            this.entries.sort((a, b) => {
                if (a.status === b.status) return 0;

                if (this.sortAscStatus) {
                    return a.status === "unread" ? -1 : 1;
                } else {
                    return a.status === "read" ? -1 : 1;
                }
            });

            this.sortAscStatus = !this.sortAscStatus;
        },
        showEntry(i) {
            const entry = this.entries[i];
            this.selectedEntry = entry;
            this.entryModalOpen = true;

            if (entry.status === "unread") {
                entry.status = "read";
                this.updateEntry(i, { status: "read" });
            }
        },

        markAs(status) {
            if (!this.entryModalOpen) return;
            this.selectedEntry.status = status;
            this.entryModalOpen = false;
        },

        copied: false,

        copyEntryToClipboard() {
            const lines = Object.entries(this.selectedEntry.entry || {})
                .map(([key, value]) => `${key}: ${value || "-"}`)
                .join("\n");

            navigator.clipboard.writeText(lines).then(() => {
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 2000);
            }).catch((err) => {
                console.error("Copy failed:", err);
            });
        },

        async updateEntry(index, changes = {}) {
            const entry = this.entries[index];

            const payload = {
                id: entry.id,
                form_id: entry.form_id,
                entry: entry.entry,
                status: entry.status,
                is_favorite: Number(entry.is_favorite),
                note: entry.note,
                exported_to_csv: Number(entry.exported_to_csv),
                synced_to_gsheet: Number(entry.synced_to_gsheet),
                printed_at: entry.printed_at,
                resent_at: entry.resent_at,
                ...changes,
            };

            try {
                const res = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/update`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-WP-Nonce': swpfeSettings.nonce
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();
                console.log("Entry updated:", data);
            } catch (error) {
                console.error("Failed to update entry:", error);
            }
        },

        async deleteEntry() {
            if (!this.selectedEntry) return;

            try {
                const response = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/delete`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': swpfeSettings.nonce
                    },
                    body: JSON.stringify({
                        id: this.selectedEntry.id,
                        form_id: this.selectedEntry.form_id,
                    }),
                });

                const data = await response.json();

                if (data.deleted) {
                    this.entries = this.entries.filter(e => e.id !== this.selectedEntry.id);
                    this.entryModalOpen = false;
                    this.selectedEntry = null;

                    if (this.currentPage > this.totalPages) {
                        this.currentPage = this.totalPages;
                    }

                    console.log('Entry deleted successfully');
                } else {
                    alert('Failed to delete entry: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                alert('Delete request failed. Check console for details.');
                console.error('Delete request failed:', error);
            }
        },

        toggleFavorite(index) {
            const entry = this.entries[index];
            entry.is_favorite = entry.is_favorite === 1 ? 0 : 1;
            this.updateEntry(index, { is_favorite: entry.is_favorite });
        },
        toggleRead(index) {
            const entry = this.paginatedEntries[index];
            entry.status = entry.status === "unread" ? "read" : "unread";
            this.updateEntry(index, { status: entry.status });
        },
        toggleModalReadStatus() {
            const entry = this.selectedEntry;
            const newStatus = entry.status === "unread" ? "read" : "unread";
            entry.status = newStatus;

            const index = this.entries.findIndex((e) => e.id === entry.id);
            if (index !== -1) {
                this.entries[index].status = newStatus;
                this.updateEntry(index, { status: newStatus });
            }
        },
        async updateSelectedEntry(changes = {}) {
            const entryId = this.selectedEntry.id;

            const index = this.entries.findIndex(e => e.id === entryId);
            if (index === -1) {
                console.error('❌ Entry not found in the list.');
                return;
            }

            // Merge changes into selectedEntry
            Object.assign(this.selectedEntry, changes);

            const payload = {
                id: this.selectedEntry.id,
                form_id: this.selectedEntry.form_id,
                entry: this.selectedEntry.entry,
                status: this.selectedEntry.status,
                is_favorite: Number(this.selectedEntry.is_favorite),
                note: this.selectedEntry.note,
                exported_to_csv: Number(this.selectedEntry.exported_to_csv),
                synced_to_gsheet: Number(this.selectedEntry.synced_to_gsheet),
                printed_at: this.selectedEntry.printed_at,
                resent_at: this.selectedEntry.resent_at,
            };

            try {
                const res = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/update`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-WP-Nonce": swpfeSettings.nonce,
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();
                console.log("✅ Note saved via updateSelectedEntry:", data);

                // Update local entry in entries[] as well
                this.entries[index] = { ...this.selectedEntry };
            } catch (err) {
                console.error("❌ Failed to save selected entry:", err);
            }
        },
        validateAndSaveNote() {
            const note = this.selectedEntry.note?.trim() || '';

            if (note.length > 1000) {
                alert('Note is too long. Please limit to 1000 characters.', 'save-wpf-entries');
                return;
            }

            this.updateSelectedEntry({ note });
        },
        syncToGoogleSheet(index) {
            const entry = this.entries[index];
            entry.synced_to_gsheet = 1;
            this.updateEntry(index, { synced_to_gsheet: 1 });
        },
        printEntry(index) {
            const entry = this.entries[index];
            const formTitle = entry.form_title || "Form Entry";

            entry.printed_at = new Date().toISOString().slice(0, 19).replace("T", " ");
            this.updateEntry(index, { printed_at: entry.printed_at });

            const entryData = entry.entry || {};
            const formattedFields = Object.entries(entryData).map(([key, value]) => {
                return `
                <div style="margin-bottom: 16px;">
                    <div style="font-weight: 600; font-size: 15px; color: #2d3748;">${key}</div>
                    <div style="margin-top: 4px; font-size: 14px; color: #1a202c;">${value}</div>
                    <hr style="border-top: 1px dashed #ddd; margin-top: 10px;" />
                </div>
            `;
            }).join("");

            const printWindow = window.open("", "", "width=1000,height=700");
            printWindow.document.write(`
                <html>
                <head>
                    <title>${formTitle} - Entry Details</title>
                    <style>
                        body {
                            font-family: 'Segoe UI', Tahoma, sans-serif;
                            padding: 30px;
                            background: #f3f4f6;
                        }
                        .entry-box {
                            max-width: 800px;
                            margin: auto;
                            background: white;
                            padding: 40px;
                            border-radius: 10px;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                        }
                        .entry-header {
                            font-size: 24px;
                            font-weight: bold;
                            text-align: center;
                            color: #1a202c;
                            margin-bottom: 30px;
                            border-bottom: 2px solid #e2e8f0;
                            padding-bottom: 10px;
                        }
                    </style>
                </head>
                <body>
                    <div class="entry-box">
                        <div class="entry-header">${formTitle}</div>
                        ${formattedFields}
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        },
        timeAgo(dateString) {
            // Convert "YYYY-MM-DD HH:mm:ss" → "YYYY-MM-DDTHH:mm:ssZ" (UTC)
            const utcDateString = dateString.replace(' ', 'T') + 'Z';

            const date = new Date(utcDateString);
            const now = new Date();

            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 60) return "just now";
            if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;

            const yesterday = new Date();
            yesterday.setDate(now.getDate() - 1);

            if (
                date.getDate() === yesterday.getDate() &&
                date.getMonth() === yesterday.getMonth() &&
                date.getFullYear() === yesterday.getFullYear()
            ) {
                return "Yesterday";
            }

            const optionsSameYear = { day: "numeric", month: "long" };
            const optionsLastYear = {
                day: "numeric",
                month: "long",
                year: "numeric",
            };
            const isThisYear = now.getFullYear() === date.getFullYear();

            return date.toLocaleDateString(
                "en-US",
                isThisYear ? optionsSameYear : optionsLastYear
            );
        },
        get visiblePages() {
            const pages = [];
            const delta = 2;
            const range = [];
            const total = this.totalPages;
            const current = this.currentPage;

            const left = current - delta;
            const right = current + delta;

            for (let i = 1; i <= total; i++) {
                if (
                    i === 1 ||
                    i === total ||
                    (i >= left && i <= right)
                ) {
                    range.push(i);
                }
            }

            let lastPage = 0;
            for (let page of range) {
                if (lastPage && page - lastPage > 1) {
                    pages.push('...');
                }
                pages.push(page);
                lastPage = page;
            }

            return pages;
        }, formatNumber(n) {
            const num = Number(n);

            if (num >= 1_000_000) {
                return (num / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
            } else if (num >= 1_000) {
                return (num / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
            }

            return num.toString();
        },
        formatFullNumber(n) {
            return Number(n).toLocaleString('en-US'); // e.g. 1,234,567
        }
    };
}

function entriesApp() {
    return {
        forms: [],
        entries: [],
        totalEntries: 0,
        formId: null, // currently selected form
        currentPage: 1,
        pageSize: 10,
        filterStatus: 'all',
        searchQuery: '',
        onlyFavorites: false,
        setError: false,

        async fetchForms() {
            try {
                const res = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/forms`, {
                    headers: {
                        'X-WP-Nonce': swpfeSettings.nonce,
                    },
                });
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const data = await res.json();
                this.forms = data;
            } catch (error) {
                this.setError = true;
                console.error("Failed to fetch forms:", error);
            }
        },

        async fetchEntries() {
            if (!this.formId) return;

            const query = new URLSearchParams({
                form_id: this.formId,
                per_page: this.pageSize,
                page: this.currentPage,
                ...(this.filterStatus !== 'all' ? { status: this.filterStatus } : {}),
                ...(this.searchQuery ? { search: this.searchQuery } : {})
            });

            try {
                const res = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/entries?${query}`, {
                    headers: {
                        'X-WP-Nonce': swpfeSettings.nonce,
                    },
                });
                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                const data = await res.json();

                this.entries = JSON.parse(JSON.stringify(data.entries || []));
                this.totalEntries = data.total || 0;
            } catch (error) {
                console.error("Failed to fetch entries:", error);
            }
        },

        // Optional: use this if you want to debounce search input
        handleSearchInput: _.debounce(function () {
            this.currentPage = 1;
            this.fetchEntries();
        }, 500),

        handleFilterChange() {
            this.currentPage = 1;
            this.fetchEntries();
        },

        changePage(page) {
            this.currentPage = page;
            this.fetchEntries();
        }
    };
}

function formEntriesApp(formId, entryCount) {
    return {
        entries: [],
        total: entryCount || 0,
        searchQuery: '',
        filterStatus: 'all',
        onlyFavorites: false,
        currentPage: 1,
        perPage: 10,
        loading: false,

        async fetchEntries() {
            this.loading = true;

            let queryParams = new URLSearchParams({
                form_id: formId,
                page: this.currentPage,
                per_page: this.perPage,
            });

            if (this.searchQuery.trim() !== '') {
                queryParams.append('search', this.searchQuery.trim());
            }

            if (this.filterStatus !== 'all') {
                queryParams.append('status', this.filterStatus);
            }

            // You can handle favorite filtering client-side if not supported by API

            try {
                const res = await fetch(`${swpfeSettings.restUrl}wpforms/entries/v1/entries?${queryParams}`, {
                    headers: {
                        'X-WP-Nonce': swpfeSettings.nonce,
                    },
                });
                const data = await res.json();

                this.entries = this.onlyFavorites
                    ? data.entries.filter(e => e.is_favorite)
                    : data.entries;

                this.total = data.total;
            } catch (err) {
                console.error("Fetch failed:", err);
            } finally {
                this.loading = false;
            }
        },

        handleSearchInput() {
            this.currentPage = 1;
            this.fetchEntries();
        },

        handleStatusChange() {
            this.currentPage = 1;
            this.fetchEntries();
        },

        handleFavoriteToggle() {
            this.currentPage = 1;
            this.fetchEntries();
        },

        goToPage(pageNum) {
            this.currentPage = pageNum;
            this.fetchEntries();
        }
    }
}

function toastHandler() {
    return {
        message: '',
        type: 'success',
        visible: false,
        init() {
            window.addEventListener('toast', e => {
                this.message = e.detail.message;
                this.type = e.detail.type || 'success';
                this.visible = true;
            });
        }
    }
}