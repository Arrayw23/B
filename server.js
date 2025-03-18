require("dotenv").config();
const express = require("express");
const mongoose = require("mongoose");
const cors = require("cors");
const jwt = require("jsonwebtoken");
const bcrypt = require("bcryptjs");
const { Server } = require("socket.io");
const http = require("http");

const app = express();
const server = http.createServer(app);
const io = new Server(server, { cors: { origin: "*" } });

app.use(cors());
app.use(express.json());

// Connect to MongoDB
mongoose.connect(process.env.MONGO_URI, { useNewUrlParser: true, useUnifiedTopology: true })
  .then(() => console.log("MongoDB connected"))
  .catch(err => console.log(err));

// User Schema
const UserSchema = new mongoose.Schema({
  username: String,
  password: String,
  role: { type: String, default: "student" },
  blooks: { type: Array, default: [] },
  isSuspended: { type: Boolean, default: false },
  suspendMessage: { type: String, default: "" }
});
const User = mongoose.model("User", UserSchema);

// Register Route
app.post("/api/register", async (req, res) => {
  const { username, password, role } = req.body;
  const hashedPassword = await bcrypt.hash(password, 10);
  const user = new User({ username, password: hashedPassword, role });
  await user.save();
  res.json({ message: "User registered" });
});

// Login Route
app.post("/api/login", async (req, res) => {
  const { username, password } = req.body;
  const user = await User.findOne({ username });
  if (!user) return res.status(400).json({ error: "User not found" });

  const isMatch = await bcrypt.compare(password, user.password);
  if (!isMatch) return res.status(400).json({ error: "Invalid credentials" });

  const token = jwt.sign({ id: user._id, role: user.role }, process.env.JWT_SECRET);
  res.json({ token, user });
});

// Buy Blooks
app.post("/api/buy", async (req, res) => {
  const { userId, blook } = req.body;
  await User.findByIdAndUpdate(userId, { $push: { blooks: blook } });
  res.json({ message: "Blook purchased!" });
});

// Admin Suspend Player
app.post("/api/suspend", async (req, res) => {
  const { userId, message } = req.body;
  await User.findByIdAndUpdate(userId, { isSuspended: true, suspendMessage: message });
  io.emit("suspend", { userId, message });
  res.json({ message: "User suspended" });
});

// Admin Unsuspend Player
app.post("/api/unsuspend", async (req, res) => {
  const { userId } = req.body;
  await User.findByIdAndUpdate(userId, { isSuspended: false, suspendMessage: "" });
  io.emit("unsuspend", { userId });
  res.json({ message: "User unsuspended" });
});

// Start Server
const PORT = process.env.PORT || 10000;
server.listen(PORT, () => console.log(`Server running on port ${PORT}`));
