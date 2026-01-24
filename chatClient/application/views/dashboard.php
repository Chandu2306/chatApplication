<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Chat Dashboard</title>
    <link rel="stylesheet" href="<?= base_url("assets/dashboard.css") ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="top-bar">
    <h2>Welcome, <?= htmlspecialchars($currentUser ?? "User") ?></h2>

    <div class="buttons">
        <button onclick="createGroupModal()">Create Group</button>
        <a class="logout-btn" href="<?= site_url("chatController/logout") ?>">Logout</a>
    </div>
</header>

<!-- ================= MAIN LAYOUT ================= -->
<div class="chat-layout">

    <!-- ========== SIDEBAR ========== -->
    <aside class="users-panel">

        <h3>Users</h3>
        <ul id="usersList"></ul>

        <h3 class="group-heading">Groups</h3>
        <ul id="groupsList"></ul>

    </aside>

    <!-- ========== CHAT AREA ========== -->
    <section id="messageContainer" class="chat-panel hidden">

        <div class="chat-header">
            <p id="userInfo"></p>
        </div>

        <ul id="chatbox" class="chat-messages"></ul>

        
<form id="privateMessageForm" class="chat-input">
    <input
        id="privateMessage"
        type="text"
        placeholder="Type a message..."
        autocomplete="off"
    />
    <button type="submit">Send</button>
</form>



    </section>
</div>

<!-- ================= GROUP MODAL ================= -->
<div id="modalContainer" style="display:none;">
    <div class="groupCreationModal">

        <h3>Create Group</h3>

        <input
            id="groupName"
            type="text"
            placeholder="Enter group name"
            autocomplete="off"
        />

        <h4>All Users</h4>
        <ul id="listForGroupCreation"></ul>

        <h4>Selected Members</h4>
        <div id="selectedMembersList"></div>

        <button id="createGroupBtn">Create Group</button>
        <button id="closeGroupModal" onclick="closeGroupModal()">cancel</button>

    </div>
</div>
<script src="http://localhost:4000/socket.io/socket.io.js"></script>
<script>
const socket = io("http://localhost:4000");


let selectedUser = null;
let activeGroup = null;
let selectedMembers = [];
let isTyping = false;

// dom elements
const currentUser = "<?= htmlspecialchars($currentUser ?? '') ?>";
const usersList = document.getElementById("usersList");
const groupsList = document.getElementById("groupsList");
const chatbox = document.getElementById("chatbox");
const messageInput = document.getElementById("privateMessage");
const mediaFile = document.getElementById("mediaFile");
const messageContainer = document.getElementById("messageContainer");
const privateMessageForm = document.getElementById("privateMessageForm");
const createGroupBtn = document.getElementById("createGroupBtn");
const listForGroupCreation = document.getElementById("listForGroupCreation");
const selectedMembersList = document.getElementById("selectedMembersList");
const userInfo = document.getElementById("userInfo");
const typingUsers = new Set();

const typingIndicator = document.createElement("p");
typingIndicator.className = "typing-indicator";
document.querySelector(".chat-header").appendChild(typingIndicator);

const allUsers = <?= json_encode($users); ?>;
console.log("uers",allUsers);

function getCurrentTime12() {
  const d = new Date();
  let h = d.getHours(), m = d.getMinutes();
  const ampm = h >= 12 ? "PM" : "AM";
  h = h % 12 || 12;
  return `${h}:${m.toString().padStart(2, "0")} ${ampm}`;
}

function scrollToBottom() {
  chatbox.scrollTo({
    top: chatbox.scrollHeight,
    behavior: "smooth"
  });
}

