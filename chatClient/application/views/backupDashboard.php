<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Document</title>
    <link rel="stylesheet" href="<?php echo base_url("assets/dashboard.css")?>">
</head>

<body>
    <header class="top-bar">
        <h2>Welcome, <?= $this->session->userdata("user"); ?></h2>
        <div class="buttons">
            <button id="createGroup" onclick="createGroupModal()">create group</button>
            <a class="logout-btn" href="<?=site_url("chatController/logout")?>">Logout</a>
        </div>
    </header>

    <div class="chat-layout">
        <aside class="users-panel">
            <h3>Users</h3>
            <ul id="usersList"></ul>
        </aside>



        <!-- CHAT AREA -->
        <section id="messageContainer" class="chat-panel hidden">
            <div class="chat-header">
                <p id="userInfo"></p>
            </div>

            <ul id="privatChat" class="chat-messages"></ul>

            <form id="privateMessageForm" class="chat-input">
                <input id="privateMessage" type="text" placeholder="Type a message..." required />
                <button type="submit">Send</button>
            </form>
        </section>
    </div>

    <div id="modalContainer">
        <div class="groupCreationModal">
            group name:
            <input id="groupName" type="text" placeholder="enter group name "><br>
            <h3>All Users</h3>
            <ul id="listForGroupCreation"></ul>

            <h3>Selected Members</h3>
            <div id="selectedMembersList">

            </div>

            <button  id="createGroupBtn">Create Group</button>
        </div>

    </div>


    <script src="http://127.0.0.1:4000/socket.io/socket.io.js"></script>
    <script>
    const socket = io("http://localhost:4000");
    let selectedUser = null;

    const currentUser = "<?= $this->session->userdata("user") ?>";
    const usersList = document.getElementById("usersList");
    const privateChat = document.getElementById("privatChat");
    const messageInput = document.getElementById("privateMessage");
    const messageContainer = document.getElementById("messageContainer");
    const privateMessageForm = document.getElementById("privateMessageForm");
    const createGroupBtn = document.getElementById("createGroupBtn");
    const listForGroupCreation = document.getElementById("listForGroupCreation");
    const selectedMembersList = document.getElementById("selectedMembersList");
    const allUsers = <?= json_encode($users); ?>;
    let selectedMembers = []; 

    function getCurrentTime12() {
        const now = new Date();

        let hours = now.getHours();
        let minutes = now.getMinutes();
        let ampm = hours >= 12 ? "PM" : "AM";

        hours = hours % 12 || 12;
        minutes = minutes.toString().padStart(2, "0");

        return `${hours}:${minutes} ${ampm}`;
    }

    function scrollToBottom() {
        privateChat.scrollTop = privateChat.scrollHeight;
    }

    function openModal() {
        document.getElementById("modalContainer").style.display = "flex";
    }




    function createGroupModal() {
        openModal();
      updateCreateGroupButton();
        allUsers.forEach((user) => {
            if(user.username === currentUser){
                return;
            }
            const li = document.createElement("li");
            li.innerText = user.username;
            if (selectedMembers.includes(user.username)) {
                li.classList.add("selected");
            }
            li.onclick = () => {
                if (selectedMembers.includes(user.username)) {
                    selectedMembers = selectedMembers.filter((member) => {
                        return member !== user.username;
                    })
                    li.classList.remove("selected");
                    renderSelectedMembers();

                } else {
                    selectedMembers.push(user.username);
                    renderSelectedMembers();
                    li.classList.add("selected");
                }
                console.log(selectedMembers);
                updateCreateGroupButton();
            }
            listForGroupCreation.appendChild(li);
        });
    }

    function updateCreateGroupButton() {
    createGroupBtn.disabled = selectedMembers.length === 0;
    }



    function renderSelectedMembers() {
        selectedMembersList.innerHTML = "";

        selectedMembers.forEach((member) => {
            const wrapper = document.createElement("span");
            wrapper.classList.add("selected-member");

            wrapper.innerHTML = `
            ${member}
            <span class="remove-member" onclick="removeMember('${member}')">×</span>
        `;

            selectedMembersList.appendChild(wrapper);
        });
    }

    function removeMember(member) {
        selectedMembers = selectedMembers.filter(m => m !== member);
        document.querySelectorAll("#listForGroupCreation li").forEach(li => {
            if (li.innerText === member) {
                li.classList.remove("selected");
            }
        });

        renderSelectedMembers();
        updateCreateGroupButton();
    }
