"""Temporal and data-integrity tests for the real XGBoost forecasting engine."""
import json
import subprocess
import sys
import unittest
from datetime import date, timedelta
from pathlib import Path
from unittest.mock import patch

import numpy as np

from xgboost_forecast import (MAX_HORIZON, direction_probabilities,
                              forecast_asset, new_model, predict_dates, prepare_asset,
                              rolling_evaluation, run, training_arrays)


class XGBoostForecastTests(unittest.TestCase):
    def rows(self, count=160, forex=False, constant=False, start=date(2024, 1, 1)):
        rows, current = [], start
        while len(rows) < count:
            if not forex or current.weekday() < 5:
                index = len(rows)
                price = 100.0 if constant else 100 * np.exp(0.001 * index + 0.01 * np.sin(index / 4))
                rows.append({'fecha': current.isoformat(), 'precio_cierre': float(price)})
            current += timedelta(days=1)
        return rows

    def as_of(self, rows):
        return date.fromisoformat(rows[-1]['fecha']) + timedelta(days=1)

    def test_training_and_features_do_not_see_future(self):
        original = self.rows(180)
        changed = [dict(row) for row in original]
        for row in changed[140:]:
            row['precio_cierre'] *= 4
        baseline = prepare_asset('BTC/USD', original, self.as_of(original))
        modified = prepare_asset('BTC/USD', changed, self.as_of(changed))
        np.testing.assert_array_equal(baseline.features[:140], modified.features[:140])
        first = training_arrays(baseline, 140)
        second = training_arrays(modified, 140)
        np.testing.assert_array_equal(first[0], second[0])
        np.testing.assert_array_equal(first[1], second[1])
        # Refit the real algorithm as well: later prices cannot alter this forecast.
        original_model = new_model().fit(*first)
        changed_model = new_model().fit(*second)
        target_dates = [baseline.dates[139] + timedelta(days=1)]
        np.testing.assert_array_equal(predict_dates(original_model, baseline, 139, target_dates),
                                      predict_dates(changed_model, modified, 139, target_dates))

    def test_labels_exist_only_on_observed_dates(self):
        rows = self.rows(170, forex=True)
        del rows[65]
        asset = prepare_asset('EUR/USD', rows, self.as_of(rows))
        self.assertTrue(all(asset.dates[index].weekday() < 5 for index in asset.sample_targets))
        self.assertTrue(all(0 < (asset.dates[target] - asset.dates[origin]).days <= MAX_HORIZON
                            for origin, target in zip(asset.sample_origins, asset.sample_targets)))
        for position in (0, len(asset.sample_y) // 2, len(asset.sample_y) - 1):
            origin, target = asset.sample_origins[position], asset.sample_targets[position]
            self.assertAlmostEqual(asset.sample_y[position],
                                   np.log(asset.prices[target] / asset.prices[origin]))

    def test_observed_forex_weekends_remain_real_labels(self):
        rows = self.rows(160)
        asset = prepare_asset('USD/PEN', rows, self.as_of(rows))
        self.assertTrue(any(asset.dates[index].weekday() > 4 for index in asset.sample_targets))

    def test_long_gap_keeps_only_recent_segment(self):
        older = self.rows(150, start=date(2022, 1, 1))
        recent = self.rows(130, start=date(2024, 1, 1))
        asset = prepare_asset('BTC/USD', older + recent, self.as_of(recent))
        self.assertEqual(len(asset.dates), 130)
        self.assertEqual(asset.dates[0], date(2024, 1, 1))
        with self.assertRaisesRegex(ValueError, '120 cierres'):
            prepare_asset('BTC/USD', older + recent[:100], self.as_of(recent[:100]))

    def test_invalid_insufficient_duplicate_and_stale_history(self):
        rows = self.rows(160)
        with self.assertRaisesRegex(ValueError, '120 cierres'):
            prepare_asset('BTC/USD', rows[:119], self.as_of(rows[:119]))
        for invalid in (0, -1, float('nan'), float('inf'), True):
            changed = [dict(row) for row in rows]
            changed[40]['precio_cierre'] = invalid
            with self.assertRaises(ValueError):
                prepare_asset('BTC/USD', changed, self.as_of(changed))
        with self.assertRaisesRegex(ValueError, 'duplicadas'):
            prepare_asset('BTC/USD', rows + [rows[40]], self.as_of(rows))
        with self.assertRaisesRegex(ValueError, 'desactualizado'):
            prepare_asset('BTC/USD', rows, self.as_of(rows) + timedelta(days=7))

    def test_today_and_future_are_excluded(self):
        rows = self.rows(160)
        as_of = self.as_of(rows)
        asset = prepare_asset('BTC/USD', rows + [{'fecha': as_of.isoformat(), 'precio_cierre': 1e9},
                                               {'fecha': (as_of + timedelta(days=1)).isoformat(),
                                                'precio_cierre': 2e9}], as_of)
        self.assertEqual(len(asset.dates), 160)
        self.assertEqual(asset.prices[-1], rows[-1]['precio_cierre'])

    def test_window_does_not_use_prices_before_window(self):
        rows = self.rows(600)
        changed = [dict(row) for row in rows]
        for row in changed[:100]:
            row['precio_cierre'] *= 3
        original = prepare_asset('BTC/USD', rows, self.as_of(rows))
        modified = prepare_asset('BTC/USD', changed, self.as_of(rows))
        first, second = training_arrays(original, 600), training_arrays(modified, 600)
        np.testing.assert_array_equal(first[0], second[0])
        np.testing.assert_array_equal(first[1], second[1])

    def test_constant_forecast_actual_xgboost_and_calendar(self):
        rows = self.rows(142, forex=True, constant=True)
        as_of = self.as_of(rows)
        result = forecast_asset('USD/PEN', rows, as_of)
        self.assertEqual(len(result['proyeccion']), 8)
        self.assertEqual(result['observaciones'], 142)
        for offset, point in enumerate(result['proyeccion']):
            self.assertEqual(point['fecha'], (as_of + timedelta(days=offset)).isoformat())
            self.assertAlmostEqual(point['precio'], 100)
            self.assertGreater(point['precio'], 0)
        self.assertAlmostEqual(result['validacion']['error_mape'], 0)
        self.assertAlmostEqual(result['validacion']['error_base'], 0)
        observed_dates = [date.fromisoformat(row['fecha']) for row in rows]
        expected_evaluations = sum(
            1 for stop in range(len(rows) - 21, len(rows))
            for target in observed_dates[stop:]
            if 0 < (target - observed_dates[stop - 1]).days <= 7)
        self.assertEqual(result['validacion']['evaluaciones'], expected_evaluations)
        probabilities = result['probabilidades']
        self.assertGreaterEqual(probabilities['evaluaciones'], 10)
        self.assertEqual(probabilities['subida'], 50)
        self.assertEqual(probabilities['bajada'], 50)
        expected_horizon = (as_of + timedelta(days=7) - observed_dates[-1]).days
        self.assertEqual(result['horizonte_probabilidades'], expected_horizon)

    def test_probability_counts_and_smoothing(self):
        result = direction_probabilities([1] * 20, 105, 100)
        self.assertLess(result['subida'], 100)
        self.assertEqual(result['subida'] + result['bajada'], 100)
        self.assertEqual(direction_probabilities([1] * 20, 100, 100)['subida'], 50)
        self.assertIsNone(direction_probabilities([1] * 9, 105, 100)['subida'])

    def test_probability_replay_keeps_exact_calendar_horizon(self):
        rows = self.rows(220, forex=True, constant=True)
        asset = prepare_asset('EUR/USD', rows, self.as_of(rows))
        horizon = 9
        available = set(asset.dates)
        expected = sum(asset.dates[stop - 1] + timedelta(days=horizon) in available
                       for stop in range(len(rows) - 60, len(rows)))
        with patch('xgboost_forecast.fit_at', return_value=object()), \
                patch('xgboost_forecast.predict_dates',
                      side_effect=lambda model, data, origin, dates: np.full(len(dates), 100.0)):
            _, ratios = rolling_evaluation(asset, horizon)
        self.assertEqual(len(ratios), expected)

    def test_forex_weekend_is_predicted_on_its_calendar_date(self):
        rows = self.rows(160, forex=True)
        asset = prepare_asset('EUR/USD', rows, self.as_of(rows))
        origin = len(asset.dates) - 1
        friday = asset.dates[origin] + timedelta(days=1)
        while friday.weekday() != 4:
            friday += timedelta(days=1)
        dates = [friday + timedelta(days=offset) for offset in range(4)]

        class IncreasingReturns:
            def predict(self, x):
                return x[:, -2] * 0.001

        forecast = predict_dates(IncreasingReturns(), asset, origin, dates)
        self.assertTrue((np.diff(forecast) > 0).all())

    def test_batch_preserves_errors_without_faking_predictions(self):
        response = run({'as_of': '2024-10-01', 'assets': [
            {'par': 'BTC/USD', 'rows': []}, {'par': 'DOGE/USD', 'rows': []}]})
        self.assertEqual(response['results'], {})
        self.assertEqual(set(response['errors']), {'BTC/USD', 'DOGE/USD'})

    def test_cli_returns_only_utf8_json_for_an_error(self):
        script = Path(__file__).with_name('xgboost_forecast.py')
        payload = {'as_of': '2024-10-01', 'assets': [{'par': 'BTC/USD', 'rows': []}]}
        completed = subprocess.run([sys.executable, '-B', str(script)],
                                   input=json.dumps(payload).encode('utf-8'),
                                   capture_output=True, check=True, timeout=15)
        response = json.loads(completed.stdout.decode('utf-8'))
        self.assertEqual(response['results'], {})
        self.assertIn('120 cierres', response['errors']['BTC/USD'])


if __name__ == '__main__':
    unittest.main()
