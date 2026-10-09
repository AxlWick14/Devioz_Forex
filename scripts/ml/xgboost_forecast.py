"""Direct calendar-horizon XGBoost forecasts from confirmed closing prices.

Input and output are JSON; this module never downloads data or writes artifacts.
Rolling evaluation fits only labels whose closing date was known at the origin.
"""
import json
import math
import sys
from dataclasses import dataclass
from datetime import date, timedelta

import numpy as np
import xgboost
from xgboost import XGBRegressor


SUPPORTED_PAIRS = {'USD/PEN', 'EUR/USD', 'BTC/USD', 'ETH/USD'}
WINDOW = 500
MIN_OBSERVATIONS = 120  # Operational choice, not a scientific accuracy guarantee.
LOOKBACK = 30
MAX_HORIZON = 14
ENGINE_VERSION = 'direct-log-v1'


@dataclass
class Asset:
    pair: str
    dates: list
    prices: np.ndarray
    features: np.ndarray
    sample_x: np.ndarray
    sample_y: np.ndarray
    sample_origins: np.ndarray
    sample_targets: np.ndarray


def parse_date(value):
    if not isinstance(value, str):
        raise ValueError('Fecha inválida; usa YYYY-MM-DD.')
    try:
        result = date.fromisoformat(value)
    except ValueError as error:
        raise ValueError('Fecha inválida; usa YYYY-MM-DD.') from error
    if result.isoformat() != value:
        raise ValueError('Fecha inválida; usa YYYY-MM-DD.')
    return result


def close_features(dates, prices):
    """Each feature row sees only the last 30 actual observations, including t."""
    result = np.full((len(prices), 13), np.nan, dtype=np.float64)
    for index in range(LOOKBACK - 1, len(prices)):
        recent = prices[index - LOOKBACK + 1:index + 1]
        returns = np.diff(np.log(recent))
        last = recent[-1]
        values = [math.log(last / recent[-lag - 1]) for lag in (1, 3, 7, 14, 29)]
        values.extend(math.log(last / recent[-days:].mean()) for days in (5, 10, 30))
        values.extend(float(returns[-days:].std(ddof=1)) for days in (7, 14, 29))
        values.extend(((dates[index] - dates[index - 1]).days,
                       (dates[index] - dates[index - LOOKBACK + 1]).days))
        result[index] = values
    return result


def prepare_asset(pair, rows, as_of):
    if pair not in SUPPORTED_PAIRS:
        raise ValueError('Activo no permitido para XGBoost.')
    if not isinstance(rows, list) or len(rows) > 20000:
        raise ValueError('Histórico inválido o demasiado grande.')
    observations = {}
    for row in rows:
        if not isinstance(row, dict):
            raise ValueError('Una fila del histórico es inválida.')
        observed_date = parse_date(row.get('fecha'))
        # Today's unfinished candle and later rows must not affect any calculation.
        if observed_date >= as_of:
            continue
        if observed_date in observations:
            raise ValueError('Hay fechas duplicadas en el histórico.')
        try:
            raw_price = row['precio_cierre']
            if isinstance(raw_price, bool):
                raise ValueError
            price = float(raw_price)
        except (KeyError, TypeError, ValueError, OverflowError) as error:
            raise ValueError('Hay un cierre inválido en el histórico.') from error
        if not math.isfinite(price) or price <= 0:
            raise ValueError('Los cierres deben ser positivos y finitos.')
        observations[observed_date] = price
    ordered = sorted(observations)
    # Never bridge a long missing interval with features or training labels.
    segment_start = 0
    for index in range(1, len(ordered)):
        if (ordered[index] - ordered[index - 1]).days > 7:
            segment_start = index
    dates = ordered[segment_start:]
    if len(dates) < MIN_OBSERVATIONS:
        raise ValueError('Se requieren 120 cierres reales en el tramo reciente.')
    if (as_of - dates[-1]).days + 7 > MAX_HORIZON:
        raise ValueError('El último cierre está desactualizado; actualiza el histórico.')
    prices = np.asarray([observations[item] for item in dates], dtype=np.float64)
    features = close_features(dates, prices)
    samples, labels, origins, targets = [], [], [], []
    for origin in range(LOOKBACK - 1, len(dates) - 1):
        for target in range(origin + 1, len(dates)):
            horizon = (dates[target] - dates[origin]).days
            if horizon > MAX_HORIZON:
                break
            # Targets are actual observed closes, never a forward-filled label.
            samples.append([*features[origin], horizon, dates[target].weekday()])
            labels.append(math.log(prices[target] / prices[origin]))
            origins.append(origin)
            targets.append(target)
    return Asset(pair, dates, prices, features,
                 np.asarray(samples, dtype=np.float64).reshape(-1, 15),
                 np.asarray(labels, dtype=np.float64),
                 np.asarray(origins, dtype=np.int64),
                 np.asarray(targets, dtype=np.int64))