createGroupBtn.addEventListener("click", () => {
  const groupName = document.getElementById("groupName").value.trim();

  if (!groupName) {
    alert("Group name required");
    return;
  }

  if (selectedMembers.length === 0) {
    alert("Select at least one member");
    return;
  }

  socket.emit("createGroup", {
    data: {
      admin: currentUser,
      groupName,
      members: selectedMembers,
      time: new Date()
    }
  });

  document.getElementById("modalContainer").style.display = "none";
  selectedMembers = [];
  selectedMembersList.innerHTML = "";
});

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
function createGroupModal() {
  document.getElementById("modalContainer").style.display = "flex";
  renderGroupUserList();
}
function closeGroupModal(){
    document.getElementById("modalContainer").style.display = "none";
      selectedMembers = [];
  selectedMembersList.innerHTML = "";
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





socket.on("connect", () => {
  socket.emit("registerUser", currentUser);
  socket.emit("getMyGroups", currentUser);
});


socket.on("onlineUsers", users => {
  usersList.innerHTML = "";

  allUsers.forEach(u => {
    if (u.username === currentUser) return;

    const li = document.createElement("li");
    li.innerHTML = `
      ${u.username}
      <span class="status-dot ${users[u.username] ? "online" : "offline"}"></span>
    `;

    li.onclick = () => {
      selectedUser = u.username;
      activeGroup = null;
      userInfo.innerText = selectedUser;
      messageContainer.classList.remove("hidden");
      chatbox.innerHTML = "";
      typingIndicator.innerText = "";
      isTyping = false;
      typingUsers.clear();

      socket.emit("getChatHistory", {
        fromUser: currentUser,
        toUser: selectedUser
      });
    };

    usersList.appendChild(li);
  });
});

socket.on("myGroups", groups => {
  groupsList.innerHTML = "";
  groups.forEach(g => addGroup(g.groupName));
});

socket.on("joinGroup", groupName => addGroup(groupName));

function addGroup(groupName) {
  if (document.getElementById(`group-${groupName}`)) return;

  const li = document.createElement("li");
  li.id = `group-${groupName}`;
 li.innerHTML = ` ${groupName} <i class="fa-solid fa-users group-icon"></i> `;


  li.onclick = () => {
    activeGroup = groupName;
    selectedUser = null;
    userInfo.innerText = `Group: ${groupName}`;
    messageContainer.classList.remove("hidden");
    chatbox.innerHTML = "";
    typingIndicator.innerText = "";
    isTyping = false;

    socket.emit("getGroupHistory", groupName);
  };

  groupsList.appendChild(li);
}

socket.on("chatHistory", msgs => {
  chatbox.innerHTML = "";
  msgs.forEach(renderMessage);
  scrollToBottom();
});

socket.on("groupHistory", msgs => {
  chatbox.innerHTML = "";
  msgs.forEach(renderMessage);
  scrollToBottom();
});

socket.on("receivePrivateMessage", m => {
  if (m.fromUser === selectedUser) renderMessage(m);
});

socket.on("groupMsg", m => {
  if (m.groupName === activeGroup) renderMessage(m);
});

function renderMessage(m) {
  const li = document.createElement("li");

  if (m.isGroup && m.fromUser === currentUser) {
    li.innerHTML = `<span class="msgFrom">You</span>
                    <p>${m.message}</p>
                    <span class="time_text">${m.time}</span>`;
  } 
  else if (m.isGroup) {
    li.innerHTML = `<span class="msgFrom">${m.fromUser}</span>
                    <p>${m.message}</p>
                    <span class="time_text">${m.time}</span>`;
  } 
  else {
    li.innerHTML = `<p>${m.message}</p>
                    <span class="time_text">${m.time}</span>`;
  }

  li.className =
    m.fromUser === currentUser ? "sent_message" : "received_message";

  chatbox.appendChild(li);
  scrollToBottom();
}


privateMessageForm.onsubmit = e => {
  e.preventDefault();

  const msg = messageInput.value.trim();
  if (!msg) return;

  const time = getCurrentTime12();

  const messageData = {
    fromUser: currentUser,
    message: msg,
    time,
    isGroup: !!activeGroup,
    groupName: activeGroup
  };

  renderMessage(messageData);

  if (activeGroup) {
    socket.emit("sendGroupMessage", messageData);
  } else if (selectedUser) {
    socket.emit("sendPrivateMessage", {
      toUser: selectedUser,
      ...messageData
    });
  }

  messageInput.value = "";
  typingIndicator.innerText = "";
  typingUsers.clear();
  isTyping = false;
};


/* ================= TYPING ================= */
messageInput.addEventListener("input", () => {
  if (!selectedUser && !activeGroup) return;

  const value = messageInput.value.trim();

  if (value.length > 0 && !isTyping) {
    isTyping = true;
    socket.emit("typing", {
      fromUser: currentUser,
      toUser: selectedUser,
      groupName: activeGroup
    });
  }

  if (value.length === 0 && isTyping) {
    isTyping = false;
    socket.emit("stopTyping", {
      fromUser: currentUser,
      toUser: selectedUser,
      groupName: activeGroup
    });                                                                                                                
  }
});

socket.on("typing", ({ fromUser, groupName }) => {
  if (groupName && groupName === activeGroup) {
    typingUsers.add(fromUser);
    updateTypingIndicator();
    return;
  }

  if (!groupName && fromUser === selectedUser) {
    typingIndicator.innerText = "1 person is typing...";
  }
});

socket.on("stopTyping", ({ fromUser }) => {
  typingUsers.delete(fromUser);
  updateTypingIndicator();
});

function updateTypingIndicator() {
  const count = typingUsers.size;
  typingIndicator.innerText =
    count === 0 ? "" : count === 1 ? "1 person is typing..." : `${count} people are typing...`;
}
</script>

</body>
</html>
