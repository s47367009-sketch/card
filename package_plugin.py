"""Build installable plugin ZIPs; run from any working directory."""
from pathlib import Path
import hashlib
import re
import shutil
import zipfile

ROOT = Path(__file__).resolve().parent


def zip_plugin():
    source = ROOT / 'cartara-pro'
    header = (source / 'cartara-pro.php').read_text(encoding='utf-8')
    version = re.search(r'^ \* Version: ([\d.]+)$', header, re.MULTILINE).group(1)
    output = ROOT / 'releases' / f'cartara-pro-v{version}.zip'
    output.parent.mkdir(exist_ok=True)
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
        for path in sorted(source.rglob('*')):
            if path.is_file() and not any(p.startswith('.') for p in path.relative_to(source).parts):
                entry = zipfile.ZipInfo(path.relative_to(ROOT).as_posix(), (2026, 9, 9, 0, 0, 0))
                entry.compress_type = zipfile.ZIP_DEFLATED
                entry.external_attr = 0o100644 << 16
                archive.writestr(entry, path.read_bytes())
    with zipfile.ZipFile(output) as archive:
        assert archive.testzip() is None
        assert 'cartara-pro/cartara-pro.php' in archive.namelist()
        assert all(name.startswith('cartara-pro/') for name in archive.namelist())
    shutil.copyfile(output, ROOT / 'cartara-pro.zip')
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix('.sha256').write_text(f'{digest}  {output.name}\n')
    print(f'Created {output}\nSHA-256: {digest}')


if __name__ == '__main__':
    zip_plugin()
