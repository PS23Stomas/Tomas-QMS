---
name: PostgreSQL BYTEA via PDO
description: How to correctly insert binary (PDF) data into BYTEA columns using PDO with PostgreSQL
---

## Rule
Never pass raw binary strings as PDO parameters for BYTEA columns — PostgreSQL rejects them with "invalid byte sequence for encoding UTF8".

## How to apply
Use `decode(?, 'hex')` in the SQL and pass `bin2hex($binaryData)` as the parameter:

```php
$stmt = $pdo->prepare("INSERT INTO table (col) VALUES (decode(?,'hex'))");
$stmt->execute([bin2hex($binaryContent)]);
```

**Why:** PDO sends parameters as text. PostgreSQL's UTF-8 encoding rejects arbitrary binary bytes (e.g. 0xe2 0xe3 0xcf in PDF headers). The hex decode approach bypasses encoding validation entirely.

**How to apply:** Any time binary data (PDFs, images, etc.) is written to a BYTEA column via PDO prepared statements in this project.
