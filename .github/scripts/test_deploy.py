"""Offline checks for packaging and the non-deletion safety boundary."""
from pathlib import Path
import tempfile
import unittest
from guard_deploy import protected_paths
from prepare_deploy import allowed


class DeploySafetyTests(unittest.TestCase):
    def test_private_and_local_files_never_enter_package(self):
        for name in ['config/database.php', '.env', '.git/config', '.github/workflows/deploy.yml',
                     'database/schema.sql', 'public/uploads/avatar.jpg', 'public/.htaccess',
                     'public/node_modules/a.js', 'src/cache/a.php', 'src/test.local.php',
                     'public/error.log', 'public/index.php.bak', 'src/.env.php',
                     'src/credentials.php']:
            with self.subTest(name=name):
                self.assertFalse(allowed(name))
        for name in ['config/app.php', 'public/index.php', 'public/css/app.css',
                     'src/models/UserModel.php', 'views/dashboard/index.php']:
            self.assertTrue(allowed(name))

    def test_removed_files_and_folders_are_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'public').mkdir()
            (root / 'public/index.php').write_text('updated')
            state = {'version': '1.0.0', 'data': [
                {'name': 'public', 'type': 'folder'},
                {'name': 'public/index.php', 'type': 'file'},
                {'name': 'public/old.php', 'type': 'file'},
                {'name': 'old', 'type': 'folder'},
                {'name': 'old/data.php', 'type': 'file'},
            ]}
            rules = protected_paths(state, root).splitlines()
            self.assertIn('public/old.php', rules)
            self.assertIn('old/', rules)
            self.assertIn('old/**', rules)
            self.assertIn('old/data.php', rules)
            self.assertNotIn('public/index.php', rules)
            self.assertNotIn('public/', rules)

    def test_unknown_state_and_unsafe_paths_fail_closed(self):
        for state in [{'version': '2', 'data': []},
                      {'version': '1.0.0', 'data': [{'name': '../x', 'type': 'file'}]},
                      {'version': '1.0.0', 'data': [{'name': 'x*', 'type': 'file'}]}]:
            with self.assertRaises(ValueError):
                protected_paths(state, Path('nonexistent-package'))


if __name__ == '__main__':
    unittest.main()