def training_arrays(asset, stop):
    """stop is exclusive: no feature or target from stop or later may enter fit."""
    start = max(0, stop - WINDOW)
    if stop - start < MIN_OBSERVATIONS:
        return None
    # The first feature also uses only observations within this fold's window.
    usable = ((asset.sample_origins >= start + LOOKBACK - 1)
              & (asset.sample_targets < stop))
    x, y = asset.sample_x[usable], asset.sample_y[usable]
    if not len(y) or not np.isfinite(x).all() or not np.isfinite(y).all():
        return None
    return x, y


def new_model():
    return XGBRegressor(n_estimators=80, max_depth=3, learning_rate=0.05,
                        min_child_weight=8, reg_lambda=10.0, reg_alpha=0.0,
                        objective='reg:squarederror', tree_method='hist', max_bin=32,
                        base_score=0.0, n_jobs=1, random_state=42,
                        subsample=1.0, colsample_bytree=1.0, verbosity=0)


def fit_at(asset, stop):
    arrays = training_arrays(asset, stop)
    if arrays is None:
        return None
    return new_model().fit(*arrays)


def predict_dates(model, asset, origin, target_dates):
    origin_date, origin_price = asset.dates[origin], asset.prices[origin]
    x, positions = [], []
    result = np.full(len(target_dates), origin_price, dtype=np.float64)
    # The provider includes weekend observations for these Forex series.
    # Preserve its actual calendar; never impose a fabricated market calendar.
    for position, target in enumerate(target_dates):
        horizon = (target - origin_date).days
        if horizon <= 0:
            continue
        if horizon > MAX_HORIZON:
            raise ValueError('El horizonte excede 14 días desde el último cierre.')
        x.append([*asset.features[origin], horizon, target.weekday()])
        positions.append(position)
    if x:
        estimated_returns = model.predict(np.asarray(x, dtype=np.float64))
        with np.errstate(over='ignore', invalid='ignore', under='ignore'):
            result[positions] = origin_price * np.exp(estimated_returns.astype(np.float64))
    if not np.isfinite(result).all() or (result <= 0).any():
        raise ValueError('XGBoost produjo un precio inválido.')
    return result


def rolling_evaluation(asset, probability_horizon):
    """Replay the last 60 origins; reuse the last 21 fits for comparable MAPE."""
    count = len(asset.dates)
    known_indices = {item: index for index, item in enumerate(asset.dates)}
    validation_errors, baseline_errors, probability_ratios = [], [], []
    for stop in range(max(10, count - 60), count):
        origin = stop - 1
        model = fit_at(asset, stop)
        if model is None:
            continue
        targets = []
        validation_targets = []
        if stop >= max(10, count - 21):
            for index in range(stop, count):
                horizon = (asset.dates[index] - asset.dates[origin]).days
                if horizon > 7:
                    break
                validation_targets.append(index)
                targets.append(asset.dates[index])
        # Replay the exact live calendar horizon; omit dates without a real close.
        probability_date = asset.dates[origin] + timedelta(days=probability_horizon)
        probability_index = known_indices.get(probability_date)
        has_probability = probability_index is not None and probability_index > origin
        if has_probability:
            targets.append(probability_date)
        if not targets:
            continue
        predictions = predict_dates(model, asset, origin, targets)
        for position, target in enumerate(validation_targets):
            actual = asset.prices[target]
            validation_errors.append(abs(predictions[position] / actual - 1) * 100)
            baseline_errors.append(abs(asset.prices[origin] / actual - 1) * 100)
        if has_probability:
            probability_ratios.append(asset.prices[probability_index] / predictions[-1])
    validation = {'ventana': WINDOW,
                  'error_mape': float(np.mean(validation_errors)) if validation_errors else None,
                  'error_base': float(np.mean(baseline_errors)) if baseline_errors else None,
                  'evaluaciones': len(validation_errors)}
    return validation, probability_ratios


