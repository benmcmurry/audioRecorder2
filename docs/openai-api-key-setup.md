# OpenAI API key setup

## Production project

Create a dedicated OpenAI API project for the production Audio Recorder service. Create a service account for the project and a project key for the app. In the project's API Keys settings, change the key to restricted permissions and allow only the write access required for audio transcription and audio speech generation. Set every unrelated permission to None.

Restrict model access to the models used by the app, currently `whisper-1` and `gpt-4o-mini-tts-2025-12-15`. Set a project spend limit, configure an alert, and give the key an expiration date so it can be rotated on a schedule.

Enable the IP allowlist for this project, not the whole organization. The server interface reported `128.187.107.38/27`, but an interface address does not prove the source address OpenAI sees. Confirm the host's outbound IP first. If it is `128.187.107.38`, add `128.187.107.38/32`; use `128.187.107.32/27` only if outbound traffic can leave from multiple addresses in that subnet.

## Development project

Use a separate development project and key. The currently reported local IP is `128.187.116.10`; confirm the outbound address while on campus and again through BYU VPN. If both routes reliably use that one IP, add `128.187.116.10/32` to the development project's allowlist. Add only other verified egress addresses if they differ.

## Secret configuration

Audio Recorder now follows the Vocabulary private configuration convention. Copy the repository's `.env.example` to `audioRecorder.env` inside that environment's private configuration directory, then set `OPENAI_API_KEY` there. By default the app looks in the sibling `private-config` directory; set `APP_PRIVATE_ROOT` to use another directory. Process environment values take precedence over file values. Keep the file outside Git and the web root, with read access limited to the PHP service account.

Use a separate `audioRecorder.env` for local development with the development project's key. Production and development must use different keys. The transcription worker receives the configured value through its process environment. Do not put a key in browser JavaScript, a URL, a log, or a support message.

After configuring the replacement production key and confirming both audio endpoints work, revoke any remaining old keys. The previously reported key has already been revoked.

## Server file access

The app uses Apache `.htaccess` rules to block direct access to `uploads/` and `generatedAudio/prompts/`. Keep those rules enabled in production so media is served through the signed-in PHP routes. If the web server ignores `.htaccess`, configure equivalent deny rules in the server's virtual-host configuration before deployment.
