// Main application interactions and circulation workflows.

lucide.createIcons();


// SESSION

const sessionUser = document.getElementById("sessionUser");
// DATA

let books = [];

let transactions = JSON.parse(
    localStorage.getItem("libraryTransactions")
) || [];

let borrowRequests = [];
let historyRecords = [];
let accounts = [];
let students = [];
let selectedStudentActivity = [];
let selectedStudentActivityTab = "borrowed";
let notifications = [];
let bookToDelete = null;
let accountToDelete = null;
let transactionToReturn = null;
let requestToDecline = null;
let currentRole =
    document.body.dataset.role ||
    "student";
const currentUserName = document.body.dataset.userName || "";
const currentUserId = document.body.dataset.userId || "";

// Show a short transition after login so the workspace does not appear abruptly.
if (document.body.dataset.showWelcome === "true") {
    window.setTimeout(() => {
        document.getElementById("appLoading")?.classList.add("hidden");
    }, 650);
}

const canManageBooks =
    currentRole === "librarian" ||
    currentRole === "admin";

const roleConfig = {
    student: {
        label: "Student",
        eyebrow: "STUDENT MODULE",
        heroTitle: "Your library, <em>in your hands.</em>",
        heroDescription: "Check available books, track borrowed titles, and stay on top of due dates and notifications.",
        summaryLabel: "MY DASHBOARD",
        summaryHint: "Books currently available for your account",
        metrics: [
            { label: "Borrowed", value: "borrowed" },
            { label: "Due Soon", value: "dueSoon" }
        ]
    },
    librarian: {
        label: "Librarian",
        eyebrow: "LIBRARIAN MODULE",
        heroTitle: "Approve, track, and <em>keep circulation moving.</em>",
        heroDescription: "Review pending requests, maintain the collection, and resolve overdue items with ease.",
        summaryLabel: "APPROVAL QUEUE",
        summaryHint: "Borrow requests awaiting review",
        metrics: [
            { label: "Pending", value: "pending" },
            { label: "Overdue", value: "overdue" }
        ]
    },
    admin: {
        label: "Admin",
        eyebrow: "ADMIN MODULE",
        heroTitle: "System overview and <em>full control.</em>",
        heroDescription: "Monitor students, librarians, inventory, and broad library activity from one place.",
        summaryLabel: "SYSTEM OVERVIEW",
        summaryHint: "Accounts and records across the library",
        metrics: [
            { label: "Students", value: "students" },
            { label: "Librarians", value: "librarians" },
            { label: "Books", value: "books" }
        ]
    }
};

// ELEMENTS

const bookList =
    document.getElementById("bookList");

const emptyState =
    document.getElementById("emptyState");

const activeBorrowingList =
    document.getElementById("activeBorrowingList");

const historyList =
    document.getElementById("historyList");

const pendingRequestList =
    document.getElementById("pendingRequestList");

const notificationList =
    document.getElementById("notificationList");

async function loadCirculation() {
    // Load all circulation data together so badges and views share one snapshot.
    const [requestResponse, notificationResponse, historyResponse] = await Promise.all([
        fetch("circulation_api.php?action=requests", { credentials: "same-origin" }),
        fetch("circulation_api.php?action=notifications", { credentials: "same-origin" }),
        fetch("circulation_api.php?action=history", { credentials: "same-origin" })
    ]);

    if (!requestResponse.ok || !notificationResponse.ok || !historyResponse.ok) {
        throw new Error("Unable to load borrowing updates.");
    }

    borrowRequests = await requestResponse.json();
    notifications = await notificationResponse.json();
    historyRecords = await historyResponse.json();
}

async function loadAccounts() {
    if (currentRole !== "admin") return;
    const search = document.getElementById("accountSearch")?.value || "";
    const role = document.getElementById("accountRoleFilter")?.value || "";
    const response = await fetch(
        `admin_api.php?action=users&search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}`,
        { credentials: "same-origin" }
    );
    if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        throw new Error(result.error || "Unable to load accounts.");
    }
    accounts = await response.json();
}

async function loadStudents() {
    // Students are visible to staff only; the API enforces the same rule server-side.
    if (!["librarian", "admin"].includes(currentRole)) return;
    const search = document.getElementById("studentSearch")?.value || "";
    const response = await fetch(
        `admin_api.php?action=students&search=${encodeURIComponent(search)}`,
        { credentials: "same-origin" }
    );
    if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        throw new Error(result.error || "Unable to load students.");
    }
    students = await response.json();
}

function requestAsTransaction(request) {
    return {
        id: request.id,
        bookId: request.bookId,
        bookTitle: request.bookTitle,
        bookAuthor: request.bookAuthor,
        borrowerName: request.borrowerName,
        studentId: request.studentId,
        borrowDate: request.borrowDate,
        dueDate: request.dueDate,
        status: request.status === "approved" ? "borrowed" : request.status,
        returnDate: null
    };
}


// SAVE DATA

function saveBooks() {
    return Promise.resolve();
}

async function loadBooks() {
    const response = await fetch("books_api.php", {
        credentials: "same-origin"
    });

    if (!response.ok) {
        throw new Error("Unable to load books.");
    }

    books = await response.json();
}

async function saveBook(book, id = null) {
    const response = await fetch("books_api.php", {
        method: id ? "PUT" : "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            id,
            title: book.title,
            author: book.author,
            category: book.category,
            total: book.total,
            available: book.available
        })
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.error || "Unable to save book.");
    }

    return response.json();
}

async function deleteBook(id) {
    const response = await fetch("books_api.php", {
        method: "DELETE",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ id })
    });

    if (!response.ok) {
        throw new Error("Unable to remove book.");
    }
}

function saveTransactions() {
    localStorage.setItem(
        "libraryTransactions",
        JSON.stringify(transactions)
    );
}


// DATE HELPERS

function getToday() {
    return new Date()
        .toISOString()
        .split("T")[0];
}

function formatDate(date) {

    if (!date) return "â€”";

    return new Intl.DateTimeFormat(
        "en-US",
        {
            month: "short",
            day: "numeric",
            year: "numeric"
        }
    ).format(new Date(date + "T00:00:00"));
}

function formatDateTime(dateTime) {
    if (!dateTime) return "â€”";
    const normalized = dateTime.includes("T")
        ? dateTime
        : dateTime.replace(" ", "T");

    return new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit"
    }).format(new Date(normalized));
}

function isOverdue(transaction) {

    if (transaction.status === "returned") {
        return false;
    }

    const today =
        new Date(getToday());

    const dueDate =
        new Date(
            transaction.dueDate + "T00:00:00"
        );

    return dueDate < today;
}


// NAVIGATION

