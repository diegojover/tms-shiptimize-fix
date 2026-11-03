"""Assemble pinned dependencies, retain licenses, and isolate PHP namespaces."""
import argparse
import hashlib
import io
from pathlib import Path, PurePosixPath
from urllib.request import Request, urlopen
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1] / 'tms-shiptimize-fix' / 'vendor'
PACKAGES = [
    ('phone', 'giggsey/libphonenumber-for-php-lite',
     '6b9f5e5e2ff347b566ee6ea558cd56f878a3ea2e',
     '5fbe778476e7b0a1f7b04e96b023d4870850bc717a1e8c5decf1b6afbf312f8d'),
    ('mbstring', 'symfony/polyfill-mbstring',
     '122889ed24dc000165bcbfd77ec412f15cbdfc6a',
     '560bc33ca56b79d9c285d9a2f14058ac489b6e6341df75c4e49cf87434c6e3ce'),
]
parser = argparse.ArgumentParser()
parser.add_argument('--phone-archive', type=Path)
parser.add_argument('--mbstring-archive', type=Path)
args = parser.parse_args()
for name, repo, commit, digest in PACKAGES:
    local = getattr(args, name + '_archive')
    if local:
        data = local.read_bytes()
    else:
        request = Request(f'https://api.github.com/repos/{repo}/zipball/{commit}',
                          headers={'User-Agent': 'TMS-plugin-build'})
        with urlopen(request, timeout=60) as response:
            data = response.read(20_000_001)
    if hashlib.sha256(data).hexdigest() != digest:
        raise SystemExit(f'{name}: dependency checksum mismatch')
    with ZipFile(io.BytesIO(data)) as archive:
        for item in archive.infolist():
            relative = PurePosixPath(item.filename)
            if item.is_dir() or len(relative.parts) < 2:
                continue
            parts = relative.parts[1:]
            if any(part in ('..', '') for part in parts):
                raise SystemExit('Invalid dependency path')
            if name == 'phone' and parts[0] not in ('src', 'LICENSE.txt'):
                continue
            target = ROOT / name / Path(*parts)
            if not target.resolve().is_relative_to((ROOT / name).resolve()):
                raise SystemExit('Dependency path escaped vendor directory')
            content = archive.read(item)
            if target.suffix == '.php':
                content = content.decode('utf-8').replace('libphonenumber', 'TMSVendor\\Phone').replace(
                    'Symfony\\Polyfill\\Mbstring', 'TMSVendor\\Mbstring').encode('utf-8')
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(content)
    print(f'{name}: verified and assembled')
