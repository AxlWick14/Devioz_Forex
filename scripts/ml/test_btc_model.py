import tempfile
import unittest
from pathlib import Path

import numpy as np
import pandas as pd

from btc_model import FEATURES, features, load_data


class BitcoinDataTests(unittest.TestCase):
    def series(self):
        close = 100 + np.sin(np.arange(300) / 3)
        return pd.DataFrame({
            'par': 'BTC/USD', 'fecha': pd.date_range('2020-01-01', periods=300),
            'precio_apertura': close, 'precio_maximo': close + 1,
            'precio_minimo': close - 1, 'precio_cierre': close,
        })

    def test_features_do_not_see_future(self):
        df = self.series()
        expected = features(df).iloc[:200]
        df.loc[200:, ['precio_apertura', 'precio_maximo', 'precio_minimo', 'precio_cierre']] *= 4
        pd.testing.assert_frame_equal(expected, features(df).iloc[:200])

    def test_flat_prices_have_finite_features(self):
        df = self.series()
        df['precio_cierre'] = df['precio_apertura'] = 100
        x = features(df).iloc[-1]
        self.assertEqual(list(x.index), FEATURES)
        self.assertEqual(x['rsi_14'], 50)
        self.assertTrue(np.isfinite(x.to_numpy()).all())

    def test_missing_day_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'btc.csv'
            self.series().drop(index=120).to_csv(path, sep=';', index=False)
            with self.assertRaisesRegex(ValueError, 'Faltan días'):
                load_data(path)


if __name__ == '__main__':
    unittest.main()
