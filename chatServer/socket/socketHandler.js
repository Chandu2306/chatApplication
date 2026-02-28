import { connectDb } from "../db/connectDb.js";

const db = await connectDb();

const messagesCol = db.collection("messages");
const groupsCol = db.collection("groups");
const groupMessagesCol = db.collection("groupMessages");

export const socketHandler = (io) => {
  const onlineUsers = {};
// connection
  io.on("connection", (socket) => {
    socket.on("registerUser", async (username) => {
      onlineUsers[username] = socket.id;

      const groups = await groupsCol.find({ members: username }).toArray();
      groups.forEach((g) => socket.join(g.groupName));

      io.emit("onlineUsers", onlineUsers);
    });

    // disconnect
    socket.on("disconnect", () => {
      for (const user in onlineUsers) {
        if (onlineUsers[user] === socket.id) {
          delete onlineUsers[user];
        }
      }
      io.emit("onlineUsers", onlineUsers);
    });

    // prviate message
    socket.on("sendPrivateMessage", async (data) => {
      const { toUser, fromUser, message = "", file = null, time } = data;

      const msg = {
        fromUser,
        toUser,
        message,
        file,
        time,
        createdAt: new Date(),
      };

      await messagesCol.insertOne(msg);

      if (onlineUsers[toUser]) {
        io.to(onlineUsers[toUser]).emit("receivePrivateMessage", msg);
      }
    });

    // private chat history
    socket.on("getChatHistory", async ({ fromUser, toUser }) => {
      const chats = await messagesCol
        .find({
          $or: [
            { fromUser, toUser },
            { fromUser: toUser, toUser: fromUser },
          ],
        })
        .sort({ createdAt: 1 })
        .toArray();

      socket.emit("chatHistory", chats);
    });

    // group creation
    socket.on("createGroup", async ({ data }) => {
      const { admin, groupName, members, time } = data;
      const finalMembers = [...new Set([admin, ...members])];

      await groupsCol.insertOne({
        groupName,
        admin,
        members: finalMembers,
        createdAt: time,
      });

      finalMembers.forEach((member) => {
        const sid = onlineUsers[member];
        if (sid) {
          io.to(sid).emit("joinGroup", groupName);
          io.sockets.sockets.get(sid)?.join(groupName);
        }
      });
    });

    // get user groups
    socket.on("getMyGroups", async (username) => {
      const groups = await groupsCol.find({ members: username }).toArray();
      socket.emit("myGroups", groups);
    });

    // group message
    socket.on("sendGroupMessage", async (data) => {
      const { groupName, fromUser, message = "", file = null, time } = data;

      const msg = {
        isGroup: true,
        groupName,
        fromUser,
        message,
        file,
        time,
        createdAt: new Date(),
      };

      await groupMessagesCol.insertOne(msg);
      io.to(groupName).emit("groupMsg", msg);
    });

    // group chat history
    socket.on("getGroupHistory", async (groupName) => {
      const msgs = await groupMessagesCol
        .find({ groupName })
        .sort({ createdAt: 1 })
        .toArray();

      socket.emit("groupHistory", msgs);
    });

    // typing indicator
    socket.on("typing", ({ fromUser, toUser, groupName }) => {
      if (groupName) {
        socket.to(groupName).emit("typing", { fromUser, groupName });
      } else if (toUser && onlineUsers[toUser]) {
        io.to(onlineUsers[toUser]).emit("typing", { fromUser });
      }
    });

    socket.on("stopTyping", ({ fromUser, toUser, groupName }) => {
      if (groupName) {
        socket.to(groupName).emit("stopTyping", { fromUser });
      } else if (toUser && onlineUsers[toUser]) {
        io.to(onlineUsers[toUser]).emit("stopTyping", { fromUser });
      }
    });
  });
};
