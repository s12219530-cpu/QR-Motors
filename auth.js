"use strict";

const ADMIN_USERNAME = "admin";
const ADMIN_PASSWORD = "QRMotors@123";

const STORAGE_KEYS = {
    users: "users",
    currentUser: "currentUser",
    activities: "qrActivities",
    purchaseRequests: "qrPurchaseRequests",
    favorites: "qrFavorites",
    sellRequests: "qrSellRequests",
    messages: "qrMessages"
};

function readStorage(key, fallback) {
    try {
        const value = JSON.parse(localStorage.getItem(key));
        return value ?? fallback;
    } catch (error) {
        console.warn("Could not read", key, error);
        return fallback;
    }
}

function writeStorage(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
}

function makeId(prefix) {
    return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function formatDate(value) {
    if (!value) return "—";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return escapeHtml(value);
    return date.toLocaleString("en-GB", {
        year: "numeric", month: "short", day: "2-digit",
        hour: "2-digit", minute: "2-digit"
    });
}

function getUsers() {
    return readStorage(STORAGE_KEYS.users, []);
}

function saveUsers(users) {
    writeStorage(STORAGE_KEYS.users, users);
}

function getCurrentUser() {
    return readStorage(STORAGE_KEYS.currentUser, null);
}

function getActivities() {
    return readStorage(STORAGE_KEYS.activities, []);
}

function getPurchaseRequests() {
    return readStorage(STORAGE_KEYS.purchaseRequests, []);
}

function getFavorites() {
    return readStorage(STORAGE_KEYS.favorites, []);
}

function getSellRequests() {
    return readStorage(STORAGE_KEYS.sellRequests, []);
}

function getMessages() {
    return readStorage(STORAGE_KEYS.messages, []);
}

function showUserBox() {
    document.getElementById("choiceBox")?.classList.add("hidden");
    document.getElementById("adminBox")?.classList.add("hidden");
    document.getElementById("userBox")?.classList.remove("hidden");
}

function showAdminBox() {
    document.getElementById("choiceBox")?.classList.add("hidden");
    document.getElementById("userBox")?.classList.add("hidden");
    document.getElementById("adminBox")?.classList.remove("hidden");
}

function showChoices() {
    document.getElementById("userBox")?.classList.add("hidden");
    document.getElementById("adminBox")?.classList.add("hidden");
    document.getElementById("choiceBox")?.classList.remove("hidden");
}

function showRegisterForm() {
    document.getElementById("userLoginForm")?.classList.add("hidden");
    document.getElementById("userRegisterForm")?.classList.remove("hidden");
}

function showLoginForm() {
    document.getElementById("userRegisterForm")?.classList.add("hidden");
    document.getElementById("userLoginForm")?.classList.remove("hidden");
}

function registerUser() {
    const username = document.getElementById("registerName")?.value.trim() || "";
    const password = document.getElementById("registerPassword")?.value.trim() || "";

    clearFormMessage("registerMessage");
    if (!username || !password) {
        showFormMessage("registerMessage", "Please enter username and password.");
        return;
    }
    if (username.length < 3) {
        showFormMessage("registerMessage", "Username must contain at least 3 characters.");
        return;
    }
    if (password.length < 6) {
        showFormMessage("registerMessage", "Password must contain at least 6 characters.");
        return;
    }
    if (username.toLowerCase() === ADMIN_USERNAME.toLowerCase()) {
        showFormMessage("registerMessage", "This username is reserved.");
        return;
    }

    const users = getUsers();
    if (users.some(user => user.username.toLowerCase() === username.toLowerCase())) {
        showFormMessage("registerMessage", "This username already exists.");
        return;
    }

    users.push({ username, password, role: "user", createdAt: new Date().toISOString() });
    saveUsers(users);
    addActivityFor(username, "Account", "Created a new user account");

    showFormMessage("registerMessage", "Account created successfully. You can sign in now.", "success");
    document.getElementById("registerName").value = "";
    document.getElementById("registerPassword").value = "";
    showLoginForm();
}

function loginUser() {
    const username = document.getElementById("userLoginName")?.value.trim() || "";
    const password = document.getElementById("userLoginPassword")?.value.trim() || "";
    const foundUser = getUsers().find(user =>
        user.username.toLowerCase() === username.toLowerCase() && user.password === password
    );

    clearFormMessage("userLoginMessage");
    if (!username || !password) {
        showFormMessage("userLoginMessage", "Please enter username and password.");
        return;
    }
    if (!foundUser) {
        showFormMessage("userLoginMessage", "Invalid username or password.");
        return;
    }

    writeStorage(STORAGE_KEYS.currentUser, { username: foundUser.username, role: "user" });
    addActivityFor(foundUser.username, "Login", "Logged in to QR Motors");
    window.location.href = "home.html";
}

function loginAdmin() {
    const username = document.getElementById("adminName")?.value.trim() || "";
    const password = document.getElementById("adminPassword")?.value.trim() || "";

    clearFormMessage("adminLoginMessage");
    if (!username || !password) {
        showFormMessage("adminLoginMessage", "Please enter the admin username and password.");
        return;
    }
    if (username === ADMIN_USERNAME && password === ADMIN_PASSWORD) {
        writeStorage(STORAGE_KEYS.currentUser, { username: "Admin", role: "admin" });
        window.location.href = "admin.html";
    } else {
        showFormMessage("adminLoginMessage", "Wrong admin username or password.");
    }
}

function requireLogin(allowedRoles) {
    const currentUser = getCurrentUser();
    if (!currentUser) {
        window.location.href = "index.html";
        return false;
    }
    if (!allowedRoles.includes(currentUser.role)) {
        if (typeof window.qrToast === "function") window.qrToast("Access denied.", "error");
        window.location.href = currentUser.role === "admin" ? "admin.html" : "home.html";
        return false;
    }
    return true;
}

function setupNavbar() {
    const currentUser = getCurrentUser();
    if (!currentUser) return;

    const userNameBox = document.getElementById("loggedUserName");
    if (userNameBox) userNameBox.textContent = `${currentUser.username} (${currentUser.role})`;

    document.querySelectorAll(".admin-only").forEach(link => {
        link.style.display = currentUser.role === "admin" ? "" : "none";
    });
    document.querySelectorAll(".user-only").forEach(link => {
        link.style.display = currentUser.role === "user" ? "" : "none";
    });
}

function logout() {
    const currentUser = getCurrentUser();
    if (currentUser?.role === "user") {
        addActivityFor(currentUser.username, "Logout", "Logged out of QR Motors");
    }
    localStorage.removeItem(STORAGE_KEYS.currentUser);
    window.location.href = "index.html";
}

function addActivityFor(username, type, description, metadata = {}) {
    const activities = getActivities();
    activities.unshift({
        id: makeId("ACT"),
        username,
        type,
        description,
        metadata,
        createdAt: new Date().toISOString()
    });
    writeStorage(STORAGE_KEYS.activities, activities.slice(0, 1000));
}

function logActivity(type, description, metadata = {}) {
    const currentUser = getCurrentUser();
    if (currentUser?.role !== "user") return;
    addActivityFor(currentUser.username, type, description, metadata);
}

function logPageView() {
    const currentUser = getCurrentUser();
    if (currentUser?.role !== "user") return;
    const page = document.title || location.pathname.split("/").pop();
    const activities = getActivities();
    const last = activities.find(item => item.username === currentUser.username);
    const isDuplicate = last && last.type === "Page View" && last.description === `Viewed: ${page}` &&
        Date.now() - new Date(last.createdAt).getTime() < 2500;
    if (!isDuplicate) logActivity("Page View", `Viewed: ${page}`, { page: location.pathname });
}

function getCardData(card) {
    return {
        carName: card?.querySelector("h3")?.textContent.trim() || "Selected Car",
        price: card?.querySelector("h4")?.textContent.trim() || "Price on request",
        image: card?.querySelector("img[data-img]")?.getAttribute("data-img") || "lamborghini-aventador"
    };
}

function initializeCarCards() {
    document.querySelectorAll(".car-card").forEach(card => {
        const data = getCardData(card);
        const detailsLink = card.querySelector("a.details-btn");
        if (detailsLink && detailsLink.getAttribute("href")?.includes("details.html")) {
            const params = new URLSearchParams(data);
            detailsLink.href = `details.html?${params.toString()}`;
        }
        const favoriteButton = card.querySelector(".favorite-btn");
        if (favoriteButton) {
            favoriteButton.type = "button";
            favoriteButton.onclick = () => addFavorite(data);
        }
    });
}

function addFavorite(data) {
    const currentUser = getCurrentUser();
    if (currentUser?.role !== "user") {
        qrToast("Favorites are available for users only.");
        return;
    }
    const favorites = getFavorites();
    const exists = favorites.some(item => item.username === currentUser.username && item.carName === data.carName);
    if (exists) {
        qrToast("This car is already in your favorites.");
        return;
    }
    favorites.unshift({ id: makeId("FAV"), username: currentUser.username, ...data, createdAt: new Date().toISOString() });
    writeStorage(STORAGE_KEYS.favorites, favorites);
    logActivity("Favorite", `Added ${data.carName} to favorites`, data);
    qrToast("Car added to your favorites.");
}

function removeFavorite(id) {
    const currentUser = getCurrentUser();
    const favorites = getFavorites();
    const selected = favorites.find(item => item.id === id && item.username === currentUser?.username);
    writeStorage(STORAGE_KEYS.favorites, favorites.filter(item => !(item.id === id && item.username === currentUser?.username)));
    if (selected) logActivity("Favorite", `Removed ${selected.carName} from favorites`, selected);
    renderFavorites();
}

function renderFavorites() {
    const box = document.getElementById("favoritesList");
    if (!box) return;
    const currentUser = getCurrentUser();
    const favorites = getFavorites().filter(item => item.username === currentUser?.username);
    if (!favorites.length) {
        box.innerHTML = '<div class="empty-state"><h3>No favorite cars yet</h3><p>Add cars from the Cars page.</p></div>';
        return;
    }
    box.innerHTML = favorites.map(item => `
        <div class="car-card">
            <img data-img="${escapeHtml(item.image)}" alt="${escapeHtml(item.carName)}">
            <div class="car-info">
                <h3>${escapeHtml(item.carName)}</h3>
                <h4>${escapeHtml(item.price)}</h4>
                <a class="details-btn" href="details.html?${new URLSearchParams({carName:item.carName,price:item.price,image:item.image}).toString()}">View Details</a>
                <button class="remove-btn" onclick="removeFavorite('${escapeHtml(item.id)}')">Remove</button>
            </div>
        </div>
    `).join("");
    document.querySelectorAll("#favoritesList img[data-img]").forEach(loadFlexibleImage);
}

function loadDetailsFromQuery() {
    const title = document.getElementById("detailCarName");
    if (!title) return;
    const params = new URLSearchParams(location.search);
    const carName = params.get("carName") || "Lamborghini Aventador 2022";
    const price = params.get("price") || "1,250,000 NIS";
    const image = params.get("image") || "lamborghini-aventador";
    title.textContent = carName;
    document.getElementById("detailPrice").textContent = price;
    const img = document.getElementById("detailImage");
    img.setAttribute("data-img", image);
    loadFlexibleImage(img);
    const buyButton = document.getElementById("buyRequestButton");
    buyButton.onclick = () => submitPurchaseRequest({ carName, price, image, showroom: "QR Motors - Nablus" });
    const favoriteButton = document.getElementById("detailFavoriteButton");
    favoriteButton.onclick = () => addFavorite({ carName, price, image });
}

function submitPurchaseRequest(car) {
    const currentUser = getCurrentUser();
    if (currentUser?.role !== "user") {
        qrToast("Only users can submit purchase requests.");
        return;
    }
    const requests = getPurchaseRequests();
    const active = requests.some(item => item.username === currentUser.username && item.carName === car.carName && !["Rejected", "Completed"].includes(item.status));
    if (active) {
        qrToast("You already have an active request for this car.");
        return;
    }
    const request = {
        id: makeId("REQ"),
        username: currentUser.username,
        ...car,
        status: "Pending",
        requestedAt: new Date().toISOString(),
        appointmentDate: "",
        appointmentTime: "",
        adminNote: ""
    };
    requests.unshift(request);
    writeStorage(STORAGE_KEYS.purchaseRequests, requests);
    logActivity("Purchase Request", `Requested to buy ${car.carName}`, { requestId: request.id, carName: car.carName, price: car.price });
    qrToast("Your purchase request was sent to the admin. Check My Activity for updates.");
}

function statusClass(status) {
    if (status === "Rejected") return "status-rejected";
    if (status === "Appointment Scheduled" || status === "Approved") return "status-approved";
    return "status-pending";
}

function renderUserDashboard() {
    const root = document.getElementById("userDashboardRoot");
    if (!root) return;
    const currentUser = getCurrentUser();
    const activities = getActivities().filter(item => item.username === currentUser?.username);
    const requests = getPurchaseRequests().filter(item => item.username === currentUser?.username);
    const favorites = getFavorites().filter(item => item.username === currentUser?.username);

    document.getElementById("myActivityCount").textContent = activities.length;
    document.getElementById("myRequestCount").textContent = requests.length;
    document.getElementById("myFavoriteCount").textContent = favorites.length;
    document.getElementById("myAppointmentCount").textContent = requests.filter(item => item.status === "Appointment Scheduled").length;

    const requestBox = document.getElementById("myRequestsList");
    requestBox.innerHTML = requests.length ? requests.map(item => `
        <div class="request-card request-grid">
            <div>
                <h3>${escapeHtml(item.carName)}</h3>
                <p><b>Price:</b> ${escapeHtml(item.price)}</p>
                <p><b>Requested:</b> ${formatDate(item.requestedAt)}</p>
                <p><b>Status:</b> <span class="status-pill ${statusClass(item.status)}">${escapeHtml(item.status)}</span></p>
            </div>
            <div class="appointment-box">
                <h4>Showroom appointment</h4>
                <p><b>Date:</b> ${escapeHtml(item.appointmentDate || "Not assigned yet")}</p>
                <p><b>Time:</b> ${escapeHtml(item.appointmentTime || "Not assigned yet")}</p>
                <p><b>Admin note:</b> ${escapeHtml(item.adminNote || "—")}</p>
            </div>
        </div>
    `).join("") : '<p class="muted">You have not sent any purchase requests.</p>';

    const activityBox = document.getElementById("myActivitiesList");
    activityBox.innerHTML = activities.length ? `
        <table><thead><tr><th>Action</th><th>Details</th><th>Date</th></tr></thead><tbody>
        ${activities.map(item => `<tr><td>${escapeHtml(item.type)}</td><td>${escapeHtml(item.description)}</td><td>${formatDate(item.createdAt)}</td></tr>`).join("")}
        </tbody></table>` : '<p class="muted">No activity recorded yet.</p>';
}

function renderAdminDashboard() {
    const root = document.getElementById("adminDashboardRoot");
    if (!root) return;
    const users = getUsers();
    const activities = getActivities();
    const requests = getPurchaseRequests();
    const sellRequests = getSellRequests();
    const messages = getMessages();

    document.getElementById("adminUsersCount").textContent = users.length;
    document.getElementById("adminActivitiesCount").textContent = activities.length;
    document.getElementById("adminPendingCount").textContent = requests.filter(item => item.status === "Pending").length;
    document.getElementById("adminAppointmentsCount").textContent = requests.filter(item => item.status === "Appointment Scheduled").length;

    document.getElementById("registeredUsersList").innerHTML = users.length ? `
        <table><thead><tr><th>Username</th><th>Role</th><th>Created</th><th>Status</th></tr></thead><tbody>
        ${users.map(user => `<tr><td>${escapeHtml(user.username)}</td><td>${escapeHtml(user.role)}</td><td>${formatDate(user.createdAt)}</td><td><span class="approved">Active</span></td></tr>`).join("")}
        </tbody></table>` : '<p class="muted">No registered users yet.</p>';

    document.getElementById("purchaseRequestsList").innerHTML = requests.length ? requests.map(item => `
        <div class="request-card admin-request-card">
            <div class="request-summary">
                <h3>${escapeHtml(item.carName)}</h3>
                <p><b>User:</b> ${escapeHtml(item.username)}</p>
                <p><b>Price:</b> ${escapeHtml(item.price)}</p>
                <p><b>Sent:</b> ${formatDate(item.requestedAt)}</p>
                <p><b>Status:</b> <span class="status-pill ${statusClass(item.status)}">${escapeHtml(item.status)}</span></p>
            </div>
            <div class="schedule-panel">
                <label>Date<input type="date" id="date-${escapeHtml(item.id)}" value="${escapeHtml(item.appointmentDate)}"></label>
                <label>Time<input type="time" id="time-${escapeHtml(item.id)}" value="${escapeHtml(item.appointmentTime)}"></label>
                <label>Admin note<textarea id="note-${escapeHtml(item.id)}" placeholder="What should the user bring?">${escapeHtml(item.adminNote)}</textarea></label>
                <div class="button-row">
                    <button class="approve-btn" onclick="schedulePurchaseRequest('${escapeHtml(item.id)}')">Save Appointment</button>
                    <button class="edit-btn" onclick="approvePurchaseRequest('${escapeHtml(item.id)}')">Approve</button>
                    <button class="delete-btn" onclick="rejectPurchaseRequest('${escapeHtml(item.id)}')">Reject</button>
                </div>
            </div>
        </div>
    `).join("") : '<p class="muted">No purchase requests yet.</p>';

    document.getElementById("allActivitiesList").innerHTML = activities.length ? `
        <table><thead><tr><th>User</th><th>Type</th><th>Action</th><th>Date</th></tr></thead><tbody>
        ${activities.map(item => `<tr><td>${escapeHtml(item.username)}</td><td>${escapeHtml(item.type)}</td><td>${escapeHtml(item.description)}</td><td>${formatDate(item.createdAt)}</td></tr>`).join("")}
        </tbody></table>` : '<p class="muted">No user activity recorded yet.</p>';

    document.getElementById("sellRequestsList").innerHTML = sellRequests.length ? sellRequests.map(item => `
        <div class="request-card"><h3>${escapeHtml(item.carName)}</h3><p><b>User:</b> ${escapeHtml(item.username)}</p><p><b>Price:</b> ${escapeHtml(item.price)} NIS</p><p><b>Phone:</b> ${escapeHtml(item.phone)}</p><p><b>Submitted:</b> ${formatDate(item.createdAt)}</p></div>
    `).join("") : '<p class="muted">No sell-car requests yet.</p>';

    document.getElementById("messagesList").innerHTML = messages.length ? messages.map(item => `
        <div class="message-card"><h3>${escapeHtml(item.subject)}</h3><p><b>From:</b> ${escapeHtml(item.username)} (${escapeHtml(item.email)})</p><p>${escapeHtml(item.message)}</p><p class="muted">${formatDate(item.createdAt)}</p></div>
    `).join("") : '<p class="muted">No messages yet.</p>';
}

function updateRequest(id, changes) {
    const requests = getPurchaseRequests();
    const index = requests.findIndex(item => item.id === id);
    if (index < 0) return null;
    requests[index] = { ...requests[index], ...changes, updatedAt: new Date().toISOString() };
    writeStorage(STORAGE_KEYS.purchaseRequests, requests);
    return requests[index];
}

function schedulePurchaseRequest(id) {
    const date = document.getElementById(`date-${id}`)?.value || "";
    const time = document.getElementById(`time-${id}`)?.value || "";
    const note = document.getElementById(`note-${id}`)?.value.trim() || "";
    if (!date || !time) {
        qrToast("Please select both appointment date and time.");
        return;
    }
    const request = updateRequest(id, { status: "Appointment Scheduled", appointmentDate: date, appointmentTime: time, adminNote: note });
    if (request) addActivityFor(request.username, "Admin Update", `Admin scheduled a showroom appointment for ${request.carName} on ${date} at ${time}`, { requestId: id });
    renderAdminDashboard();
    qrToast("Appointment saved. The user can now see it in My Activity.");
}

function approvePurchaseRequest(id) {
    const request = updateRequest(id, { status: "Approved" });
    if (request) addActivityFor(request.username, "Admin Update", `Admin approved the purchase request for ${request.carName}`, { requestId: id });
    renderAdminDashboard();
}

function rejectPurchaseRequest(id) {
    const note = document.getElementById(`note-${id}`)?.value.trim() || "Request rejected by admin.";
    const request = updateRequest(id, { status: "Rejected", adminNote: note, appointmentDate: "", appointmentTime: "" });
    if (request) addActivityFor(request.username, "Admin Update", `Admin rejected the purchase request for ${request.carName}`, { requestId: id });
    renderAdminDashboard();
}

function submitSellCar(event) {
    event.preventDefault();
    const currentUser = getCurrentUser();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form).entries());
    if (!data.carName || !data.price || !data.phone) {
        qrToast("Please fill in car name, price, and phone number.");
        return;
    }
    const requests = getSellRequests();
    requests.unshift({ id: makeId("SELL"), username: currentUser.username, ...data, status: "Pending", createdAt: new Date().toISOString() });
    writeStorage(STORAGE_KEYS.sellRequests, requests);
    logActivity("Sell Request", `Submitted ${data.carName} for admin review`, { carName: data.carName, price: data.price });
    form.reset();
    qrToast("Your car was submitted to the admin for review.");
}

