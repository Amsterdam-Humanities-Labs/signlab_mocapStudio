# signlab_mocap_lab — Lab Capture Endpoints and Reference Media

A small set of PHP endpoints used during motion-capture sessions, plus the
reference imagery and example videos the capture pages display alongside them.

## What it does

This is a collection of endpoints and assets rather than an application. There
is no index page — the bare folder returns 500, which is expected. Everything
here is called by another page, in practice `signlab_mocapStudio`'s recording
page.

**Endpoints**

| File | Does |
|---|---|
| `fetch_images.php` | Lists the JPG/PNG/GIF files in `meta_images/` as JSON |
| `fetch_topics.php` | Returns `topics.json` — conversation prompts (in Dutch) given to signers to elicit spontaneous signing |
| `generate_video_files.php` | Lists the MP4/MOV files in `lsc_videos/` as JSON |
| `save_fbx_studio.php` | Marks a gloss captured: sets `take` and `take_date` in `mocap_data` and flips the matching `captures` row to captured |
| `saveThree.php` | Flags a set of three glosses in `mocap_data` (the `pineapple` column) |
| `upload_video.php` | Receives a camera's video file and files it under `gebarenoverleg_media/mocap_video/<camera>/` |
| `test_fbx_sql.php` | Ad-hoc script: reads recent GLBs out of the media FBX directory and inserts matching `mocap_files` rows |

**Reference media** (tracked in git, and the bulk of the repository)

- `meta_images/` — 58 handshape reference photographs (`B_bent.jpg`, `Beak_open.jpg`, `ILY.jpg`, …).
- `csl_images/` — 41 handshape diagrams in a second, more systematic naming scheme (`index+thumb-flatclosed.png`, `5_b-curvedopen.png`, …).
- `mocap_videos/` — 18 short example clips of individual signs and handshapes.

## Size, so nobody is surprised

The **repository** is modest: 128 tracked files, about 34 MB packed and ~70 MB
checked out, nearly all of it the reference media above. The **deployed
directory** on the server is much larger (around 198 MB) because it accumulates
untracked working media — notably `lsc_videos/`, which
`generate_video_files.php` lists but which is not in git. Do not go looking for
that difference in the history; it is on disk only.

Note also that `signlab_mocapStudio` fetches
`../mocap_lab/helpScripts/emptyVideoTop.php`, which is likewise not in this
repository.
<!-- TODO: confirm where helpScripts/ and lsc_videos/ come from on the server —
     whether they are deliberately untracked working data or a directory that
     should be in git. -->

## Where it runs

The **signcollect core server** (the production VPS), at `/web/mocap_lab`,
served as `https://signcollect.nl/mocap_lab/`. On the demo hosts it is
`/web/mocap_lab` (dev2) and `/srv/signcollect/web/mocap_lab` (dev-1).

Everything here is plain PHP handling ordinary web requests against a MySQL
database on `localhost`. Nothing in this repository runs on the Vicon PC or on
any other machine.

## Status

**Production** — in the sense that the deployed endpoints are live and the
studio page depends on them. Several of the files (`test_fbx_sql.php`,
`saveThree.php`) are clearly experiments that were never cleaned up.

## How to run or deploy it

No build step. Deployment is a git clone performed by the stack:
`signlab_signcollect-stack`'s `interface_deploy/scripts/repos.tsv` lists

    mocap_lab	signlab_mocap_lab	main

and the install scripts clone this repository and rsync it into
`<webroot>/mocap_lab`. `fetch_images.php` and `generate_video_files.php` read
their directories relative to the *current working directory*, so they only
work when requested over HTTP from within the deployed folder.

`requirements.txt` is present but empty; the Python script that used to sit
here was removed from the document root and there is nothing to install.

## Configuration

- **`mysql_config.php`** — required by `save_fbx_studio.php`, `saveThree.php`
  and `test_fbx_sql.php`, which `include('../mysql_config.php')`: one level
  *above* this directory, at the docroot root (`/web/mysql_config.php`). It is
  gitignored and created per host by the deploy; Apache returns 403 for it.
- **`sc_paths.php`** — the vendored signcollect-lib install-root resolver
  (`sc_path()`, `sc_dir()`), used by `test_fbx_sql.php`. It finds
  `/web/lib/paths.php` or falls back to `/web`. Do not edit this copy — it is
  byte-identical across repos and is checksummed by the stack's
  `tests/path-test.sh`; edit the copy in `signlab_signcollect-lib`.
- `upload_video.php` writes to a hardcoded `../gebarenoverleg_media/mocap_video/`
  rather than going through `sc_path()`, so it assumes the media tree is a
  sibling of this directory.

## Dependencies

- **MySQL** database `admin_gebarenoverleg` on localhost — `mocap_data`,
  `captures`, `mocap_files`.
- **`gebarenoverleg_media`** — the media tree written to by `upload_video.php`
  and read by `test_fbx_sql.php`. Supplied by `signlab_demo-media` on the demo
  hosts.
- **`signlab_signcollect-lib`** (`/web/lib`) — optional; the resolver falls back
  to `/web` without it.
- **Consumed by `signlab_mocapStudio`**, which is the only caller of these
  endpoints in the deployed tree. If this repository is missing, the studio
  recording page loads to an empty form.
- The mocap portal at `mocap.signcollect.nl` is served by the separate
  `mocap_site` repository; it does not link here directly.
