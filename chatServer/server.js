import dotenv from "dotenv";
import http from "node:http";
import express from "express";
import { Server } from "socket.io";
import { socketHandler } from "./socket/socketHandler.js";
import { fileURLToPath } from "url";
import router from "./routes/userRouter.js";
import cookieParser from "cookie-parser";
import uploadRoute from "./routes/upload.js";
import path from "path";
import cors from "cors";
dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const app = express();
const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: process.env.CORS_ORIGIN,
    methods: ["GET", "POST"],
  },
});
app.use(
  cors({
    origin: "*",
    credentials: true,
    methods: ["GET", "POST", "PUT", "DELETE"],
  }),
);
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use("/uploads", express.static(path.join(__dirname, "uploads")));
app.use("/", router);
app.use("/upload", uploadRoute);
socketHandler(io);

const port = process.env.PORT;
const host = process.env.HOST;
server.listen(port, host, () => {
  console.log(`server running on port ${port}`);
});
