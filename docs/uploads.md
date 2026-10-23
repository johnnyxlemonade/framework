# Uploads

Lemonade provides profile-based generic-file and image uploads through `UploadFactory`. A configured
uploader performs file-size, extension, server-detected MIME, risk-policy, visibility and path
validation before it delegates final persistence to internal storage infrastructure. Image profiles
add image and dimension validation and can re-encode the stored image.

## Configuration

Each profile declares an explicit `allowed_extensions` allowlist. The framework detects the actual
MIME type server-side through `ext-fileinfo`, then accepts the upload only when the detected MIME is
a `MimeTypeCatalog` match for the client filename extension and the catalog classification is
authorized by `UploadRiskPolicy`. Client-supplied MIME metadata is never trusted.
`allowed_mime_types` is not an upload configuration option.

```yaml
module: upload
config:
  images:
    admin-image:
      target_directory: admin/images
      max_bytes: 5MB
      allowed_extensions:
        - jpg
        - jpeg
        - png
        - webp
      reencode: true

  files:
    admin-file:
      target_directory: admin/files
      max_bytes: 10MB
      allowed_extensions:
        - jpg
        - jpeg
        - png
        - webp
        - pdf
        - doc
        - docx
        - xls
        - xlsx
        - txt

    private-document:
      target_directory: documents
      visibility: private
      max_bytes: 10MB
      allowed_extensions:
        - pdf
        - txt
```

`visibility` accepts `public` (the backward-compatible default) or `private`. Public uploads are
stored below `<public path>/uploads`; private uploads are stored below
`storage/writable/uploads/files` and never receive a public-relative path. Applications should
deliver private files only through an authenticated controller or another authorization boundary.

Public storage always rejects `active_content`, `executable`, and `unclassified` formats. This rule
cannot be weakened with application configuration. Private storage permits passive formats by
default and requires every other category to be listed explicitly in `allowed_risks`:

```yaml
module: upload
config:
  files:
    private-source-review:
      target_directory: source-review
      visibility: private
      max_bytes: 1MB
      allowed_extensions:
        - php
      allowed_risks:
        - executable
```

An explicit private risk authorization only permits storage. It does not make the content safe to
execute, include, render inline, deserialize, or extract. Container formats likewise require an
explicit `container` authorization when stored privately; existing public document profiles remain
compatible because public storage permits individually allowlisted passive and container formats.

## Private staging and publication

Every file and image upload is copied once into a cryptographically named snapshot below
`storage/writable/uploads/staging`. This directory must be outside the public web root and cannot
contain symbolic-link path components. The boundary is checked whenever staging begins, so an
unsafe legacy layout fails closed when an upload is attempted without affecting applications that
do not use uploads.

The framework does not trust `UploadedFileInterface::getSize()`. It reads the PSR stream from its
beginning, counts the bytes actually received, and stops before writing a byte beyond the profile's
`max_bytes` limit. Empty, unreadable, stalled, truncated, or throwing streams fail before a final
storage directory or file is created. MIME detection, risk policy, and content validation then use
only the sealed snapshot; later changes to the original request temporary file cannot affect the
published bytes.

Image snapshots are completely decoded before publication, including when `reencode` is `false`.
JPEG, PNG, and WebP decoding retains the image subsystem's width, height, pixel-count, input-byte,
codec, and conservative PHP memory-budget checks. With `reencode: true`, only newly encoded bytes
are sealed into a second private snapshot, decoded again, and published; data trailing the original
image is therefore not retained.

After every required check succeeds, publication writes a complete temporary file in the
target-local framework-owned `.lemonade-publication` directory and atomically hard-links it to the
generated final name. Existing targets are never replaced, a
concurrent name collision fails safely, and errors after creation of the final link remove that
link. Request staging files and publication temporaries are removed on both success and handled
failure. Staging and publication temporary files hold an OS lease from creation; bounded automatic
cleanup skips active leases and later reclaims sufficiently old files left by process termination.