function submitContact(event) {
    event.preventDefault();
    const currentUser = getCurrentUser();
    const form = event.currentTarget;
    const data = Object.fromEntries(new FormData(form).entries());
    if (!data.email || !data.subject || !data.message) {
        qrToast("Please complete email, subject, and message.");
        return;
    }
    const messages = getMessages();
    messages.unshift({ id: makeId("MSG"), username: currentUser.username, ...data, createdAt: new Date().toISOString() });
    writeStorage(STORAGE_KEYS.messages, messages);
    logActivity("Contact Message", `Sent a message: ${data.subject}`);
    form.reset();
    qrToast("Your message was sent to the admin.");
}

function logNamedAction(type, description) {
    logActivity(type, description);
    alert(description);
}



const CAR_CATALOG = [
    {name:"Lamborghini Aventador 2022",brand:"Lamborghini",year:2022,category:"Luxury Sport",fuel:"Petrol",gear:"Automatic",mileage:"12,000 km",price:1250000,image:"lamborghini-aventador"},
    {name:"Ferrari 488 GTB 2020",brand:"Ferrari",year:2020,category:"Luxury Sport",fuel:"Petrol",gear:"Automatic",mileage:"18,000 km",price:980000,image:"ferrari-488"},
    {name:"Porsche 911 Carrera 2021",brand:"Porsche",year:2021,category:"Sport",fuel:"Petrol",gear:"Automatic",mileage:"25,000 km",price:620000,image:"porsche-911"},
    {name:"Audi RS7 2021",brand:"Audi",year:2021,category:"Luxury Sedan",fuel:"Petrol",gear:"Automatic",mileage:"30,000 km",price:430000,image:"audi-rs7"},
    {name:"Range Rover Sport 2020",brand:"Range Rover",year:2020,category:"Luxury SUV",fuel:"Diesel",gear:"Automatic",mileage:"55,000 km",price:360000,image:"range-rover-sport"},
    {name:"Tesla Model 3 2022",brand:"Tesla",year:2022,category:"Electric",fuel:"Electric",gear:"Automatic",mileage:"22,000 km",price:150000,image:"tesla-model3"},
    {name:"Nissan GT-R 2019",brand:"Nissan",year:2019,category:"Sport",fuel:"Petrol",gear:"Automatic",mileage:"40,000 km",price:390000,image:"nissan-gtr"},
    {name:"Chevrolet Camaro 2020",brand:"Chevrolet",year:2020,category:"Sport",fuel:"Petrol",gear:"Automatic",mileage:"45,000 km",price:210000,image:"chevrolet-camaro"},
    {name:"Ford Mustang GT 2021",brand:"Ford",year:2021,category:"Sport",fuel:"Petrol",gear:"Automatic",mileage:"35,000 km",price:260000,image:"ford-mustang"},
    {name:"Hyundai Tucson 2020",brand:"Hyundai",year:2020,category:"Family SUV",fuel:"Hybrid",gear:"Automatic",mileage:"70,000 km",price:85000,image:"hyundai-tucson"},
    {name:"Kia Sportage 2019",brand:"Kia",year:2019,category:"Family SUV",fuel:"Petrol",gear:"Automatic",mileage:"80,000 km",price:78000,image:"kia-sportage"},
    {name:"Toyota Corolla 2021",brand:"Toyota",year:2021,category:"Daily Car",fuel:"Hybrid",gear:"Automatic",mileage:"50,000 km",price:70000,image:"toyota-corolla"},
    {name:"BMW 320i 2018",brand:"BMW",year:2018,category:"Luxury Sedan",fuel:"Petrol",gear:"Automatic",mileage:"95,000 km",price:95000,image:"bmw-320i"},
    {name:"Mercedes C-Class 2020",brand:"Mercedes",year:2020,category:"Luxury Sedan",fuel:"Petrol",gear:"Automatic",mileage:"60,000 km",price:140000,image:"mercedes-cclass"},
    {name:"Volkswagen Golf 2017",brand:"Volkswagen",year:2017,category:"Daily Car",fuel:"Petrol",gear:"Manual",mileage:"110,000 km",price:62000,image:"volkswagen-golf"}
];

