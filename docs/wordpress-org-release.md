# WordPress.org release process

CMPly is deployed from GitHub Releases to the WordPress.org SVN repository by
`.github/workflows/deploy-wordpress-org.yml`.

## One-time repository setup

Add these GitHub Actions repository secrets:

- `SVN_USERNAME`: the case-sensitive WordPress.org username `cmplyapp`.
- `SVN_PASSWORD`: the separate SVN password from the WordPress.org profile.

The normal WordPress.org account password is not used for SVN automation.

## Publishing a release

1. Update the version in the `cmply.php` plugin header.
2. Update `CMPLY_COOKIE_CONSENT_VERSION` in `cmply.php`.
3. Update `Stable tag` and add the changelog entry in `readme.txt`.
4. Run the PHP checks, connection-state regression test, package build, and
   Plugin Check against the resulting ZIP.
5. Commit and push the release source to GitHub.
6. Create and push an annotated Git tag that is exactly the version, for
   example `1.0.19`. Do not prefix the tag with `v`.

The workflow refuses to deploy when the GitHub tag, PHP plugin version, and
readme stable tag differ. It copies only the distributable plugin files into a
clean build directory, publishes that directory to WordPress.org `trunk` and a
matching SVN tag, copies `.wordpress-org` artwork to SVN `assets`, generates a
ZIP, creates the GitHub Release, and attaches the ZIP to it.

## Manual test package on Windows

Build local upload packages with the checked-in script:

```powershell
.\scripts\build-plugin-zip.ps1
```

Upload the generated `output/cmply.zip`. Do not package a containing `cmply`
folder and do not use PowerShell `Compress-Archive` directly: it can store entry
names with Windows backslashes such as `includes\class-cmply.php`, which causes
a fatal activation error on Linux WordPress hosts. The script uses `tar`, keeps
the distributable files at the ZIP root, verifies version consistency, rejects
backslash entry names, checks all required files, and prints the SHA-256 hash.

Before handing off any manual ZIP, inspect its entry list and confirm at least:

```text
cmply.php
includes/class-cmply.php
assets/admin.css
readme.txt
uninstall.php
LICENSE
```

## Release safety

- Do not recreate or overwrite an existing WordPress.org version tag. Publish a
  new patch version for every correction.
- Release `1.0.18` was published manually before this workflow existed. The
  first automated production release must therefore be newer than `1.0.18`.
- Do not push the Git tag until all package checks pass. Pushing the tag is the
  production deployment trigger.
- If deployment fails before the SVN commit, correct the source or secrets and
  rerun the failed workflow. If SVN already contains the tag, increment the
  plugin version instead of replacing the published tag.
