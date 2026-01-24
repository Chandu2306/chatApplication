import jwt from "jsonwebtoken";
import dotenv from "dotenv";
dotenv.config();
const SECRET = process.env.SECRET;

export const authMiddleware = (req, res, next) => {
  console.log("HEADERS:", req.headers); // 👈 TEMP DEBUG

  const authHeader = req.headers.authorization;

  if (!authHeader || !authHeader.startsWith("Bearer ")) {
    console.log("NO AUTH HEADER");
    return res.status(401).json({ success: false });
  }

  const token = authHeader.split(" ")[1];

  try {
    const decoded = jwt.verify(token, SECRET);
    req.user = decoded;
    next();
  } catch (err) {
    console.log("JWT ERROR:", err.message);
    return res.status(401).json({ success: false });
  }
};
