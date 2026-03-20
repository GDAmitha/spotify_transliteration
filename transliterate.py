#!/usr/bin/env python3
import base64
import json
import sys

from hangul_romanize import Transliter  # type: ignore[import]
from hangul_romanize.rule import academic  # type: ignore[import]


def decode_payload() -> list[str]:
    if len(sys.argv) > 1:
        try:
            payload = base64.b64decode(sys.argv[1]).decode('utf-8')
        except Exception:
            sys.exit(1)
        try:
            return json.loads(payload)
        except json.JSONDecodeError:
            sys.exit(1)

    try:
        payload = json.load(sys.stdin)
    except json.JSONDecodeError:
        sys.exit(1)

    if not isinstance(payload, list):
        sys.exit(1)

    return payload


def main() -> None:
    payload = decode_payload()
    transliter = Transliter(academic)
    result = []

    for item in payload:
        if isinstance(item, str) and item:
            result.append(transliter.translit(item))
        else:
            result.append('')

    json.dump(result, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
