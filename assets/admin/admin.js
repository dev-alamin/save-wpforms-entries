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

      try {
        if (action === "export_csv") {
          const res = await fetch(
            `${swpfeSettings.restUrl}aem/v1/export/bulk`,
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": swpfeSettings.nonce,
              },
              body: JSON.stringify({ ids: this.bulkSelected }),
            }
          );

          const blob = await res.blob();
          const url = window.URL.createObjectURL(blob);

          const a = document.createElement("a");
          a.href = url;
          a.download = `aem-entries-${Date.now()}.csv`;
          document.body.appendChild(a);
          a.click();
          a.remove();

          window.URL.revokeObjectURL(url);

          this.$dispatch("toast", {
            type: "success",
            message: "✅ CSV exported successfully!",
          });
        } else {
          // Handle other actions
          const res = await fetch(
            `${swpfeSettings.restUrl}aem/v1/entries/bulk`,
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": swpfeSettings.nonce,
              },
              body: JSON.stringify({ ids: this.bulkSelected, action }),
            }
          );

          const data = await res.json();

          this.$dispatch("toast", {
            type: "success",
            message: "✅ Bulk action completed successfully!",
          });
        }

        this.bulkSelected = [];
        this.selectAll = false;
      } catch (error) {
        console.error("Bulk action failed:", error);
        alert("Bulk action failed. Please try again.");
      }
    },
    handleCheckbox(event, entryId) {
      const index = this.entries.findIndex((e) => e.id === entryId);
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

        const res = await fetch(
          `${swpfeSettings.restUrl}aem/v1/entries?${query}`,
          {
            headers: {
              "X-WP-Nonce": swpfeSettings.nonce,
            },
          }
        );
        const data = await res.json();

        // Flat array now, no more need for lookup
        const rawEntries = Array.isArray(data.entries) ? data.entries : [];

        this.entries = rawEntries.map((entry) => ({
          ...entry,
          email: entry.email || "",
          is_favorite: Number(entry.is_favorite),
          synced_to_gsheet: Number(entry.synced_to_gsheet),
          exported_to_csv: Number(entry.exported_to_csv),
          printed_at: entry.printed_at ?? null,
          resent_at: entry.resent_at ?? null,
          status: entry.status ?? "unread",
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
        this.bulkSelected = this.paginatedEntries.map((entry) => entry.id);
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
        const res = await fetch(
          `${swpfeSettings.restUrl}aem/v1/entries/${
            payload.id
          }?form_id=${encodeURIComponent(payload.form_id)}`,
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": swpfeSettings.nonce,
            },
            body: JSON.stringify(payload),
          }
        );

        const data = await res.json();
      } catch (error) {
        console.error("Failed to update entry:", error);
      }
    },

    async deleteEntry() {
      if (!this.selectedEntry) return;

      try {
        const response = await fetch(`${swpfeSettings.restUrl}aem/v1/entries`, {
          method: "DELETE",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": swpfeSettings.nonce,
          },
          body: JSON.stringify({
            id: this.selectedEntry.id,
            form_id: this.selectedEntry.form_id,
          }),
        });

        const data = await response.json();

        if (data.deleted) {
          this.entries = this.entries.filter(
            (e) => e.id !== this.selectedEntry.id
          );
          this.entryModalOpen = false;
          this.selectedEntry = null;

          if (this.currentPage > this.totalPages) {
            this.currentPage = this.totalPages;
          }

          console.log("Entry deleted successfully");
        } else {
          alert("Failed to delete entry: " + (data.message || "Unknown error"));
        }
      } catch (error) {
        alert("Delete request failed. Check console for details.");
        console.error("Delete request failed:", error);
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

      const index = this.entries.findIndex((e) => e.id === entryId);
      if (index === -1) {
        console.error("❌ Entry not found in the list.");
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
        const res = await fetch(`${swpfeSettings.restUrl}aem/v1/entries/${
            payload.id
          }?form_id=${encodeURIComponent(payload.form_id)}`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": swpfeSettings.nonce,
          },
          body: JSON.stringify(payload),
        });

        const data = await res.json();

        this.$dispatch("toast", {
          type: "success",
          message: "✅ Saved changes successfully!",
        });

        // Dispatch Alpine event to update UI
        window.dispatchEvent(new CustomEvent("note-saved"));

        // Update local list
        this.entries[index] = { ...this.selectedEntry };

        // Update local entry in entries[] as well
        this.entries[index] = { ...this.selectedEntry };
      } catch (err) {
        console.error("❌ Failed to save selected entry:", err);
      }
    },
    validateAndSaveNote() {
      const note = this.selectedEntry.note?.trim() || "";

      if (note.length > 1000) {
        alert(
          "Note is too long. Please limit to 1000 characters.",
          "save-wpf-entries"
        );
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

      entry.printed_at = new Date()
        .toISOString()
        .slice(0, 19)
        .replace("T", " ");
      this.updateEntry(index, { printed_at: entry.printed_at });

      const entryData = entry.entry || {};
      const formattedFields = Object.entries(entryData)
        .map(([key, value]) => {
          return `
                <div style="margin-bottom: 16px;">
                    <div style="font-weight: 600; font-size: 15px; color: #2d3748;">${key}</div>
                    <div style="margin-top: 4px; font-size: 14px; color: #1a202c;">${value}</div>
                    <hr style="border-top: 1px dashed #ddd; margin-top: 10px;" />
                </div>
            `;
        })
        .join("");

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
    exportSingleEntry(entry) {
      const csvContent =
        `"Field","Value"\n` +
        Object.entries(entry.entry)
          .map(
            ([key, val]) =>
              `"${key.replace(/\r?\n|\r/g, " ")}","${(val ?? "")
                .toString()
                .replace(/"/g, '""')}"`
          )
          .join("\n");

      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = `entry-${entry.id}.csv`;
      link.click();
    },
    timeAgo(dateString) {
      // Convert "YYYY-MM-DD HH:mm:ss" → "YYYY-MM-DDTHH:mm:ssZ" (UTC)
      const utcDateString = dateString.replace(" ", "T") + "Z";

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
        if (i === 1 || i === total || (i >= left && i <= right)) {
          range.push(i);
        }
      }

      let lastPage = 0;
      for (let page of range) {
        if (lastPage && page - lastPage > 1) {
          pages.push("...");
        }
        pages.push(page);
        lastPage = page;
      }

      return pages;
    },
    formatNumber(n) {
      const num = Number(n);

      if (num >= 1_000_000) {
        return (num / 1_000_000).toFixed(1).replace(/\.0$/, "") + "M";
      } else if (num >= 1_000) {
        return (num / 1_000).toFixed(1).replace(/\.0$/, "") + "K";
      }

      return num.toString();
    },
    formatFullNumber(n) {
      return Number(n).toLocaleString("en-US"); // e.g. 1,234,567
    },
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
    filterStatus: "all",
    searchQuery: "",
    onlyFavorites: false,
    setError: false,
    loading: false,

    async fetchForms() {
      this.loading = true;
      try {
        const res = await fetch(`${swpfeSettings.restUrl}aem/v1/forms`, {
          headers: {
            "X-WP-Nonce": swpfeSettings.nonce,
          },
        });
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        const data = await res.json();
        this.forms = data;
        this.loading = false;
      } catch (error) {
        this.setError = true;
        this.loading = false;
        console.error("Failed to fetch forms:", error);
      }
    },

    async fetchEntries() {
      if (!this.formId) return;

      const query = new URLSearchParams({
        form_id: this.formId,
        per_page: this.pageSize,
        page: this.currentPage,
        ...(this.filterStatus !== "all" ? { status: this.filterStatus } : {}),
        ...(this.searchQuery ? { search: this.searchQuery } : {}),
      });

      try {
        const res = await fetch(
          `${swpfeSettings.restUrl}aem/v1/entries?${query}`,
          {
            headers: {
              "X-WP-Nonce": swpfeSettings.nonce,
            },
          }
        );
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
    },
  };
}

