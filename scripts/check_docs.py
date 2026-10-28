#!/usr/bin/env python3
"""Check local inline Markdown/HTML links in the repository's entry documents.

No network requests or product dependencies. Explicit file arguments override
the default scope. Reference-style links and fragments are intentionally omitted.
"""
from __future__ import annotations

import argparse
import re
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit

DEFAULT_FILES = (
    "README.md", "CONTRIBUTING.md", "SECURITY.md", "docs/README.md",
    "docs/CI_CD.md", ".github/pull_request_template.md",
    "docs/CLONAR_PROYECTOS.md",
)
INLINE_LINK = re.compile(r'!?\[[^\]\n]*\]\(\s*(<[^>\n]+>|[^\s)]+)(?:\s+"[^"\n]*")?\s*\)')
CONFLICT = re.compile(r"^(?:<{7}|={7}|>{7})(?:\s|$)", re.MULTILINE)


class HtmlLinks(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.targets: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        for key, value in attrs:
            if value and ((tag == "a" and key == "href") or (tag == "img" and key == "src")):
                self.targets.append(value)


def prose_only(content: str) -> str:
    lines: list[str] = []
    fence: str | None = None
    for line in content.splitlines():
        marker = re.match(r"^\s{0,3}(`{3,}|~{3,})", line)
        if marker:
            token = marker.group(1)
            if fence is None:
                fence = token
            elif token[0] == fence[0] and len(token) >= len(fence):
                fence = None
            lines.append("")
        elif fence is None:
            lines.append(line)
    return re.sub(r"`+[^`\n]*`+", "", "\n".join(lines))


def check(root: Path, relative: str) -> list[str]:
    source = (root / relative).resolve()
    if not source.is_relative_to(root):
        return [f"{relative}: document is outside this repository"]
    try:
        content = source.read_text(encoding="utf-8-sig")
    except (OSError, UnicodeError) as error:
        return [f"{relative}: {error}"]
    errors: list[str] = []
    if not content.strip():
        errors.append(f"{relative}: empty document")
    if CONFLICT.search(content):
        errors.append(f"{relative}: unresolved merge conflict")
    prose = prose_only(content)
    html = HtmlLinks()
    html.feed(prose)
    targets = [match.group(1).strip("<>") for match in INLINE_LINK.finditer(prose)]
    for target in sorted(set(targets + html.targets)):
        # No external fetches, including mailto:, data:, or protocol-relative URLs.
        if target.startswith(("#", "//")) or re.match(r"^[a-zA-Z][a-zA-Z0-9+.-]*:", target):
            continue
        path = unquote(urlsplit(target).path)
        if not path:
            continue
        destination = (root / path.lstrip("/") if path.startswith("/") else source.parent / path).resolve()
        if not destination.is_relative_to(root):
            errors.append(f"{relative}: link escapes this repository: {target}")
        elif not destination.exists():
            errors.append(f"{relative}: missing local target: {target}")
    return errors


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("files", nargs="*", help="Repository-relative Markdown files")
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    files = args.files or DEFAULT_FILES
    errors = [error for relative in files for error in check(root, relative)]
    for error in errors:
        print(f"ERROR: {error}")
    if errors:
        return 1
    print(f"OK: {len(files)} documents; local inline Markdown/HTML links resolve.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