function showFormMessage(id, message, type = "error") {
    const element = document.getElementById(id);
    if (!element) return;
    element.textContent = message;
    element.className = `form-message ${type === "success" ? "success-message" : "error-message"}`;
}
function clearFormMessage(id) {
    const element = document.getElementById(id);
    if (!element) return;
    element.textContent = "";
    element.className = "form-message hidden";
}
function formatPrice(price) { return `${Number(price).toLocaleString("en-US")} NIS`; }
function catalogDetailsUrl(car) {
    return `details.html?${new URLSearchParams({carName:car.name,price:formatPrice(car.price),image:car.image}).toString()}`;
}
function getCatalogCar(name) { return CAR_CATALOG.find(car => car.name === name) || CAR_CATALOG[0]; }

let catalogPage = 1;
const CARS_PER_PAGE = 6;
function initializeCatalog() {
    const root = document.getElementById("carCatalog");
    if (!root) return;
    root.querySelectorAll(".car-card").forEach((card, index) => {
        const car = CAR_CATALOG[index];
        if (!car) return;
        card.dataset.name = car.name.toLowerCase();
        card.dataset.brand = car.brand.toLowerCase();
        card.dataset.category = car.category.toLowerCase();
        card.dataset.fuel = car.fuel.toLowerCase();
        card.dataset.price = String(car.price);
        card.dataset.year = String(car.year);
    });
    const params = new URLSearchParams(location.search);
    document.getElementById("carSearch").value = params.get("q") || "";
    document.getElementById("brandFilter").value = params.get("brand") || "";
    document.getElementById("categoryFilter").value = params.get("category") || params.get("type") || "";
    document.getElementById("fuelFilter").value = params.get("fuel") || "";
    ["carSearch","brandFilter","categoryFilter","fuelFilter","sortCars"].forEach(id => {
        document.getElementById(id)?.addEventListener(id === "carSearch" ? "input" : "change", () => { catalogPage = 1; applyCatalogFilters(); });
    });
    document.getElementById("resetFilters")?.addEventListener("click", () => {
        ["carSearch","brandFilter","categoryFilter","fuelFilter"].forEach(id => document.getElementById(id).value = "");
        document.getElementById("sortCars").value = "default";
        catalogPage = 1; applyCatalogFilters();
    });
    applyCatalogFilters();
}
function applyCatalogFilters() {
    const root = document.getElementById("carCatalog"); if (!root) return;
    const search = document.getElementById("carSearch").value.trim().toLowerCase();
    const brand = document.getElementById("brandFilter").value.toLowerCase();
    const category = document.getElementById("categoryFilter").value.toLowerCase();
    const fuel = document.getElementById("fuelFilter").value.toLowerCase();
    const sort = document.getElementById("sortCars").value;
    let cards = [...root.querySelectorAll(".car-card")].filter(card =>
        (!search || card.dataset.name.includes(search)) && (!brand || card.dataset.brand === brand) &&
        (!category || card.dataset.category.includes(category)) && (!fuel || card.dataset.fuel === fuel));
    const original = [...root.querySelectorAll(".car-card")];
    cards.sort((a,b) => sort === "price-asc" ? +a.dataset.price - +b.dataset.price : sort === "price-desc" ? +b.dataset.price - +a.dataset.price : sort === "year-desc" ? +b.dataset.year - +a.dataset.year : sort === "year-asc" ? +a.dataset.year - +b.dataset.year : sort === "name-asc" ? a.dataset.name.localeCompare(b.dataset.name) : original.indexOf(a)-original.indexOf(b));
    cards.forEach(card => root.appendChild(card));
    const pages = Math.max(1, Math.ceil(cards.length / CARS_PER_PAGE)); catalogPage = Math.min(catalogPage, pages);
    original.forEach(card => card.classList.add("catalog-hidden"));
    cards.slice((catalogPage-1)*CARS_PER_PAGE, catalogPage*CARS_PER_PAGE).forEach(card => card.classList.remove("catalog-hidden"));
    document.getElementById("catalogResultCount").textContent = `${cards.length} car${cards.length === 1 ? "" : "s"} found`;
    document.getElementById("catalogEmpty").classList.toggle("hidden", cards.length !== 0);
    renderCatalogPagination(pages, cards.length);
}
function renderCatalogPagination(pages, resultCount) {
    const box = document.getElementById("catalogPagination"); if (!box) return;
    if (pages <= 1 || !resultCount) { box.innerHTML = ""; return; }
    box.innerHTML = `<button type="button" ${catalogPage===1?"disabled":""} data-page="${catalogPage-1}">Previous</button>` +
        Array.from({length:pages},(_,i)=>`<button type="button" class="${catalogPage===i+1?"active":""}" data-page="${i+1}">${i+1}</button>`).join("") +
        `<button type="button" ${catalogPage===pages?"disabled":""} data-page="${catalogPage+1}">Next</button>`;
    box.querySelectorAll("button:not([disabled])").forEach(button => button.addEventListener("click", () => { catalogPage=+button.dataset.page; applyCatalogFilters(); document.querySelector(".page-title")?.scrollIntoView({behavior:"smooth"}); }));
}
function searchFromHome() {
    const q = document.getElementById("homeCarSearch")?.value.trim() || "";
    const category = document.getElementById("homeCarType")?.value || "";
    location.href = `cars.html?${new URLSearchParams({q,category}).toString()}`;
}

