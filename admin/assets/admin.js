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

    // Computed: total pages
    get totalPages() {
      return Math.ceil(this.all.length / this.pageSize) || 1;
    },

    showEntry(i) {
      // i is index relative to paginatedEntries, so adjust to real index in all
      const realIndex = (this.currentPage - 1) * this.pageSize + i;
      this.selectedEntry = this.all[realIndex];
      this.entryModalOpen = true;
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
  };
}

function entriesApp() {
  return {
    grouped: {},
    currentPage: 1,
    perPage: 20,
    selectedFormId: null, // Set this dynamically per form
    totalPages: 1, // Set this based on response if you add total count

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

        const res = await fetch(`http://localhost/devspark/wordpress-backend/wp-json/wpforms/entries/v1/entries?${query}`);
        const data = await res.json();

        // If you return total count later, calculate total pages
        // this.totalPages = Math.ceil(data.total / perPage);

        this.grouped = data;
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
  };
}
