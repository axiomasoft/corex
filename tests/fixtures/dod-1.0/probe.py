#!/usr/bin/env python3
"""Fixture probe for planted DoD negatives. Exit 0 unless the named plant exists."""

from __future__ import annotations

import sys
from pathlib import Path

MESSAGES = {
    "missing-api-symbol": "missing API symbol",
    "internal-import": "foreign Internal import",
}


def main() -> int:
    kind = sys.argv[1]
    plant = Path(sys.argv[2])
    if (plant / kind).is_file():
        print(MESSAGES[kind])
        return 1
    print("ok")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