function formEntriesApp(formId, entryCount) {
  return {
    entries: [],
    total: entryCount || 0,
    searchQuery: "",
    filterStatus: "all",
    onlyFavorites: false,
    currentPage: 1,
    perPage: 10,
    loading: false,
    searchType: "email",

    async fetchEntries() {
      this.loading = true;

      let queryParams = new URLSearchParams({
        form_id: formId,
        page: this.currentPage,
        per_page: this.perPage,
      });

      if (this.searchQuery.trim() !== "") {
        queryParams.append("search", this.searchQuery.trim());
        queryParams.append("search_type", this.searchType.trim()); // 👈 Add this line
      }

      if (this.filterStatus !== "all") {
        queryParams.append("status", this.filterStatus);
      }

      // You can handle favorite filtering client-side if not supported by API

      try {
        const res = await fetch(
          `${swpfeSettings.restUrl}aem/v1/entries?${queryParams}`,
          {
            headers: {
              "X-WP-Nonce": swpfeSettings.nonce,
            },
          }
        );
        const data = await res.json();

        this.entries = this.onlyFavorites
          ? data.entries.filter((e) => e.is_favorite)
          : data.entries;

        this.total = data.total;
      } catch (err) {
        console.error("Fetch failed:", err);
      } finally {
        this.loading = false;
      }
    },

    handleSearchInput() {
      const trimmed = this.searchQuery.trim();

      if (trimmed === "") {
        this.entries = [];
        this.loading = false;
        return;
      }

      this.loading = true;
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
    },
  };
}

