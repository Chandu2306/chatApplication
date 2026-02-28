import express from "express";
import multer from "multer";
import path from "path";
import fs from "fs";

const router = express.Router();

// ensure uploads folder exists
const uploadDir = "uploads";
if (!fs.existsSync(uploadDir)) {
  fs.mkdirSync(uploadDir);
}

const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, uploadDir);
  },
  filename: (req, file, cb) => {
    const uniqueName =
      Date.now() +
      "-" +
      Math.round(Math.random() * 1e9) +
      path.extname(file.originalname);
    cb(null, uniqueName);
  },
});

const upload = multer({
  storage,
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB
});

// router.post("/", upload.single("file"), (req, res) => {
//   console.log(
//     "---------------------------------------------------------------uuuuuuuuuuuuuuuuuuuuuuu",
//   );
//   if (!req.file) {
//     return res.status(400).json({ success: false });
//   }

//   res.json({
//     success: true,
//     file: {
//       name: req.file.originalname,
//       type: req.file.mimetype,
//       url: `/uploads/${req.file.filename}`,
//     },
//   });
// });
router.post("/", upload.single("file"), (req, res) => {
  console.log("FILE RECEIVED:", req.file);

  if (!req.file) {
    return res.status(400).json({ success: false });
  }

  res.json({
    success: true,
    file: {
      name: req.file.originalname,
      type: req.file.mimetype,
      url: `/uploads/${req.file.filename}`,
    },
  });
});

export default router;