`target_directory` is always a relative directory below the visibility-specific root. Absolute
paths, `.` or `..` segments, empty segments, backslashes, stream-wrapper syntax, control characters,
and symbolic-link path components are rejected. Bootstrap rejects a configured private profile when
the private root overlaps the public web root. Public-only legacy applications may continue to use a
legacy web root, but private runtime options still reject that overlap when used.

`max_bytes` accepts either a positive raw byte integer, such as `10485760`, or a positive whole
number followed by `B`, `KB`, `MB`, or `GB`; whitespace before the unit is allowed and units are
case-insensitive. These units use binary multipliers: `1KB = 1024` bytes, `1MB = 1024 * 1024`
bytes, and `1GB = 1024 * 1024 * 1024` bytes. Zero, negative, decimal, unknown-unit, and overflowing
values are configuration errors.

Applications select an existing file or image profile and pass an `UploadedFileInterface` to the
configured uploader. The resulting `UploadedFile` or `UploadedImage` is the same regardless of how
the temporary upload bytes reached the framework. `visibility()` identifies its storage boundary;
`publicRelativePath()` returns a path only for public results and returns `null` for private results.
`storedRelativePath()` remains a storage-relative identifier and must not be treated as a URL for a
private result.

`UploadFactory` and its configured uploaders are the supported application API. `UploadStorage` is
internal trusted-code infrastructure: it enforces visibility paths, permissions, byte identity and
atomic publication, but deliberately does not perform MIME, risk or image validation. Passing
untrusted bytes to it directly bypasses the validated upload contract.

`FileUploadValidator`, `ImageUploadValidator`, `UploadService`, staging classes, and storage classes
are internal pipeline boundaries. Their constructors and method signatures may change as the
framework strengthens the pipeline; applications should continue to use `UploadFactory::file()` or
`UploadFactory::image()`.

Application-provided `MimeTypeDefinition` instances must now pass a `MimeRisk` explicitly. Omitting
the classification is a configuration error rather than an implicit `Passive` classification.

## Sequential chunk uploads

`ChunkUploadService` is an optional transport layer for applications that need to receive one file
in sequential chunks. It does not register HTTP routes or controllers: the consuming application
owns authorization, endpoint shape and response mapping.

The service is request-scoped and resolves the target profile through the existing `UploadFactory`.
Its public flow is:

```text
start -> append -> complete
                 \-> abort
```

`start($kind, $profile, $originalFilename, $declaredSize)` accepts an explicit `file` or `image`
kind, resolves the corresponding existing profile and rejects a declared size above that profile's
`max_bytes` before it creates temporary data. A private filesystem quota lock atomically reserves the
declared bytes and one active-session slot, including when multiple workers start uploads concurrently.
The client filename is retained only for extension validation; it is never used as a filesystem path.
Optional context must be JSON-serializable and fit the configured encoded-byte limit.

`append($uploadId, $offset, $body)` accepts exactly the next byte offset. It reads at most one
server-authorized chunk, verifies the assembled size against both the declared size and the
profile's maximum, then advances the stored offset and server-owned chunk counter only after the
full payload is written. Empty, stalled, throwing, oversized, parallel, and out-of-order streams are
rejected without advancing metadata. The chunk-count ceiling prevents bypassing request-cost limits
with an extreme number of tiny chunks.

`complete($uploadId)` verifies the final assembled size and wraps the private payload in a PSR-7
`UploadedFileInterface`. It then delegates to the unchanged standard flow:

```text
UploadFactory -> Configured*Uploader -> UploadService -> validators -> UploadStorage
```

Therefore MIME, extension, image, dimension and re-encoding behavior remain exactly the same as
for a normal multipart upload. Deterministic validation and image-processing failures atomically
retire the session. Storage and other infrastructure failures leave it retryable when no final file
was returned.