document
    .querySelectorAll(".nav-button")
    .forEach((button) => {

        button.addEventListener(
            "click",
            () => {

                const view =
                    button.dataset.view;

                document
                    .querySelectorAll(".nav-button")
                    .forEach(btn =>
                        btn.classList.remove("active")
                    );

                button.classList.add("active");


                document
                    .querySelectorAll(".view")
                    .forEach(viewElement =>
                        viewElement.classList.remove("active-view")
                    );


                document
                    .getElementById(`${view}View`)
                    .classList.add("active-view");


                if (view === "borrowed" || view === "history" || view === "notifications" || view === "accounts" || view === "students") {
                    // Refresh data when opening a view that depends on server records.
                    const refresh = view === "accounts"
                        ? loadAccounts()
                        : view === "students"
                            ? loadStudents()
                            : loadCirculation();
                    refresh
                        .then(renderAll)
                        .catch(error => showToast(error.message));
                    return;
                }

                renderAll();

            }
        );

    });

const myBooksTabButtons = document.querySelectorAll(".my-books-tab");
myBooksTabButtons.forEach((button) => {
    button.addEventListener("click", () => {
        const selectedTab = button.dataset.myBooksTab;
        document.querySelectorAll(".my-books-tab").forEach(tab => tab.classList.toggle("active", tab === button));
        document.querySelectorAll(".my-books-panel-section").forEach(section => {
            section.classList.toggle("active", section.dataset.myBooksPanel === selectedTab);
        });
    });
});

const profileToggleButton = document.getElementById("toggleProfileEdit");
const profileEditor = document.getElementById("profileEditor");
if (profileToggleButton && profileEditor) {
    profileToggleButton.addEventListener("click", () => {
        profileEditor.classList.toggle("hidden");
        const input = document.getElementById("profileNameInput");
        if (!profileEditor.classList.contains("hidden") && input) {
            input.focus();
            input.select();
        }
    });

    profileEditor.addEventListener("submit", saveProfileName);
}

const passwordModal = document.getElementById("passwordModal");
const passwordForm = document.getElementById("passwordForm");
document.getElementById("openPasswordModal")?.addEventListener("click", () => {
    passwordForm?.reset();
    passwordModal?.classList.add("show");
    document.getElementById("currentPassword")?.focus();
});
document.getElementById("closePasswordModal")?.addEventListener("click", () => closeModal("passwordModal"));
document.getElementById("cancelPasswordModal")?.addEventListener("click", () => closeModal("passwordModal"));
passwordForm?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const currentPassword = document.getElementById("currentPassword")?.value || "";
    const newPassword = document.getElementById("newPassword")?.value || "";
    const confirmPassword = document.getElementById("confirmPassword")?.value || "";

    if (newPassword !== confirmPassword) {
        showToast("New passwords do not match.");
        return;
    }

    try {
        const response = await fetch("admin_api.php?action=password-update", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ currentPassword, newPassword, confirmPassword })
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.error || "Unable to change password.");
        }

        closeModal("passwordModal");
        showToast("Password updated successfully.");
    } catch (error) {
        showToast(error.message || "Unable to change password.");
    }
});

const settingsToggleButtons = document.querySelectorAll(".toggle-pill");
settingsToggleButtons.forEach((toggleButton) => {
    toggleButton.addEventListener("click", () => {
        const isEnabled = toggleButton.classList.toggle("on");
        toggleButton.textContent = isEnabled ? "ON" : "OFF";
    });
});
function getRoleMetrics() {
    const totalBooks = books.length;
    const activeBorrowings = borrowRequests.filter(request => request.status === "approved");
    const pendingRequests = borrowRequests.filter(request => request.status === "pending");
    const overdueCount = activeBorrowings.filter(request => isOverdue(requestAsTransaction(request))).length;
    const dueSoonCount = activeBorrowings.filter(request => {
        const dueDate = new Date(request.dueDate + "T00:00:00");
        const today = new Date(getToday() + "T00:00:00");
        const diff = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
        return diff >= 0 && diff <= 3;
    }).length;

    const roleMetrics = {
        student: {
            borrowed: activeBorrowings.length,
            dueSoon: dueSoonCount,
        },
        librarian: {
            pending: pendingRequests.length,
            overdue: overdueCount
        },
        admin: {
            students: accounts.filter(account => account.role === "student" && account.isActive).length,
            librarians: accounts.filter(account => account.role === "librarian" && account.isActive).length,
            books: totalBooks
        }
    };

    return roleMetrics[currentRole] || roleMetrics.student;
}

function renderRoleBoard() {
    const role = roleConfig[currentRole];
    const metrics = getRoleMetrics();
    const roleBoard = document.getElementById("roleBoard");

    if (!roleBoard) return;

    roleBoard.innerHTML = Object.entries(metrics).map(([key, value]) => {
        const labelMap = {
            borrowed: "Borrowed",
            dueSoon: "Due Soon",
            pending: "Pending",
            overdue: "Overdue",
            returned: "Returned",
            students: "Students",
            librarians: "Librarians",
            books: "Books"
        };

        return `
            <article class="role-panel">
                <span class="role-panel-label">${labelMap[key]}</span>
                <strong>${value}</strong>
            </article>
        `;
    }).join("");
}

function updateRoleContext() {
    const role = roleConfig[currentRole];
    const metrics = getRoleMetrics();
    const summaryValue = metrics[Object.keys(metrics)[0]] ?? 0;

    const eyebrow = document.getElementById("roleEyebrow");
    const heroTitle = document.getElementById("roleHeroTitle");
    const heroDescription = document.getElementById("roleHeroDescription");
    const summaryLabel = document.getElementById("roleSummaryLabel");
    const summaryValueEl = document.getElementById("roleSummaryValue");
    const summaryHint = document.getElementById("roleSummaryHint");

    if (eyebrow) eyebrow.textContent = role.eyebrow;
    if (heroTitle) heroTitle.innerHTML = role.heroTitle;
    if (heroDescription) heroDescription.textContent = role.heroDescription;
    if (summaryLabel) summaryLabel.textContent = role.summaryLabel;
    if (summaryValueEl) summaryValueEl.textContent = summaryValue;
    if (summaryHint) summaryHint.textContent = role.summaryHint;

}

// STATISTICS

function updateStatistics() {

    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    };

    setText("totalBooks", books.length);
    setText("bookCount", books.length);
    const activeBorrowings = borrowRequests.filter(
        request => request.status === "approved"
    ).length;


    const borrowedBadge = document.getElementById("borrowedBadge");
    if (borrowedBadge) {
        borrowedBadge.textContent = activeBorrowings > 99 ? "99+" : activeBorrowings;
        borrowedBadge.classList.toggle("has-items", activeBorrowings > 0);
    }
    setText("activeBorrowingCount", activeBorrowings);

}


// CATEGORIES

function updateCategories() {

    const categoryFilter =
        document.getElementById("categoryFilter");

    if (!categoryFilter) return;

    const current =
        categoryFilter.value;


    const categories =
        [...new Set(
            books.map(book => book.category)
        )].sort();


    categoryFilter.innerHTML =
        `<option value="all">All Categories</option>`;


    categories.forEach(category => {

        const option =
            document.createElement("option");

        option.value = category;
        option.textContent = category;

        categoryFilter.appendChild(option);

    });


    categoryFilter.value =
        [...categoryFilter.options]
            .some(option => option.value === current)
            ? current
            : "all";
}


