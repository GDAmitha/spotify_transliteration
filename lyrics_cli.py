#!/usr/bin/env python3
"""
CLI tool to fetch Spotify lyrics via the PHP API.

Usage:
    python lyrics_cli.py -t <track_id> [--format lrc|srt|raw]
    python lyrics_cli.py -u <spotify_url> [--format lrc|srt|raw]
"""

import argparse
import json
import sys

import requests


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Fetch Spotify lyrics from the PHP API",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python lyrics_cli.py -t 4PTG3Z6ehGkBFwjybzWkR8
  python lyrics_cli.py -u "https://open.spotify.com/track/4PTG3Z6ehGkBFwjybzWkR8"
  python lyrics_cli.py -t 4PTG3Z6ehGkBFwjybzWkR8 --format lrc
        """,
    )

    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument(
        "-t", "--track-id",
        help="Spotify track ID",
    )
    group.add_argument(
        "-u", "--url",
        help="Full Spotify track URL",
    )

    parser.add_argument(
        "-f", "--format",
        choices=["lrc", "srt", "raw"],
        default=None,
        help="Output format (lrc, srt, raw). Default: JSON with all metadata",
    )

    parser.add_argument(
        "--api-url",
        default="http://localhost:8000",
        help="Base URL of the PHP API (default: http://localhost:8000)",
    )

    return parser.parse_args()


def fetch_lyrics(api_url: str, track_id: str | None, url: str | None, fmt: str | None) -> dict:
    """Fetch lyrics from the PHP API."""
    params = {}

    if track_id:
        params["trackid"] = track_id
    elif url:
        params["url"] = url

    if fmt:
        params["format"] = fmt

    endpoint = f"{api_url.rstrip('/')}/index.php"

    try:
        response = requests.get(endpoint, params=params, timeout=30)
        response.raise_for_status()
        return response.json()
    except requests.exceptions.ConnectionError:
        print(f"Error: Could not connect to API at {endpoint}", file=sys.stderr)
        print("Make sure the PHP server is running (e.g., php -S localhost:8000)", file=sys.stderr)
        sys.exit(1)
    except requests.exceptions.Timeout:
        print("Error: Request timed out", file=sys.stderr)
        sys.exit(1)
    except requests.exceptions.HTTPError as e:
        try:
            error_data = response.json()
            print(f"Error: {error_data.get('message', str(e))}", file=sys.stderr)
        except (json.JSONDecodeError, ValueError):
            print(f"Error: HTTP {response.status_code}", file=sys.stderr)
        sys.exit(1)
    except json.JSONDecodeError:
        print("Error: Invalid JSON response from API", file=sys.stderr)
        sys.exit(1)


def format_output(data: dict, fmt: str | None) -> str:
    """Format the lyrics data for output."""
    if data.get("error"):
        return json.dumps(data, indent=2)

    lines = data.get("lines", [])

    # Raw format returns a string directly
    if fmt == "raw" and isinstance(lines, str):
        return lines

    # LRC format
    if fmt == "lrc" and isinstance(lines, list):
        output_lines = []
        for line in lines:
            time_tag = line.get("timeTag", "")
            words = line.get("words", "")
            output_lines.append(f"[{time_tag}] {words}")
        return "\n".join(output_lines)

    # SRT format
    if fmt == "srt" and isinstance(lines, list):
        output_lines = []
        for line in lines:
            idx = line.get("index", 0)
            start = line.get("startTime", "")
            end = line.get("endTime", "")
            words = line.get("words", "")
            output_lines.append(f"{idx}")
            output_lines.append(f"{start} --> {end}")
            output_lines.append(words)
            output_lines.append("")
        return "\n".join(output_lines)

    # Default: JSON output
    return json.dumps(data, indent=2, ensure_ascii=False)


def main() -> None:
    args = parse_args()

    data = fetch_lyrics(
        api_url=args.api_url,
        track_id=args.track_id,
        url=args.url,
        fmt=args.format,
    )

    output = format_output(data, args.format)
    print(output)


if __name__ == "__main__":
    main()