Successful completion atomically renames the session out of the active namespace before returning.
Only one concurrent `complete()` can therefore succeed. A repeated completion reports a missing
session and cannot publish another file. If this final confirmation cannot be performed, the newly
published file is rolled back and the original session remains retryable. `append()`, `complete()` and
`abort()` serialize on a pre-created session lock; waiting operations never recreate a retired session.
Each session also owns a random publication token distinct from its bearer upload ID. A retry after
process termination targets the same final filename, repeats all validation and processing, and may
reuse an existing regular file only when its measured size and SHA-256 exactly match the newly
validated snapshot. Different content fails closed and is never overwritten.

`abort($uploadId)` atomically retires the session directory, metadata and assembled payload. Calling
it for a missing valid session is safe. Physical deletion follows as best effort; an inactive retired
directory left by an interruption is reclaimed by a later cleanup pass.

## Limits and temporary storage

`ChunkUploadConfig` has these secure defaults:

- 2 MiB per chunk;
- 3600-second session TTL;
- 128 active sessions;
- 512 MiB total declared bytes reserved across active sessions;
- 1024 chunks per session;
- 16 KiB of encoded session context;
- 24 hours before an unlocked staging snapshot is considered orphaned;
- 128 entries examined by one opportunistic cleanup pass.

`ChunkUploadService::chunkBytes()` exposes the server-authoritative chunk limit to an application
endpoint; clients do not select their own maximum. Applications with a justified larger workload may
replace the `ChunkUploadConfig` singleton from an application provider. No environment variable,
database, Redis service, queue worker, or configuration change is required for the defaults.

Temporary data is stored outside the public web root below:

```text
storage/writable/uploads/chunks/<two-hex-character-shard>/<random-upload-id>/
```

Each session contains `meta.json`, `payload.part` once data arrives, and a pre-created per-session lock
file. Directories use mode `0700`; private payload, metadata and lock files use `0600`. Session IDs are
cryptographically random bearer capabilities. Session and quota paths reject symbolic links and are
contained below the private root.

## Cleanup

Starting a normal chunk session opportunistically examines a bounded number of expired sessions
before reserving quota. Cleanup attempts each session lock without waiting, so initialization,
append, completion and abort operations cannot stall another session's start request. New sessions
are fully prepared below a hidden initialization namespace while holding their session lock, then
atomically published under the global quota lock.

Every normal file or image upload also examines a bounded number of old staging snapshots, and each
publication examines the selected final directory for framework-owned publication temporaries. Both
types hold a shared process lease from file creation through their active lifecycle; cleanup obtains
a non-blocking exclusive lease, so active files are skipped. Process termination releases the OS
lock, allowing a sufficiently old orphan to be reclaimed automatically by a later upload.

The explicit operational command remains available for production maintenance:

```bash
vendor/bin/lemonade upload:chunks:cleanup
```

It removes expired or sufficiently old corrupt sessions, retired session directories, and unlocked
orphan staging snapshots, then reports the reclaimed counts and bytes. Cleanup uses session locks and
never follows symbolic links or scans outside its private roots.

The framework restricts managed public upload directories to `0755`, private directories to `0700`,
public files to `0644`, and private files to `0600`. An existing world-writable managed upload root is
restricted before use; inability to apply and verify the safe mode fails the upload.

The provided Apache and Nginx development configurations independently deny browser-active and
server-executable suffixes below `/uploads`, disable CGI-style execution where supported, and add
`X-Content-Type-Options: nosniff`. Production deployments must preserve equivalent rules even though
the upload risk policy already rejects these formats through the supported application API.

Final publication still requires local-filesystem atomic hard links. The framework verifies that the
temporary and final names refer to the same inode before removing the temporary name. Filesystems that
do not support hard links fail closed without copying, renaming over an existing target, or exposing a
partial file. Same-filesystem atomic rename support is likewise required for single-use chunk-session
retirement.
