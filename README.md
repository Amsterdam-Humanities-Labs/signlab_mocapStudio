# signlab_mocapStudio — 3D Studio Capture

The recording-session UI for the motion-capture studio: it puts the next item
to be signed on screen, drives the Unreal recorder over a WebSocket, logs every
take, and tracks what still needs capturing.

> The original one-line description of this repository was
> *"Unreal server & client & signCollect controller"* — that is still accurate.
> The **client** is `3dOpname*.html`, the **server** is `unrealServer/server.js`,
> and the **controller** is the set of PHP endpoints that read and write the
> capture state in MySQL.

## What it does

**The capture pages.** `3dOpname_test.html` is the current, larger page: it
opens on a mode chooser — *Glosses*, *HH*, *Sentences*, *BAK* (Basiswoordenlijst
Amsterdamse Kleuters) and a *Capture List* modal — and then shows one item at a
time with the recorder controls, a Babylon.js preview and a Manual Sync button.
`3dOpname.html` is the older, smaller version of the same page.

**The recorder link.** The page holds a WebSocket to a small relay
(`unrealServer/server.js`, a `ws` server on port 3002) that fans `startCapture`
/ `stopCapture` / `replayCapture` messages out to the connected Unreal client.
The two pages do not point at the same relay: `3dOpname_test.html` uses
`wss://signcollect.nl/unrealServer/` and `3dOpname.html` still uses
`wss://leffe.science.uva.nl:8043/unrealServer/`.

**The endpoints** (all JSON over MySQL):

| Endpoint | Does |
|---|---|
| `getZinnen.php`, `getTeksten.php` | Fetch the sentences / texts still to record |
| `getBakLabels.php` | The BAK word-list items and their remaining count |
| `logMocapRecording.php` | Record that a take happened, in `mocap_recording_logs`; returns today's running totals |
| `getMocapStats.php` | Today's takes, broken down by recording mode and by user |
| `updateZinMocap.php`, `updateTekstMocap.php`, `updateBakMocap.php` | Mark an item captured |
| `checkZinVideos.php`, `checkZinVideosFfprobe.php` | Verify the resulting videos exist and are readable (the second uses `ffprobe`) |
| `reencodeZinVideos.php` | Re-encode takes that need it |
| `triggerSync.php` | Same-origin proxy that starts (or polls) a Vicon sync without waiting for the scheduler |
| `test_duplicates.php` | Ad-hoc duplicate check |

## Where it runs

The **signcollect core server** (the production VPS), at `/web/mocapStudio`,
served as `https://signcollect.nl/mocapStudio/3dOpname.html`. On the demo hosts
it is `/web/mocapStudio` (dev2) and `/srv/signcollect/web/mocapStudio` (dev-1).
It is one of the pages the `mocap.signcollect.nl` portal links to.

`triggerSync.php` runs on the server too, and forwards to `127.0.0.1:8765` —
the control port of `signlab_viconSync`'s `sync_vicon_rsync.py`, which is the
component that actually reaches the **Vicon PC** (over SSH on the tailnet).
Nothing in this repository runs on the Vicon PC itself; the Unreal client at
the far end of the WebSocket runs on whichever studio machine is running
Unreal.

<!-- TODO: confirm which of the two capture pages is the live one, and where
     the unrealServer WebSocket is actually served from. 3dOpname.html points
     at leffe.science.uva.nl:8043 (a UvA host, not the core server) while
     3dOpname_test.html points at wss://signcollect.nl/unrealServer/. Whether
     unrealServer/server.js is run on the core server behind an Apache
     WebSocket proxy, on the studio machine, or both, is not determinable from
     this repository. -->

## Status

**Production.** (`3dOpname_test.html` carries a "test" name but is the fuller
of the two pages and is the one `triggerSync.php` documents itself against.)

## How to run or deploy it

No build step. Deployment is a git clone performed by the stack:
`signlab_signcollect-stack`'s `interface_deploy/scripts/repos.tsv` lists

    mocapStudio	signlab_mocapStudio	main

and the install scripts clone this repository and rsync it into
`<webroot>/mocapStudio`. Hardcoded `https://signcollect.nl/...` URLs are
rewritten to the demo host's own domain by `rewrite-urls.sh` at deploy time.

`unrealServer/server.js` is *not* started by the web deploy. It is a standalone
Node process (`npm i ws && node unrealServer/server.js`, listening on 3002).

The stray `.git_disabled/` directory is a disabled nested checkout left over
from before this tree became its own repository. It is inert.

## Configuration

- **`mysql_config.php`** — required, gitignored. Unlike most of the estate,
  the endpoints here `require_once 'mysql_config.php'` *next to themselves*
  rather than at the docroot root, so the deploy symlinks
  `<webroot>/mysql_config.php` into this directory on every run
  (`host-bootstrap.sh` does this explicitly for `mocapStudio` and `animMIDI`).
  Copy `mysql_config.example.php` when setting one up by hand.
- **`triggerSync.php` shared secret** — must match `$SECRET` in
  `/web/vicon_sync.php`. It is currently inline in the source.
  <!-- TODO: move this secret out of git into the per-host config, and rotate
       it. It is committed here and in the file it has to match. -->
- **`sc_paths.php`** — the vendored signcollect-lib install-root resolver used
  by `reencodeZinVideos.php` and `checkZinVideosFfprobe.php`. It finds
  `/web/lib/paths.php` or falls back to `/web`. Do not edit this copy; it is
  byte-identical across repos and checksummed by the stack's `path-test.sh`.

## Dependencies

- **MySQL** database `admin_gebarenoverleg` on localhost — `mocap_recording_logs`,
  `sentences`, `matched_transcriptions`, `captures` and the BAK label tables.
- **`signlab_mocap`** — the page fetches `../mocap/getCaptures.php` and
  `../mocap/fetch_all.php`, and links out to `../mocap/opnameLijst.html`.
- **`signlab_mocap_lab`** — the page fetches `../mocap_lab/fetch_images.php`,
  `fetch_topics.php`, `generate_video_files.php`, `save_fbx_studio.php`,
  `saveThree.php` and `helpScripts/emptyVideoTop.php`. Without either of these
  two neighbours the recording page loads to an empty form.
- **`signlab_viconSync`** — its control server on `127.0.0.1:8765` is what
  `triggerSync.php` talks to; that is also what reaches the Vicon PC.
- **`signlab_signcollect-lib`** (`/web/lib`) — optional, for the install-root
  resolver.
- **`ffmpeg` / `ffprobe`** on the host, for the video check and re-encode
  endpoints.
- **Node.js with `ws`** for `unrealServer/server.js`.
- **`/userProtect.js`** — the estate's shared login guard, loaded from the
  docroot root by `3dOpname.html`. It is not in this repository.
- The mocap portal at `mocap.signcollect.nl` is served by the separate
  `mocap_site` repository, which links here.
