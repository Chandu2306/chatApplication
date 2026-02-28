<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Chat Dashboard</title>
    <link rel="stylesheet" href="<?= base_url("assets/dashboard.css") ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>


    <header class="top-bar">
        <h2>Welcome, <?= htmlspecialchars($currentUser ?? "User") ?></h2>

        <div class="buttons">
            <button onclick="createGroupModal()">Create Group</button>
            <a class="logout-btn" href="<?= site_url("chatController/logout") ?>">Logout</a>
        </div>
    </header>

    <!-- users list -->
    <div class="chat-layout">
        <aside class="users-panel">
            <input type="search" id="searchChats" placeholder="Search chats...">

            <h3>Chats</h3>
            <ul id="chatsList"></ul>
        </aside>


        <section id="messageContainer" class="chat-panel hidden">

            <div class="chat-header">
                <p id="userInfo"></p>
                <p id="typingIndicator" class="typing-indicator"></p>
            </div>

            <ul id="chatbox" class="chat-messages"></ul>
            <form id="privateMessageForm" class="chat-input">
                <label class="file-btn">
  <i class="fa-solid fa-paperclip"></i>
  <input type="file" id="fileInput" hidden />
</label>


                <input id="privateMessage" type="text" placeholder="Type a message..." autocomplete="off" />
                <button type="submit">Send</button>
            </form>



        </section>
    </div>

    <!-- group modal -->
    <div id="modalContainer" class="modal-overlay">
  <div class="groupCreationModal">

    <h3>Create Group</h3>

    <input id="groupName" type="text" placeholder="Enter group name" autocomplete="off" />

    <div class="users-section">
      <h4>All Users</h4>
      <ul id="listForGroupCreation" class="users-list"></ul>
    </div>

    <div class="selected-section">
      <h4>Selected Members</h4>
      <div id="selectedMembersList" class="selected-members"></div>
    </div>

    <div class="modal-buttons">
      <button id="createGroupBtn" class="primary-btn">Create Group</button>
      <button onclick="closeGroupModal()" class="secondary-btn">Cancel</button>
    </div>

  </div>
</div>
</div>
<script src="http://localhost:4000/socket.io/socket.io.js"></script>
<script>
const socket = io("http://localhost:4000");

let selectedUser = null;
let activeGroup = null;
let selectedMembers = [];
let selectedFile = null;

let usersList = [];
let groupsList = [];

const currentUser = "<?= htmlspecialchars($currentUser ?? '') ?>";
const chatsList = document.getElementById("chatsList");
const chatbox = document.getElementById("chatbox");
const messageInput = document.getElementById("privateMessage");
const messageContainer = document.getElementById("messageContainer");
const privateMessageForm = document.getElementById("privateMessageForm");
const createGroupBtn = document.getElementById("createGroupBtn");
const listForGroupCreation = document.getElementById("listForGroupCreation");
const selectedMembersList = document.getElementById("selectedMembersList");
const userInfo = document.getElementById("userInfo");
const fileInput = document.getElementById("fileInput");
const searchInput = document.getElementById("searchChats");
const typingIndicator = document.getElementById("typingIndicator");
const typingUsers = new Set();
const allUsers = <?= json_encode($users); ?>;

let typingTimeout;

/* ================= CONNECT ================= */
socket.on("connect", () => {
    socket.emit("registerUser", currentUser);
    socket.emit("getMyGroups", currentUser);
});

/* ================= ONLINE USERS ================= */
socket.on("onlineUsers", users => {

    usersList = [];

    allUsers.forEach(u => {
        if (u.username === currentUser) return;

        usersList.push({
            type: "user",
            name: u.username,
            online: !!users[u.username]
        });
    });

    renderCombinedChats();
});

/* ================= GROUPS ================= */
socket.on("myGroups", groups => {

    groupsList = [];

    groups.forEach(g => {
        groupsList.push({
            type: "group",
            name: g.groupName
        });
    });

    renderCombinedChats();
});

