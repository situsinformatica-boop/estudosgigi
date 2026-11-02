"""Read-only FTPS preflight. Never write credentials or mutate the FTP server."""
import ftplib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import ssl

STATE = '.estudosgigi-deploy-state.json'


class ReusedTLS(ftplib.FTP_TLS):
    # cPanel FTP servers may require TLS session reuse on data connections.
    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host,
                                            session=self.sock.session)
        return conn, size


def protected_paths(state, package):
    if state.get('version') != '1.0.0' or not isinstance(state.get('data'), list):
        raise ValueError('Unknown deployment state format; stop for review.')
    excluded = ['**/.git*', '**/.git*/**', '**/node_modules/**', '**/.env*',
                '**/.htaccess', 'config/database.php', 'public/uploads/**']
    for item in state['data']:
        name = item['name']
        # Exact safe globs only. Fail closed for unusual or unsafe state entries.
        if (not re.fullmatch(r'[A-Za-z0-9_./-]+', name)
                or name.startswith('/') or '..' in PurePosixPath(name).parts
                or item['type'] not in ('file', 'folder')):
            raise ValueError('Unsafe entry in deployment state; stop for review.')
        local = package / name
        expected = local.is_dir() if item['type'] == 'folder' else local.is_file()
        if local.exists() and not expected:
            raise ValueError('File/directory type changed; manual review required: ' + name)
        if not expected:
            excluded.append(name)
            if item['type'] == 'folder':
                excluded.extend([name.rstrip('/') + '/', name.rstrip('/') + '/**'])
            print('PRESERVE REMOTE:', name)
    return '\n'.join(excluded)


def main():
    required = ('FTP_SERVER', 'FTP_USERNAME', 'FTP_PASSWORD', 'FTP_SERVER_DIR')
    for key in required:
        if not os.environ.get(key):
            raise ValueError('Missing GitHub Secret: ' + key)
    host = os.environ['FTP_SERVER']
    directory = os.environ['FTP_SERVER_DIR']
    if not re.fullmatch(r'[A-Za-z0-9.-]+', host):
        raise ValueError('FTP_SERVER must be a hostname without protocol, port or path.')
    if (not directory.endswith('/') or not re.fullmatch(r'[A-Za-z0-9_./-]+', directory)
            or '..' in PurePosixPath(directory).parts):
        raise ValueError('FTP_SERVER_DIR must be a safe FTP path ending in /.')
    context = ssl.create_default_context()
    context.minimum_version = ssl.TLSVersion.TLSv1_2
    context.maximum_version = ssl.TLSVersion.TLSv1_2
    with ReusedTLS(context=context, timeout=60) as ftp:
        ftp.connect(host, 21)
        ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        ftp.cwd(directory)  # Fail rather than create or guess the destination.
        print('FTPS authenticated; existing destination accessible (FTP_SERVER_DIR).')
        print('FTP working directory:', ftp.pwd())
        # MLSD is structured and includes hidden entries on cPanel servers.
        # Fail closed if the server cannot supply this listing.
        names = {name for name, facts in ftp.mlsd()}
        state = {'version': '1.0.0', 'data': []}
        if STATE in names:
            data = io.BytesIO()
            def collect(chunk):
                if data.tell() + len(chunk) > 5_000_000:
                    raise ValueError('Deployment state too large; stop for review.')
                data.write(chunk)
            ftp.retrbinary('RETR ' + STATE, collect)
            state = json.loads(data.getvalue())
        excluded = protected_paths(state, Path('.deploy'))
    # Only exclusion patterns are written, never connection details or passwords.
    with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as output:
        output.write('exclude<<END_EXCLUDES\n' + excluded + '\nEND_EXCLUDES\n')


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        message = str(error)
        for key in ('FTP_PASSWORD', 'FTP_USERNAME'):
            value = os.environ.get(key)
            if value:
                message = message.replace(value, '***')
        raise SystemExit('FTPS preflight failed: ' + message)