// RENDER BOOKS

function renderBooks() {

    const search =
        document
            .getElementById("searchInput")
            .value
            .toLowerCase()
            .trim();

    const category =
        document
            .getElementById("categoryFilter")
            .value;

    const availability =
        document
            .getElementById("availabilityFilter")
            .value;


    const filteredBooks =
        books.filter(book => {

            const matchesSearch =
                book.title
                    .toLowerCase()
                    .includes(search)
                ||
                book.author
                    .toLowerCase()
                    .includes(search);


            const matchesCategory =
                category === "all"
                ||
                book.category === category;


            const matchesAvailability =
                availability === "all"
                ||
                (
                    availability === "available"
                    &&
                    book.available > 0
                )
                ||
                (
                    availability === "unavailable"
                    &&
                    book.available === 0
                );


            return (
                matchesSearch
                &&
                matchesCategory
                &&
                matchesAvailability
            );

        });


    bookList.innerHTML = "";


    if (books.length === 0) {
        emptyState.classList.add("show");
    } else {
        emptyState.classList.remove("show");
    }


    filteredBooks.forEach((book, index) => {
        const hasActiveRequest = currentRole === "student"
            && borrowRequests.some(request =>
                String(request.bookId) === String(book.id)
                && ["pending", "approved"].includes(request.status)
            );
        const cannotRequest = book.available === 0 || hasActiveRequest;
        const requestLabel = hasActiveRequest ? "Unavailable" : "Request";
        const requestNote = hasActiveRequest
            ? "You cannot borrow this book because you already have it."
            : "This book is currently unavailable.";

        const percentage =
            book.total > 0
                ? (book.available / book.total) * 100
                : 0;


        const card =
            document.createElement("article");

        card.className = "book-card";

        card.style.animationDelay =
            `${index * 0.04}s`;


        card.innerHTML = `

            <div class="book-cover">
                <i data-lucide="book-open"></i>
            </div>

            <div class="book-info">

                <h3>
                    ${escapeHTML(book.title)}
                </h3>

                <p>
                    ${escapeHTML(book.author)}
                </p>

                <span class="book-category">
                    ${escapeHTML(book.category)}
                </span>

            </div>


            <div class="availability">

                <div class="availability-label">

                    <span>Available</span>

                    <strong>
                        ${book.available} / ${book.total}
                    </strong>

                </div>

                <div class="availability-bar">

                    <div
                        class="availability-progress"
                        style="width: ${percentage}%"
                    ></div>

                </div>

            </div>


            <div class="book-actions">

                ${
                    currentRole === "student"
                        ? `
                            <span class="borrow-button-wrap" title="${escapeHTML(requestNote)}">
                                <button
                                    class="borrow-button${cannotRequest ? " unavailable" : ""}"
                                    ${cannotRequest ? "disabled aria-disabled=\"true\"" : `onclick="openBorrowModal('${book.id}')"`}
                                >
                                    <i data-lucide="${cannotRequest ? "ban" : "book-open"}"></i>
                                    ${requestLabel}
                                </button>
                            </span>
                        `
                        : ""
                }

                ${
                    canManageBooks
                        ? `
                            <button
                                class="icon-action"
                                onclick="editBook('${book.id}')"
                                title="Edit"
                            >
                                <i data-lucide="pencil"></i>
                            </button>

                            <button
                                class="icon-action remove"
                                onclick="openDeleteModal('${book.id}')"
                                title="Remove"
                            >
                                <i data-lucide="trash-2"></i>
                            </button>
                        `
                        : ""
                }

            </div>

        `;


        bookList.appendChild(card);

    });


    lucide.createIcons();
}

function renderStudents() {
    const list = document.getElementById("studentList");
    if (!list) return;

    const empty = document.getElementById("noStudents");
    list.innerHTML = students.map(student => `
        <article class="transaction-card account-card student-card">
            <div class="transaction-icon"><i data-lucide="graduation-cap"></i></div>
            <div class="transaction-main">
                <h3>${escapeHTML(student.name)}</h3>
                <p>${escapeHTML(student.email)}</p>
                <div class="borrower-info"><span>Student ID: <strong>${escapeHTML(student.userId)}</strong></span></div>
            </div>
            <div class="transaction-actions account-actions">
                <span class="status-badge ${student.isActive ? "status-borrowed" : "status-overdue"}">${student.isActive ? "Active" : "Inactive"}</span>
                <button class="profile-action-button" data-student-activity="${student.id}" type="button">
                    <i data-lucide="book-open-check"></i>
                    Borrowing Activity
                </button>
            </div>
        </article>
    `).join("");
    if (empty) empty.classList.toggle("show", students.length === 0);
    lucide.createIcons();
}

function renderStudentActivity() {
    const list = document.getElementById("studentActivityList");
    if (!list) return;

    const today = getToday();
    // Overdue means an approved borrowing whose due date has passed today.
    const records = selectedStudentActivity.filter(record => {
        if (selectedStudentActivityTab === "returned") return record.status === "returned";
        if (selectedStudentActivityTab === "overdue") {
            return record.status === "approved" && record.dueDate < today;
        }
        return record.status === "approved" && record.dueDate >= today;
    });

    list.innerHTML = records.map(record => `
        <article class="student-activity-record">
            <div>
                <strong>${escapeHTML(record.bookTitle)}</strong>
                <span>${escapeHTML(record.bookAuthor || "Library book")}</span>
            </div>
            <div class="student-activity-dates">
                <span>Borrowed <strong>${formatDate(record.borrowDate)}</strong></span>
                <span>${record.status === "returned" ? "Returned" : "Due"} <strong>${formatDate(record.returnedAt || record.dueDate)}</strong></span>
            </div>
            <span class="status-badge status-${selectedStudentActivityTab}">${selectedStudentActivityTab.toUpperCase()}</span>
        </article>
    `).join("");

    const empty = document.getElementById("noStudentActivity");
    if (empty) empty.classList.toggle("show", records.length === 0);
}

async function openStudentActivity(student) {
    // Fetch the selected student's complete history before filtering the tabs.
    const response = await fetch(
        `circulation_api.php?action=student-history&studentId=${encodeURIComponent(student.id)}`,
        { credentials: "same-origin" }
    );
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Unable to load student activity.");

    selectedStudentActivity = result;
    selectedStudentActivityTab = "borrowed";
    document.getElementById("studentActivityTitle").textContent = `${student.name}'s Activity`;
    document.getElementById("studentActivityBorrowedCount").textContent = result.filter(item => item.status === "approved" && item.dueDate >= getToday()).length;
    document.getElementById("studentActivityReturnedCount").textContent = result.filter(item => item.status === "returned").length;
    document.getElementById("studentActivityOverdueCount").textContent = result.filter(item => item.status === "approved" && item.dueDate < getToday()).length;
    document.querySelectorAll(".student-activity-tab").forEach(tab => tab.classList.toggle("active", tab.dataset.studentActivityTab === selectedStudentActivityTab));
    renderStudentActivity();
    document.getElementById("studentActivityModal").classList.add("show");
    lucide.createIcons();
}

