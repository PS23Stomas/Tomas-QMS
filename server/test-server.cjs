const http = require("http");
const { spawn } = require("child_process");

const PHP_PORT = 5001;
const PROXY_PORT = 5000;

function startPhp() {
  console.log("[PHP] Starting on port " + PHP_PORT);
  const php = spawn("php", ["-S", "0.0.0.0:" + PHP_PORT, "-t", "public", "public/router.php"], {
    cwd: process.cwd(),
    stdio: "inherit"
  });
  php.on("exit", (code) => {
    console.log("[PHP] Exited (" + code + "), restarting...");
    setTimeout(startPhp, 1000);
  });
  php.on("error", (err) => {
    console.error("[PHP] Error: " + err.message);
    setTimeout(startPhp, 2000);
  });
}

const proxy = http.createServer((req, res) => {
  const opts = {
    hostname: "127.0.0.1",
    port: PHP_PORT,
    path: req.url,
    method: req.method,
    headers: req.headers
  };
  const p = http.request(opts, (pRes) => {
    const status = pRes.statusCode || 502;
    const headers = Object.assign({}, pRes.headers);

    if (req.url === "/" && status >= 300 && status < 400 && headers.location) {
      const redirectUrl = headers.location;
      const followOpts = {
        hostname: "127.0.0.1",
        port: PHP_PORT,
        path: redirectUrl.startsWith("http") ? new URL(redirectUrl).pathname : redirectUrl,
        method: "GET",
        headers: req.headers
      };
      const f = http.request(followOpts, (fRes) => {
        res.writeHead(fRes.statusCode || 200, fRes.headers);
        fRes.pipe(res);
      });
      f.on("error", () => {
        res.writeHead(status, headers);
        pRes.pipe(res);
      });
      f.end();
      pRes.resume();
    } else {
      res.writeHead(status, headers);
      pRes.pipe(res);
    }
  });
  p.on("error", () => {
    res.writeHead(503, {"Content-Type": "text/html"});
    res.end("<html><body><p>Loading...</p><script>setTimeout(()=>location.reload(),1500)</script></body></html>");
  });
  p.setTimeout(30000, () => { p.destroy(); });
  req.pipe(p);
});

proxy.listen(PROXY_PORT, "0.0.0.0", () => {
  console.log("[Proxy] Port " + PROXY_PORT + " -> PHP:" + PHP_PORT);
  startPhp();
});
