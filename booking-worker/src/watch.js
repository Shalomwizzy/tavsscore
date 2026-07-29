// Long-running, local-Mac listener for the admin “Generate codes” button.
// It never handles login or customer data. It only starts the existing browser
// worker after an authenticated request appears on TavsScore.

import { spawn } from 'node:child_process';
import { completeGenerationRequest, fetchGenerationRequest } from './api.js';

const seconds = Math.max(15, Number.parseInt(process.env.BOOKING_REQUEST_POLL_SECONDS || '60', 10) || 60);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function runWorker() {
  return new Promise((resolve) => {
    const child = spawn(process.platform === 'win32' ? 'npm.cmd' : 'npm', ['start'], {
      stdio: 'inherit', env: { ...process.env, REQUIRE_BOOKING_CODE: 'true' },
    });
    child.on('error', (error) => {
      console.error(`Could not start the booking worker: ${error.message}`);
      resolve(false);
    });
    child.on('exit', (code) => resolve(code === 0));
  });
}

console.log(`TavsScore booking request listener started. Checking every ${seconds}s.`);
console.log('Keep this terminal running on the Nigerian-IP Mac. Press Ctrl+C to stop it.');

while (true) {
  try {
    const response = await fetchGenerationRequest();
    const request = response?.request;

    if (response?.requested && request?.id) {
      console.log(`Admin requested code generation at ${request.requested_at}. Starting SportyBet worker…`);
      const success = await runWorker();
      if (success) {
        await completeGenerationRequest(request.id);
        console.log('Generation completed and request cleared.');
      } else {
        console.error('Generation did not finish. The request remains queued and will retry.');
      }
    }
  } catch (error) {
    console.error(`Could not check the admin request: ${error.message}`);
  }

  await sleep(seconds * 1000);
}
