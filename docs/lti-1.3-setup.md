# Canvas LTI 1.3 setup

This integration uses LTI 1.3 launches, Deep Linking for assignment content selection, and the Assignment and Grade Services Score service to attach a response link to the Canvas submission. Audio remains in Audio Recorder. The tool sends a pending submission state only; teachers enter grades in Canvas SpeedGrader.

## Prerequisites

1. Back up the Audio Recorder database, then run `php scripts/migrate.php` from the project root. The runner applies pending SQL files from `sql/` and records completed migrations in `schema_migrations`.
2. Generate an RSA private key of at least 2048 bits and install it outside the web root. Do not commit it. Publish the matching public key only through `/lti/jwks.php`.
3. Configure an LTI 1.3 Developer Key in Canvas with the `/lti/login.php` OIDC initiation URL, `/lti/launch.php` target URL, and `/lti/jwks.php` public-key URL. Enable the `assignment_selection` placement with an `LtiDeepLinkingRequest` message targeting `/lti/deep-link.php`. Permit `ltiResourceLink` content and line items. Enable the AGS `score` scope; enable `lineitem` if the Canvas setup uses tool-created line items.
4. Deploy the Developer Key to the intended Canvas account or course and record the resulting issuer, client ID, deployment ID, OIDC authorization URL, OAuth token URL, and Canvas JWKS URL.

## Server configuration

Set these values in the private service environment. `AR_LTI_PLATFORMS_JSON` may contain multiple Canvas registrations.

```text
AR_LTI_PRIVATE_KEY_PATH=/private/path/audio-recorder-lti-private.pem
AR_LTI_KEY_ID=audio-recorder-2026-01
AR_LTI_PLATFORMS_JSON=[{"issuer":"https://canvas.example.edu","client_id":"CANVAS_CLIENT_ID","deployment_ids":["CANVAS_DEPLOYMENT_ID"],"auth_login_url":"https://canvas.example.edu/api/lti/authorize_redirect","token_url":"https://canvas.example.edu/login/oauth2/token","jwks_url":"https://canvas.example.edu/api/lti/security/jwks"}]
```

Use the actual values shown in the Canvas Developer Key. The app requires HTTPS for Canvas authorization, token, and signing-key endpoints. `AR_PUBLIC_ORIGIN` and `AR_WEB_ROOT` must resolve to the externally reachable Audio Recorder origin and mount path so Canvas receives the same URLs that PHP uses to validate launches.

The public JWK route returns only the public portion of the configured key. Keep the private-key file readable by the PHP service account only. Rotate keys by coordinating the Canvas Developer Key/JWKS cache and the server key ID.

## Identity and submission behavior

Canvas identities are keyed by issuer, deployment, and LTI subject. The first launch gets a separate Audio Recorder identity unless the user chooses **Link existing account**. Linking requires an authenticated existing account and never uses email matching. If the old and existing accounts already have responses for the same prompt, automatic merge stops so data is not overwritten.

Each selected resource receives a random resource key. The database stores only its hash and ties it to the teacher's account and Canvas deployment. Student uploads validate that the active launch points to that exact prompt or activity step. The AGS Score request uses `basic_lti_launch` submission data pointing to a token-protected page with audio and transcripts; it does not send a score. The token is refreshed when a student resubmits the same Canvas resource.

## Browser and Canvas acceptance check

The app uses a `SameSite=None; Secure` PHP session cookie for `/lti/` endpoints so the OIDC form post and recorder requests can work in an embedded Canvas launch. Some browsers block third-party cookies even with that setting. Verify OIDC state continuity, microphone permissions in Canvas iframes, recording uploads, response playback in SpeedGrader, and teacher grading in the target Canvas instance before enabling the Developer Key broadly. If a browser blocks the embedded session, use Canvas's supported top-level launch or storage-access flow for that environment.
