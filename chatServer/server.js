import dotenv from "dotenv";
dotenv.config();

import http from "node:http";
import { Server } from "socket.io";
import express from "express";
import { socketHandler } from "./socket/socketHandler.js";
import router from "./routes/userRouter.js";
import cookieParser from "cookie-parser";

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: process.env.CORS_ORIGIN,
    methods: ["GET", "POST"],
  },
});
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use("/", router);

socketHandler(io);

const port = process.env.PORT;
const host = process.env.HOST;
server.listen(port, host, () => {
  console.log(`server running on port ${port}`);
});