document.getElementById("studentSearch")?.addEventListener("input", () => {
    loadStudents().then(renderStudents).catch(error => showToast(error.message));
});

document.getElementById("studentList")?.addEventListener("click", event => {
    const button = event.target.closest("[data-student-activity]");
    if (!button) return;
    const student = students.find(item => String(item.id) === button.dataset.studentActivity);
    if (student) openStudentActivity(student).catch(error => showToast(error.message));
});

document.querySelectorAll(".student-activity-tab").forEach(tab => {
    tab.addEventListener("click", () => {
        selectedStudentActivityTab = tab.dataset.studentActivityTab;
        document.querySelectorAll(".student-activity-tab").forEach(item => item.classList.toggle("active", item === tab));
        renderStudentActivity();
    });
});

document.getElementById("closeStudentActivity")?.addEventListener("click", () => closeModal("studentActivityModal"));


// ADD BOOK

function openAddBookModal() {

    if (!canManageBooks) return;

    const form = document.getElementById("bookForm");
    const bookId = document.getElementById("bookId");
    const total = document.getElementById("bookTotal");
    const available = document.getElementById("bookAvailable");
    const modalTitle = document.getElementById("modalTitle");
    const saveButtonText = document.getElementById("saveButtonText");
    const modal = document.getElementById("bookModal");

    if (!form || !bookId || !total || !available || !modalTitle || !saveButtonText || !modal) {
        showToast("The book form is unavailable. Please refresh the page.");
        return;
    }

    form.reset();
    bookId.value = "";
    total.value = 1;
    available.value = 1;
    modalTitle.textContent = "Add a Book";
    saveButtonText.textContent = "Add Book";
    modal.classList.add("show");
}


const openAddModalButton =
    document.getElementById("openAddModal");

if (openAddModalButton) {
    openAddModalButton.addEventListener(
        "click",
        openAddBookModal
    );
}

const emptyAddButton =
    document.getElementById("emptyAddButton");

if (emptyAddButton) {
    emptyAddButton.addEventListener(
        "click",
        openAddBookModal
    );
}


// EDIT BOOK

function editBook(id) {

    if (!canManageBooks) return;

    const book =
        books.find(book => book.id === id);

    if (!book) return;


    document
        .getElementById("bookId")
        .value = book.id;

    document
        .getElementById("bookTitle")
        .value = book.title;

    document
        .getElementById("bookAuthor")
        .value = book.author;

    document
        .getElementById("bookCategory")
        .value = book.category;

    document
        .getElementById("bookTotal")
        .value = book.total;

    document
        .getElementById("bookAvailable")
        .value = book.available;


    document
        .getElementById("modalTitle")
        .textContent = "Edit Book";

    document
        .getElementById("saveButtonText")
        .textContent = "Save Changes";


    document
        .getElementById("bookModal")
        .classList.add("show");
}


// SAVE BOOK

document
    .getElementById("bookForm")
    .addEventListener(
        "submit",
        event => {

            event.preventDefault();


            const id =
                document
                    .getElementById("bookId")
                    .value;


            const title =
                document
                    .getElementById("bookTitle")
                    .value
                    .trim();

            const author =
                document
                    .getElementById("bookAuthor")
                    .value
                    .trim();

            const category =
                document
                    .getElementById("bookCategory")
                    .value
                    .trim();

            const total =
                Number(
                    document
                        .getElementById("bookTotal")
                        .value
                );

            const available =
                Number(
                    document
                        .getElementById("bookAvailable")
                        .value
                );


            if (available > total) {

                showToast(
                    "Available copies cannot exceed total copies."
                );

                return;
            }


            if (id) {

                const book =
                    books.find(book => book.id === id);

                const activeTransactions =
                    transactions.filter(
                        transaction =>
                            transaction.bookId === id
                            &&
                            transaction.status === "borrowed"
                    ).length;


                if (total < activeTransactions) {

                    showToast(
                        "Total copies cannot be less than currently borrowed copies."
                    );

                    return;
                }


                const updatedBook = {
                    ...book,
                    title,
                    author,
                    category,
                    total,
                    available
                };

                saveBook(updatedBook, id)
                    .then(() => {
                        books = books.map(item =>
                            item.id === id ? updatedBook : item
                        );
                        showToast("Book updated.");
                        updateCategories();
                        closeModal("bookModal");
                        renderAll();
                    })
                    .catch(error => showToast(error.message));

                return;

            } else {

                const newBook = {
                    title,
                    author,
                    category,
                    total,
                    available
                };

                saveBook(newBook)
                    .then(book => {
                        books.push(book);
                        showToast("Book added to collection.");
                        updateCategories();
                        closeModal("bookModal");
                        renderAll();
                    })
                    .catch(error => showToast(error.message));
            }

        }
    );


// BORROW BOOK

function openBorrowModal(id) {

    const book =
        books.find(book => book.id === id);

    if (!book || book.available <= 0) {
        return;
    }


    document
        .getElementById("borrowForm")
        .reset();

    if (currentRole !== "student") {
        document.getElementById("borrowerName").value = currentUserName;
    }

    const borrowPurpose = document.getElementById("borrowPurpose");
    if (borrowPurpose) {
        borrowPurpose.value = "Coursework";
    }

    // Students submit requests; staff create approved circulation records directly.
    document
        .getElementById("borrowBookId")
        .value = book.id;


    document
        .getElementById("borrowBookTitle")
        .textContent = book.title;


    document
        .getElementById("selectedBookInfo")
        .innerHTML = `
            <strong>${escapeHTML(book.title)}</strong>
            ${escapeHTML(book.author)}
            Â·
            ${book.available} copies currently available
        `;


    const today = getToday();

    document
        .getElementById("borrowDate")
        .value = today;


    const due =
        new Date();

    due.setDate(due.getDate() + 7);

    document
        .getElementById("dueDate")
        .value =
            due
                .toISOString()
                .split("T")[0];


    document
        .getElementById("borrowModal")
        .classList.add("show");
}


