# Bundled dependencies

## Dompdf 3.1.6

The packaged Dompdf release is bundled for server-side quotation PDF downloads.
It is pinned to version `3.1.6`, downloaded from the signed upstream GitHub
release tag `v3.1.6`. The reviewed package archive SHA-256 is:

`05df8ee4907325ed2e09a139de9784325f761b43a049297155499270163ac94d`

Dompdf and its packaged dependencies retain their upstream license notices in
`vendor/dompdf/`. PDF generation disables remote fetching and restricts the
renderer chroot to this plugin directory.