/* ================= JOIN GROUP REALTIME ================= */
socket.on("joinGroup", groupName => {

    if (groupsList.some(g => g.name === groupName)) return;

    groupsList.push({
        type: "group",
        name: groupName
    });

    renderCombinedChats();
});

/* ================= CHAT HISTORY ================= */
socket.on("chatHistory", messages => {
    chatbox.innerHTML = "";
    messages.forEach(m => renderMessage(m));
});

socket.on("groupHistory", messages => {
    chatbox.innerHTML = "";
    messages.forEach(m => renderMessage(m));
});

/* ================= RENDER CHATS ================= */
function renderCombinedChats() {

    chatsList.innerHTML = "";

    // USERS FIRST, GROUPS AFTER
    const ordered = [...usersList, ...groupsList];

    ordered.forEach(chat => {

        const li = document.createElement("li");
        li.dataset.name = chat.name.toLowerCase();
        li.dataset.type = chat.type;

        if (chat.type === "user") {

            li.innerHTML = `
                <span>${chat.name}</span>
                <span class="status-dot ${chat.online ? "online" : "offline"}"></span>
            `;

            li.onclick = () => {
                selectedUser = chat.name;
                activeGroup = null;
                openChat(chat.name, false);
                socket.emit("getChatHistory", {
                    fromUser: currentUser,
                    toUser: chat.name
                });
            };
        }

        if (chat.type === "group") {

            li.innerHTML = `
                <span>${chat.name}</span>
                <i class="fa-solid fa-users group-icon"></i>
            `;

            li.onclick = () => {
                activeGroup = chat.name;
                selectedUser = null;
                openChat(chat.name, true);
                socket.emit("getGroupHistory", chat.name);
            };
        }

        chatsList.appendChild(li);
    });
}

/* ================= OPEN CHAT ================= */
function openChat(name, isGroup) {

    messageContainer.classList.remove("hidden");
    chatbox.innerHTML = "";
    messageInput.value = "";
    fileInput.value = "";
    selectedFile = null;

    userInfo.innerText = isGroup ? `Group: ${name}` : name;
    typingUsers.clear();
typingIndicator.innerText = "";
}

/* ================= SEARCH FILTER ================= */
searchInput.addEventListener("input", () => {

    const value = searchInput.value.toLowerCase();

    const items = chatsList.querySelectorAll("li");

    items.forEach(li => {
        const name = li.dataset.name;
        li.style.display = name.includes(value) ? "flex" : "none";
    });
});

/* ================= CREATE GROUP ================= */
createGroupBtn.addEventListener("click", () => {

    const groupName = document.getElementById("groupName").value.trim();

    if (!groupName) return alert("Group name required");
    if (selectedMembers.length === 0) return alert("Select members");

    socket.emit("createGroup", {
        data: {
            admin: currentUser,
            groupName,
            members: selectedMembers,
            time: new Date()
        }
    });

    closeGroupModal();
});

/* ================= GROUP MODAL ================= */
function renderGroupUserList() {

    listForGroupCreation.innerHTML = "";

    allUsers.forEach(u => {

        if (u.username === currentUser) return;

        const li = document.createElement("li");
        li.innerText = u.username;

        li.onclick = () => {

            if (selectedMembers.includes(u.username)) return;

            selectedMembers.push(u.username);
            updateSelectedMembers();
        };

        listForGroupCreation.appendChild(li);
    });
}

function updateSelectedMembers() {

    selectedMembersList.innerHTML = "";

    selectedMembers.forEach(member => {

        const span = document.createElement("span");
        span.className = "selected-member";
        span.innerText = member;

        span.onclick = () => {
            selectedMembers = selectedMembers.filter(m => m !== member);
            updateSelectedMembers();
        };

        selectedMembersList.appendChild(span);
    });
}