def direction_probabilities(ratios, target, last):
    samples = len(ratios)
    if samples < 10:
        return {'subida': None, 'bajada': None, 'evaluaciones': samples}
    up = 0.0
    for ratio in ratios:
        scenario = target * ratio
        up += 0.5 if abs(scenario - last) <= last * 1e-8 else float(scenario > last)
    # One observation in each direction prevents claims of absolute certainty.
    probability = round(100 * (up + 1) / (samples + 2), 1)
    return {'subida': probability, 'bajada': round(100 - probability, 1),
            'evaluaciones': samples}


def forecast_asset(pair, rows, as_of):
    asset = prepare_asset(pair, rows, as_of)
    origin = len(asset.dates) - 1
    model = fit_at(asset, len(asset.dates))
    if model is None:
        raise ValueError('No hay suficientes ejemplos utilizables para entrenar.')
    dates = [as_of + timedelta(days=offset) for offset in range(8)]
    prices = predict_dates(model, asset, origin, dates)
    rounded_prices = [round(float(price), 6) for price in prices]
    if any(price <= 0 for price in rounded_prices):
        raise ValueError('El precio no admite la precisión de seis decimales del gráfico.')
    probability_horizon = (dates[-1] - asset.dates[-1]).days
    validation, ratios = rolling_evaluation(asset, probability_horizon)
    return {'proyeccion': [{'fecha': item.isoformat(), 'precio': price}
                           for item, price in zip(dates, rounded_prices)],
            'validacion': validation,
            'observaciones': min(WINDOW, len(asset.dates)),
            'probabilidades': direction_probabilities(ratios, float(prices[-1]), float(asset.prices[-1])),
            'horizonte_probabilidades': probability_horizon,
            'version': xgboost.__version__}


def run(payload):
    if not isinstance(payload, dict):
        raise ValueError('La entrada debe ser un objeto JSON.')
    as_of = parse_date(payload.get('as_of'))
    assets = payload.get('assets')
    if not isinstance(assets, list) or not 1 <= len(assets) <= len(SUPPORTED_PAIRS):
        raise ValueError('Envía entre uno y cuatro activos.')
    results, errors, seen = {}, {}, set()
    for index, item in enumerate(assets):
        pair = item.get('par') if isinstance(item, dict) else None
        key = pair if isinstance(pair, str) and len(pair) <= 32 else f'activo_{index + 1}'
        if key in seen:
            results.pop(key, None)
            errors[key] = 'Activo duplicado en la solicitud.'
            continue
        seen.add(key)
        try:
            if not isinstance(item, dict):
                raise ValueError('Activo inválido.')
            results[key] = forecast_asset(pair, item.get('rows'), as_of)
        except ValueError as error:
            errors[key] = str(error)
        except Exception:
            errors[key] = 'No se pudo calcular XGBoost para este activo.'
    return {'results': results, 'errors': errors}


def main():
    # PHP sends UTF-8 JSON over pipes; Windows' locale encoding may differ.
    for stream in (sys.stdin, sys.stdout):
        if hasattr(stream, 'reconfigure'):
            stream.reconfigure(encoding='utf-8')
    try:
        payload = json.load(sys.stdin)
        response = run(payload)
    except (ValueError, TypeError, OverflowError, json.JSONDecodeError) as error:
        response = {'results': {}, 'errors': {'request': str(error)}}
    except Exception:
        response = {'results': {}, 'errors': {'request': 'No se pudo ejecutar el motor XGBoost.'}}
    print(json.dumps(response, ensure_ascii=False, allow_nan=False, separators=(',', ':')))


if __name__ == '__main__':
    main()
