"""Build a WordPress ZIP containing exactly the plugin directory."""
from pathlib import Path
import re
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
plugin = root / "tms-shiptimize-fix"
if not (plugin / 'vendor/phone/src/PhoneNumberUtil.php').is_file():
    raise SystemExit('Run python3 tools/vendor.py before packaging')
header = (plugin / "tms-shiptimize-fix.php").read_text(encoding="utf-8")
version = re.search(r"^ \* Version: ([0-9]+\.[0-9]+\.[0-9]+)$", header, re.M).group(1)
if len(sys.argv) > 1 and sys.argv[1] != f"v{version}":
    raise SystemExit("Release tag must match the plugin version")
dest = root / "dist" / "tms-shiptimize-fix.zip"
dest.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(dest, "w", zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(plugin.rglob("*")):
        if path.is_file():
            archive.write(path, path.relative_to(root).as_posix())
with zipfile.ZipFile(dest) as archive:
    assert archive.testzip() is None
    assert "tms-shiptimize-fix/tms-shiptimize-fix.php" in archive.namelist()
print(f"Built {dest.name} for {version} ({dest.stat().st_size} bytes)")
