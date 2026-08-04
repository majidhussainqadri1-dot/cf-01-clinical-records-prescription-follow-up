#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1785792000}"
VERSION="1.0.0"
NAME="cf-01-clinical-records-prescription-follow-up-${VERSION}"
BUILD="${ROOT}/build"
STAGE="${BUILD}/stage/sabri-clinical-records"
ZIP="${BUILD}/${NAME}.zip"

rm -rf "${BUILD}"
mkdir -p "${STAGE}"
rsync -a --delete --exclude='.DS_Store' --exclude='*.log' "${ROOT}/sabri-clinical-records/" "${STAGE}/"

find "${STAGE}" -type f -print0 | xargs -0 touch -d "@${SOURCE_DATE_EPOCH}"
(
  cd "${STAGE}"
  find . -type f ! -name 'MANIFEST.sha256' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > MANIFEST.sha256
)
touch -d "@${SOURCE_DATE_EPOCH}" "${STAGE}/MANIFEST.sha256"

mkdir -p "${BUILD}"
(
  cd "${BUILD}/stage"
  find sabri-clinical-records -type f -print | LC_ALL=C sort | zip -X -q "${ZIP}" -@
)
sha256sum "${ZIP}" > "${ZIP}.sha256"
(
  cd "${STAGE}"
  sha256sum -c MANIFEST.sha256 >/dev/null
)
cp "${STAGE}/MANIFEST.sha256" "${BUILD}/${NAME}.manifest.sha256"
rm -rf "${BUILD}/stage"
printf 'Built %s\nSHA-256 %s\n' "$(basename "${ZIP}")" "$(sha256sum "${ZIP}" | awk '{print $1}')"
