import { connectDb } from "../db/connectDb.js";
import bcrypt from "bcrypt";
import jwt from "jsonwebtoken";

const db = await connectDb();
const SECRET = "SECRET_KEY";

export const register = async (req, res) => {
  try {
    const { username, password } = req.body;

    const exists = await db.collection("users").findOne({ username });
    if (exists) {
      return res.json({
        success: false,
        message: "Username already exists,try different",
      });
    }

    const hashed = await bcrypt.hash(password, 10);
    await db.collection("users").insertOne({ username, password: hashed });

    res.json({ success: true });
  } catch {
    res.json({ success: false });
  }
};

export const login = async (req, res) => {
  const { username, password } = req.body;

  const user = await db.collection("users").findOne({ username });
  if (!user || !(await bcrypt.compare(password, user.password))) {
    return res.json({ success: false, message: "wrong username or password" });
  }

  const token = jwt.sign({ id: user._id, username: user.username }, SECRET, {
    expiresIn: "1h",
  });

  res.json({
    success: true,
    token,
  });
};

export const dashboard = async (req, res) => {
  console.log("USER FROM TOKEN:", req.user);
  const users = await db.collection("users").find().toArray();
  res.json({ success: true, users });
};
