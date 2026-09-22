# signlab_mocapStudio
Recording-session UI for the mocap studio: shows the next item to sign, drives the Unreal recorder and logs every take.

## What it does
- `3dOpname_test.html` (the live page despite its name): modes Glosses, HH, Sentences, BAK, Capture List; Babylon.js preview, Manual Sync button.
- `3dOpname.html`: older version, still points at `wss://leffe.science.uva.nl:8043/unrealServer/`.
- `unrealServer/server.js`: `ws` relay on port 3002 that forwards `startCapture`/`stopCapture`/`replayCapture` to the Unreal client.
- PHP endpoints (JSON/MySQL): `get{Zinnen,Teksten,BakLabels}.php`, `logMocapRecording.php`, `getMocapStats.php`, `update{Zin,Tekst,Bak}Mocap.php`, `{check,reencode}ZinVideos*.php`, `test_duplicates.php`.
- `triggerSync.php`: same-origin proxy to viconSync's control port `127.0.0.1:8765`.

## Where it runs
core (production): `/web/mocapStudio`, https://signcollect.nl/mocapStudio/3dOpname_test.html. Demo: dev2 `/web/mocapStudio`, dev-1 `/srv/signcollect/web/mocapStudio`.

## Status
production

## How to run / deploy
Deployed by the stack (repos.tsv row `mocapStudio`): https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack
No build step. The relay is not started by the deploy: `npm i ws && node unrealServer/server.js`.

## Configuration
- `mysql_config.php` (not in git) next to the endpoints; the deploy symlinks the webroot copy here. Template: `mysql_config.example.php`.
- `sc_paths.php`: vendored signcollect-lib resolver; do not edit this copy.

## Dependencies
- MySQL `admin_gebarenoverleg`: `mocap_recording_logs`, `sentences`, `matched_transcriptions`, `captures`, BAK label tables.
- signlab_mocap (`../mocap/getCaptures.php`, `fetch_all.php`, `opnameLijst.html`) and `lab/` (was signlab_mocap_lab; served as `../mocap_lab/*` via a symlink: images, topics, gloss videos, FBX save).
- signlab_viconSync control server on `127.0.0.1:8765`; `ffmpeg`/`ffprobe`; Node.js + `ws`; `/userProtect.js` at the docroot.
- Not in any repo: `../mocap_lab/helpScripts/emptyVideoTop.php` and `/jari/BabylonSignLab/*` (now forked as signlab_BabylonSignLab).
