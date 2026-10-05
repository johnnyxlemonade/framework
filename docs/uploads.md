# Uploads

Lemonade provides profile-based generic-file and image uploads through `UploadFactory`. A configured
uploader performs file-size, extension and server-detected MIME validation before it delegates final
publication to `UploadStorage`. Image profiles add image and dimension validation and can re-encode
the published image.

Applications select an existing file or image profile and pass an `UploadedFileInterface` to the
configured uploader. The resulting `UploadedFile` or `UploadedImage` is the same regardless of how
the temporary upload bytes reached the framework.

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
`max_bytes` before it creates temporary data. The client filename is retained only for the existing
extension-validation boundary; it is never used as a filesystem path.

`append($uploadId, $offset, $body)` accepts exactly the next byte offset. It reads at most one
server-authorized chunk, verifies the assembled size against both the declared size and the
profile's maximum, then advances the stored offset only after the full payload is written. Chunks
are intentionally sequential; parallel and out-of-order uploads are not supported.

`complete($uploadId)` verifies the final assembled size and wraps the private payload in a PSR-7
`UploadedFileInterface`. It then delegates to the unchanged standard flow:

```text
UploadFactory -> Configured*Uploader -> UploadService -> validators -> UploadStorage
```

Therefore MIME, extension, image, dimension and re-encoding behavior remain exactly the same as
for a normal multipart upload. A validation failure removes the temporary session; an unexpected
storage or infrastructure failure leaves it available for explicit abort or eventual TTL cleanup.

`abort($uploadId)` removes the session directory, metadata and assembled payload. Calling it for a
missing valid session is safe.

## Limits and temporary storage

`ChunkUploadConfig` defaults to a maximum chunk payload of `2 * 1024 * 1024` bytes (2 MiB) and a
fixed session TTL of 3600 seconds. `ChunkUploadService::chunkBytes()` exposes the server-authoritative
chunk limit to an application endpoint; clients do not select their own maximum.

Temporary data is stored outside the public web root below:

```text
storage/writable/uploads/chunks/<two-hex-character-shard>/<random-upload-id>/
```

Each session contains `meta.json`, `payload.part` once data arrives, and a per-session lock file.
Session IDs are cryptographically random bearer capabilities. The filesystem store serializes append,
complete, abort and cleanup operations for one session.

## Cleanup

Run the framework operational command periodically from cron or another scheduler:

```bash
vendor/bin/lemonade upload:chunks:cleanup
```

It removes expired sessions and sufficiently old sessions whose metadata cannot be read, then reports
the number of removed sessions and released bytes. It does not require a queue worker or a database.
