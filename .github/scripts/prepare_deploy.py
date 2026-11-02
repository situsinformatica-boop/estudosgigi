"""Build a deployment package from tracked, explicitly allowed application files."""
from pathlib import Path
import shutil
import subprocess

PACKAGE = Path('.deploy')
ALLOWED = {'public', 'src', 'views'}
DENIED_DIRS = {'uploads', 'cache', 'caches', 'logs', 'tmp', 'temp', 'backups',
               'backup', 'node_modules', 'vendor', 'tests'}
EXTENSIONS = {'.php', '.css', '.js', '.html', '.svg', '.png', '.jpg', '.jpeg',
              '.webp', '.gif', '.ico', '.woff', '.woff2'}


def allowed(name):
    path = Path(name)
    parts = [part.lower() for part in path.parts]
    if name == 'config/app.php':
        return True
    if not parts or parts[0] not in ALLOWED:
        return False
    if any(p.startswith('.') or p in DENIED_DIRS for p in parts):
        return False
    if any(token in path.name.lower() for token in
           ('credential', 'secret', '.local.', '.backup.', '.bak.', '.test.')):
        return False
    return path.suffix.lower() in EXTENSIONS


def main():
    if PACKAGE.exists():
        raise SystemExit('Package already exists; refusing to reuse stale files.')
    PACKAGE.mkdir()
    names = subprocess.check_output(['git', 'ls-files', '-z']).decode().split('\0')
    for name in sorted(filter(None, names)):
        source = Path(name)
        if not allowed(name):
            print('IGNORE:', name)
            continue
        if source.is_symlink() or any(p.is_symlink() for p in source.parents):
            raise SystemExit('Symlinks are not allowed: ' + name)
        target = PACKAGE / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
        print('PACKAGE:', name)
    for required in ('public/index.php', 'config/app.php', 'src/helpers/auth.php'):
        if not (PACKAGE / required).is_file():
            raise SystemExit('Missing required application file: ' + required)
    print('Hosting .htaccess files, database configuration and uploads are preserved.')


if __name__ == '__main__':
    main()