document
    .getElementById("borrowForm")
    .addEventListener(
        "submit",
        event => {

            event.preventDefault();


            const bookId =
                document
                    .getElementById("borrowBookId")
                    .value;

            const borrowerName =
                document
                    .getElementById("borrowerName")
                    .value
                    .trim();

            const purpose =
                document
                    .getElementById("borrowPurpose")
                    .value
                    .trim();

            const borrowDate =
                document
                    .getElementById("borrowDate")
                    .value;

            const dueDate =
                document
                    .getElementById("dueDate")
                    .value;

            if (!purpose) {
                showToast("Please select a purpose for this borrowing.");
                return;
            }

            if (dueDate < borrowDate) {

                showToast(
                    "Due date cannot be before borrow date."
                );

                return;
            }


            const book =
                books.find(book => book.id === bookId);


            if (!book || book.available <= 0) {

                showToast(
                    "This book is no longer available."
                );

                return;
            }


            if (currentRole === "student") {
                fetch("circulation_api.php?action=request", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        bookId,
                        borrowDate,
                        dueDate,
                        purpose
                    })
                })
                    .then(async response => {
                        const result = await response.json();
                        if (!response.ok) {
                            throw new Error(result.error || "Unable to submit request.");
                        }
                        return result;
                    })
                    .then(() => {
                        closeModal("borrowModal");
                        showToast("Borrow request submitted for approval.");
                        return loadCirculation();
                    })
                    .then(renderAll)
                    .catch(error => showToast(error.message));
                return;
            }

            fetch("circulation_api.php?action=borrow", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    bookId,
                    borrowDate,
                    dueDate,
                    purpose
                })
            })
                .then(async response => {
                    const result = await response.json();
                    if (!response.ok) {
                        throw new Error(result.error || "Unable to borrow this book.");
                    }
                    return result;
                })
                .then(() => {
                    closeModal("borrowModal");
                    showToast("Book borrowed successfully.");
                    return Promise.all([loadBooks(), loadCirculation()]);
                })
                .then(renderAll)
                .catch(error => showToast(error.message));
        }
    );


// ACTIVE BORROWINGS

function renderPendingRequests() {
    if (!pendingRequestList) return;

    const pending = borrowRequests.filter(
        request => request.status === "pending"
    );

    pendingRequestList.innerHTML = "";
    const empty = document.getElementById("noPendingRequests");
    if (empty) empty.classList.toggle("show", pending.length === 0);

    pending.forEach(request => {
        const card = document.createElement("article");
        card.className = "transaction-card";
        card.innerHTML = `
            <div class="transaction-icon">
                <i data-lucide="clock-3"></i>
            </div>
            <div class="transaction-main">
                <h3>${escapeHTML(request.bookTitle)}</h3>
                <p>${escapeHTML(request.bookAuthor)}</p>
                <div class="borrower-info">
                    <span>Student: <strong>${escapeHTML(request.borrowerName)}</strong></span>
                    <span>Purpose: <strong>${escapeHTML(request.purpose || "Coursework")}</strong></span>
                </div>
            </div>
            <div class="transaction-dates">
                <div class="date-row"><span>Requested</span><strong>${formatDate(request.borrowDate)}</strong></div>
                <div class="date-row"><span>Due</span><strong>${formatDate(request.dueDate)}</strong></div>
            </div>
            <div class="transaction-actions request-actions">
                <button class="return-button" onclick="reviewRequest('${request.id}', 'approve')">Approve</button>
                <button class="delete-button" onclick="reviewRequest('${request.id}', 'decline')">Decline</button>
            </div>
        `;
        pendingRequestList.appendChild(card);
    });

    lucide.createIcons();
}

function reviewRequest(requestId, decision) {
    if (decision === "decline") {
        requestToDecline = requestId;
        document.getElementById("declineReason").value = "";
        document.getElementById("declineModal").classList.add("show");
        return;
    }

    submitRequestReview(requestId, decision, "");
}

function submitRequestReview(requestId, decision, reason) {
    fetch("circulation_api.php?action=review", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ requestId, decision, reason })
    })
        .then(async response => {
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || "Unable to review request.");
            return result;
        })
        .then(() => Promise.all([loadBooks(), loadCirculation()]))
        .then(() => {
            showToast(decision === "approve" ? "Request approved." : "Request declined.");
            renderAll();
        })
        .catch(error => showToast(error.message));
}

function renderStudentMyBooks() {
    if (currentRole !== "student") return;

    const myRequests = borrowRequests.filter(request => String(request.studentId) === String(currentUserId));
    const pending = myRequests.filter(request => request.status === "pending");
    const borrowed = myRequests.filter(request => request.status === "approved");
    const returned = myRequests.filter(request => request.status === "returned");

    const tabCountMap = {
        pending: pending.length,
        borrowed: borrowed.length,
        returned: returned.length,
    };

    ["pending", "borrowed", "returned"].forEach(key => {
        const element = document.getElementById(`${key}TabCount`);
        if (element) {
            element.textContent = tabCountMap[key];
        }
    });

    // The same renderer keeps pending, borrowed, and returned cards consistent.
    const renderList = (containerId, items, type) => {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = "";

        const empty = document.getElementById(containerId.replace("List", "Empty"));
        if (items.length === 0) {
            if (empty) empty.classList.add("show");
            return;
        }

        if (empty) empty.classList.remove("show");

        items.forEach(item => {
            const entry = document.createElement("article");
            entry.className = "student-my-book-card";

            const dateLabel = type === "pending" ? "Requested" : type === "borrowed" ? "Borrowed" : "Returned";
            const dateValue = type === "pending" ? item.borrowDate : type === "borrowed" ? item.borrowDate : (item.returnedAt || item.returnDate || item.borrowDate);
            const secondaryLabel = type === "pending" ? "Status" : type === "borrowed" ? "Due" : "Status";
            const secondaryValue = type === "pending" ? "Pending" : type === "borrowed" ? formatDate(item.dueDate) : "Returned";

            entry.innerHTML = `
                <div class="student-my-book-main">
                    <h3>${escapeHTML(item.bookTitle)}</h3>
                    <p>${escapeHTML(item.bookAuthor || "Library book")}</p>
                </div>
                <div class="student-my-book-meta">
                    <div><span>${dateLabel}</span><strong>${formatDate(dateValue)}</strong></div>
                    <div><span>${secondaryLabel}</span><strong>${escapeHTML(secondaryValue)}</strong></div>
                </div>
                <div class="student-my-book-status ${type === "pending" ? "pending" : type === "borrowed" ? "borrowed" : "returned"}">
                    ${type === "pending" ? "PENDING" : type === "borrowed" ? "BORROWED" : "RETURNED"}
                </div>
            `;

            container.appendChild(entry);
        });
    };

    renderList("studentPendingList", pending, "pending");
    renderList("studentBorrowedList", borrowed, "borrowed");
    renderList("studentReturnedList", returned, "returned");
}

function renderNotifications() {
    if (!notificationList) return;

    notificationList.innerHTML = "";
    const empty = document.getElementById("noNotifications");
    if (empty) empty.classList.toggle("show", notifications.length === 0);

    notifications.forEach(notification => {
        const item = document.createElement("article");
        item.className = `notification-item ${notification.is_read ? "" : "unread"}`;
        item.innerHTML = `
            <div class="notification-icon"><i data-lucide="bell"></i></div>
            <div class="notification-content">
                <strong>${escapeHTML(notification.message)}</strong>
                <span>${formatDate((notification.created_at || "").slice(0, 10))}</span>
            </div>
            <div class="notification-actions">
                ${
                    Number(notification.is_read)
                        ? ""
                        : `<button class="notification-action" data-notification-read="${notification.id}">Mark read</button>`
                }
                <button class="notification-action delete" data-notification-delete="${notification.id}">Delete</button>
            </div>
        `;
        notificationList.appendChild(item);
    });

    const unread = notifications.filter(item => !Number(item.is_read)).length;
    const badge = document.getElementById("notificationBadge");
    if (badge) {
        badge.textContent = unread > 99 ? "99+" : unread;
        badge.classList.toggle("has-unread", unread > 0);
    }
    const historyBadge = document.getElementById("historyBadge");
    if (historyBadge) {
        historyBadge.textContent = unread > 99 ? "99+" : unread;
        historyBadge.classList.toggle("has-items", unread > 0);
    }
    lucide.createIcons();
}

