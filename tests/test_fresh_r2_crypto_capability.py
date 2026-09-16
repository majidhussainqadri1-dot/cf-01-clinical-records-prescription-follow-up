import unittest
from pathlib import Path

SOURCE = (Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes' / 'class-cf01-crypto.php').read_text(encoding='utf-8')


class FreshR2CryptoCapabilityTests(unittest.TestCase):
    def test_available_requires_actual_cipher_support(self):
        available = SOURCE.split('public static function available(): bool', 1)[1].split('public static function key(): ?string', 1)[0]
        self.assertIn("function_exists('openssl_get_cipher_methods')", available)
        self.assertIn("array_map('strtolower', openssl_get_cipher_methods())", available)
        self.assertIn('in_array(self::CIPHER, $methods, true)', available)


if __name__ == '__main__':
    unittest.main()