// create group 
    createGroupBtn.addEventListener("click",()=>{
        const groupName = document.getElementById("groupName");
        const data = {
            admin:currentUser,
            groupName:groupName.value,
            members:selectedMembers,
            time:getCurrentTime12(),
        }
    socket.emit("createGroup",{data});
    });
    

    // ---------------- SOCKET CONNECT ----------------
    socket.on("connect", () => {
        socket.emit("registerUser", currentUser);
    });

    console.log(allUsers);
    socket.on("onlineUsers", (users) => {
        usersList.innerHTML = "";
        allUsers.forEach((user) => {

            if (user.username == currentUser) {
                return;
            }
            const li = document.createElement("li");
            if (users[user.username]) {
                li.innerHTML = `
                <span class="username">${user.username}</span>
                <span class="status-dot online"></span>
             `;
            } else {
                li.innerHTML = `
                <span class="username">${user.username}</span>
                <span class="status-dot offline"></span>
            `;
            }

            li.onclick = () => {
                selectedUser = user.username;
                document.querySelector("#userInfo").innerHTML = `${selectedUser}`;
                messageContainer.classList.remove("hidden")
                privateChat.innerHTML = ""
                socket.emit("getChatHistory", {
                    fromUser: currentUser,
                    toUser: selectedUser,
                })

            };
            usersList.appendChild(li);
        });

    });

    socket.on("chatHistory", (messages) => {
        privateChat.innerHTML = "";

        messages.forEach(msg => {
            const li = document.createElement("li");
            li.classList.add(
                msg.fromUser === currentUser ?
                "sent_message" :
                "received_message"
            );

            li.innerHTML = `
            <p>${msg.message}</p>
            <span class="time_text">${msg.time}</span>
        `;

            privateChat.appendChild(li);
        });

        scrollToBottom();
    });

    socket.on("groupMsg",(msg)=>{
        console.log(msg);
    })

    // ---------------- SEND MESSAGE ----------------
    privateMessageForm.addEventListener("submit", e => {
        e.preventDefault();

        socket.emit("sendPrivateMessage", {
            toUser: selectedUser,
            fromUser: currentUser,
            message: messageInput.value,
            time: getCurrentTime12()
        });


        const li = document.createElement("li");
        li.innerHTML =
            `<p>${messageInput.value}</p>
                                <span class="time_text"> ${getCurrentTime12()}</span>`
        li.classList.add("sent_message");
        privateChat.appendChild(li);
        scrollToBottom();

        messageInput.value = "";

    });


    socket.on("receivePrivateMessage", ({
        fromUser,
        message,
        time
    }) => {

        if (selectedUser === fromUser) {
            const li = document.createElement("li");
            li.innerHTML =
                `<p>${message}</p>
                <span class="time_text"> ${time}</span>`
            li.classList.add("received_message");

            privateChat.appendChild(li);
            scrollToBottom();
        }
    });
    
    </script>
</body>

</html>



RESET
* {
	margin: 0;
	padding: 0;
	box-sizing: border-box;
	font-family: Arial, Helvetica, sans-serif;
}

body {
	background: #f4f6f8;
	padding: 15px;
}

/* TOP BAR */
.top-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: #1e88e5;
	color: #fff;
	padding: 12px 20px;
	border-radius: 8px;
}

.logout-btn {
	background: #ff5252;
	color: white;
	padding: 6px 12px;
	border-radius: 6px;
	text-decoration: none;
	font-size: 14px;
}

.socket-id {
	margin: 10px 0;
	font-size: 14px;
	color: #555;
}

/* MAIN LAYOUT */
.chat-layout {
	display: flex;
	height: 75vh;
	gap: 15px;
}

/* USERS PANEL */
.users-panel {
	width: 25%;
	background: white;
	border-radius: 10px;
	padding: 10px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.users-panel h3 {
	margin-bottom: 10px;
}

/* USER LIST ITEM */
#usersList li {
	list-style: none;
	padding: 10px 12px;
	margin-bottom: 6px;
	border-radius: 6px;
	cursor: pointer;
	transition: background 0.2s;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

#usersList li:hover {
	background: #e3f2fd;
}

/* USERNAME */
.username {
	font-size: 15px;
	font-weight: 500;
}

/* STATUS DOT */
.status-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	display: inline-block;
	flex-shrink: 0;
}

.status-dot.online {
	background-color: green;
}

.status-dot.offline {
	background-color: red;
}