function updateNotification(action, notificationId) {
    fetch(`circulation_api.php?action=${action}`, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ notificationId })
    })
        .then(async response => {
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || "Unable to update notification.");
            return result;
        })
        .then(() => loadCirculation())
        .then(renderAll)
        .catch(error => showToast(error.message));
}

notificationList?.addEventListener("click", event => {
    const readButton = event.target.closest("[data-notification-read]");
    const deleteButton = event.target.closest("[data-notification-delete]");

    if (readButton) {
        updateNotification("notifications-read", readButton.dataset.notificationRead);
    } else if (deleteButton) {
        updateNotification("notifications-delete", deleteButton.dataset.notificationDelete);
    }
});

function renderActiveBorrowings() {
    if (!activeBorrowingList) return;

    // Prefer persistent API records and only use legacy local records as a fallback.
    const serverActive = borrowRequests
        .filter(request => request.status === "approved")
        .map(requestAsTransaction);
    const active = serverActive.length > 0
        ? serverActive
        : transactions.filter(transaction => transaction.status === "borrowed");


    activeBorrowingList.innerHTML = "";


    document
        .getElementById("noBorrowings")
        .classList.toggle(
            "show",
            active.length === 0
        );


    active.forEach(transaction => {

        const overdue =
            isOverdue(transaction);


        const card =
            document.createElement("article");

        card.className =
            "transaction-card";


        card.innerHTML = `

            <div class="transaction-icon">
                <i data-lucide="book-open"></i>
            </div>


            <div class="transaction-main">

                <h3>
                    ${escapeHTML(transaction.bookTitle)}
                </h3>

                <p>
                    ${escapeHTML(transaction.bookAuthor)}
                </p>

                <div class="borrower-info">

                    <span>
                        Borrower:
                        <strong>
                            ${escapeHTML(transaction.borrowerName)}
                        </strong>
                    </span>

                    <span>
                        ID:
                        <strong>
                            ${escapeHTML(transaction.studentId)}
                        </strong>
                    </span>

                </div>

            </div>


            <div class="transaction-dates">

                <div class="date-row">
                    <span>Borrowed</span>
                    <strong>
                        ${formatDate(transaction.borrowDate)}
                    </strong>
                </div>

                <div class="date-row">
                    <span>Due</span>
                    <strong>
                        ${formatDate(transaction.dueDate)}
                    </strong>
                </div>

            </div>


            <div class="transaction-actions">

                <span class="
                    status-badge
                    ${overdue ? "status-overdue" : "status-borrowed"}
                ">
                    ${overdue ? "Overdue" : "Borrowed"}
                </span>

                ${
                    currentRole === "student"
                        ? `<button class="return-button" onclick="openReturnModal('${transaction.id}')">Return Book</button>`
                        : ""
                }

            </div>

        `;


        activeBorrowingList.appendChild(card);

    });


    lucide.createIcons();
}


// RETURN BOOK

function openReturnModal(id) {

    transactionToReturn = id;

    const transaction =
        borrowRequests.find(
            item => item.id === id
        ) || transactions.find(
            item => item.id === id
        );

    if (!transaction) return;


    document
        .getElementById("returnBookInfo")
        .textContent =
            `${transaction.bookTitle} borrowed by ${transaction.borrowerName}.`;


    document
        .getElementById("returnModal")
        .classList.add("show");
}


document
    .getElementById("confirmReturn")
    .addEventListener(
        "click",
        () => {

            if (!transactionToReturn) return;


            const transaction =
                borrowRequests.find(
                    item => item.id === transactionToReturn
                ) || transactions.find(
                    item => item.id === transactionToReturn
                );

            if (!transaction) {
                showToast("This borrowing could not be found.");
                return;
            }


            if (borrowRequests.some(request => request.id === transactionToReturn)) {
                fetch("circulation_api.php?action=return", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ requestId: transactionToReturn })
                })
                    .then(async response => {
                        const result = await response.json();
                        if (!response.ok) {
                            throw new Error(result.error || "Unable to return this book.");
                        }
                        return result;
                    })
                    .then(() => Promise.all([loadBooks(), loadCirculation()]))
                    .then(() => {
                        closeModal("returnModal");
                        showToast("Book returned successfully.");
                        transactionToReturn = null;
                        renderAll();
                    })
                    .catch(error => showToast(error.message));
                return;
            }

            transaction.status = "returned";
            transaction.returnDate = getToday();

            const book = books.find(book => book.id === transaction.bookId);
            if (book) {
                book.available = Math.min(book.available + 1, book.total);
            }

            saveBooks();
            saveTransactions();
            closeModal("returnModal");
            showToast("Book returned successfully.");
            transactionToReturn = null;
            renderAll();
        }
    );


// HISTORY