function toastHandler() {
  return {
    message: "",
    type: "success",
    visible: false,
    init() {
      window.addEventListener("toast", (e) => {
        this.message = e.detail.message;
        this.type = e.detail.type || "success";
        this.visible = true;

        setTimeout(() => (this.visible = false), 3000);
      });
    },
  };
}

function settingsForm() {
  return {
    isSaving: false,
    message: "",

    async saveSettings() {
      this.isSaving = true;
      this.message = "";

      const form = document.querySelector("#swpfe-settings-form");
      const formData = new FormData(form);

      // ❌ Remove default WP fields
      formData.delete("option_page");
      formData.delete("action");
      formData.delete("_wpnonce");
      formData.delete("_wp_http_referer");

      // ✅ Add custom action and nonce
      formData.append("action", "swpfe_save_settings");
      formData.append("_wpnonce", swpfeSettings.nonce);

      try {
        const res = await fetch(ajaxurl, {
          method: "POST",
          body: formData,
        });

        const data = await res.json();

        if (data.success) {
          window.dispatchEvent(
            new CustomEvent("toast", {
              detail: {
                message: "✅ Settings saved successfully!",
                type: "success",
              },
            })
          );
        } else {
          window.dispatchEvent(
            new CustomEvent("toast", {
              detail: {
                message: "❌ " + (data.data?.message || "Save failed."),
                type: "error",
              },
            })
          );
        }
      } catch (err) {
        console.error(err);
        this.message = "❌ Unexpected error occurred.";
      }

      this.isSaving = false;
    },
  };
}

