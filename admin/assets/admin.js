function formTable(entries) {
    return {
        open: false,
        all: entries,
        keys: Object.keys(entries[0]?.entry || {}),
        entryModalOpen: false,
        selectedEntry: {},
        bgClasses: [
            "swpfe-row-bg-1",
            "swpfe-row-bg-2",
            "swpfe-row-bg-3",
            "swpfe-row-bg-4",
        ],

        // Pagination state
        currentPage: 1,
        pageSize: 5,

        // Computed: paginated entries
        get paginatedEntries() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.all.slice(start, start + this.pageSize);
        },

        sortAsc: true,
        sortAscStatus: true,

        sortByDate() {
            this.all.sort((a, b) => {
                return this.sortAsc
                    ? new Date(a.date) - new Date(b.date)
                    : new Date(b.date) - new Date(a.date);
            });
            this.sortAsc = !this.sortAsc;
        },
        sortByStatus() {
            this.all.sort((a, b) => {
                if (a.status === b.status) return 0;

                if (this.sortAscStatus) {
                    return a.status === "unread" ? -1 : 1;
                } else {
                    return a.status === "read" ? -1 : 1;
                }
            });

            this.sortAscStatus = !this.sortAscStatus;
        },
        // Computed: total pages
        get totalPages() {
            return Math.ceil(this.all.length / this.pageSize) || 1;
        },

        showEntry(i) {
            const realIndex = (this.currentPage - 1) * this.pageSize + i;
            const entry = this.all[realIndex];

            this.selectedEntry = entry;
            this.entryModalOpen = true;

            if (entry.status === "unread") {
                entry.status = "read"; // Instant UI update

                // Also update backend
                this.updateEntry(realIndex, { status: "read" });
            }
        },
        markAs(status) {
            if (!this.entryModalOpen) return;
            this.selectedEntry.status = status;
            this.entryModalOpen = false;
        },
        deleteEntry() {
            if (!this.entryModalOpen) return;
            this.all = this.all.filter((e) => e !== this.selectedEntry);
            this.entryModalOpen = false;
            // Reset page if current page is out of bounds after deletion
            if (this.currentPage > this.totalPages) {
                this.currentPage = this.totalPages;
            }
        },
        copied: false,

        copyEntryToClipboard() {
            const lines = Object.entries(this.selectedEntry.entry || {})
                .map(([key, value]) => `${key}: ${value || "-"}`)
                .join("\n");

            navigator.clipboard
                .writeText(lines)
                .then(() => {
                    this.copied = true;
                    setTimeout(() => {
                        this.copied = false;
                    }, 2000);
                })
                .catch((err) => {
                    console.error("Copy failed:", err);
                });
        },

        // Pagination controls
        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        async deleteEntry() {
            if (!this.selectedEntry) return;

            try {
                const response = await fetch('http://localhost/devspark/wordpress-backend/wp-json/wpforms/entries/v1/delete', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: this.selectedEntry.id,
                        form_id: this.selectedEntry.form_id,
                    }),
                });

                const data = await response.json();

                if (data.deleted) {
                    // Remove from local array to update UI
                    const index = this.paginatedEntries.findIndex(e => e.id === this.selectedEntry.id);
                    if (index !== -1) {
                        this.paginatedEntries.splice(index, 1);
                    }

                    // Close modal
                    this.entryModalOpen = false;
                    this.selectedEntry = null;

                    if (this.currentPage > this.totalPages) {
                        this.currentPage = this.totalPages;
                    }

                    console.log('Entry deleted successfully');
                } else {
                    console.error('Failed to delete entry:', data.message || data);
                    alert('Failed to delete entry: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Delete request failed:', error);
                alert('Delete request failed. Check console for details.');
            }
        },
    };
}

function entriesApp() {
    return {
        grouped: {},
        paginatedEntries: [],
        currentPage: 1,
        perPage: 20,
        selectedFormId: null,
        totalPages: 1,

        async fetchEntries(
            formId = this.selectedFormId,
            page = this.currentPage,
            perPage = this.perPage
        ) {
            try {
                const query = new URLSearchParams({
                    form_id: formId,
                    page,
                    per_page: perPage,
                });

                const res = await fetch(
                    `http://localhost/devspark/wordpress-backend/wp-json/wpforms/entries/v1/entries?${query}`
                );
                const data = await res.json();

                this.grouped = data;

                // Normalize values to ensure Alpine reacts properly and backend gets correct types
                this.paginatedEntries =
                    Object.values(data)[0]?.map((entry) => ({
                        ...entry,
                        is_favorite: Number(entry.is_favorite),
                        synced_to_gsheet: Number(entry.synced_to_gsheet),
                        exported_to_csv: Number(entry.exported_to_csv),
                        printed_at: entry.printed_at ?? null,
                        resent_at: entry.resent_at ?? null,
                        status: entry.status ?? "unread",
                    })) || [];

                this.selectedFormId = formId;
                this.currentPage = page;
            } catch (error) {
                console.error("Failed to fetch entries:", error);
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

        goToPage(page) {
            if (page !== this.currentPage) {
                this.currentPage = page;
                this.fetchEntries();
            }
        },

        async updateEntry(index, changes = {}) {
            const entry = this.paginatedEntries[index];

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
                const res = await fetch(
                    "http://localhost/devspark/wordpress-backend/wp-json/wpforms/entries/v1/update",
                    {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(payload),
                    }
                );

                const data = await res.json();
                console.log("Entry updated:", data);
            } catch (error) {
                console.error("Failed to update entry:", error);
            }
        },
        toggleRead(index) {
            const entry = this.paginatedEntries[index];
            entry.status = entry.status === "unread" ? "read" : "unread";
            this.updateEntry(index, { status: entry.status });
        },

        toggleFavorite(index) {
            const entry = this.paginatedEntries[index];
            entry.is_favorite = entry.is_favorite === 1 ? 0 : 1;
            this.updateEntry(index, { is_favorite: entry.is_favorite });
        },
        toggleModalReadStatus() {
            const entry = this.selectedEntry;
            const newStatus = entry.status === "unread" ? "read" : "unread";
            entry.status = newStatus;

            // Find index in `all` array
            const index = this.all.findIndex((e) => e.id === entry.id);
            if (index !== -1) {
                this.all[index].status = newStatus;
                this.updateEntry(index, { status: newStatus });
            }
        },
        syncToGoogleSheet(index) {
            const entry = this.paginatedEntries[index];
            entry.synced_to_gsheet = 1;
            this.updateEntry(index, { synced_to_gsheet: 1 });
        },
        printEntry(index) {
            const entry = this.paginatedEntries[index];
            const formTitle = entry.form_title || "Form Entry"; // ← Optional fallback
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
            const date = new Date(dateString);
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
    };
}