/* CHAT PANEL */
.chat-panel {
	width: 75%;
	background: white;
	border-radius: 10px;
	display: flex;
	flex-direction: column;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.hidden {
	display: none;
}

/* CHAT HEADER */
.chat-header {
	padding: 12px;
	background: #f1f1f1;
	border-bottom: 1px solid #ddd;
	font-weight: bold;
}

/* MESSAGES */
.chat-messages {
	flex: 1;
	padding: 15px;
	overflow-y: auto;
	list-style: none;
}

.chat-messages li {
	max-width: 65%;
	padding: 10px 14px;
	margin-bottom: 10px;
	border-radius: 12px;
	font-size: 14px;
	word-wrap: break-word;
	overflow-wrap: break-word;
}

.chat-messages li p {
	white-space: normal;
	word-break: break-word;
}

/* SENT */
.sent_message {
	background: #1e88e5;
	color: white;
	margin-left: auto;
	text-align: right;
	border-bottom-right-radius: 2px;
}

/* RECEIVED */
.received_message {
	background: #e0e0e0;
	color: #333;
	margin-right: auto;
	border-bottom-left-radius: 2px;
}

/* INPUT AREA */
.chat-input {
	display: flex;
	padding: 10px;
	border-top: 1px solid #ddd;
}

.chat-input input {
	flex: 1;
	padding: 10px;
	border-radius: 20px;
	border: 1px solid #ccc;
	outline: none;
}

.chat-input button {
	margin-left: 10px;
	padding: 10px 18px;
	border: none;
	border-radius: 20px;
	background: #1e88e5;
	color: white;
	cursor: pointer;
}

.time_text {
	font-size: 11px;
	opacity: 0.7;
}

/* ===========================
   GROUP CREATION MODAL
=========================== */

#modalContainer {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.4);
	display: none;
	align-items: center;
	justify-content: center;
	z-index: 1000;
}

/* MODAL BOX */
.groupCreationModal {
	background: #ffffff;
	width: 420px;
	max-height: 80vh;
	border-radius: 12px;
	padding: 16px 18px;
	box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
	display: flex;
	flex-direction: column;
	gap: 12px;
}

/* HEADINGS */
.groupCreationModal h3 {
	font-size: 16px;
	font-weight: 600;
	margin-bottom: 6px;
	color: #333;
}

/* USERS LIST */
#listForGroupCreation,
#selectedMembersList {
	list-style: none;
	padding: 0;
	margin: 0;
	border: 1px solid #ddd;
	border-radius: 8px;
	overflow-y: auto;
}

/* LIMIT HEIGHT */
#listForGroupCreation {
	max-height: 180px;
}

#selectedMembersList {
	max-height: 120px;
	background: #f9f9f9;
}

/* LIST ITEMS */
#listForGroupCreation li,
#selectedMembersList li {
	padding: 10px 12px;
	cursor: pointer;
	font-size: 14px;
	border-bottom: 1px solid #eee;
	transition: background 0.2s, color 0.2s;
}

/* REMOVE BORDER FROM LAST */
#listForGroupCreation li:last-child,
#selectedMembersList li:last-child {
	border-bottom: none;
}

/* HOVER — ONLY WHEN NOT SELECTED */
#listForGroupCreation li:hover:not(.selected) {
	background: #e3f2fd;
}

/* SELECTED USER */
#listForGroupCreation li.selected {
	background-color: #2e7d32;
	color: white;
}

/* SELECTED MEMBERS LIST */
#selectedMembersList li {
	background: #e3f2fd;
	color: #1e88e5;
	border-radius: 6px;
	margin: 6px;
	text-align: center;
}

/* CREATE BUTTON */
.groupCreationModal button {
	margin-top: auto;
	padding: 10px;
	border: none;
	border-radius: 20px;
	background: #1e88e5;
	color: white;
	font-size: 14px;
	cursor: pointer;
	transition: background 0.2s;
}

.groupCreationModal button:hover {
	background: #1565c0;
}

#deleteIcon:hover {
	background: red;
}
/* Selected member pill */
.selected-member {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	background: #e3f2fd;
	color: #1e88e5;
	padding: 6px 10px;
	border-radius: 14px;
	margin: 4px;
	font-size: 13px;
}

/* Remove (X) icon */
.remove-member {
	cursor: pointer;
	font-weight: bold;
	color: #ff5252;
}

.remove-member:hover {
	color: #d32f2f;
}
button:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}
