"""
Menambahkan folder ml/ ke sys.path, supaya tes bisa dijalankan dari folder
ml/ maupun dari folder proyek: python -m pytest ml/tests
"""

import sys
from pathlib import Path

ML_ROOT = Path(__file__).resolve().parents[1]

if str(ML_ROOT) not in sys.path:
    sys.path.insert(0, str(ML_ROOT))
