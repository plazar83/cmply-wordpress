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
6. Publish a non-draft, non-prerelease GitHub Release whose tag is exactly the
   version, for example `1.0.19`. Do not prefix the tag with `v`.

The workflow refuses to deploy when the GitHub tag, PHP plugin version, and
readme stable tag differ. It copies only the distributable plugin files into a
clean build directory, publishes that directory to WordPress.org `trunk` and a
matching SVN tag, copies `.wordpress-org` artwork to SVN `assets`, generates a
ZIP, and attaches the ZIP to the GitHub Release.

## Release safety

- Do not recreate or overwrite an existing WordPress.org version tag. Publish a
  new patch version for every correction.
- Release `1.0.18` was published manually before this workflow existed. The
  first automated production release must therefore be newer than `1.0.18`.
- Keep the GitHub Release as a draft until all package checks pass. Publishing
  the release is the production deployment trigger.
- If deployment fails before the SVN commit, correct the source or secrets and
  rerun the failed workflow. If SVN already contains the tag, increment the
  plugin version instead of replacing the published tag.
