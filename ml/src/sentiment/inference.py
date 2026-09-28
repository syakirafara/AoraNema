from functools import lru_cache
from pathlib import Path

import torch
from transformers import (
    AutoTokenizer,
    AutoModelForSequenceClassification,
)

from .preprocessing import clean_text


# ============================================================
# CONFIGURATION
# ============================================================

MAX_LENGTH = 128

# inference.py berada di:
# ml/src/sentiment/inference.py
#
# parents[2] = folder ml
ML_ROOT = Path(__file__).resolve().parents[2]

LOCAL_MODEL_PATH = (
    ML_ROOT
    / "models"
    / "sentiment"
    / "indobert_sentiment_model"
)

# Model repository di Hugging Face
HF_MODEL_ID = "onalla/indobert_sentiment_model_aoranema"


# ============================================================
# MODEL SOURCE
# ============================================================

def get_model_source() -> str:
    """
    Gunakan model lokal jika tersedia.

    Jika model lokal tidak tersedia, model akan diambil
    dari Hugging Face Hub.
    """

    # Cek folder sekaligus config.json agar tidak menganggap
    # folder kosong sebagai model yang valid.
    local_model_available = (
        LOCAL_MODEL_PATH.is_dir()
        and (LOCAL_MODEL_PATH / "config.json").exists()
    )

    if local_model_available:
        print(
            "Loading sentiment model from local:"
            f" {LOCAL_MODEL_PATH}"
        )

        return str(LOCAL_MODEL_PATH)

    print(
        "Local sentiment model not found."
        f" Loading from Hugging Face: {HF_MODEL_ID}"
    )

    return HF_MODEL_ID


# ============================================================
# DEVICE
# ============================================================

DEVICE = torch.device(
    "cuda" if torch.cuda.is_available() else "cpu"
)


# ============================================================
# LOAD MODEL
# ============================================================

@lru_cache(maxsize=1)
def load_model():
    """
    Muat tokenizer dan model saat pertama kali dibutuhkan, bukan saat
    modul diimpor. Dengan begitu layanan ML (termasuk rekomendasi) tetap
    menyala walaupun model sentimen belum bisa diunduh, misalnya tanpa
    internet. Kalau gagal, pemanggilan berikutnya akan mencoba lagi.
    """

    source = get_model_source()

    tokenizer = AutoTokenizer.from_pretrained(source)
    model = AutoModelForSequenceClassification.from_pretrained(source)

    model.to(DEVICE)
    model.eval()

    return tokenizer, model


# ============================================================
# INFERENCE
# ============================================================

def predict_sentiment(text: str) -> dict:
    """
    Prediksi sentiment dari satu teks.

    Output:
    {
        "sentiment": "negative",
        "confidence": 0.98
    }
    """

    tokenizer, model = load_model()

    text = clean_text(text)

    encoded = tokenizer(
        text,
        return_tensors="pt",
        truncation=True,
        max_length=MAX_LENGTH,
        padding=True,
    )

    encoded = {
        key: value.to(DEVICE)
        for key, value in encoded.items()
    }

    with torch.inference_mode():
        outputs = model(**encoded)

    probabilities = torch.softmax(
        outputs.logits,
        dim=-1,
    )[0]

    prediction_id = int(
        torch.argmax(probabilities).item()
    )

    confidence = float(
        probabilities[prediction_id].item()
    )

    sentiment = model.config.id2label[
        prediction_id
    ]

    return {
        "sentiment": str(sentiment),
        "confidence": confidence,
    }


# ============================================================
# OPTIONAL: DETAIL PROBABILITY
# ============================================================

def predict_sentiment_detail(text: str) -> dict:
    """
    Versi lengkap untuk debugging / eksperimen.

    Menampilkan probabilitas seluruh kelas.
    """

    tokenizer, model = load_model()

    text = clean_text(text)

    encoded = tokenizer(
        text,
        return_tensors="pt",
        truncation=True,
        max_length=MAX_LENGTH,
        padding=True,
    )

    encoded = {
        key: value.to(DEVICE)
        for key, value in encoded.items()
    }

    with torch.inference_mode():
        outputs = model(**encoded)

    probabilities = torch.softmax(
        outputs.logits,
        dim=-1,
    )[0]

    prediction_id = int(
        torch.argmax(probabilities).item()
    )

    sentiment = model.config.id2label[
        prediction_id
    ]

    probability_by_label = {}

    for label_id, probability in enumerate(probabilities):
        label = model.config.id2label[
            label_id
        ]

        probability_by_label[
            str(label)
        ] = float(
            probability.item()
        )

    return {
        "sentiment": str(sentiment),
        "confidence": float(
            probabilities[prediction_id].item()
        ),
        "probabilities": probability_by_label,
    }


# ============================================================
# MANUAL TEST
# ============================================================

if __name__ == "__main__":
    examples = [
        "Proses booking tiketnya cepat dan gampang banget.",
        "Pembayaran QRIS sering gagal dan bikin kesal.",
        "Aplikasinya biasa saja.",
        "Customer service sangat ramah dan membantu.",
    ]

    print()
    print("Device :", DEVICE)
    print("Model  :", MODEL_SOURCE)
    print()

    for text in examples:
        result = predict_sentiment_detail(text)

        print("=" * 60)
        print("Text       :", text)
        print("Sentiment  :", result["sentiment"])
        print(
            "Confidence :",
            round(
                result["confidence"],
                4,
            ),
        )
        print(
            "Probability:",
            {
                key: round(value, 4)
                for key, value
                in result["probabilities"].items()
            },
        )