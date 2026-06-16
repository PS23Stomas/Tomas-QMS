import { spawn } from "child_process";

function startPhp() {
  const phpProcess = spawn(
    "php",
    [
      "-d", "upload_max_filesize=25M",
      "-d", "post_max_size=26M",
      "-S", "0.0.0.0:5000",
      "-t", "public",
      "public/router.php",
    ],
    { cwd: process.cwd(), stdio: "inherit" }
  );

  phpProcess.on("exit", (code) => {
    console.error(`[PHP] Exited with code ${code}, restarting in 1s...`);
    setTimeout(startPhp, 1000);
  });

  phpProcess.on("error", (err) => {
    console.error(`[PHP] Error: ${err.message}, restarting in 2s...`);
    setTimeout(startPhp, 2000);
  });
}

startPhp();
