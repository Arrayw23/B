import React, { useState, useEffect } from "react";
import axios from "axios";
import io from "socket.io-client";

const socket = io("https://your-app.onrender.com");

const App = () => {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [user, setUser] = useState(null);
  const [suspended, setSuspended] = useState(false);
  const [suspendMessage, setSuspendMessage] = useState("");

  useEffect(() => {
    socket.on("suspend", (data) => {
      if (user && user._id === data.userId) {
        setSuspended(true);
        setSuspendMessage(data.message);
      }
    });

    socket.on("unsuspend", (data) => {
      if (user && user._id === data.userId) {
        setSuspended(false);
        setSuspendMessage("");
      }
    });
  }, [user]);

  const login = async () => {
    const res = await axios.post("https://your-app.onrender.com/api/login", { username, password });
    setUser(res.data.user);
  };

  return (
    <div className="App">
      {suspended ? (
        <div className="overlay">
          <h2>You are suspended</h2>
          <p>{suspendMessage}</p>
        </div>
      ) : (
        <div>
          {!user ? (
            <div>
              <h1>Blooket (Modified)</h1>
              <input placeholder="Username" onChange={(e) => setUsername(e.target.value)} />
              <input type="password" placeholder="Password" onChange={(e) => setPassword(e.target.value)} />
              <button onClick={login}>Login</button>
            </div>
          ) : (
            <h2>Welcome, {user.username}</h2>
          )}
        </div>
      )}
    </div>
  );
};

export default App;
