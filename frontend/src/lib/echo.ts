import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { api } from "../api/client";

// Reverb uses the Pusher protocol for WebSocket connections.
const pusher = new Pusher(import.meta.env.VITE_REVERB_APP_KEY, {
  cluster: "mt1",
  wsHost: import.meta.env.VITE_REVERB_HOST ?? "localhost",
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === "https",
  enabledTransports: ["ws", "wss"],

  // Reuse the existing Sanctum session and CSRF configuration.
  channelAuthorization: {
    transport: "ajax",
    endpoint: "http://localhost:8000/api/broadcasting/auth",
    customHandler: async (params, callback) => {
      try {
        const response = await api.post("/api/broadcasting/auth", {
          socket_id: params.socketId,
          channel_name: params.channelName,
        });

        callback(null, response.data);
      } catch (error) {
        callback(error as Error, null);
      }
    },
  },
});

// Echo reuses the Pusher client rather than creating another connection.
const echo = new Echo({
  broadcaster: "reverb",
  key: import.meta.env.VITE_REVERB_APP_KEY,
  client: pusher,
});

export default echo;