function renderHistory() {
    if (!historyList) return;

    const searchInput = document.getElementById("historySearch");
    const filterInput = document.getElementById("historyFilter");
    if (!searchInput || !filterInput) return;

    const search =
        searchInput.value
            .toLowerCase()
            .trim();


    const filter =
        filterInput.value;


    // Filters are applied in the browser after the API returns the user's permitted records.
    const records = historyRecords.length > 0
        ? historyRecords.map(request => ({
            ...request,
            status: request.status === "approved" ? "borrowed" : request.status,
            returnDate: request.returnedAt ? request.returnedAt.slice(0, 10) : null
        }))
        : transactions;

    const filtered =
        [...records]
            .reverse()
            .filter(transaction => {

                const matchesSearch =
                    String(transaction.bookTitle || "")
                        .toLowerCase()
                        .includes(search)
                    ||
                    String(transaction.borrowerName || "")
                        .toLowerCase()
                        .includes(search)
                    ||
                    String(transaction.studentId || "")
                        .toLowerCase()
                        .includes(search);


                let matchesFilter =
                    filter === "all";


                if (filter === "borrowed") {
                    matchesFilter =
                        transaction.status === "borrowed"
                        &&
                        !isOverdue(transaction);
                }


                if (filter === "returned") {
                    matchesFilter =
                        transaction.status === "returned";
                }


                if (filter === "overdue") {
                    matchesFilter =
                        isOverdue(transaction);
                }


                return (
                    matchesSearch
                    &&
                    matchesFilter
                );

            });


    historyList.innerHTML = "";


    const emptyState = document.getElementById("noHistory");
    if (emptyState) {
        emptyState.classList.toggle("show", filtered.length === 0);
    }


    filtered.forEach(transaction => {

        let status = "Returned";
        let statusClass = "status-returned";


        if (isOverdue(transaction)) {

            status = "Overdue";
            statusClass = "status-overdue";

        } else if (
            transaction.status === "borrowed"
        ) {

            status = "Borrowed";
            statusClass = "status-borrowed";

        }


        const card =
            document.createElement("article");

        card.className =
            "transaction-card";


        card.innerHTML = `

            <div class="transaction-icon">
                <i data-lucide="book-open"></i>
            </div>

            <div class="transaction-main">

                <h3>
                    ${escapeHTML(transaction.bookTitle)}
                </h3>

                <p>
                    ${escapeHTML(transaction.bookAuthor)}
                </p>

                <div class="borrower-info">

                    <span>
                        Borrower:
                        <strong>
                            ${escapeHTML(transaction.borrowerName)}
                        </strong>
                    </span>

                    <span>
                        ID:
                        <strong>
                            ${escapeHTML(transaction.studentId)}
                        </strong>
                    </span>

                </div>

            </div>

            <div class="transaction-dates">

                <div class="date-row">
                    <span>Borrowed</span>
                    <strong>
                        ${formatDate(transaction.borrowDate)}
                    </strong>
                </div>

                <div class="date-row">
                    <span>Due</span>
                    <strong>
                        ${formatDate(transaction.dueDate)}
                    </strong>
                </div>

                ${
                    transaction.returnedAt || transaction.returnDate
                        ? `
                            <div class="date-row">
                                <span>Returned</span>
                                <strong>
                                    ${formatDateTime(transaction.returnedAt || transaction.returnDate)}
                                </strong>
                            </div>
                        `
                        : ""
                }
                ${
                    transaction.reviewedAt
                        ? `
                            <div class="date-row">
                                <span>Reviewed</span>
                                <strong>${formatDateTime(transaction.reviewedAt)}</strong>
                            </div>
                        `
                        : ""
                }

            </div>

            <div class="transaction-actions">

                <span class="
                    status-badge
                    ${statusClass}
                ">
                    ${status}
                </span>

            </div>

        `;


        historyList.appendChild(card);

    });


    lucide.createIcons();
}


// DELETE BOOK

function openDeleteModal(id) {

    if (!canManageBooks) return;

    const activeBorrowings =
        transactions.filter(
            transaction =>
                transaction.bookId === id
                &&
                transaction.status === "borrowed"
        );


    if (activeBorrowings.length > 0) {

        showToast(
            "Cannot remove a book while copies are borrowed."
        );

        return;
    }


    bookToDelete = id;


    document
        .getElementById("deleteModal")
        .classList.add("show");
}


document
    .getElementById("confirmDelete")
    .addEventListener(
        "click",
        () => {

            if (!bookToDelete) return;


            deleteBook(bookToDelete)
                .then(() => {
                    books = books.filter(
                        book => book.id !== bookToDelete
                    );
                    updateCategories();
                    closeModal("deleteModal");
                    showToast("Book removed from collection.");
                    bookToDelete = null;
                    renderAll();
                })
                .catch(error => showToast(error.message));

        }
    );


// MODAL CONTROLS

function closeModal(id) {

    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove("show");
    }
}


document
    .getElementById("closeModal")
    .addEventListener(
        "click",
        () => closeModal("bookModal")
    );

document
    .getElementById("cancelModal")
    .addEventListener(
        "click",
        () => closeModal("bookModal")
    );

document
    .getElementById("closeBorrowModal")
    .addEventListener(
        "click",
        () => closeModal("borrowModal")
    );

document
    .getElementById("cancelBorrow")
    .addEventListener(
        "click",
        () => closeModal("borrowModal")
    );

document
    .getElementById("cancelReturn")
    .addEventListener(
        "click",
        () => closeModal("returnModal")
    );

document
    .getElementById("cancelDelete")
    .addEventListener(
        "click",
        () => closeModal("deleteModal")
    );

document
    .getElementById("cancelDecline")
    .addEventListener(
        "click",
        () => {
            requestToDecline = null;
            closeModal("declineModal");
        }
    );

document
    .getElementById("declineForm")
    .addEventListener(
        "submit",
        event => {
            event.preventDefault();
            if (!requestToDecline) return;

            const requestId = requestToDecline;
            const reason = document.getElementById("declineReason").value.trim();
            requestToDecline = null;
            closeModal("declineModal");
            submitRequestReview(requestId, "decline", reason);
        }
    );


// Close modal on background click

document
    .querySelectorAll(".modal-overlay")
    .forEach(modal => {

        modal.addEventListener(
            "click",
            event => {

                if (event.target === modal) {
                    modal.classList.remove("show");
                }

            }
        );

    });


// SEARCH / FILTER EVENTS

document
    .getElementById("searchInput")
    .addEventListener(
        "input",
        renderBooks
    );

document
    .getElementById("categoryFilter")
    .addEventListener(
        "change",
        renderBooks
    );

document
    .getElementById("availabilityFilter")
    .addEventListener(
        "change",
        renderBooks
    );

document.getElementById("historySearch")?.addEventListener("input", renderHistory);
document.getElementById("historyFilter")?.addEventListener("change", renderHistory);


// TOAST

function showToast(message) {

    const toast =
        document.getElementById("toast");

    const toastMessage =
        document.getElementById("toastMessage");

    if (!toast || !toastMessage) {
        return;
    }

    toastMessage.textContent = message;


    toast.classList.add("show");


    setTimeout(
        () => {
            toast.classList.remove("show");
        },
        3000
    );
}


// SECURITY

function escapeHTML(value) {

    const div =
        document.createElement("div");

    div.textContent = value;

    return div.innerHTML;
}


// RENDER EVERYTHING

function renderAll() {
    // Re-render every visible data surface after any successful API update.

    renderProfile();
    updateProfileMetrics();
    updateStatistics();
    updateRoleContext();
    renderRoleBoard();
    renderBooks();
    renderPendingRequests();
    renderStudentMyBooks();
    renderActiveBorrowings();
    renderHistory();
    renderNotifications();
    renderAccounts();
    renderStudents();

}

function renderProfile() {
    const profileName = document.getElementById("profileName");
    const profileEmail = document.getElementById("profileEmail");
    const profileRole = document.getElementById("profileRole");
    const profileId = document.getElementById("profileId");

    if (profileName) {
        profileName.textContent = document.body.dataset.userName || currentUserName || "User";
    }

    if (profileEmail) {
        const userEmail = document.body.dataset.userEmail || "";
        profileEmail.textContent = userEmail || "No email on file";
    }

    if (profileRole) {
        const role = document.body.dataset.role || "student";
        profileRole.textContent = role.charAt(0).toUpperCase() + role.slice(1);
    }

    if (profileId) {
        profileId.textContent = document.body.dataset.userId || "â€”";
    }
}