function exportSettings() {
  return {
    // Reactive State
    forms: [],
    selectedFormId: "",
    fields: [],
    excludedFields: [],
    dateFrom: "",
    dateTo: "",
    isExporting: false,
    exportJobId: null,
    exportProgress: 0,
    exportInterval: null,
    processedCount: 0,
    totalEntries: 0,
    isExportComplete: false,

    // UI State
    showProgressModal: false,
    errorMessage: "",
    isOntheGoExport: false,
    formTitle: "",

    init() {
      console.log("Alpine exportSettings initialized");
      this.fetchForms();

      // Check for a saved job on page load
      const savedJobId = localStorage.getItem("swpfe_export_job_id");
      if (savedJobId) {
        this.exportJobId = savedJobId;
        this.isExporting = true;
        this.startPolling();
        // CHANGED: Automatically show the modal on reload for better UX.
        // this.showProgressModal = true;
        console.log(`Resuming export job: ${this.exportJobId}`);
      }
    },

    // Fetches forms from the REST API
    async fetchForms() {
      try {
        const res = await fetch(`${swpfeSettings.restUrl}aem/v1/forms`, {
          headers: { "X-WP-Nonce": swpfeSettings.nonce },
        });
        const data = await res.json();
        this.forms = data;
      } catch (e) {
        console.error("Error fetching forms:", e);
      }
    },

    // Fetches form fields when a form is selected
    async fetchFormFields() {
      if (!this.selectedFormId) {
        this.fields = [];
        this.excludedFields = [];
        return;
      }

      this.errorMessage = "";
      try {
        const res = await fetch(
          `${swpfeSettings.restUrl}aem/v1/forms/${this.selectedFormId}/fields`,
          {
            headers: { "X-WP-Nonce": swpfeSettings.nonce },
          }
        );
        const data = await res.json();
        this.fields = data.fields;
        this.excludedFields = [];
      } catch (e) {
        console.error("Error fetching form fields:", e);
        this.errorMessage = "Failed to fetch form fields. Please try again.";
      }
    },

    // Initiates the async export job
    async exportAllBatchesAsync() {
      if (!this.selectedFormId) {
        this.errorMessage = "Please select a form before exporting.";
        return;
      }

      // CHANGED: Reset state from any previous completed job before starting a new one.
      this.resetExportState();
      this.isExportComplete = false;

      this.errorMessage = "";
      this.isExporting = true;
      this.exportProgress = 0;
      this.exportJobId = null;
      this.dateFrom = document.getElementById("swpfe_export_date_from").value;
      this.dateTo = document.getElementById("swpfe_export_date_to").value;


      const exportData = {
        form_id: this.selectedFormId,
        date_from: this.dateFrom,
        date_to: this.dateTo,
        exclude_fields: this.excludedFields,
      };

      try {
        const res = await fetch(`${swpfeSettings.restUrl}aem/v1/export/start`, {
          method: "POST",
          headers: {
            "X-WP-Nonce": swpfeSettings.nonce,
            "Content-Type": "application/json",
          },
          body: JSON.stringify(exportData),
        });

        const contentType = res.headers.get("content-type");

        if (contentType.includes("text/csv")) {
          const text = await res.text();
          this.isOntheGoExport = true;

          if (text.startsWith("id,") && text.includes("\n")) {
            const blob = new Blob([text], { type: "text/csv" });
            const url = window.URL.createObjectURL(blob);

            const a = document.createElement("a");
            a.href = url;
            a.download = `aem-entries-${this.formTitle}-${Date.now()}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            this.exportProgress = 100;
            this.isExportComplete = true;
            return; // Done: no polling needed
          } else {
            throw new Error("Invalid CSV content.");
          }
        }

        const result = await res.json();

        if (res.ok && result.success) {
          this.exportJobId = result.job_id;
          localStorage.setItem("swpfe_export_job_id", this.exportJobId);
          this.startPolling();
        } else {
          throw new Error(result.message || "Failed to start export.");
        }
      } catch (e) {
        console.error("Export start error:", e);
        this.errorMessage = e.message;
        this.resetExportState();
      }
    },

    // Starts the polling for job progress
    startPolling() {
      if (this.exportInterval) clearInterval(this.exportInterval);

      this.exportInterval = setInterval(async () => {
        try {
          const res = await fetch(
            `${
              swpfeSettings.restUrl
            }aem/v1/export/progress?job_id=${encodeURIComponent(
              this.exportJobId
            )}`,
            {
              headers: { "X-WP-Nonce": swpfeSettings.nonce },
            }
          );
          const result = await res.json();

          if (!res.ok) {
            throw new Error(
              result.message || "Failed to fetch export progress."
            );
          }

          this.exportProgress = result.progress;
          this.processedCount = result.processed;
          this.totalEntries = result.total;

          if (result.status !== "complete" && result.status !== "failed") {
            this.isExporting = true;
          }

          if (result.status === "complete") {
            // CHANGED: Major logic change here. Do not reset the full state.
            // Just stop polling and set the completion flags.
            // The exportJobId is intentionally kept to allow re-download/delete.
            clearInterval(this.exportInterval);
            this.exportInterval = null;
            this.isExporting = false;
            this.isExportComplete = true;
            this.exportProgress = 100; // Ensure it shows 100%

            this.handleDownload(result.file_url); // Trigger the download

            this.errorMessage =
              "Export complete! Your download should start shortly.";
            this.showProgressModal = false; // Close modal on completion
          }

          if (result.status === "failed") {
            throw new Error(result.message || "Export job failed.");
          }
        } catch (e) {
          console.error("Progress polling failed:", e);
          this.errorMessage = e.message;
          this.resetExportState(); // Full reset is correct for a failure
          this.showProgressModal = false;
        }
      }, 3000); // Poll every 3 seconds
    },

    // Handles the file download
    handleDownload(fileUrl) {
      if (!this.exportJobId) {
        console.error("No export job ID found for download.");
        this.errorMessage = "Cannot download file: Export Job ID is missing.";
        return;
      }
      // This implementation is fine. It uses a dedicated endpoint which is good practice.
      const downloadUrl = `${
        swpfeSettings.restUrl
      }aem/v1/export/download?job_id=${encodeURIComponent(this.exportJobId)}`;
      window.open(downloadUrl, "_blank");
    },

    // Resets the state variables. Called on new export, on failure, or after deletion.
    resetExportState() {
      if (this.exportInterval) {
        clearInterval(this.exportInterval);
        this.exportInterval = null;
      }
      this.isExporting = false;
      this.exportJobId = null;
      this.exportProgress = 0;
      this.processedCount = 0;
      this.totalEntries = 0;
      // Note: isExportComplete is NOT reset here. It's handled separately.
      localStorage.removeItem("swpfe_export_job_id");
    },

    showExportProgress() {
      this.showProgressModal = true;
    },

    closeProgressModal() {
      this.showProgressModal = false;
    },

    async deleteExportFile() {
      if (
        !confirm(
          "Are you sure you want to delete the export file? This action cannot be undone."
        )
      ) {
        return;
      }

      this.errorMessage = "";
      try {
        const res = await fetch(
          `${
            swpfeSettings.restUrl
          }aem/v1/export/delete?job_id=${encodeURIComponent(this.exportJobId)}`,
          {
            method: "POST",
            headers: {
              "X-WP-Nonce": swpfeSettings.nonce,
            },
          }
        );

        const result = await res.json();

        if (res.ok && result.success) {
          alert(result.message);
          this.isExportComplete = false;
          // This is a correct place to call resetExportState.
          this.resetExportState();
        } else {
          throw new Error(result.message || "Failed to delete the file.");
        }
      } catch (e) {
        console.error("Delete file error:", e);
        this.errorMessage = e.message;
      }
    },
  };
}

function migrationHandler() {
  return {
    totalEntries: 0,
    batchSize: 50,
    migrating: false,
    complete: false,
    progress: 0,
    log: [],
    pollInterval: null,

    migrated: 0,
    total: 0,
    estimatedTime: "",
    startTime: null,
    lastLoggedProgress: null,
    entryFetchStarted: false,

    // New flag to track ongoing migration even if not showing progress UI
    migrationInProgress: false,

    init() {
      // Load batch size from localStorage if you want to persist that too
      const savedBatch = localStorage.getItem("swpfe_batch_size");
      if (savedBatch) this.batchSize = parseInt(savedBatch, 10);

      // ✅ Prevent duplicate fetch
      if (!this.entryFetchStarted) {
        this.entryFetchStarted = true;

        fetch(`${swpfeSettings.restUrl}aem/v1/legacy/source/count`, {
          headers: { "X-WP-Nonce": swpfeSettings.nonce },
        })
          .then((res) => {
            if (!res.ok) throw new Error("Failed to fetch entry counts");
            return res.json();
          })
          .then((data) => {
            this.totalEntries = data.reduce(
              (sum, item) => sum + parseInt(item.entry_count),
              0
            );
            this.log.push(
              `📊 Found total ${this.totalEntries} entries to migrate`
            );
          })
          .catch((err) => {
            this.log.push(`⚠️ Error loading total entries: ${err.message}`);
            this.totalEntries = 0;
          });
      }

      // Check if migration was in progress before page reload
      const inProgress = localStorage.getItem("swpfe_migration_in_progress");
      if (inProgress === "true") {
        this.migrationInProgress = true;

        // Check current backend status once and decide whether to show progress UI
        this.checkProgress().then(() => {
          if (!this.complete) {
            this.migrating = false; // Not actively polling yet
          }
        });
      }
    },

    async startMigration() {
      if (this.totalEntries === 0) {
        this.log.push("⚠️ No entries to migrate.");
        return;
      }

      this.migrating = true;
      this.complete = false;
      this.log = [];
      this.progress = 0;
      this.startTime = Date.now();
      this.lastLoggedProgress = null;

      this.log.push(`🔁 Starting migration with batch size: ${this.batchSize}`);

      localStorage.setItem("swpfe_migration_in_progress", "true");
      localStorage.setItem("swpfe_batch_size", this.batchSize);

      try {
        const triggerRes = await fetch(
          `${swpfeSettings.restUrl}aem/v1/migration/trigger`,
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": swpfeSettings.nonce,
            },
            body: JSON.stringify({ batch_size: this.batchSize }),
          }
        );
        const triggerData = await triggerRes.json();

        if (!triggerData.success) {
          this.migrating = false;
          localStorage.setItem("swpfe_migration_in_progress", "false");
          this.log.push(
            `❌ Failed to start migration: ${
              triggerData.message || "Unknown error."
            }`
          );
          return;
        }

        this.log.push("🚀 Migration triggered successfully.");

        // Start polling progress every 2 seconds
        this.pollInterval = setInterval(() => this.checkProgress(), 2000);
      } catch (error) {
        this.migrating = false;
        localStorage.setItem("swpfe_migration_in_progress", "false");
        this.log.push(`❌ Error starting migration: ${error.message}`);
      }
    },

    async checkProgress() {
      try {
        const res = await fetch(
          `${swpfeSettings.restUrl}aem/v1/migration/progress`,
          {
            headers: { "X-WP-Nonce": swpfeSettings.nonce },
          }
        );
        const data = await res.json();

        const migrated = data.migrated || 0;
        const total = data.total || 1;
        const progress = Math.floor((migrated / total) * 100);

        this.migrated = migrated;
        this.total = total;
        this.progress = progress;

        if (!this.startTime) this.startTime = Date.now();
        const elapsedSec = (Date.now() - this.startTime) / 1000;
        const rate = migrated / data.eta;
        const remaining = total - migrated;
        const estimatedSec = remaining / (rate || 1);
        this.estimatedTime = this.formatTime(estimatedSec);

        if (progress !== this.lastLoggedProgress) {
          this.log.push(`📊 Progress: ${progress}% (${migrated} / ${total})`);
          this.lastLoggedProgress = progress;
        }

        if (data.complete || progress >= 100) {
          clearInterval(this.pollInterval);
          this.pollInterval = null;
          this.migrating = false;
          this.complete = true;
          this.progress = 100;
          this.log.push("🎉 Migration complete!");
          localStorage.setItem("swpfe_migration_in_progress", "false");
          this.migrationInProgress = false;
        }
      } catch (error) {
        this.log.push(`❌ Error checking progress: ${error.message}`);
      }
    },

    formatTime(seconds) {
      const mins = Math.floor(seconds / 60);
      const secs = Math.floor(seconds % 60);
      return `${mins}m ${secs}s`;
    },

    stopMigration() {
      if (this.pollInterval) {
        clearInterval(this.pollInterval);
        this.pollInterval = null;
      }
      this.migrating = false;
      this.complete = false;
      this.progress = 0;
      this.log.push("🛑 Migration stopped.");
      localStorage.setItem("swpfe_migration_in_progress", "false");
      this.migrationInProgress = false;
    },

    // New method: Show migration progress UI when user clicks "See Progress"
    seeProgress() {
      this.migrating = true;
      this.migrationInProgress = true;

      // Start polling progress every 2 seconds
      if (!this.pollInterval) {
        this.pollInterval = setInterval(() => this.checkProgress(), 2000);
      }
    },
  };
}