function exportInventory() {
    const rows = [
        ["Name", "Brand", "Year", "Category", "Fuel", "Transmission", "Mileage", "Price (NIS)"],
        ...CAR_CATALOG.map(car => [car.name, car.brand, car.year, car.category, car.fuel, car.gear, car.mileage, car.price])
    ];
    const csv = rows.map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    link.download = "qr-motors-inventory.csv";
    link.click();
    URL.revokeObjectURL(link.href);
    qrToast("Inventory exported successfully.");
}

function initializeCompare() {
    const first = document.getElementById("compareCarOne"); if (!first) return;
    const second = document.getElementById("compareCarTwo");
    const options = CAR_CATALOG.map(car => `<option value="${escapeHtml(car.name)}">${escapeHtml(car.name)}</option>`).join("");
    first.innerHTML=options; second.innerHTML=options; second.selectedIndex=1;
    document.getElementById("compareButton").addEventListener("click", renderComparison);
    renderComparison();
}
function renderComparison() {
    const one=getCatalogCar(document.getElementById("compareCarOne").value), two=getCatalogCar(document.getElementById("compareCarTwo").value);
    const error=document.getElementById("compareError");
    if(one.name===two.name){error.textContent="Please select two different cars.";error.classList.remove("hidden");return;} error.classList.add("hidden");
    const rows=[["Price",formatPrice(one.price),formatPrice(two.price)],["Category",one.category,two.category],["Fuel",one.fuel,two.fuel],["Transmission",one.gear,two.gear],["Mileage",one.mileage,two.mileage],["Year",one.year,two.year]];
    document.getElementById("compareResult").innerHTML=`<div class="compare-cards">${[one,two].map(c=>`<div class="compare-car-card"><img data-img="${c.image}" alt="${escapeHtml(c.name)}"><h2>${escapeHtml(c.name)}</h2><p>${escapeHtml(c.category)}</p><h3>${formatPrice(c.price)}</h3><a class="details-btn" href="${catalogDetailsUrl(c)}">View Details</a></div>`).join("")}</div><div class="table-scroll"><table class="compare-table"><thead><tr><th>Feature</th><th>${escapeHtml(one.name)}</th><th>${escapeHtml(two.name)}</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${r[0]}</td><td>${r[1]}</td><td>${r[2]}</td></tr>`).join("")}</tbody></table></div>`;
    document.querySelectorAll("#compareResult img[data-img]").forEach(loadFlexibleImage); logActivity("Compare",`Compared ${one.name} with ${two.name}`);
}
function enhanceDetails() {
    const title=document.getElementById("detailCarName"); if(!title)return;
    const car=getCatalogCar(title.textContent.trim());
    [["detailBrand",car.brand],["detailCategory",car.category],["detailFuel",car.fuel],["detailGear",car.gear],["detailMileage",car.mileage],["breadcrumbCar",car.name]].forEach(([id,v])=>{const el=document.getElementById(id);if(el)el.textContent=v;});
    const financePrice = document.getElementById("finPrice");
    if (financePrice) financePrice.value = car.price;
    const related=CAR_CATALOG.filter(c=>c.name!==car.name && (c.category.includes(car.category.split(" ")[0]) || c.fuel===car.fuel)).slice(0,3);
    const box=document.getElementById("relatedCars"); if(box){box.innerHTML=related.map(c=>`<div class="car-card"><img data-img="${c.image}" alt="${escapeHtml(c.name)}"><div class="car-info"><h3>${escapeHtml(c.name)}</h3><p>${escapeHtml(c.category)} | ${escapeHtml(c.fuel)}</p><h4>${formatPrice(c.price)}</h4><a class="details-btn" href="${catalogDetailsUrl(c)}">View Details</a></div></div>`).join("");box.querySelectorAll("img[data-img]").forEach(loadFlexibleImage);}
}
function initializeKeyboardForms(){document.querySelectorAll("#userLoginName,#userLoginPassword").forEach(el=>el.addEventListener("keydown",e=>{if(e.key==="Enter")loginUser();}));document.querySelectorAll("#registerName,#registerPassword").forEach(el=>el.addEventListener("keydown",e=>{if(e.key==="Enter")registerUser();}));document.querySelectorAll("#adminName,#adminPassword").forEach(el=>el.addEventListener("keydown",e=>{if(e.key==="Enter")loginAdmin();}));}
function initializePage() {
    logPageView(); initializeCarCards(); loadDetailsFromQuery(); renderFavorites(); renderUserDashboard(); renderAdminDashboard();
    initializeCatalog(); initializeCompare(); enhanceDetails(); initializeKeyboardForms();
}
