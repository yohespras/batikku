import json
import sys
import os

# Kurangi log TensorFlow agar output JSON tetap bersih di PHP
os.environ.setdefault('TF_CPP_MIN_LOG_LEVEL', '3')
os.environ.setdefault('TF_ENABLE_ONEDNN_OPTS', '0')

import numpy as np
from PIL import Image
import tensorflow as tf

try:
    tf.get_logger().setLevel('ERROR')
except Exception:
    pass

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODELS = {
    'final': os.path.join(BASE, 'models', 'final_efficientnetb0_batik.keras'),
    'best': os.path.join(BASE, 'models', 'best_efficientnetb0_batik.keras'),
}
CLASS_FILE = os.path.join(BASE, 'models', 'class_names.txt')


def load_classes():
    with open(CLASS_FILE, 'r', encoding='utf-8') as f:
        return [x.strip() for x in f if x.strip()]


def get_model_input_size(model):
    """Ambil ukuran input langsung dari model, contoh: (None, 128, 128, 3)."""
    shape = model.input_shape
    if isinstance(shape, list):
        shape = shape[0]
    if len(shape) >= 4 and shape[1] and shape[2]:
        return int(shape[1]), int(shape[2])
    # fallback aman jika shape tidak terbaca
    return 128, 128


def preprocess(path, size):
    img = Image.open(path).convert('RGB').resize(size)
    arr = np.asarray(img, dtype=np.float32)
    arr = np.expand_dims(arr, 0)
    return arr


def normalize_prediction(pred):
    pred = np.asarray(pred, dtype=np.float64)
    if pred.max() > 1 or pred.min() < 0 or abs(pred.sum() - 1) > 0.1:
        pred = tf.nn.softmax(pred).numpy()
    return pred.astype(float)


def predict_one(model_path, image_path):
    model = tf.keras.models.load_model(model_path, compile=False)
    size = get_model_input_size(model)
    arr = preprocess(image_path, size)
    pred = model.predict(arr, verbose=0)[0]
    return normalize_prediction(pred), size


def main():
    if len(sys.argv) < 2:
        raise ValueError('Path gambar wajib diisi')

    image_path = sys.argv[1]
    mode = sys.argv[2] if len(sys.argv) > 2 else 'ensemble'
    classes = load_classes()

    used_sizes = []
    if mode == 'ensemble':
        preds = []
        for model_path in MODELS.values():
            pred, size = predict_one(model_path, image_path)
            preds.append(pred)
            used_sizes.append(f'{size[0]}x{size[1]}')
        pred = np.mean(preds, axis=0)
    elif mode in MODELS:
        pred, size = predict_one(MODELS[mode], image_path)
        used_sizes.append(f'{size[0]}x{size[1]}')
    else:
        raise ValueError('Mode model tidak valid')

    idxs = pred.argsort()[-5:][::-1]
    result = {
        'success': True,
        'mode': mode,
        'input_size': ', '.join(sorted(set(used_sizes))),
        'prediksi': classes[int(idxs[0])] if int(idxs[0]) < len(classes) else str(int(idxs[0])),
        'confidence': float(pred[int(idxs[0])]),
        'top_predictions': [
            {
                'class': classes[int(i)] if int(i) < len(classes) else str(int(i)),
                'confidence': float(pred[int(i)])
            }
            for i in idxs
        ]
    }
    print(json.dumps(result, ensure_ascii=False))


if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        print(json.dumps({'success': False, 'error': str(e)}, ensure_ascii=False))
