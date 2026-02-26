// server/index.ts
var import_child_process = require("child_process");
var import_http = require("http");
process.on("uncaughtException", (err) => {
  console.error("[Process] Uncaught exception:", err.message);
});
process.on("unhandledRejection", (reason) => {
  console.error("[Process] Unhandled rejection:", reason);
});
process.on("SIGTERM", () => {
  console.log("[Process] Received SIGTERM, ignoring...");
});
process.on("SIGINT", () => {
  console.log("[Process] Received SIGINT, ignoring...");
});
var PHP_PORT = 5001;
var PROXY_PORT = 5e3;
var phpProcess = null;
function startPhp() {
  console.log("[PHP] Starting server on port " + PHP_PORT + "...");
  phpProcess = (0, import_child_process.spawn)("php", ["-S", `0.0.0.0:${PHP_PORT}`, "-t", "public", "public/router.php"], {
    cwd: process.cwd(),
    stdio: "inherit"
  });
  phpProcess.on("exit", (code) => {
    console.error(`[PHP] Server exited (code ${code}), restarting in 1s...`);
    setTimeout(startPhp, 1e3);
  });
  phpProcess.on("error", (err) => {
    console.error(`[PHP] Error: ${err.message}, restarting in 2s...`);
    setTimeout(startPhp, 2e3);
  });
}
function proxyRequest(req, res) {
  const options = {
    hostname: "127.0.0.1",
    port: PHP_PORT,
    path: req.url,
    method: req.method,
    headers: req.headers
  };
  const proxyReq = (0, import_http.request)(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode || 502, proxyRes.headers);
    proxyRes.pipe(res, { end: true });
  });
  proxyReq.on("error", (_err) => {
    if (!res.headersSent) {
      res.writeHead(502, { "Content-Type": "text/html" });
      res.end("<html><body><h3>Loading...</h3><script>setTimeout(()=>location.reload(),2000)</script></body></html>");
    }
  });
  proxyReq.setTimeout(3e4, () => {
    proxyReq.destroy();
    if (!res.headersSent) {
      res.writeHead(504, { "Content-Type": "text/plain" });
      res.end("Request timeout");
    }
  });
  req.pipe(proxyReq, { end: true });
}
var server = (0, import_http.createServer)((req, res) => {
  proxyRequest(req, res);
});
server.on("error", (err) => {
  console.error("[Proxy] Server error:", err.message);
});
server.listen(PROXY_PORT, "0.0.0.0", () => {
  console.log(`[Proxy] Listening on port ${PROXY_PORT}, proxying to PHP on port ${PHP_PORT}`);
  startPhp();
});
setInterval(() => {
  console.log(`[Heartbeat] ${(/* @__PURE__ */ new Date()).toISOString()} - alive`);
}, 6e4);
