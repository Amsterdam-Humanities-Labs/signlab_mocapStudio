# signlab_mocap_lab
PHP endpoints and reference media used by the mocapStudio recording page during capture sessions.

## What it does
- `fetch_images.php` (lists `meta_images/`), `fetch_topics.php` (returns `topics.json`, Dutch conversation prompts).
- `generate_video_files.php`: lists `lsc_videos/` (untracked, on the server only); the studio plays gloss videos from there.
- `save_fbx_studio.php`: marks a gloss captured in `mocap_data` and `captures`. `saveThree.php`: flags three glosses (`pineapple` column).
- `upload_video.php`: files a camera video under `../gebarenoverleg_media/mocap_video/<camera>/`. `test_fbx_sql.php`: ad-hoc script.
- Media in git: `meta_images/` (58 photos), `csl_images/` (41 diagrams), `mocap_videos/` (18 clips, fallback for capture playback).
- No index page; the bare folder returns 500. `helpScripts/emptyVideoTop.php`, which the studio fetches, is in no repo.

## Where it runs
core (production): `/web/mocap_lab`, https://signcollect.nl/mocap_lab/. Demo: dev2 `/web/mocap_lab`, dev-1 `/srv/signcollect/web/mocap_lab`.

## Status
production

## How to run / deploy
Deployed by the stack (repos.tsv row `mocap_lab`): https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack
No build step. The listing endpoints read paths relative to the CWD, so call them over HTTP from the deployed folder.

## Configuration
- `../mysql_config.php` (docroot, not in git).
- Vendored `sc_paths.php` (edit it in signcollect-lib, not here).
- `SC_LEGACY_WEB_ROOT` (env or `/web/.env`, default `/var/www/html`): where `save_fbx_studio.php` looks for GLBs.

## Dependencies
- MySQL `admin_gebarenoverleg`: `mocap_data`, `captures`, `mocap_files`.
- `gebarenoverleg_media` as a sibling dir (demo: signlab_demo-media); signlab_signcollect-lib (optional).
- Only caller: signlab_mocapStudio.
