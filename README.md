# signlab_mocapStudio
The page used during a mocap recording session. It shows the next item to sign, controls the Unreal recorder and logs every recording.

## What it does
- `capture.html` is the live page. It used to be `3dOpname_test.html`; that name now redirects here. Modes: Glosses, HH, Sentences, BAK and Capture List. It has a Babylon.js preview and a Manual Sync button.
- `3dOpname.html` is an older version. It still connects to `wss://leffe.science.uva.nl:8043/unrealServer/`.
- `unrealServer/server.js` is a `ws` relay on port 3002. The page connects to it as `wss://signcollect.nl/unrealServer/`. It passes `startCapture`, `stopCapture` and `replayCapture` to the Unreal client.
- PHP endpoints (JSON, MySQL): `get{Zinnen,Teksten,BakLabels}.php`, `logMocapRecording.php`, `getMocapStats.php`, `update{Zin,Tekst,Bak}Mocap.php`, `{check,reencode}ZinVideos*.php`, `getDuplicateZinTakes.php` (sentences with more than one take; the old name `test_duplicates.php` is a stub).
- `triggerSync.php` lets the page reach the viconSync control server without cross-origin calls. It forwards `?action=trigger` (POST) or `?action=status` to `127.0.0.1:8765`. It checks neither login nor origin.
- `lab/` holds the endpoints and reference media the page calls as `../mocap_lab/`. It used to be signlab_mocap_lab. See `lab/README.md`.

## Where it runs
Core server: `/web/mocapStudio`, https://signcollect.nl/mocapStudio/capture.html.
Demo hosts: dev2 `/web/mocapStudio`, dev-1 `/srv/signcollect/web/mocapStudio`. On demo hosts `<docroot>/mocap_lab` is a symlink to `mocapStudio/lab`.

## Status
Production.

## How to run / deploy
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack) deploys it (`repos.tsv` row `mocapStudio`). Its `host-bootstrap.sh` creates the `mocap_lab` symlink.
There is no build step. The deploy does not start the relay; start it by hand:
```bash
npm i ws && node unrealServer/server.js
```

## Configuration
- `mysql_config.php` (not in git) sits next to the endpoints. The deploy symlinks the docroot copy here. Template: `mysql_config.example.php`.
- `sc_paths.php` is the path resolver copied from signcollect-lib. Do not edit this copy.

## Dependencies
- MySQL `admin_gebarenoverleg`: `mocap_recording_logs`, `sentences`, `matched_transcriptions`, `captures` and the BAK label tables.
- [signlab_mocap](https://github.com/Amsterdam-Humanities-Labs/signlab_mocap): `../mocap/getCaptures.php`, `fetch_all.php`, `opnameLijst.html`.
- The [signlab_viconSync](https://github.com/Amsterdam-Humanities-Labs/signlab_viconSync) control server on `127.0.0.1:8765`, `ffmpeg` and `ffprobe`, Node.js with `ws`, and `/userProtect.js` at the docroot.
- [signlab_BabylonSignLab](https://github.com/Amsterdam-Humanities-Labs/signlab_BabylonSignLab) at `/jari/BabylonSignLab/` (docroot): the avatar scripts and meshes `capture.html` loads.
- Missing from every repo: `../mocap_lab/helpScripts/emptyVideoTop.php`. The page also fetches `fetch_data.php` and `uniqueThema.php` next to itself; neither is in this repo (a `uniqueThema.php` exists only at the docroot), so on a fresh deploy the user and theme menus stay empty.