function updateProfileMetrics() {
    const setMetric = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    };

    const userBorrowRequests = borrowRequests.filter(request => String(request.studentId) === String(currentUserId));
    const activeBorrowings = userBorrowRequests.filter(request => request.status === "approved");
    const pendingBorrowings = userBorrowRequests.filter(request => request.status === "pending");
    const returnedBorrowings = userBorrowRequests.filter(request => request.status === "returned");
    const overdueBorrowings = activeBorrowings.filter(request => isOverdue(requestAsTransaction(request))).length;

    setMetric("studentBorrowedCount", activeBorrowings.length);
    setMetric("studentPendingCount", pendingBorrowings.length);
    setMetric("studentReturnedCount", returnedBorrowings.length);
    setMetric("studentOverdueCount", overdueBorrowings);

    const activeStudents = accounts.filter(account => account.role === "student" && account.isActive).length;
    const activeLibrarians = accounts.filter(account => account.role === "librarian" && account.isActive).length;
    const pendingRequests = borrowRequests.filter(request => request.status === "pending").length;
    const overdueCount = borrowRequests.filter(request => request.status === "approved" && isOverdue(requestAsTransaction(request))).length;

    setMetric("adminStudentCount", activeStudents);
    setMetric("adminLibrarianCount", activeLibrarians);
    setMetric("adminBookCount", books.length);
    setMetric("adminPendingCount", pendingRequests);
    setMetric("adminOverdueCount", overdueCount);
}

async function saveProfileName(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const input = document.getElementById("profileNameInput");
    const name = input.value.trim();

    if (!name) {
        showToast("Name cannot be empty.");
        return;
    }

    try {
        const response = await fetch("admin_api.php?action=profile-update", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ name })
        });

        const data = await response.json();
        if (!response.ok || data.error) {
            throw new Error(data.error || "Unable to update your profile.");
        }

        document.body.dataset.userName = data.name;
        document.getElementById("profileName").textContent = data.name;
        form.classList.add("hidden");
        showToast("Profile updated.");
    } catch (error) {
        showToast(error.message || "Unable to update your profile.");
    }
}

function renderAccounts() {
    const list = document.getElementById("accountList");
    if (!list) return;
    list.innerHTML = accounts.map(account => `
        <article class="transaction-card account-card">
            <div class="transaction-icon"><i data-lucide="user"></i></div>
            <div class="transaction-main">
                <h3>${escapeHTML(account.name)}</h3>
                <p>${escapeHTML(account.email)}</p>
                <div class="borrower-info"><span>Role: <strong>${escapeHTML(account.role)}</strong></span><span>ID: <strong>${escapeHTML(account.userId)}</strong></span></div>
            </div>
            <div class="transaction-actions account-actions">
                <span class="status-badge ${account.isActive ? "status-borrowed" : "status-overdue"}">${account.isActive ? "Active" : "Inactive"}</span>
                <div class="account-action-stack">
                    <button class="icon-action" data-account-edit="${account.id}" title="Edit"><i data-lucide="pencil"></i></button>
                    <button class="account-delete-action" data-account-delete="${account.id}">Delete</button>
                    <button class="account-status-action ${account.isActive ? "deactivate" : "activate"}" data-account-status="${account.id}" data-active="${account.isActive ? "0" : "1"}">${account.isActive ? "Deactivate" : "Activate"}</button>
                </div>
            </div>
        </article>
    `).join("");
    lucide.createIcons();
}

function openAccountModal(account = null) {
    document.getElementById("accountModalTitle").textContent = account ? "Edit Account" : "Add an Account";
    document.getElementById("accountId").value = account?.id || "";
    document.getElementById("accountName").value = account?.name || "";
    document.getElementById("accountEmail").value = account?.email || "";
    document.getElementById("accountRole").value = account?.role || "student";
    document.getElementById("accountPassword").value = "";
    document.getElementById("accountPassword").required = !account;
    document.getElementById("accountModal").classList.add("show");
}

document.getElementById("openAccountModal")?.addEventListener("click", () => openAccountModal());
document.getElementById("cancelAccountModal")?.addEventListener("click", () => closeModal("accountModal"));
document.getElementById("closeAccountModal")?.addEventListener("click", () => closeModal("accountModal"));
document.getElementById("accountSearch")?.addEventListener("input", () => loadAccounts().then(renderAccounts).catch(error => showToast(error.message)));
document.getElementById("accountRoleFilter")?.addEventListener("change", () => loadAccounts().then(renderAccounts).catch(error => showToast(error.message)));

document.getElementById("accountList")?.addEventListener("click", event => {
    const edit = event.target.closest("[data-account-edit]");
    const status = event.target.closest("[data-account-status]");
    const remove = event.target.closest("[data-account-delete]");
    if (edit) {
        openAccountModal(accounts.find(account => String(account.id) === edit.dataset.accountEdit));
    }
    if (remove) {
        const accountId = Number(remove.dataset.accountDelete);
        const account = accounts.find(item => item.id === accountId);
        if (!account) return;

        accountToDelete = account;
        document.getElementById("accountDeleteName").textContent = account.name;
        document.getElementById("accountDeleteModal").classList.add("show");
    }
    if (status) {
        fetch("admin_api.php?action=users-status", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id: status.dataset.accountStatus,
                active: status.dataset.active === "1"
            })
        })
            .then(async response => {
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || "Unable to update account status.");
                return result;
            })
            .then(() => loadAccounts())
            .then(renderAll)
            .catch(error => showToast(error.message));
    }
});

document.getElementById("cancelAccountDelete")?.addEventListener("click", () => {
    accountToDelete = null;
    closeModal("accountDeleteModal");
});

document.getElementById("confirmAccountDelete")?.addEventListener("click", () => {
    if (!accountToDelete) return;

    const accountId = accountToDelete.id;
    accountToDelete = null;
    closeModal("accountDeleteModal");

    fetch("admin_api.php?action=users-delete", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: accountId })
    })
        .then(async response => {
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || "Unable to delete account.");
            return result;
        })
        .then(() => loadAccounts())
        .then(renderAll)
        .catch(error => showToast(error.message));
});

document.getElementById("accountForm")?.addEventListener("submit", event => {
    event.preventDefault();
    const id = document.getElementById("accountId").value;
    const action = id ? "users-update" : "users-create";
    fetch(`admin_api.php?action=${action}`, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id: id || undefined,
            name: document.getElementById("accountName").value.trim(),
            email: document.getElementById("accountEmail").value.trim(),
            role: document.getElementById("accountRole").value,
            password: document.getElementById("accountPassword").value
        })
    })
        .then(async response => {
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || "Unable to save account.");
            return result;
        })
        .then(() => loadAccounts())
        .then(() => {
            closeModal("accountModal");
            showToast("Account saved.");
            renderAll();
        })
        .catch(error => showToast(error.message));
});


// INITIAL LOAD

updateRoleContext();
renderRoleBoard();
loadBooks()
    .then(() => {
        return Promise.all([loadCirculation(), loadAccounts(), loadStudents()]);
    })
    .then(() => {
        updateCategories();
        renderAll();
    })
    .catch(error => showToast(error.message));

