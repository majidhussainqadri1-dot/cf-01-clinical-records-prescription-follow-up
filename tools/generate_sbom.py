#!/usr/bin/env python3
"""Generate a deterministic SPDX 2.3 JSON SBOM for the staged CF-01 plugin.

The generator uses only the Python standard library. It records every regular
file in the staged package, SHA-256/SHA-1 checksums, a deterministic package
verification code and a content-derived document namespace. No local paths,
current clock values, credentials or environment-specific identifiers enter the
output.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path


def digest(path: Path, algorithm: str) -> str:
    hasher = hashlib.new(algorithm)
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            hasher.update(chunk)
    return hasher.hexdigest()


def spdx_file_id(relative_path: str) -> str:
    token = hashlib.sha256(relative_path.encode("utf-8")).hexdigest()[:24]
    return f"SPDXRef-File-{token}"


def utc_timestamp(source_date_epoch: int) -> str:
    value = datetime.fromtimestamp(source_date_epoch, tz=timezone.utc)
    return value.replace(microsecond=0).isoformat().replace("+00:00", "Z")


def generate_sbom(root: Path, name: str, version: str, source_date_epoch: int) -> dict:
    root = root.resolve()
    if not root.is_dir():
        raise ValueError(f"SBOM root is not a directory: {root}")

    records: list[dict[str, str]] = []
    for path in sorted((item for item in root.rglob("*") if item.is_file()), key=lambda item: item.as_posix()):
        relative = path.relative_to(root).as_posix()
        records.append(
            {
                "path": relative,
                "sha1": digest(path, "sha1"),
                "sha256": digest(path, "sha256"),
                "spdx_id": spdx_file_id(relative),
            }
        )

    if not records:
        raise ValueError("SBOM root contains no files")

    verification_input = "".join(sorted(record["sha1"] for record in records))
    verification_code = hashlib.sha1(verification_input.encode("ascii")).hexdigest()

    namespace_input = "\n".join(
        f"{record['path']}\t{record['sha256']}" for record in records
    ).encode("utf-8")
    namespace_hash = hashlib.sha256(namespace_input).hexdigest()
    document_namespace = (
        "https://sabrihomeopathy.com/spdx/"
        f"cf-01/{version}/{namespace_hash}"
    )

    files = [
        {
            "SPDXID": record["spdx_id"],
            "checksums": [
                {"algorithm": "SHA1", "checksumValue": record["sha1"]},
                {"algorithm": "SHA256", "checksumValue": record["sha256"]},
            ],
            "copyrightText": "NOASSERTION",
            "fileName": f"./{record['path']}",
            "licenseConcluded": "NOASSERTION",
        }
        for record in records
    ]

    relationships = [
        {
            "spdxElementId": "SPDXRef-DOCUMENT",
            "relationshipType": "DESCRIBES",
            "relatedSpdxElement": "SPDXRef-Package",
        }
    ]
    relationships.extend(
        {
            "spdxElementId": "SPDXRef-Package",
            "relationshipType": "CONTAINS",
            "relatedSpdxElement": record["spdx_id"],
        }
        for record in records
    )

    return {
        "SPDXID": "SPDXRef-DOCUMENT",
        "creationInfo": {
            "created": utc_timestamp(source_date_epoch),
            "creators": ["Tool: CF-01 deterministic SPDX SBOM generator-1.0.0"],
        },
        "dataLicense": "CC0-1.0",
        "documentNamespace": document_namespace,
        "files": files,
        "name": name,
        "packages": [
            {
                "SPDXID": "SPDXRef-Package",
                "copyrightText": "NOASSERTION",
                "downloadLocation": "NOASSERTION",
                "filesAnalyzed": True,
                "licenseConcluded": "NOASSERTION",
                "licenseDeclared": "NOASSERTION",
                "name": "Sabri Clinical Records",
                "packageVerificationCode": {
                    "packageVerificationCodeValue": verification_code
                },
                "versionInfo": version,
            }
        ],
        "relationships": relationships,
        "spdxVersion": "SPDX-2.3",
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", required=True, type=Path)
    parser.add_argument("--name", required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--source-date-epoch", required=True, type=int)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()

    document = generate_sbom(
        args.root, args.name, args.version, args.source_date_epoch
    )
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(document, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
