"""Prueba ExtraTrees con cierres diarios BTC/USD y evaluación cronológica."""
import argparse
import json
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from flask import Flask, jsonify
from sklearn.ensemble import ExtraTreesClassifier
from sklearn.metrics import accuracy_score, balanced_accuracy_score, confusion_matrix

ROOT = Path(__file__).resolve().parents[2]
DATA = ROOT / 'storage' / 'ml' / 'btc.csv'
MODEL = ROOT / 'storage' / 'ml' / 'btc_extratrees.joblib'
FEATURES = ['ret_1', 'ret_3', 'ret_7', 'sma_5', 'sma_10', 'sma_20',
            'sma_50', 'vol_7', 'vol_14', 'vol_30', 'rsi_14', 'atr_14',
            'rango', 'cuerpo', 'gap']


def load_data(path):
    df = pd.read_csv(path, sep=';')
    required = {'par', 'fecha', 'precio_apertura', 'precio_maximo',
                'precio_minimo', 'precio_cierre'}
    if not required.issubset(df.columns):
        raise ValueError('El CSV no contiene las columnas OHLC requeridas.')
    df = df.loc[df['par'] == 'BTC/USD'].copy()
    df['fecha'] = pd.to_datetime(df['fecha'], errors='raise').dt.normalize()
    today = pd.Timestamp.now(tz='America/Lima').tz_localize(None).normalize()
    df = df.loc[df['fecha'] < today].sort_values('fecha').reset_index(drop=True)
    if df['fecha'].duplicated().any():
        raise ValueError('Hay fechas duplicadas en el CSV.')
    cols = ['precio_apertura', 'precio_maximo', 'precio_minimo', 'precio_cierre']
    df[cols] = df[cols].apply(pd.to_numeric, errors='raise')
    if not np.isfinite(df[cols].to_numpy()).all() or (df[cols] <= 0).any().any():
        raise ValueError('Los precios deben ser positivos y finitos.')
    if (df['precio_maximo'] < df[['precio_apertura', 'precio_cierre']].max(axis=1)).any() or (df['precio_minimo'] > df[['precio_apertura', 'precio_cierre']].min(axis=1)).any():
        raise ValueError('Las velas OHLC son inconsistentes.')
    if len(df) < 250:
        raise ValueError(f'Se requieren al menos 250 cierres diarios para esta prueba; hay {len(df)}.')
    if not df['fecha'].diff().dropna().dt.total_seconds().eq(86400).all():
        raise ValueError('Faltan días en el histórico BTC: completa los cierres sin inventar datos.')
    return df


def features(df):
    close, opened = df['precio_cierre'], df['precio_apertura']
    high, low = df['precio_maximo'], df['precio_minimo']
    x = pd.DataFrame(index=df.index)
    for days in (1, 3, 7):
        x[f'ret_{days}'] = close.pct_change(days, fill_method=None)
    for days in (5, 10, 20, 50):
        x[f'sma_{days}'] = close / close.rolling(days).mean() - 1
    for days in (7, 14, 30):
        x[f'vol_{days}'] = x['ret_1'].rolling(days).std()
    delta = close.diff()
    gains = delta.clip(lower=0).rolling(14).mean()
    losses = (-delta.clip(upper=0)).rolling(14).mean()
    x['rsi_14'] = 100 - 100 / (1 + gains / losses.replace(0, np.nan))
    x.loc[(losses == 0) & (gains > 0), 'rsi_14'] = 100
    x.loc[(losses == 0) & (gains == 0), 'rsi_14'] = 50
    ranges = pd.concat([high - low, (high - close.shift()).abs(),
                        (low - close.shift()).abs()], axis=1)
    x['atr_14'] = ranges.max(axis=1).rolling(14).mean() / close
    x['rango'] = (high - low) / close
    x['cuerpo'] = (close - opened) / opened
    x['gap'] = opened / close.shift() - 1
    return x[FEATURES].replace([np.inf, -np.inf], np.nan)


def new_model():
    return ExtraTreesClassifier(n_estimators=400, max_depth=8,
                                min_samples_leaf=8, random_state=42, n_jobs=1)