function createGroupModal() {
    document.getElementById("modalContainer").style.display = "flex";
    renderGroupUserList();
}

function closeGroupModal() {
    document.getElementById("modalContainer").style.display = "none";
    selectedMembers = [];
    selectedMembersList.innerHTML = "";
    document.getElementById("groupName").value = "";
}

/* ================= SEND MESSAGE ================= */
privateMessageForm.onsubmit = e => {

    e.preventDefault();

    const msg = messageInput.value.trim();
    if (!msg && !selectedFile) return;

    const time = getCurrentTime12();

    if (selectedFile) {

        const form = new FormData();
        form.append("file", selectedFile);

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "http://localhost:4000/upload");

        xhr.onload = () => {
            const res = JSON.parse(xhr.responseText);
            if (!res.success) return;

            sendMessage(msg, time, res.file);
        };

        xhr.send(form);

    } else {
        sendMessage(msg, time, null);
    }

    messageInput.value = "";
    fileInput.value = "";
    selectedFile = null;
};
messageInput.addEventListener("input", () => {

    if (!selectedUser && !activeGroup) return;

    socket.emit("typing", {
        fromUser: currentUser,
        toUser: selectedUser,
        groupName: activeGroup
    });

    clearTimeout(typingTimeout);

    typingTimeout = setTimeout(() => {
        socket.emit("stopTyping", {
            fromUser: currentUser,
            toUser: selectedUser,
            groupName: activeGroup
        });
    }, 1000);
});

socket.on("typing", data => {

    if (data.fromUser === currentUser) return;

    if (activeGroup) {
        if (data.groupName !== activeGroup) return;
    } else {
        if (data.fromUser !== selectedUser) return;
    }

    typingUsers.add(data.fromUser);
    updateTypingIndicator();
});

socket.on("stopTyping", data => {

    typingUsers.delete(data.fromUser);
    updateTypingIndicator();
});

function updateTypingIndicator() {

    if (typingUsers.size === 0) {
        typingIndicator.innerText = "";
        return;
    }

    const names = Array.from(typingUsers);

    if (names.length === 1) {
        typingIndicator.innerText = `${names[0]} is typing...`;
    } else {
        typingIndicator.innerText = `${names.join(", ")} are typing...`;
    }
}
function sendMessage(msg, time, file) {

    const messageData = {
        fromUser: currentUser,
        message: msg,
        time,
        file,
        groupName: activeGroup
    };

    renderMessage(messageData);

    if (activeGroup) {
        socket.emit("sendGroupMessage", messageData);
    } else {
        socket.emit("sendPrivateMessage", {
            toUser: selectedUser,
            ...messageData
        });
    }
}

/* ================= RENDER MESSAGE ================= */
function renderMessage(m) {

    const li = document.createElement("li");
    let content = "";

    if (m.message) content += `<p>${m.message}</p>`;

    if (m.file) {
        if (m.file.type.startsWith("image")) {
            content += `<img src="http://localhost:4000${m.file.url}" />`;
        } else {
            content += `
                <a class="file-attachment" href="http://localhost:4000${m.file.url}" download>
                    <i class="fa-solid fa-paperclip"></i>
                    <span>${m.file.name}</span>
                </a>
            `;
        }
    }

    li.innerHTML = `${content}<span class="time_text">${m.time}</span>`;
    li.className = m.fromUser === currentUser ? "sent_message" : "received_message";

    chatbox.appendChild(li);
    chatbox.scrollTop = chatbox.scrollHeight;
}

/* ================= FILE ================= */
fileInput.addEventListener("change", () => {
    selectedFile = fileInput.files[0] || null;
});

/* ================= TIME ================= */
function getCurrentTime12() {
    const d = new Date();
    let h = d.getHours();
    let m = d.getMinutes();
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12 || 12;
    return `${h}:${m.toString().padStart(2, "0")} ${ampm}`;
}
</script>