def train(csv_path):
    df = load_data(csv_path)
    x = features(df)
    next_close = df['precio_cierre'].shift(-1)
    # La última fila no tiene etiqueta: se reserva para la predicción futura.
    usable = x.notna().all(axis=1) & next_close.notna()
    y = (next_close > df['precio_cierre']).astype(int).loc[usable]
    x = x.loc[usable]
    cut = int(len(x) * 0.8)
    # Purga una observación: su etiqueta coincide con el primer día del test.
    x_train, x_test = x.iloc[:cut - 1], x.iloc[cut:]
    y_train, y_test = y.iloc[:cut - 1], y.iloc[cut:]
    if y_train.nunique() < 2 or y_test.nunique() < 2:
        raise ValueError('Entrenamiento y prueba deben contener ambas clases.')
    evaluated = new_model().fit(x_train, y_train)
    predictions = evaluated.predict(x_test)
    majority = int(y_train.value_counts().idxmax())
    metrics = {
        'accuracy': round(float(accuracy_score(y_test, predictions)) * 100, 2),
        'balanced_accuracy': round(float(balanced_accuracy_score(y_test, predictions)) * 100, 2),
        'baseline_accuracy': round(float((y_test == majority).mean()) * 100, 2),
        'confusion_matrix': confusion_matrix(y_test, predictions, labels=[0, 1]).tolist(),
        'clases': ['BAJA_O_IGUAL', 'SUBE'],
        'entrenamiento': len(x_train), 'prueba': len(x_test),
        'prueba_desde': df.loc[x_test.index[0], 'fecha'].date().isoformat(),
        'prueba_hasta': df.loc[x_test.index[-1], 'fecha'].date().isoformat(),
    }
    # Después de medir el test, el modelo operativo se ajusta con todo lo etiquetado.
    fitted = new_model().fit(x, y)
    MODEL.parent.mkdir(parents=True, exist_ok=True)
    temporary = MODEL.with_suffix('.tmp')
    joblib.dump({'modelo': fitted, 'metrics': metrics, 'features': FEATURES,
                 'fecha_datos': df.iloc[-1]['fecha'].date().isoformat()}, temporary)
    temporary.replace(MODEL)
    print(json.dumps(metrics, ensure_ascii=False, indent=2))


def predict(csv_path):
    if not MODEL.exists():
        raise ValueError('Primero ejecuta el entrenamiento del modelo.')
    bundle = joblib.load(MODEL)
    df = load_data(csv_path)
    if df.iloc[-1]['fecha'].date().isoformat() < bundle['fecha_datos']:
        raise ValueError('El CSV es anterior al modelo. Reentrena con este histórico.')
    row = features(df).iloc[[-1]]
    if row.isna().any().any():
        raise ValueError('No hay suficientes datos para los indicadores finales.')
    probabilities = dict(zip(bundle['modelo'].classes_, bundle['modelo'].predict_proba(row)[0]))
    up = float(probabilities.get(1, 0))
    last = df.iloc[-1]
    target = last['fecha'] + pd.Timedelta(days=1)
    today = pd.Timestamp.now(tz='America/Lima').date()
    return {'ok': True, 'modelo': 'ExtraTrees', 'par': 'BTC/USD',
            'fecha_base': last['fecha'].date().isoformat(),
            'fecha_prediccion': target.date().isoformat(), 'horizonte_dias': 1,
            'precio_cierre': float(last['precio_cierre']),
            'prediccion': 'SUBE' if up >= .60 else 'BAJA' if up <= .40 else 'SIN_SEÑAL_CLARA',
            'probabilidad_sube': round(up * 100, 2),
            'probabilidad_baja_o_igual': round((1 - up) * 100, 2),
            'datos_desactualizados': target.date() < today,
            'fecha_datos_entrenamiento': bundle['fecha_datos'],
            'validacion': bundle['metrics'],
            'nota': 'Probabilidades sin calibrar. Umbral experimental del 60%. BAJA incluye cierre igual. No estima un precio futuro.'}


def serve(csv_path):
    app = Flask(__name__)

    @app.get('/predict')
    def endpoint():
        try:
            return jsonify(predict(csv_path))
        except (ValueError, FileNotFoundError) as error:
            return jsonify(ok=False, error=str(error)), 422
        except Exception:
            app.logger.exception('Fallo de predicción')
            return jsonify(ok=False, error='No se pudo ejecutar el modelo. Revisa la consola Python.'), 500

    app.run(host='127.0.0.1', port=5000, debug=False)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('action', choices=['train', 'predict', 'serve'])
    parser.add_argument('--csv', type=Path, default=DATA)
    args = parser.parse_args()
    try:
        if args.action == 'train':
            train(args.csv)
        elif args.action == 'predict':
            print(json.dumps(predict(args.csv), ensure_ascii=False, indent=2))
        else:
            serve(args.csv)
    except (ValueError, FileNotFoundError) as error:
        parser.exit(1, str(error) + '\n')
